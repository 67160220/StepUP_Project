<?php
// ผู้ซื้อ: ยืนยันรับสินค้า (do=confirm) หรือแจ้งปัญหา (do=dispute, reason)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/../includes/escrow_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('pages/my_orders.php');
require_login();
csrf_check();

$userId = (int)($_SESSION['user']['id'] ?? 0);
$orderId = (int)($_POST['order_id'] ?? 0);
$paymentId = (int)($_POST['payment_id'] ?? 0);
$do = (string)($_POST['do'] ?? '');

try {
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT p.* FROM payments p JOIN orders o ON o.id = p.order_id
                         WHERE p.id = ? AND p.order_id = ? AND o.user_id = ? FOR UPDATE");
    $st->execute([$paymentId, $orderId, $userId]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new DomainException('ไม่พบรายการนี้');
    $open = $p['shipped_at'] !== null && $p['received_at'] === null
        && !in_array($p['status'], ['refunded', 'rejected'], true)
        && in_array($p['escrow_status'], ['held', 'none'], true);
    if (!$open) throw new DomainException('รายการนี้ไม่อยู่ในสถานะที่ทำรายการได้');

    if ($do === 'confirm') {
        if ($p['method'] === 'cod') {
            $pdo->prepare("UPDATE payments SET status = 'verified', verified_at = NOW(), received_at = NOW(),
                           note = 'ผู้ซื้อยืนยันรับสินค้าและชำระเงินปลายทาง' WHERE id = ?")->execute([$paymentId]);
        } else {
            $pdo->prepare("UPDATE payments SET received_at = NOW(),
                           escrow_status = IF(store_id IS NULL, 'released', 'releasable') WHERE id = ?")->execute([$paymentId]);
        }
        flash('ขอบคุณที่ยืนยันรับสินค้า');
    } elseif ($do === 'dispute') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 5) throw new DomainException('กรุณาอธิบายปัญหาอย่างน้อย 5 ตัวอักษร');
        $pdo->prepare("UPDATE payments SET escrow_status = 'disputed', note = ? WHERE id = ?")
            ->execute([mb_substr('ผู้ซื้อแจ้งปัญหา: ' . $reason, 0, 255), $paymentId]);
        flash('ส่งเรื่องให้แอดมินตรวจสอบแล้ว เงินจะถูกพักไว้จนกว่าจะได้ข้อสรุป');
    } else {
        throw new DomainException('คำสั่งไม่ถูกต้อง');
    }
    $pdo->commit();
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash($e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('escrow_action error: ' . $e->getMessage());
    flash('ทำรายการไม่สำเร็จ กรุณาลองใหม่');
}
redirect('pages/payment.php?id=' . $orderId);
