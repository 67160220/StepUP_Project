<?php /* วางแทนปุ่ม "จัดส่ง" เดิมในหน้าออเดอร์ของร้านค้า ($order['id'] = เลขออเดอร์ของรายการนั้น) */ ?>
<form method="post" action="<?= BASE_URL ?>/actions/seller_mark_shipped.php" style="display:flex;gap:6px;flex-wrap:wrap">
  <?= csrf_field() ?>
  <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
  <input type="text" name="carrier" placeholder="ขนส่ง เช่น Kerry, Flash" maxlength="40">
  <input type="text" name="tracking_no" placeholder="เลขพัสดุ" required pattern="[A-Za-z0-9\-]{6,40}" maxlength="40">
  <button class="btn">บันทึกการจัดส่ง</button>
</form>
