<?php
$pageTitle = 'จัดการสินค้า';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_admin();

// แอดมินเพิ่มสินค้าไม่ได้ (ร้านค้าเป็นผู้ลงขายเอง) ทำได้เฉพาะตรวจสอบ ซ่อน/แสดง และลบสินค้าที่ไม่เหมาะสม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'delete' && $id) {
        $im = $pdo->prepare('SELECT image FROM product_images WHERE product_id = ?'); $im->execute([$id]);
        $files = array_column($im->fetchAll(), 'image');
        $cv = $pdo->prepare('SELECT image FROM products WHERE id = ?'); $cv->execute([$id]); $files[] = $cv->fetchColumn();
        $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
        foreach (array_unique($files) as $f) delete_product_file($f);
        flash('ลบสินค้าแล้ว');
    } elseif ($act === 'toggle' && $id) {
        $pdo->prepare("UPDATE products SET status = IF(status='active','hidden','active') WHERE id=?")->execute([$id]);
        flash('เปลี่ยนสถานะการแสดงสินค้าแล้ว');
    }
    redirect('admin/manage_products.php');
}

require_once __DIR__ . '/../includes/header.php';
$list = $pdo->query('SELECT p.*, u.shop_name FROM products p LEFT JOIN users u ON u.id = p.seller_id ORDER BY p.id DESC')->fetchAll();
?>
<h2>ตรวจสอบสินค้าทั้งหมด (ทุกร้าน)</h2>
<p class="hint">แอดมินไม่สามารถเพิ่มสินค้าได้ สินค้าลงขายโดยร้านค้าเท่านั้น แอดมินมีหน้าที่ตรวจสอบ ซ่อน หรือลบสินค้าที่ไม่เหมาะสม</p>
<div class="table-wrap">
<table class="table">
  <tr><th>ID</th><th>สินค้า</th><th>ร้าน</th><th>แบรนด์</th><th>ราคา</th><th>สต็อก</th><th>สถานะ</th><th></th></tr>
  <?php foreach ($list as $p): ?>
  <tr>
    <td><?= (int)$p['id'] ?></td>
    <td><a href="<?= BASE_URL ?>/pages/product_detail.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></td>
    <td><?= e($p['shop_name'] ?: 'Shoe Store') ?></td>
    <td><?= e($p['brand']) ?></td><td><?= baht($p['price']) ?></td><td><?= (int)$p['stock'] ?></td>
    <td><?= $p['status'] === 'active' ? 'แสดงขาย' : 'ซ่อน' ?></td>
    <td>
      <form method="post" class="inline" style="display:inline">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button class="btn secondary" name="act" value="toggle"><?= $p['status'] === 'active' ? 'ซ่อน' : 'แสดง' ?></button>
        <button class="btn secondary" name="act" value="delete" data-confirm="ลบสินค้านี้ถาวร?">ลบ</button>
      </form>
    </td>
  </tr>
  <?php endforeach; if (!$list) echo '<tr><td colspan="8">ยังไม่มีสินค้า</td></tr>'; ?>
</table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
