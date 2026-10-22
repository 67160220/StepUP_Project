<?php
$pageTitle = 'สินค้าทั้งหมด';
require_once __DIR__ . '/../includes/header.php';

$q = trim($_GET['q'] ?? '');
$brand = trim($_GET['brand'] ?? '');
$category = trim($_GET['category'] ?? '');
$sort = $_GET['sort'] ?? 'new';

$sql = "SELECT * FROM products WHERE status = 'active'";
$params = [];
if ($q !== '')     { $sql .= ' AND name LIKE ?';  $params[] = "%$q%"; }
if ($brand !== '') { $sql .= ' AND brand = ?';    $params[] = $brand; }
if ($category !== '') { $sql .= ' AND category = ?'; $params[] = $category; }
$sql .= match ($sort) { 'low' => ' ORDER BY price ASC', 'high' => ' ORDER BY price DESC', default => ' ORDER BY id DESC' };

$stmt = $pdo->prepare($sql); $stmt->execute($params);
$products = $stmt->fetchAll();
$brands = $pdo->query('SELECT DISTINCT brand FROM products ORDER BY brand')->fetchAll(PDO::FETCH_COLUMN);
$categories = ['Running','Lifestyle','Sneakers','Training'];
?>
<h2><?= $category !== '' ? e($category) : 'สินค้าทั้งหมด' ?></h2>
<form class="filter" method="get">
  <input name="q" placeholder="ค้นหาชื่อรองเท้า" value="<?= e($q) ?>">
  <select name="brand">
    <option value="">ทุกแบรนด์</option>
    <?php foreach ($brands as $b): ?><option <?= $b === $brand ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
  </select>
  <select name="category">
    <option value="">ทุกหมวดหมู่</option>
    <?php foreach ($categories as $c): ?><option <?= $c === $category ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
  </select>
  <select name="sort">
    <option value="new" <?= $sort==='new'?'selected':'' ?>>ใหม่ล่าสุด</option>
    <option value="low" <?= $sort==='low'?'selected':'' ?>>ราคาต่ำ → สูง</option>
    <option value="high" <?= $sort==='high'?'selected':'' ?>>ราคาสูง → ต่ำ</option>
  </select>
  <button class="btn">กรอง</button>
</form>
<div class="grid">
  <?php foreach ($products as $p) include __DIR__ . '/../includes/product_card.php'; ?>
  <?php if (!$products): ?><p>ไม่พบสินค้า</p><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
