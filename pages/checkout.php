<?php
$pageTitle = 'ชำระเงิน';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_login();
if (is_admin()) { flash('บัญชีแอดมินไม่สามารถสั่งซื้อสินค้าได้'); redirect('admin/index.php'); }
$cart = $_SESSION['cart'] ?? [];
if (!$cart) redirect('pages/cart.php');
require_once __DIR__ . '/../includes/header.php';
$total = 0; foreach ($cart as $it) $total += $it['price'] * $it['qty'];
?>
<h2>ข้อมูลการจัดส่ง</h2>
<p class="total">ยอดชำระ: <?= baht($total) ?></p>
<form class="form" method="post" action="<?= BASE_URL ?>/actions/process_order.php">
  <?= csrf_field() ?>
  <label>ชื่อ-นามสกุล</label><input name="name" required value="<?= e($_SESSION['user']['name'] ?? '') ?>">
  <label>เบอร์โทร</label><input name="phone" required pattern="[0-9]{9,10}" title="กรอกเบอร์โทร 9-10 หลัก">
  <label>ที่อยู่จัดส่ง</label><textarea name="address" rows="4" required></textarea>
  <button class="btn">ยืนยันการสั่งซื้อ</button>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
