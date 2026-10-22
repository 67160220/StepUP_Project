<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('seller/products.php');
require_seller();
csrf_check();

$userId = (int)($_SESSION['user']['id'] ?? 0);
$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$productId) {
    flash('รหัสสินค้าไม่ถูกต้อง');
    redirect('seller/products.php');
}

try {
    $stmt = $pdo->prepare("SELECT p.id, p.image FROM products p
        INNER JOIN stores s ON s.id = p.store_id
        WHERE p.id = ? AND s.owner_user_id = ? AND s.status = 'active' LIMIT 1");
    $stmt->execute([$productId, $userId]);
    $product = $stmt->fetch();
    if (!$product) {
        flash('ไม่พบสินค้า หรือมึงไม่มีสิทธิ์ลบสินค้านี้');
        redirect('seller/products.php');
    }

    // ลบแบบ soft delete เพื่อไม่ให้สินค้าที่มีประวัติคำสั่งซื้อหายจากหน้าร้าน
    $del = $pdo->prepare("UPDATE products SET status = 'hidden' WHERE id = ? AND store_id = ?");
    $storeStmt = $pdo->prepare('SELECT id FROM stores WHERE owner_user_id = ? LIMIT 1');
    $storeStmt->execute([$userId]);
    $storeId = (int)$storeStmt->fetchColumn();
    $del->execute([$productId, $storeId]);
    flash('ซ่อนสินค้าจากหน้าร้านแล้ว');
} catch (Throwable $e) {
    error_log('StepUP seller_product_delete error: ' . $e->getMessage());
    flash('ดำเนินการไม่สำเร็จ กรุณาลองใหม่');
}
redirect('seller/products.php');
