<?php
$pageTitle = 'คำสั่งซื้อของร้าน';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_seller();
require_once __DIR__ . '/../includes/header.php';
$sid = (int)$_SESSION['user']['id'];

$st = $pdo->prepare('SELECT oi.*, o.customer_name, o.phone, o.address, o.status AS order_status, o.created_at
                     FROM order_items oi JOIN orders o ON o.id = oi.order_id
                     WHERE oi.seller_id = ? ORDER BY o.id DESC, oi.id DESC');
$st->execute([$sid]);
$items = $st->fetchAll();
?>
<h2>คำสั่งซื้อของร้าน</h2>
<p class="hint">ข้อมูลที่อยู่ผู้ซื้อจะแสดงหลังแอดมินยืนยันการชำระเงินแล้วเท่านั้น และจัดส่งได้เฉพาะรายการที่ชำระเงินแล้ว</p>
<?php foreach ($items as $it): $paid = in_array($it['order_status'], ['paid', 'shipped'], true); ?>
<div class="order">
  <b>#<?= (int)$it['order_id'] ?></b> · <?= e($it['product_name']) ?> — ไซส์ <?= e($it['size']) ?>, <?= e($it['color']) ?> × <?= (int)$it['qty'] ?>
  = <b><?= baht($it['price'] * $it['qty']) ?></b>
  <small><?= e($it['created_at']) ?></small>
  <?php if ($it['order_status'] === 'cancelled'): ?><span class="pill off">ยกเลิก</span>
  <?php elseif (!$paid): ?><span class="pill wait">รอยืนยันชำระเงิน</span>
  <?php elseif ($it['ship_status'] === 'shipped'): ?><span class="pill ok">จัดส่งแล้ว</span>
  <?php else: ?><span class="pill wait">รอจัดส่ง</span><?php endif; ?>

  <?php if ($paid): ?>
  <p>👤 <?= e($it['customer_name']) ?> · 📞 <?= e($it['phone']) ?><br>📦 <?= nl2br(e($it['address'])) ?></p>
  <?php if ($it['ship_status'] === 'pending'): ?>
  <form method="post" action="<?= BASE_URL ?>/actions/seller_ship_item.php">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
    <button class="btn secondary" data-confirm="ยืนยันว่าจัดส่งสินค้านี้แล้ว?">ทำเครื่องหมายว่าจัดส่งแล้ว</button>
  </form>
  <?php endif; endif; ?>
</div>
<?php endforeach; if (!$items) echo '<p>ยังไม่มีคำสั่งซื้อ</p>'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
