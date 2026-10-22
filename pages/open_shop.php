<?php
$pageTitle = 'เปิดร้านค้า';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_login();
if (is_admin()) { flash('บัญชีแอดมินไม่สามารถเปิดร้านได้'); redirect('admin/index.php'); }
$store = current_store();
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.shop-form-wrap{max-width:760px;margin:24px auto 48px;padding:0 16px}
.shop-form-card{background:#fff;border:1px solid #e5e5e5;padding:24px}
.shop-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.shop-form-grid .full{grid-column:1/-1}
.shop-form-card label{display:block;font-weight:600;margin:0 0 6px}
.shop-form-card input:not([type=checkbox]),.shop-form-card textarea{box-sizing:border-box;width:100%;padding:12px;border:1px solid #d1d5db;border-radius:4px;font:inherit}
.shop-form-card textarea{min-height:100px;resize:vertical}
.shop-form-card .hint{font-size:13px;color:#6b7280;margin:5px 0 0}
.shop-form-card .btn{margin-top:18px;cursor:pointer}
.shop-agree{display:flex;align-items:flex-start;gap:8px;margin-top:18px}
@media(max-width:600px){.shop-form-grid{grid-template-columns:1fr}.shop-form-grid .full{grid-column:auto}.shop-form-card{padding:16px}}
</style>
<div class="shop-form-wrap">
  <h2><?= $store ? 'จัดการข้อมูลร้านค้า' : 'เปิดร้านค้า StepUP' ?></h2>
  <p class="hint">กรอกข้อมูลร้านและบัญชีรับเงินของร้านให้ถูกต้อง ลูกค้าจะใช้ข้อมูลนี้ประกอบการชำระเงิน</p>
  <div class="shop-form-card">
    <form class="form" method="post" action="<?= BASE_URL ?>/actions/open_shop_process.php">
      <?= csrf_field() ?>
      <div class="shop-form-grid">
        <div class="full">
          <label for="shop_name">ชื่อร้านค้า *</label>
          <input id="shop_name" name="shop_name" minlength="3" maxlength="60" required value="<?= e($store['store_name'] ?? $_SESSION['user']['shop_name'] ?? '') ?>">
        </div>
        <div class="full">
          <label for="description">คำอธิบายร้าน</label>
          <textarea id="description" name="description" maxlength="2000" placeholder="แนะนำร้านค้าและสินค้าของคุณ"><?= e($store['description'] ?? '') ?></textarea>
        </div>
        <div>
          <label for="contact_phone">เบอร์โทรศัพท์ร้าน *</label>
          <input id="contact_phone" name="contact_phone" inputmode="numeric" pattern="[0-9 -]{9,14}" maxlength="14" required value="<?= e($store['contact_phone'] ?? '') ?>" placeholder="08XXXXXXXX">
        </div>
        <div>
          <label for="promptpay_id">เลขพร้อมเพย์ *</label>
          <input id="promptpay_id" name="promptpay_id" inputmode="numeric" pattern="[0-9 -]{10,17}" maxlength="17" required value="<?= e($store['promptpay_id'] ?? '') ?>" placeholder="เบอร์โทร 10 หลัก หรือเลข 13 หลัก">
          <p class="hint">กรอกเลขที่ผูกกับบัญชีรับเงินของร้านนี้</p>
        </div>
        <div class="full">
          <label for="promptpay_account_name">ชื่อบัญชีรับเงิน *</label>
          <input id="promptpay_account_name" name="promptpay_account_name" maxlength="100" required value="<?= e($store['promptpay_account_name'] ?? '') ?>" autocomplete="name">
        </div>
        <div class="full">
          <label for="address">ที่อยู่ร้าน / ที่อยู่สำหรับติดต่อ *</label>
          <textarea id="address" name="address" maxlength="500" required><?= e($store['address'] ?? '') ?></textarea>
        </div>
      </div>
      <label class="shop-agree"><input type="checkbox" name="agree" value="1" required> <span>ข้าพเจ้ายืนยันว่าข้อมูลร้านและบัญชีรับเงินถูกต้อง และยินยอมให้ใช้ข้อมูลเพื่อดำเนินการขายสินค้า</span></label>
      <button class="btn" type="submit"><?= $store ? 'บันทึกข้อมูลร้าน' : 'เปิดร้านค้า' ?></button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
