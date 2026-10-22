<?php
// รับสลิปโอนเงินเข้าบัญชีกลางของ StepUP (ระบบพักเงิน)
// SLIP_AUTO_VERIFY = true  -> ผ่านทันที (โหมดทดสอบ ไม่ตรวจการโอนจริง ห้ามใช้กับเงินจริง)
// SLIP_AUTO_VERIFY = false -> รอแอดมินตรวจที่ admin/escrow.php แล้วจึงพักเงินและให้ร้านจัดส่ง

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/../includes/escrow_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('pages/my_orders.php');
require_login();
csrf_check();

$orderId = (int)($_POST['order_id'] ?? 0);
$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($orderId <= 0 || $userId <= 0) { flash('ข้อมูลคำสั่งซื้อไม่ถูกต้อง'); redirect('pages/my_orders.php'); }

$stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) { flash('ไม่พบคำสั่งซื้อนี้'); redirect('pages/my_orders.php'); }
if ($order['status'] !== 'pending') {
    flash('คำสั่งซื้อนี้ไม่ได้อยู่ในสถานะรอชำระเงินแล้ว');
    redirect('pages/payment.php?id=' . $orderId);
}
if (!isset($_FILES['slip'])) { flash('กรุณาแนบรูปสลิป'); redirect('pages/payment.php?id=' . $orderId); }

$uploadError = null;
$slipUrl = save_upload($_FILES['slip'], $uploadError, 'slips', 5 * 1024 * 1024);
if (!$slipUrl) { flash($uploadError ?: 'อัปโหลดรูปสลิปไม่สำเร็จ'); redirect('pages/payment.php?id=' . $orderId); }

try {
    $pdo->beginTransaction();
    $upd = $pdo->prepare("UPDATE orders
        SET slip_image = ?, slip_at = NOW(), slip_result = NULL, slip_note = NULL, payment_method = 'promptpay'
        WHERE id = ? AND user_id = ? AND status = 'pending'");
    $upd->execute([$slipUrl, $orderId, $userId]);
    if ($upd->rowCount() !== 1) throw new RuntimeException('order not updated');

    // สร้างแถวชำระเงินรายร้าน แล้วแนบสลิปเดียวกันให้ทุกแถว
    escrow_create_payments($pdo, $orderId, 'promptpay');
    $pdo->prepare("UPDATE payments SET status = 'submitted', slip_image = ?, slip_at = NOW()
                   WHERE order_id = ? AND method = 'promptpay' AND status = 'pending'")->execute([$slipUrl, $orderId]);

    if (escrow_cfg('SLIP_AUTO_VERIFY', false)) {
        escrow_mark_paid($pdo, $orderId);
        $pdo->prepare("UPDATE orders SET slip_result = 'verified', slip_note = 'DEMO: ไม่ได้ตรวจสอบการโอนเงินจริง' WHERE id = ?")->execute([$orderId]);
        $msg = 'แนบสลิปสำเร็จ (โหมดทดสอบ) เงินถูกพักไว้ที่ StepUP รอร้านจัดส่งสินค้า';
    } else {
        $msg = 'ส่งสลิปแล้ว รอแอดมินตรวจสอบการโอนเงิน หลังยืนยันเงินจะถูกพักไว้จนกว่าคุณจะได้รับสินค้า';
    }
    $pdo->commit();
    flash($msg);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('slip upload error: ' . $e->getMessage());
    flash('บันทึกสลิปไม่สำเร็จ กรุณาลองใหม่');
}
redirect('pages/payment.php?id=' . $orderId);
