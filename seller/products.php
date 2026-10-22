<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_seller();

$userId = (int)($_SESSION['user']['id'] ?? 0);
$storeStmt = $pdo->prepare("SELECT id, store_name, status FROM stores WHERE owner_user_id = ? LIMIT 1");
$storeStmt->execute([$userId]);
$store = $storeStmt->fetch();
if (!$store || $store['status'] !== 'active') {
    flash('ร้านค้าของมึงยังไม่พร้อมขาย');
    redirect('pages/open_shop.php');
}

$stmt = $pdo->prepare('SELECT id, name, brand, category, price, stock, image, status, created_at FROM products WHERE store_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([(int)$store['id']]);
$products = $stmt->fetchAll();
$notice = flash();
$categories = PRODUCT_CATEGORIES;
$sizes = SIZE_OPTIONS;
$colors = COLOR_OPTIONS;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="container" style="max-width:1100px;margin:32px auto;padding:0 16px">
  <h1>จัดการสินค้าในร้าน <?= e($store['store_name']) ?></h1>
  <?php if ($notice): ?><div class="alert alert-info" role="status"><?= e($notice) ?></div><?php endif; ?>

  <section class="card" style="padding:20px;margin:20px 0">
    <h2>ลงขายสินค้าใหม่</h2>
    <form action="<?= e(BASE_URL) ?>/actions/seller_product_save.php" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="form-group"><label for="name">ชื่อสินค้า</label><input id="name" name="name" maxlength="150" required></div>
      <div class="form-group"><label for="brand">แบรนด์</label><input id="brand" name="brand" maxlength="80" required></div>
      <div class="form-group"><label for="category">หมวดหมู่</label><select id="category" name="category" required><option value="">เลือกหมวดหมู่</option><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label for="price">ราคา (บาท)</label><input id="price" name="price" type="number" min="0.01" step="0.01" required></div>
      <div class="form-group"><label for="stock">จำนวนสต็อก</label><input id="stock" name="stock" type="number" min="0" step="1" value="1" required></div>
      <div class="form-group"><label for="description">รายละเอียด</label><textarea id="description" name="description" rows="4" maxlength="10000"></textarea></div>
      <fieldset><legend>ไซส์</legend><?php foreach ($sizes as $s): ?><label style="display:inline-block;margin:4px 10px 4px 0"><input type="checkbox" name="sizes[]" value="<?= e($s) ?>"> <?= e($s) ?></label><?php endforeach; ?><label>ไซส์อื่น ๆ (คั่นด้วยจุลภาค)<input name="sizes_extra" maxlength="100"></label></fieldset>
      <fieldset><legend>สี</legend><?php foreach ($colors as $label => $hex): ?><label style="display:inline-block;margin:4px 10px 4px 0"><input type="checkbox" name="colors[]" value="<?= e($label) ?>"> <?= e($label) ?></label><?php endforeach; ?><label>สีอื่น ๆ (คั่นด้วยจุลภาค)<input name="colors_extra" maxlength="100"></label></fieldset>
      <div class="form-group"><label for="image">รูปสินค้า (JPG, PNG หรือ WEBP ไม่เกิน 2MB)</label><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
      <button type="submit" class="btn btn-primary">ลงขายสินค้า</button>
    </form>
  </section>

  <section>
    <h2>สินค้าของร้าน (<?= count($products) ?>)</h2>
    <?php if (!$products): ?><p>ร้านนี้ยังไม่มีสินค้า</p><?php else: ?>
      <div class="table-responsive"><table class="table" style="width:100%;border-collapse:collapse">
        <thead><tr><th>รูป</th><th>สินค้า</th><th>ราคา</th><th>สต็อก</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
        <tbody><?php foreach ($products as $p): ?><tr>
          <td><?php if (!empty($p['image'])): ?><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" style="width:64px;height:64px;object-fit:cover"><?php else: ?>- สินค้าไม่มีรูป<?php endif; ?></td>
          <td><?= e($p['name']) ?><br><small><?= e($p['brand']) ?> · <?= e($p['category']) ?></small></td>
          <td><?= e(number_format((float)$p['price'], 2)) ?> บาท</td><td><?= (int)$p['stock'] ?></td>
          <td><?= $p['status'] === 'active' ? 'กำลังขาย' : 'ซ่อนอยู่' ?></td>
          <td><?php if ($p['status'] === 'active'): ?><form action="<?= e(BASE_URL) ?>/actions/seller_product_delete.php" method="post" onsubmit="return confirm('ซ่อนสินค้านี้จากหน้าร้านใช่ไหม?')"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button type="submit" class="btn btn-danger">ซ่อนสินค้า</button></form><?php else: ?>- <?php endif; ?></td>
        </tr><?php endforeach; ?></tbody>
      </table></div>
    <?php endif; ?>
  </section>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
