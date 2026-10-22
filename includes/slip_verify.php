<?php
// ตรวจสลิปโอนเงินอัตโนมัติผ่าน EasySlip API v2
defined('EASYSLIP_API_KEY')       or define('EASYSLIP_API_KEY', '');
defined('EASYSLIP_BASE_URL')      or define('EASYSLIP_BASE_URL', 'https://api.easyslip.com/v2');
defined('EASYSLIP_MATCH_ACCOUNT') or define('EASYSLIP_MATCH_ACCOUNT', true);
defined('EASYSLIP_TIME_SLACK')    or define('EASYSLIP_TIME_SLACK', 600);

/**
 * ตัดสินผลจากคำตอบของ API (แยกเป็นฟังก์ชันล้วน ๆ เพื่อทดสอบได้โดยไม่ต้องยิง API จริง)
 * คืนค่า ['status' => 'ok'|'reject'|'manual', 'message' => ..., 'ref' => ...]
 *   ok     = ผ่านทุกเงื่อนไข -> ตั้งเป็นชำระแล้วอัตโนมัติ
 *   reject = สลิปไม่ผ่าน (ยอดไม่ตรง/ผู้รับผิด/สลิปเก่า/ไม่พบในระบบธนาคาร)
 *   manual = ตรวจอัตโนมัติไม่ได้ (API ล่ม/โควตาหมด/ข้อมูลไม่พอ) -> ให้แอดมินตรวจเอง
 */
function easyslip_evaluate(int $http, ?array $json, float $amount, int $createdTs, bool $matchAccount, int $slack = 600): array {
    $manual = fn(string $why) => ['status' => 'manual', 'message' => $why, 'ref' => null];
    $reject = fn(string $why) => ['status' => 'reject', 'message' => $why, 'ref' => null];

    if ($http === 0 || $json === null) return $manual('เชื่อมต่อระบบตรวจสลิปไม่ได้');

    if (empty($json['success'])) {
        $code = $json['error']['code'] ?? '';
        if ($code === 'SLIP_NOT_FOUND') return $reject('ไม่พบสลิปนี้ในระบบธนาคาร กรุณาใช้สลิปจริงที่ภาพคมชัดและเห็น QR มุมสลิปครบ');
        if ($code === 'VALIDATION_ERROR') return $reject('อ่านรูปสลิปไม่ได้ กรุณาอัปโหลดรูปสลิปที่ชัดเจน');
        // ปัญหาฝั่งระบบ/การตั้งค่า (key ผิด โควตาหมด เซิร์ฟเวอร์ล่ม ฯลฯ) ไม่ใช่ความผิดผู้ซื้อ -> ส่งให้แอดมิน
        error_log('EasySlip error: HTTP ' . $http . ' ' . $code);
        return $manual('ระบบตรวจสลิปอัตโนมัติใช้งานไม่ได้ชั่วคราว');
    }

    $d = $json['data'] ?? [];
    $ref = $d['transRef'] ?? null;
    if (!$ref) return $manual('อ่านเลขอ้างอิงรายการไม่ได้');

    // 1) ยอดเงิน
    $slipAmount = null;
    if (isset($d['amount']) && is_array($d['amount']) && isset($d['amount']['amount'])) $slipAmount = (float)$d['amount']['amount'];
    elseif (isset($d['amount']) && is_numeric($d['amount'])) $slipAmount = (float)$d['amount'];
    elseif (isset($d['amountInSlip']) && is_numeric($d['amountInSlip'])) $slipAmount = (float)$d['amountInSlip'];

    $amountOk = $slipAmount !== null
        ? abs($slipAmount - $amount) < 0.005
        : (($d['isAmountMatched'] ?? null) === true);
    if (($d['isAmountMatched'] ?? null) === false) $amountOk = false;
    if (!$amountOk) {
        $got = $slipAmount !== null ? ' (ในสลิป ฿' . number_format($slipAmount, 2) . ')' : '';
        return $reject('ยอดในสลิปไม่ตรงกับยอดที่ต้องชำระ ฿' . number_format($amount, 2) . $got);
    }

    // 2) ผู้รับเงินต้องเป็นบัญชีของร้าน (จับคู่กับบัญชีที่ลงทะเบียนไว้ใน EasySlip)
    if ($matchAccount && empty($d['matchedAccount'])) {
        return $reject('สลิปนี้ไม่ได้โอนเข้าบัญชีของร้าน กรุณาโอนตาม QR ที่แสดงในหน้าชำระเงิน');
    }

    // 3) เวลาโอนต้องไม่ก่อนเวลาสร้างออเดอร์ (กันเอาสลิปเก่ามาใช้)
    $date = $d['date'] ?? $d['transactionDate'] ?? null;
    $ts = $date ? strtotime((string)$date) : false;
    if ($ts === false) return $manual('อ่านเวลาโอนจากสลิปไม่ได้');
    if ($ts < $createdTs - $slack) return $reject('สลิปนี้โอนก่อนสร้างคำสั่งซื้อ กรุณาใช้สลิปของรายการนี้');

    return ['status' => 'ok', 'message' => 'ตรวจสลิปอัตโนมัติผ่าน', 'ref' => (string)$ref];
}

// ส่งรูปสลิปไปตรวจที่ EasySlip แล้วตัดสินผล
function easyslip_verify(string $filePath, float $amount, int $createdTs): array {
    if (!function_exists('curl_init')) return ['status' => 'manual', 'message' => 'เซิร์ฟเวอร์ไม่รองรับ cURL', 'ref' => null];
    $mime = @getimagesize($filePath)['mime'] ?? 'image/jpeg';
    $fields = ['image' => new CURLFile($filePath, $mime, 'slip'), 'matchAmount' => number_format($amount, 2, '.', '')];
    if (EASYSLIP_MATCH_ACCOUNT) $fields['matchAccount'] = 'true';

    $ch = curl_init(rtrim(EASYSLIP_BASE_URL, '/') . '/verify/bank');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . EASYSLIP_API_KEY, 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) error_log('EasySlip curl: ' . curl_error($ch));
    curl_close($ch);

    $json = is_string($body) ? json_decode($body, true) : null;
    return easyslip_evaluate($http, is_array($json) ? $json : null, $amount, $createdTs, EASYSLIP_MATCH_ACCOUNT, EASYSLIP_TIME_SLACK);
}
