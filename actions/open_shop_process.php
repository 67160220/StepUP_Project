<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_login();
if (is_admin()) { flash('บัญชีแอดมินไม่สามารถเปิดร้านได้'); redirect('admin/index.php'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('pages/open_shop.php');
csrf_check();

$uid = (int)$_SESSION['user']['id'];
$shop = trim($_POST['shop_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$phone = preg_replace('/[\s-]+/', '', trim($_POST['contact_phone'] ?? ''));
$address = trim($_POST['address'] ?? '');
$promptpay = preg_replace('/[\s-]+/', '', trim($_POST['promptpay_id'] ?? ''));
$accountName = trim($_POST['promptpay_account_name'] ?? '');
$len = preg_match_all('/./us', $shop);
$accountLen = preg_match_all('/./us', $accountName);

if ($len < 3 || $len > 60) {
    flash('ชื่อร้านต้องมีความยาว 3-60 ตัวอักษร'); redirect('pages/open_shop.php');
}
if (!preg_match('/^[0-9]{9,10}$/', $phone)) {
    flash('กรุณากรอกเบอร์โทรศัพท์ร้านเป็นตัวเลข 9-10 หลัก'); redirect('pages/open_shop.php');
}
if ($address === '' || preg_match_all('/./us', $address) > 500) {
    flash('กรุณากรอกที่อยู่ร้านให้ครบและไม่เกิน 500 ตัวอักษร'); redirect('pages/open_shop.php');
}
// PromptPay รองรับเลขโทรศัพท์ 10 หลัก หรือเลขประจำตัวประชาชน/เลขผู้เสียภาษี 13 หลัก
if (!preg_match('/^(?:[0-9]{10}|[0-9]{13})$/', $promptpay)) {
    flash('เลขพร้อมเพย์ต้องเป็นเบอร์โทร 10 หลัก หรือเลขประจำตัว 13 หลัก'); redirect('pages/open_shop.php');
}
if ($accountLen < 2 || $accountLen > 100 || empty($_POST['agree'])) {
    flash('กรุณากรอกชื่อบัญชีรับเงินและยอมรับเงื่อนไข'); redirect('pages/open_shop.php');
}
if (preg_match_all('/./us', $description) > 2000) {
    flash('คำอธิบายร้านต้องไม่เกิน 2,000 ตัวอักษร'); redirect('pages/open_shop.php');
}

try {
    $pdo->beginTransaction();
    $check = $pdo->prepare('SELECT id, role FROM users WHERE id = ? FOR UPDATE');
    $check->execute([$uid]);
    $user = $check->fetch();
    if (!$user || $user['role'] === 'admin') {
        $pdo->rollBack(); flash('บัญชีนี้ไม่สามารถเปิดร้านได้'); redirect('pages/open_shop.php');
    }

    $existing = $pdo->prepare('SELECT id FROM stores WHERE owner_user_id = ? LIMIT 1 FOR UPDATE');
    $existing->execute([$uid]);
    $storeId = $existing->fetchColumn();
    if ($storeId) {
        // ให้เจ้าของร้านแก้ข้อมูลได้โดยไม่สร้างร้านซ้ำ
        $upd = $pdo->prepare('UPDATE stores SET store_name=?, description=?, contact_phone=?, address=?, promptpay_id=?, promptpay_account_name=?, status=IF(status="suspended", status, "active") WHERE id=? AND owner_user_id=?');
        $upd->execute([$shop, $description ?: null, $phone, $address, $promptpay, $accountName, $storeId, $uid]);
    } else {
        // slug ใช้รหัสผู้ใช้เพื่อให้ไม่ซ้ำ แม้ชื่อร้านภาษาไทย
        $slug = 'store-' . $uid;
        $ins = $pdo->prepare('INSERT INTO stores (owner_user_id, store_name, slug, description, contact_phone, address, promptpay_id, promptpay_account_name, status) VALUES (?,?,?,?,?,?,?,?,?)');
        $ins->execute([$uid, $shop, $slug, $description ?: null, $phone, $address, $promptpay, $accountName, 'active']);
        $storeId = (int)$pdo->lastInsertId();
    }
    $pdo->prepare('UPDATE users SET is_seller=1, shop_name=? WHERE id=?')->execute([$shop, $uid]);
    $pdo->commit();

    $_SESSION['user']['is_seller'] = 1;
    $_SESSION['user']['shop_name'] = $shop;
    flash('บันทึกข้อมูลร้านสำเร็จ');
    redirect('seller/products.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('StepUP open shop error: ' . $e->getMessage());
    flash('บันทึกร้านไม่สำเร็จ กรุณาตรวจสอบว่ารัน migration สร้างตาราง stores แล้ว');
    redirect('pages/open_shop.php');
}
