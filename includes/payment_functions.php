<?php
// สร้างข้อมูล QR พร้อมเพย์ (มาตรฐาน EMVCo / Thai QR Payment)
function crc16_ccitt(string $s): string {
    $crc = 0xFFFF;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $crc ^= ord($s[$i]) << 8;
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

function emv_tlv(string $tag, string $value): string {
    return $tag . str_pad((string)strlen($value), 2, '0', STR_PAD_LEFT) . $value;
}

// $id = เบอร์โทร 10 หลัก หรือเลข 13 หลัก ; คืนค่า null ถ้ารูปแบบไม่ถูกต้อง
function promptpay_payload(string $id, float $amount): ?string {
    $id = preg_replace('/\D/', '', $id);
    if (strlen($id) === 10 && $id[0] === '0') {          // เบอร์โทร -> 0066 + 9 หลักท้าย
        $target = emv_tlv('01', '0066' . substr($id, 1));
    } elseif (strlen($id) === 13) {                       // เลขบัตร ปชช. / ภาษี
        $target = emv_tlv('02', $id);
    } else {
        return null;
    }
    $merchant = emv_tlv('00', 'A000000677010111') . $target;
    $p  = emv_tlv('00', '01');
    $p .= emv_tlv('01', $amount > 0 ? '12' : '11');       // 12 = ใช้ครั้งเดียว (มียอด)
    $p .= emv_tlv('29', $merchant);
    $p .= emv_tlv('53', '764');
    if ($amount > 0) $p .= emv_tlv('54', number_format($amount, 2, '.', ''));
    $p .= emv_tlv('58', 'TH');
    $p .= '6304';
    return $p . crc16_ccitt($p);
}
