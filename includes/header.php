<?php require_once __DIR__ . '/../config/database.php'; require_once __DIR__ . '/seller_functions.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'StepUP Store') ?> | StepUP Store</title>
<meta name="description" content="StepUP Store รองเท้าสไตล์โมเดิร์นสำหรับทุกวัน">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=20261003-v3">
</head>
<body>
<nav class="navbar">
<a class="logo" href="<?= BASE_URL ?>/pages/home.php">StepUP<span>.</span>Store</a>
<div class="menu">
<a href="<?= BASE_URL ?>/pages/home.php">หน้าแรก</a>
<a href="<?= BASE_URL ?>/pages/products.php">สินค้า</a>
<a href="<?= BASE_URL ?>/pages/ai_shoe_finder.php">AI Shoe Finder</a>
<a href="<?= BASE_URL ?>/pages/cart.php">🛒 ตะกร้า (<?= cart_count() ?>)</a>
<?php if (isset($_SESSION['user']) && !is_admin()): ?><a href="<?= BASE_URL ?>/pages/my_orders.php">คำสั่งซื้อของฉัน</a><?php endif; ?>
<?php if (is_admin()): ?><a href="<?= BASE_URL ?>/admin/index.php">หลังบ้าน</a><?php endif; ?>
<?php if (is_seller()): ?><a href="<?= BASE_URL ?>/seller/index.php">ร้านของฉัน</a>
<a href="<?= BASE_URL ?>/seller/assistant.php">🤖 ผู้ช่วยร้าน</a>
<?php elseif (isset($_SESSION['user']) && !is_admin()): ?><a href="<?= BASE_URL ?>/pages/open_shop.php">เปิดร้านขายของ</a><?php endif; ?>
<?php if (isset($_SESSION['user'])): ?>
<a href="<?= BASE_URL ?>/actions/logout.php">ออกจากระบบ</a>
<?php else: ?><a href="<?= BASE_URL ?>/pages/register.php">สมัครสมาชิก</a>
<a href="<?= BASE_URL ?>/pages/login.php">เข้าสู่ระบบ</a><?php endif; ?>
</div>
</nav>
<main class="container">
<?php if ($m = flash()): ?><div class="alert"><?= e($m) ?></div><?php endif; ?>
