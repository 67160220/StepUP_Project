<?php
$pageTitle = 'คำสั่งซื้อของฉัน';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_login();
require_once __DIR__ . '/../includes/header.php';

$st = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
$st->execute([(int)$_SESSION['user']['id']]);
$orders = $st->fetchAll();
$itemStmt = $pdo->prepare('SELECT oi.*, u.shop_name FROM order_items oi LEFT JOIN users u ON u.id = oi.seller_id WHERE oi.order_id = ?');
$statusTh = ['pending' => 'รอชำระเงิน', 'paid' => 'ชำระแล้ว', 'shipped' => 'จัดส่งแล้ว', 'cancelled' => 'ยกเลิก'];
?>
<h2>คำสั่งซื้อของฉัน</h2>
<?php foreach ($orders as $o): $itemStmt->execute([$o['id']]); ?>
<div class="order">
  <div class="order-head">
    <b>#<?= (int)$o['id'] ?></b> — <?= baht($o['total']) ?>
    <span class="pill <?= $o['status'] === 'cancelled' ? 'off' : ($o['status'] === 'pending' ? 'wait' : 'ok') ?>"><?= e($o['status'] === 'pending' && !empty($o['slip_image']) ? (($o['slip_result'] ?? null) === 'rejected' ? 'สลิปไม่ผ่าน' : 'รอตรวจสอบสลิป') : ($statusTh[$o['status']] ?? $o['status'])) ?></span>
    <?php if ($o['status'] === 'pending'): ?><a class="btn secondary" href="payment.php?id=<?= (int)$o['id'] ?>"><?= !empty($o['slip_image']) ? 'ดู/ส่งสลิปใหม่' : 'ชำระเงิน' ?></a><?php endif; ?>
    <small><?= e($o['created_at']) ?></small>
  </div>
  <p>📦 <?= e($o['customer_name']) ?> · <?= e($o['phone']) ?><br><?= nl2br(e($o['address'])) ?></p>
  <ul>
    <?php foreach ($itemStmt->fetchAll() as $it): ?>
      <li><?= e($it['product_name']) ?> — ไซส์ <?= e($it['size']) ?>, <?= e($it['color']) ?> × <?= (int)$it['qty'] ?>
        · <?= baht($it['price'] * $it['qty']) ?>
        · ร้าน <?= e($it['shop_name'] ?: 'Shoe Store') ?>
        <?= $it['ship_status'] === 'shipped' ? ' · ✅ จัดส่งแล้ว' : '' ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endforeach; if (!$orders) echo '<p>ยังไม่มีคำสั่งซื้อ <a href="products.php">ไปเลือกสินค้า</a></p>'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
