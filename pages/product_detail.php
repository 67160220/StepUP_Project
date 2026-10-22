<?php
require_once __DIR__ . '/../includes/header.php';
$stmt = $pdo->prepare("SELECT p.*, u.shop_name FROM products p LEFT JOIN users u ON u.id = p.seller_id WHERE p.id = ? AND p.status = 'active'");
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$p = $stmt->fetch();
if (!$p) { echo '<p>ไม่พบสินค้า</p>'; require __DIR__ . '/../includes/footer.php'; exit; }
$pageTitle = $p['name'];
$qi = $pdo->prepare('SELECT image FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
$qi->execute([$p['id']]);
$imgs = array_column($qi->fetchAll(), 'image');
if (!$imgs && !empty($p['image'])) $imgs = [$p['image']];   // สินค้าเก่า / รูปจาก URL ภายนอก
$loggedIn = isset($_SESSION['user']);
$blockReason = $loggedIn ? purchase_block_reason($p) : null;   // แอดมิน / ร้านตัวเอง
?>
<style>
  .gallery{min-width:0}
  .thumbs{display:flex;gap:8px;overflow-x:auto;margin-top:10px;padding-bottom:4px}
  .thumb{flex:none;width:70px;height:70px;padding:0;border:2px solid transparent;background:#efefed;cursor:pointer}
  .thumb.on{border-color:var(--ink)}
  .thumb img{width:100%;height:100%;object-fit:cover;display:block}
</style>
<div class="detail">
  <div class="gallery">
    <div class="detail-img">
      <?php if ($imgs): ?>
        <img id="main-img" src="<?= e($imgs[0]) ?>" alt="<?= e($p['name']) ?>">
      <?php else: ?>
        <div class="noimg big" aria-hidden="true">👟</div>
      <?php endif; ?>
    </div>
    <?php if (count($imgs) > 1): ?>
    <div class="thumbs">
      <?php foreach ($imgs as $i => $u): ?>
        <button type="button" class="thumb<?= $i === 0 ? ' on' : '' ?>" data-src="<?= e($u) ?>" aria-label="รูปที่ <?= $i + 1 ?>"><img src="<?= e($u) ?>" alt=""></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="detail-info">
    <p class="brand"><?= e($p['brand']) ?></p>
    <h1><?= e($p['name']) ?></h1>
    <p class="price big"><?= baht($p['price']) ?></p>
    <p class="brand">ขายโดย <?= e($p['shop_name'] ?: 'Shoe Store') ?> · <?= (int)$p['stock'] > 0 ? 'คงเหลือ ' . (int)$p['stock'] . ' ชิ้น' : 'สินค้าหมด' ?></p>
    <p><?= nl2br(e($p['description'])) ?></p>
    <form method="post" action="<?= BASE_URL ?>/actions/add_to_cart.php">
      <?= csrf_field() ?>
      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      <label for="size">เลือกไซส์</label>
      <select id="size" name="size" required>
        <?php foreach (explode(',', $p['sizes']) as $s): ?><option><?= e(trim($s)) ?></option><?php endforeach; ?>
      </select>
      <label for="color">เลือกสี</label>
      <select id="color" name="color" required>
        <?php foreach (explode(',', $p['colors']) as $c): ?><option><?= e(trim($c)) ?></option><?php endforeach; ?>
      </select>
      <label for="qty">จำนวน</label>
      <input id="qty" type="number" name="qty" value="1" min="1" max="<?= max(1, min(10, (int)$p['stock'])) ?>">
      <?php if (!$loggedIn): ?>
        <a class="btn" href="<?= BASE_URL ?>/pages/login.php">เข้าสู่ระบบเพื่อสั่งซื้อ →</a>
      <?php elseif ($blockReason): ?>
        <button class="btn" type="button" disabled><?= e($blockReason) ?></button>
      <?php elseif ((int)$p['stock'] > 0): ?>
        <button class="btn" type="submit">เพิ่มลงตะกร้า →</button>
      <?php else: ?>
        <button class="btn" type="button" disabled>สินค้าหมด</button>
      <?php endif; ?>
    </form>
  </div>
</div>
<script>
document.querySelectorAll('.thumb').forEach(function (t) {
  t.addEventListener('click', function () {
    document.getElementById('main-img').src = t.dataset.src;
    document.querySelectorAll('.thumb').forEach(function (x) { x.classList.toggle('on', x === t); });
  });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
