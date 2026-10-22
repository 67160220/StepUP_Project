<?php
// ระบบพักเงิน (escrow) + เก็บเงินปลายทาง (COD)
//
// ค่าตั้งค่าใน config/payment.php (ไม่ตั้งก็ใช้ค่าเริ่มต้นในวงเล็บ):
//   PROMPTPAY_ID / PAY_ACCOUNT_NAME  บัญชีพร้อมเพย์ "ของแพลตฟอร์ม" ที่รับเงินพัก  (จำเป็น)
//   ESCROW_SHIP_DAYS (3)             ร้านต้องจัดส่งภายในกี่วันหลังชำระ/สั่ง COD ไม่งั้นคืนเงิน/ยกเลิกอัตโนมัติ
//   ESCROW_AUTO_RELEASE_DAYS (7)     หลังร้านส่งของ ถ้าผู้ซื้อไม่กดรับ/แจ้งปัญหา ถือว่ารับของแล้วกี่วัน
//   COD_MAX_AMOUNT (3000)            ยอดสูงสุดที่เปิดให้เก็บเงินปลายทาง
//   SLIP_AUTO_VERIFY (false)         true = ผ่านสลิปอัตโนมัติ (โหมดทดสอบ ไม่ตรวจการโอนจริง)

$__payCfg = __DIR__ . '/../config/payment.php';
if (is_file($__payCfg)) require_once $__payCfg;
unset($__payCfg);

function escrow_cfg(string $name, $default) { return defined($name) ? constant($name) : $default; }
function escrow_ship_days(): int { return max(1, (int)escrow_cfg('ESCROW_SHIP_DAYS', 3)); }
function escrow_release_days(): int { return max(1, (int)escrow_cfg('ESCROW_AUTO_RELEASE_DAYS', 7)); }

/** สร้างแถว payments รายร้านจาก order_items (ล้างแถวที่ยังไม่สรุปก่อน เพื่ออัปโหลดสลิปใหม่ได้) */
function escrow_create_payments(PDO $pdo, int $orderId, string $method): void {
    $pdo->prepare("DELETE FROM payments WHERE order_id = ? AND status IN ('pending','submitted','rejected')")->execute([$orderId]);
    $rows = $pdo->prepare('SELECT store_id, SUM(price * qty) AS amt FROM order_items WHERE order_id = ? GROUP BY store_id');
    $rows->execute([$orderId]);
    $shipBy = $method === 'cod' ? 'DATE_ADD(NOW(), INTERVAL ' . escrow_ship_days() . ' DAY)' : 'NULL';
    $ins = $pdo->prepare("INSERT INTO payments (order_id, store_id, method, amount, status, escrow_status, ship_by)
                          VALUES (?, ?, ?, ?, 'pending', 'none', $shipBy)");
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ins->execute([$orderId, $r['store_id'] !== null ? (int)$r['store_id'] : null, $method, $r['amt']]);
    }
}

/** ยืนยันว่าได้รับเงินเข้าบัญชีกลางแล้ว: พักเงินรายร้าน + เริ่มนับเวลาจัดส่ง (เรียกภายใน transaction ของผู้เรียก) */
function escrow_mark_paid(PDO $pdo, int $orderId): bool {
    $c = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE order_id = ? AND method = 'promptpay'");
    $c->execute([$orderId]);
    if ((int)$c->fetchColumn() === 0) escrow_create_payments($pdo, $orderId, 'promptpay');
    $d = escrow_ship_days();
    $pdo->prepare("UPDATE payments
                   SET status = 'verified', verified_at = NOW(),
                       escrow_status = IF(store_id IS NULL, 'released', 'held'),
                       ship_by = DATE_ADD(NOW(), INTERVAL $d DAY)
                   WHERE order_id = ? AND method = 'promptpay' AND status IN ('pending','submitted')")->execute([$orderId]);
    $o = $pdo->prepare("UPDATE orders SET status = 'paid', payment_method = 'promptpay' WHERE id = ? AND status = 'pending'");
    $o->execute([$orderId]);
    return $o->rowCount() === 1;
}

/** ยกเลิกออเดอร์ที่ทุกร้านถูกคืนเงิน/ยกเลิกหมดแล้ว */
function escrow_cancel_fully_refunded(PDO $pdo): void {
    $pdo->exec("UPDATE orders o SET o.status = 'cancelled'
                WHERE o.status = 'paid'
                  AND EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id)
                  AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.status NOT IN ('refunded','rejected'))");
}

/** กฎอัตโนมัติ: เรียกจากหน้าชำระเงิน/หน้าแอดมิน หรือตั้ง cron เรียกซ้ำทุกชั่วโมง */
function escrow_run_auto(PDO $pdo): array {
    $out = [];
    // ร้านไม่ส่งของตามกำหนด -> คืนเงินลูกค้า (เงินยังอยู่กับแพลตฟอร์ม)
    $out['refund'] = $pdo->exec("UPDATE payments SET status = 'refunded', escrow_status = 'refunded',
            note = 'ร้านไม่จัดส่งตามกำหนด (คืนเงินอัตโนมัติ)'
        WHERE method = 'promptpay' AND escrow_status = 'held' AND shipped_at IS NULL
          AND ship_by IS NOT NULL AND ship_by < NOW()");
    // COD ร้านไม่ส่งของตามกำหนด -> ยกเลิกรายการ
    $out['cod_cancel'] = $pdo->exec("UPDATE payments SET status = 'rejected',
            note = 'ร้านไม่จัดส่งตามกำหนด (ยกเลิกอัตโนมัติ)'
        WHERE method = 'cod' AND status = 'pending' AND escrow_status = 'none' AND shipped_at IS NULL
          AND ship_by IS NOT NULL AND ship_by < NOW()");
    // ส่งแล้ว ผู้ซื้อไม่ตอบภายในกำหนด -> พร้อมจ่ายให้ร้าน
    $out['release'] = $pdo->exec("UPDATE payments SET escrow_status = 'releasable'
        WHERE method = 'promptpay' AND escrow_status = 'held' AND shipped_at IS NOT NULL
          AND received_at IS NULL AND auto_release_at IS NOT NULL AND auto_release_at <= NOW()");
    // COD ส่งแล้ว ผู้ซื้อไม่ตอบ -> ปิดรายการ
    $out['cod_close'] = $pdo->exec("UPDATE payments SET status = 'verified', verified_at = NOW(),
            note = 'ปิดอัตโนมัติ (ผู้ซื้อไม่แจ้งปัญหา)'
        WHERE method = 'cod' AND status = 'pending' AND escrow_status = 'none' AND shipped_at IS NOT NULL
          AND auto_release_at IS NOT NULL AND auto_release_at <= NOW()");
    escrow_cancel_fully_refunded($pdo);
    return $out;
}

/** กลับหน้าก่อนหน้า (เฉพาะโดเมนเดียวกัน) ไม่งั้นไป fallback */
function escrow_back(string $fallback): void {
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $rh = (string)parse_url($ref, PHP_URL_HOST);
    $host = explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0];
    if ($ref !== '' && $rh !== '' && strcasecmp($rh, $host) === 0) { header('Location: ' . $ref); exit; }
    redirect($fallback);
}
