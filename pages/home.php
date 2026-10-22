<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$pageTitle = 'หน้าแรก';
require_once __DIR__ . '/../includes/header.php';
$latest = $pdo->query('SELECT * FROM products WHERE status = \'active\' ORDER BY created_at DESC, id DESC LIMIT 4')->fetchAll();
$heroProduct = $latest[0] ?? null;
?>
<section class="hero-new">
  <div class="hero-copy">
    <div class="eyebrow">SHOE.STORE · NEW COLLECTION</div>
    <h1>STEP INTO<br>YOUR STYLE.</h1>
    <p>รองเท้าที่ใช่สำหรับทุกก้าวของคุณ ดีไซน์ที่ใส่ได้จริงตั้งแต่วันทำงานจนถึงวันพักผ่อน</p>
    <a class="btn" href="products.php">เลือกซื้อสินค้า <span aria-hidden="true">→</span></a>
  </div>
  <div class="hero-visual">
    <?php if ($heroProduct && !empty($heroProduct['image'])): ?>
      <img class="hero-product" src="<?= e($heroProduct['image']) ?>" alt="<?= e($heroProduct['name']) ?>">
    <?php else: ?>
      <div class="hero-fallback" aria-hidden="true">👟</div>
    <?php endif; ?>
  </div>
</section>

<div class="section-head"><h2>เลือกตามสไตล์</h2><a href="products.php">ดูสินค้าทั้งหมด →</a></div>
<section class="category-grid">
  <a class="category" href="products.php?category=Running"><div><h3>Running</h3><p>เบา คล่องตัว พร้อมทุกระยะ</p></div></a>
  <a class="category" href="products.php?category=Lifestyle"><div><h3>Lifestyle</h3><p>ใส่ทุกวันแบบมีสไตล์</p></div></a>
  <a class="category" href="products.php?category=Sneakers"><div><h3>Sneakers</h3><p>ดีไซน์ที่ไม่มีวันตกยุค</p></div></a>
  <a class="category" href="products.php?category=Training"><div><h3>Training</h3><p>พร้อมลุยทุกกิจกรรม</p></div></a>
</section>

<div class="section-head"><h2>สินค้าเข้าใหม่</h2><a href="products.php">ดูทั้งหมด →</a></div>
<div class="grid">
  <?php foreach ($latest as $p): include __DIR__ . '/../includes/product_card.php'; endforeach; ?>
</div>

<section class="features">
  <div class="feature"><strong>จัดส่งฟรี</strong><span>เมื่อซื้อครบ ฿3,000</span></div>
  <div class="feature"><strong>ชำระเงินปลอดภัย</strong><span>ซื้อสินค้าได้อย่างมั่นใจ</span></div>
  <div class="feature"><strong>เลือกง่าย ซื้อสะดวก</strong><span>เลือกไซส์และสีได้ในหน้าเดียว</span></div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
