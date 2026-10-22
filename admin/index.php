<?php
$pageTitle = 'แดชบอร์ด';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_admin();
require_once __DIR__ . '/../includes/header.php';
$products = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$hidden   = $pdo->query("SELECT COUNT(*) FROM products WHERE status='hidden'")->fetchColumn();
$orders   = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$pending  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$slips    = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending' AND slip_image IS NOT NULL")->fetchColumn();
$revenue  = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('paid','shipped')")->fetchColumn();
$users    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$sellers  = $pdo->query("SELECT COUNT(*) FROM users WHERE is_seller=1")->fetchColumn();
?>
<h2>แดชบอร์ดผู้ดูแล</h2>
<div class="stats">
  <div class="stat"><b><?= $products ?></b>สินค้า (ซ่อนอยู่ <?= $hidden ?>)</div>
  <div class="stat"><b><?= $orders ?></b>คำสั่งซื้อทั้งหมด</div>
  <div class="stat"><b><?= $pending ?></b>รอชำระเงิน</div>
  <div class="stat"><b><?= $slips ?></b>รอตรวจสลิป</div>
  <div class="stat"><b><?= baht($revenue) ?></b>รายได้</div>
  <div class="stat"><b><?= $users ?></b>ผู้ใช้</div>
  <div class="stat"><b><?= $sellers ?></b>ร้านค้า</div>
</div>
<a class="btn" href="manage_products.php">จัดการสินค้า</a>
<a class="btn secondary" href="manage_orders.php">จัดการคำสั่งซื้อ</a>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
