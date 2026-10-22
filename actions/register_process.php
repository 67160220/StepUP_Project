<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('pages/register.php');
csrf_check();

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$pass = $_POST['password'] ?? '';

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('กรุณากรอกชื่อและอีเมลให้ถูกต้อง'); redirect('pages/register.php'); }
if (strlen($pass) < 8)                  { flash('รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'); redirect('pages/register.php'); }
if ($pass !== ($_POST['confirm'] ?? '')) { flash('รหัสผ่านทั้งสองช่องไม่ตรงกัน'); redirect('pages/register.php'); }

$chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$chk->execute([$email]);
if ($chk->fetch()) { flash('อีเมลนี้ถูกใช้งานแล้ว'); redirect('pages/register.php'); }

$pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?, 'customer')")
    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);

session_regenerate_id(true);
$_SESSION['user'] = ['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'role' => 'customer', 'is_seller' => 0, 'shop_name' => null];
flash('สมัครสมาชิกสำเร็จ ยินดีต้อนรับ!');
redirect('pages/home.php');
