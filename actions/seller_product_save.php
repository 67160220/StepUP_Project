<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('seller/products.php');
require_seller();
csrf_check();

$userId = (int)($_SESSION['user']['id'] ?? 0);
$storeStmt = $pdo->prepare("SELECT id FROM stores WHERE owner_user_id = ? AND status = 'active' LIMIT 1");
$storeStmt->execute([$userId]);
$storeId = (int)$storeStmt->fetchColumn();
if (!$storeId) {
    flash('ไม่พบร้านค้าที่เปิดใช้งาน กรุณาตรวจสอบข้อมูลร้านก่อนลงสินค้า');
    redirect('pages/open_shop.php');
}

$name = trim((string)($_POST['name'] ?? ''));
$brand = trim((string)($_POST['brand'] ?? ''));
$category = trim((string)($_POST['category'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
$stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
$sizes = collect_list($_POST['sizes'] ?? [], (string)($_POST['sizes_extra'] ?? ''), SIZE_OPTIONS, '/^[0-9]{1,3}$/');
$colors = collect_list($_POST['colors'] ?? [], (string)($_POST['colors_extra'] ?? ''), array_keys(COLOR_OPTIONS), '/^[\p{L}\p{N} #_-]{1,25}$/u');

$errors = [];
if ($name === '' || mb_len_stepup($name) > 150) $errors[] = 'ชื่อสินค้าต้องมี 1-150 ตัวอักษร';
if ($brand === '' || mb_len_stepup($brand) > 80) $errors[] = 'กรุณาระบุแบรนด์ไม่เกิน 80 ตัวอักษร';
if (!in_array($category, PRODUCT_CATEGORIES, true)) $errors[] = 'กรุณาเลือกหมวดหมู่สินค้า';
if ($price === false || $price <= 0 || $price > 99999999) $errors[] = 'ราคาสินค้าไม่ถูกต้อง';
if ($stock === false || $stock < 0 || $stock > 1000000) $errors[] = 'จำนวนสต็อกไม่ถูกต้อง';
if ($sizes === '') $sizes = '38,39,40,41,42,43';
if ($colors === '') $colors = 'ดำ,ขาว';
if (mb_len_stepup($description) > 10000) $errors[] = 'รายละเอียดสินค้ายาวเกินไป';

$image = null;
if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $uploadError = null;
    $image = save_upload($_FILES['image'], $uploadError, 'products', 2 * 1024 * 1024);
    if ($uploadError !== null || $image === null) $errors[] = $uploadError ?: 'อัปโหลดรูปสินค้าไม่สำเร็จ';
}

if ($errors) {
    if ($image) delete_product_file($image);
    flash(implode(' / ', $errors));
    redirect('seller/products.php');
}

try {
    // store_id ถูกหาโดย session ฝั่งเซิร์ฟเวอร์เท่านั้น ห้ามรับจากฟอร์ม
    $stmt = $pdo->prepare("INSERT INTO products
        (seller_id, store_id, name, brand, category, price, stock, image, description, sizes, colors, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$userId, $storeId, $name, $brand, $category, $price, $stock, $image, $description, $sizes, $colors]);
    flash('ลงขายสินค้าเรียบร้อยแล้ว');
} catch (Throwable $e) {
    if ($image) delete_product_file($image);
    error_log('StepUP seller_product_save error: ' . $e->getMessage());
    flash('บันทึกสินค้าไม่สำเร็จ กรุณาตรวจสอบโครงสร้างฐานข้อมูลหรือแจ้งผู้ดูแลระบบ');
}
redirect('seller/products.php');

function mb_len_stepup(string $value): int {
    return preg_match_all('/./us', $value, $matches) ?: 0;
}
