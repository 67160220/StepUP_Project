<?php
// ร้านค้ากดจัดส่ง "ต้องใส่เลขพัสดุ" ใช้แทนปุ่มจัดส่งเดิม
// POST: order_id, tracking_no, carrier (ไม่บังคับ)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/../includes/escrow_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('pages/my_orders.php');
require_login();
csrf_check();

$userId = (int)($_SESSION['user']['id'] ?? 0);
$orderId = (int)($_POST['order_id'] ?? 0);
$tracking = strtoupper(trim((string)($_POST['tracking_no'] ?? '')));
$carrier = mb_substr(trim((string)($_POST['carrier'] ?? '')), 0, 40);

try {
    if (!preg_match('/^[A-Z0-9\-]{6,40}$/', $tracking)) throw new DomainException('กรุณากรอกเลขพัสดุให้ถูกต้อง (ตัวอักษรอังกฤษ/ตัวเลข 6-40 ตัว)');
    $pdo->beginTransaction();

    $s = $pdo->prepare("SELECT id FROM stores WHERE owner_user_id = ? AND status = 'active'");
    $s->execute([$userId]);
    $storeId = (int)$s->fetchColumn();
    if (!$storeId) throw new DomainException('ไม่พบร้านค้าของคุณ หรือร้านถูกระงับ');

    $p = $pdo->prepare("SELECT p.* FROM payments p JOIN orders o ON o.id = p.order_id
                        WHERE p.order_id = ? AND p.store_id = ? AND o.status IN ('paid','shipped') FOR UPDATE");
    $p->execute([$orderId, $storeId]);
    $pay = $p->fetch(PDO::FETCH_ASSOC);
    if (!$pay) throw new DomainException('ไม่พบรายการนี้ หรือยังไม่ได้ชำระเงิน');
    if ($pay['shipped_at'] !== null) throw new DomainException('รายการนี้จัดส่งแล้ว');
    $okMoney = $pay['method'] === 'promptpay' ? $pay['escrow_status'] === 'held' : ($pay['method'] === 'cod' && $pay['status'] === 'pending');
    if (!$okMoney) throw new DomainException('รายการนี้ไม่อยู่ในสถานะที่จัดส่งได้ (อาจถูกคืนเงินหรือยกเลิกแล้ว)');

    $d = escrow_release_days();
    $pdo->prepare("UPDATE payments SET tracking_no = ?, carrier = ?, shipped_at = NOW(),
                   auto_release_at = DATE_ADD(NOW(), INTERVAL $d DAY) WHERE id = ?")->execute([$tracking, $carrier ?: null, $pay['id']]);
    $pdo->prepare("UPDATE order_items SET ship_status = 'shipped' WHERE order_id = ? AND store_id = ?")->execute([$orderId, $storeId]);

    $left = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND ship_status = 'pending'");
    $left->execute([$orderId]);
    if ((int)$left->fetchColumn() === 0) $pdo->prepare("UPDATE orders SET status = 'shipped' WHERE id = ? AND status = 'paid'")->execute([$orderId]);

    $pdo->commit();
    flash('บันทึกการจัดส่งแล้ว');
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash($e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('seller_mark_shipped error: ' . $e->getMessage());
    flash('บันทึกไม่สำเร็จ กรุณาลองใหม่');
}
escrow_back('pages/my_orders.php');
