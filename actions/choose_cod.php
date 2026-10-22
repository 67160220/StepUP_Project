<?php
// ผู้ซื้อเลือกเก็บเงินปลายทาง (COD) แทนการโอนออนไลน์
// หมายเหตุ: ออเดอร์ COD จะถูกตั้ง orders.status = 'paid' เพื่อให้หน้าร้านค้าเดิมเห็นว่าพร้อมจัดส่ง
//           แยกจากออเดอร์ออนไลน์ด้วย orders.payment_method = 'cod' (ยังไม่ได้เก็บเงินจริง)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/../includes/escrow_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('pages/my_orders.php');
require_login();
csrf_check();

$orderId = (int)($_POST['order_id'] ?? 0);
$userId = (int)($_SESSION['user']['id'] ?? 0);
$max = (float)escrow_cfg('COD_MAX_AMOUNT', 3000);

try {
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT id, total, status, slip_image, phone FROM orders WHERE id = ? AND user_id = ? FOR UPDATE");
    $st->execute([$orderId, $userId]);
    $o = $st->fetch(PDO::FETCH_ASSOC);
    if (!$o) throw new DomainException('ไม่พบคำสั่งซื้อนี้');
    if ($o['status'] !== 'pending' || !empty($o['slip_image'])) throw new DomainException('คำสั่งซื้อนี้ไม่สามารถเปลี่ยนเป็นเก็บเงินปลายทางได้');
    if ((float)$o['total'] > $max) throw new DomainException('ยอดเกิน ฿' . number_format($max) . ' ไม่รองรับเก็บเงินปลายทาง');
    if (!preg_match('/^[0-9+\-\s]{9,20}$/', (string)$o['phone'])) throw new DomainException('เบอร์โทรในคำสั่งซื้อไม่ถูกต้อง ร้านค้าจำเป็นต้องติดต่อผู้รับ');

    escrow_create_payments($pdo, $orderId, 'cod');
    $pdo->prepare("UPDATE orders SET payment_method = 'cod', status = 'paid' WHERE id = ? AND status = 'pending'")->execute([$orderId]);
    $pdo->commit();
    flash('เลือกเก็บเงินปลายทางแล้ว ร้านค้าจะจัดส่งสินค้า และคุณจ่ายเงินเมื่อได้รับของ');
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash($e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('choose_cod error: ' . $e->getMessage());
    flash('ทำรายการไม่สำเร็จ กรุณาลองใหม่');
}
redirect('pages/payment.php?id=' . $orderId);
