<?php
$pageTitle = 'สมัครสมาชิก';
require_once __DIR__ . '/../includes/header.php';
if (isset($_SESSION['user'])) redirect('pages/home.php');
?>
<h2>สมัครสมาชิก</h2>
<form class="form" method="post" action="<?= BASE_URL ?>/actions/register_process.php">
  <?= csrf_field() ?>
  <label for="name">ชื่อที่แสดง</label><input id="name" name="name" required maxlength="100">
  <label for="email">อีเมล</label><input id="email" type="email" name="email" required>
  <label for="password">รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label><input id="password" type="password" name="password" required minlength="8">
  <label for="confirm">ยืนยันรหัสผ่าน</label><input id="confirm" type="password" name="confirm" required minlength="8">
  <button class="btn">สมัครสมาชิก</button>
  <p class="hint">มีบัญชีแล้ว? <a href="login.php"><u>เข้าสู่ระบบ</u></a></p>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
