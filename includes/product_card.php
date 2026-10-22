<div class="card">
  <a href="<?= BASE_URL ?>/pages/product_detail.php?id=<?= (int)$p['id'] ?>">
    <div class="card-image">
      <?php if (!empty($p['image'])): ?>
        <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
      <?php else: ?>
        <div class="noimg" aria-hidden="true">👟</div>
      <?php endif; ?>
      <span class="quick">ดูรายละเอียดสินค้า →</span>
    </div>
    <h3><?= e($p['name']) ?></h3>
    <p class="brand"><?= e($p['brand']) ?></p>
    <p class="price"><?= baht($p['price']) ?></p>
  </a>
</div>
