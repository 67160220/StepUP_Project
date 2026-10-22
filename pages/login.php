<?php
$pageTitle = 'เข้าสู่ระบบ';
require_once __DIR__ . '/../includes/header.php';
?>
<h2>เข้าสู่ระบบ</h2>
<form class="form" method="post" action="<?= BASE_URL ?>/actions/login_process.php">
  <label>อีเมล</label><input type="email" name="email" required>
  <label>รหัสผ่าน</label><input type="password" name="password" required>
  <button class="btn">เข้าสู่ระบบ</button>
  <p class="hint">ยังไม่มีบัญชี? <a href="register.php"><u>สมัครสมาชิก</u></a></p>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
