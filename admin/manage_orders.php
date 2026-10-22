<?php
$pageTitle = 'จัดการคำสั่งซื้อ';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $status = $_POST['status'] ?? '';
    $oid = (int)($_POST['id'] ?? 0);
    if (in_array($status, ['pending','paid','shipped','cancelled'], true) && $oid > 0) {
        $old = $pdo->prepare('SELECT status FROM orders WHERE id=?'); $old->execute([$oid]);
        $old = $old->fetchColumn();
        if ($old === false) { flash('ไม่พบคำสั่งซื้อ'); redirect('admin/manage_orders.php'); }
        if ($old === 'cancelled') { flash('คำสั่งซื้อที่ยกเลิกแล้วเปลี่ยนสถานะไม่ได้'); redirect('admin/manage_orders.php'); }
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status, $oid]);
            if ($status === 'cancelled') {   // ยกเลิก = คืนสต็อก
                $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.stock = p.stock + oi.qty WHERE oi.order_id = ?')->execute([$oid]);
            }
            $pdo->commit();
            flash('อัปเดตสถานะแล้ว');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    }
    redirect('admin/manage_orders.php');
}
require_once __DIR__ . '/../includes/header.php';
$orders = $pdo->query('SELECT o.*, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC')->fetchAll();
$itemStmt = $pdo->prepare('SELECT oi.*, u.shop_name FROM order_items oi LEFT JOIN users u ON u.id = oi.seller_id WHERE oi.order_id=?');
?>
<h2>คำสั่งซื้อจากลูกค้า</h2>
<?php foreach ($orders as $o): $itemStmt->execute([$o['id']]); ?>
<div class="order">
  <div class="order-head">
    <b>#<?= (int)$o['id'] ?></b> <?= e($o['customer_name']) ?> (<?= e($o['phone']) ?>) — <?= baht($o['total']) ?>
    <small><?= e($o['created_at']) ?><?= $o['email'] ? ' · บัญชี ' . e($o['email']) : '' ?></small>
  </div>
  <p>📦 <?= nl2br(e($o['address'])) ?></p>
  <?php if (!empty($o['slip_image'])): ?>
  <p>🧾 สลิปโอนเงิน (<?= e($o['slip_at']) ?>):
    <a href="<?= e($o['slip_image']) ?>" target="_blank"><img src="<?= e($o['slip_image']) ?>" alt="สลิป" style="max-height:120px;vertical-align:middle;border:1px solid #e5e5e5"></a>
    <?php $sr = $o['slip_result'] ?? null; ?>
    <?php if ($sr === 'verified'): ?><b style="color:#16a34a">— ✔ ตรวจอัตโนมัติผ่าน (อ้างอิง <?= e($o['slip_ref']) ?>)</b>
    <?php elseif ($sr === 'rejected'): ?><b style="color:#e11d48">— ✖ ระบบตีกลับ: <?= e($o['slip_note']) ?></b>
    <?php elseif ($o['status'] === 'pending'): ?><b>— รอตรวจสอบเอง<?= !empty($o['slip_note']) ? ' (' . e($o['slip_note']) . ')' : '' ?></b><?php endif; ?></p>
  <?php elseif ($o['status'] === 'pending'): ?><p><small>ยังไม่ได้แนบสลิป</small></p><?php endif; ?>
  <ul>
    <?php foreach ($itemStmt->fetchAll() as $it): ?>
      <li><?= e($it['product_name']) ?> — ไซส์ <?= e($it['size']) ?>, <?= e($it['color']) ?> × <?= (int)$it['qty'] ?>
        · ร้าน <?= e($it['shop_name'] ?: 'Shoe Store') ?>
        · <?= $it['ship_status'] === 'shipped' ? 'ร้านจัดส่งแล้ว' : 'รอร้านจัดส่ง' ?></li>
    <?php endforeach; ?>
  </ul>
  <form method="post" class="inline">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
    <select name="status">
      <?php foreach (['pending'=>'รอชำระ','paid'=>'ชำระแล้ว','shipped'=>'จัดส่งแล้ว','cancelled'=>'ยกเลิก'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $o['status']===$k?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn secondary">อัปเดต</button>
  </form>
</div>
<?php endforeach; if (!$orders) echo '<p>ยังไม่มีคำสั่งซื้อ</p>'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
