<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('pages/products.php');
require_login();            // ต้องล็อกอินก่อนซื้อ
csrf_check();

$id = (int)($_POST['product_id'] ?? 0);
$size = trim($_POST['size'] ?? '');
$color = trim($_POST['color'] ?? '');
$qty = max(1, min(10, (int)($_POST['qty'] ?? 1)));

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) redirect('pages/products.php');

// แอดมินซื้อไม่ได้ / ซื้อสินค้าร้านตัวเองไม่ได้
if ($why = purchase_block_reason($p)) {
    flash($why);
    redirect('pages/product_detail.php?id=' . $id);
}

$key = "$id-$size-$color";
$inCart = 0;                                   // จำนวนสินค้ารุ่นนี้ที่อยู่ในตะกร้าแล้ว (ทุกไซส์/สี)
foreach ($_SESSION['cart'] ?? [] as $it) if ($it['id'] === $id) $inCart += $it['qty'];

if ($inCart + $qty > (int)$p['stock']) {
    flash('สินค้าคงเหลือไม่พอ (เหลือ ' . (int)$p['stock'] . ' ชิ้น)');
    redirect('pages/product_detail.php?id=' . $id);
}

if (isset($_SESSION['cart'][$key])) {
    $_SESSION['cart'][$key]['qty'] = min(10, $_SESSION['cart'][$key]['qty'] + $qty);
} else {
    $_SESSION['cart'][$key] = ['id' => $id, 'name' => $p['name'], 'price' => $p['price'],
                               'size' => $size, 'color' => $color, 'qty' => $qty];
}
flash('เพิ่มสินค้าลงตะกร้าแล้ว');
redirect('pages/cart.php');
