<?php
$pageTitle = 'ตะกร้าสินค้า';
require_once __DIR__ . '/../includes/header.php';
$cart = $_SESSION['cart'] ?? [];
$total = 0;
?>
<h2>ตะกร้าสินค้า</h2>
<?php if (!$cart): ?>
  <p>ตะกร้าว่างเปล่า <a href="products.php">ไปเลือกสินค้า</a></p>
<?php else: ?>
<form method="post" action="<?= BASE_URL ?>/actions/update_cart.php">
  <?= csrf_field() ?>
  <div class="table-wrap">
  <table class="table">
    <tr><th>สินค้า</th><th>ไซส์/สี</th><th>ราคา</th><th>จำนวน</th><th>รวม</th><th></th></tr>
    <?php foreach ($cart as $key => $it): $sub = $it['price'] * $it['qty']; $total += $sub; ?>
    <tr>
      <td><?= e($it['name']) ?></td>
      <td><?= e($it['size']) ?> / <?= e($it['color']) ?></td>
      <td><?= baht($it['price']) ?></td>
      <td><input type="number" name="qty[<?= e($key) ?>]" value="<?= (int)$it['qty'] ?>" min="0" max="10"></td>
      <td><?= baht($sub) ?></td>
      <td><button type="submit" form="remove-form" name="remove" value="<?= e($key) ?>" class="del" style="background:none;border:0;cursor:pointer;font:inherit;padding:0">ลบ</button></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="total">รวมทั้งหมด: <?= baht($total) ?></p>
  <button class="btn secondary">อัปเดตจำนวน</button>
  <a class="btn" href="checkout.php">ไปชำระเงิน</a>
</form>
<form id="remove-form" method="post" action="<?= BASE_URL ?>/actions/update_cart.php"><?= csrf_field() ?></form>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
