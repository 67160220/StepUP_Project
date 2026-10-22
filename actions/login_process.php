<?php
require_once __DIR__ . '/../config/database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('pages/login.php');

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([trim($_POST['email'] ?? '')]);
$user = $stmt->fetch();

if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['id'], 'name' => $user['name'], 'role' => $user['role'],
        'is_seller' => (int)($user['is_seller'] ?? 0), 'shop_name' => $user['shop_name'] ?? null,
    ];
    redirect($user['role'] === 'admin' ? 'admin/index.php' : 'pages/home.php');
}
flash('อีเมลหรือรหัสผ่านไม่ถูกต้อง');
redirect('pages/login.php');
