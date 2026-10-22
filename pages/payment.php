<?php
$pageTitle = 'ชำระเงิน';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/../includes/payment_functions.php';
require_once __DIR__ . '/../includes/escrow_functions.php';
require_login();

try { escrow_run_auto($pdo); } catch (Throwable $e) { error_log('escrow_run_auto: ' . $e->getMessage()); }

$st = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$st->execute([(int)($_GET['id'] ?? 0), (int)$_SESSION['user']['id']]);
$o = $st->fetch();
if (!$o) { flash('ไม่พบคำสั่งซื้อนี้'); redirect('pages/my_orders.php'); }

$platformId   = trim((string)escrow_cfg('PROMPTPAY_ID', ''));
$platformName = trim((string)escrow_cfg('PAY_ACCOUNT_NAME', 'StepUP'));
$codMax       = (float)escrow_cfg('COD_MAX_AMOUNT', 3000);
$shipDays     = escrow_ship_days();
$releaseDays  = escrow_release_days();

// ยอดแยกตามร้าน (แสดงเพื่อให้เห็นรายละเอียด การโอนเป็นยอดรวมเข้าบัญชีกลางครั้งเดียว)
$it = $pdo->prepare("SELECT oi.store_id, s.store_name, SUM(oi.price * oi.qty) AS store_total
                     FROM order_items oi LEFT JOIN stores s ON s.id = oi.store_id
                     WHERE oi.order_id = ? GROUP BY oi.store_id, s.store_name ORDER BY s.store_name");
$it->execute([(int)$o['id']]);
$stores = $it->fetchAll(PDO::FETCH_ASSOC);

$showOnline = $o['status'] === 'pending'
    && (($_GET['method'] ?? '') === 'online' || ($o['payment_method'] ?? null) === 'promptpay' || !empty($o['slip_image']));
$payload = ($showOnline && $platformId !== '') ? promptpay_payload($platformId, (float)$o['total']) : null;

$fulfil = [];
if (in_array($o['status'], ['paid', 'shipped', 'cancelled'], true)) {
    $f = $pdo->prepare("SELECT p.*, s.store_name FROM payments p LEFT JOIN stores s ON s.id = p.store_id
                        WHERE p.order_id = ? ORDER BY s.store_name");
    $f->execute([(int)$o['id']]);
    $fulfil = $f->fetchAll(PDO::FETCH_ASSOC);
}

function fmt_dt($v) { return $v ? date('d/m/Y H:i', strtotime((string)$v)) : '-'; }

require_once __DIR__ . '/../includes/header.php';
?>
<style>
  .pay-wrap{display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:960px}
  .pay-box{background:#fff;border:1px solid #e5e5e5;padding:22px;margin-bottom:16px}
  .pay-amount{font-size:28px;font-weight:700;margin:6px 0 14px}
  .store-pay{border:1px solid #ddd;padding:16px;margin:14px 0}
  .method-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:960px}
  .method-card{background:#fff;border:1px solid #e5e5e5;padding:22px}
  .method-card h3{margin:0 0 8px}.method-card ul{padding-left:18px;margin:10px 0}
  .badge{display:inline-block;font-size:12px;padding:3px 9px;border-radius:99px;background:#eef2ff;color:#184dc7;margin-bottom:8px}
  .badge.warn{background:#fff4e5;color:#b45309}.badge.ok{background:#e8f7ee;color:#157347}.badge.bad{background:#fdecec;color:#b42318}
  .ship-card{border:1px solid #ddd;padding:16px;margin:14px 0;max-width:960px;background:#fff}
  .ship-card form{display:inline-block;margin:8px 8px 0 0}
  @media(max-width:720px){.pay-wrap,.method-grid{grid-template-columns:1fr}}
</style>
<h2>ชำระเงิน · คำสั่งซื้อ #<?= (int)$o['id'] ?></h2>

<?php if ($o['status'] === 'pending' && !$showOnline): ?>
  <p>ยอดที่ต้องชำระ <b><?= baht($o['total']) ?></b> เลือกวิธีชำระเงิน</p>
  <div class="method-grid">
    <div class="method-card">
      <span class="badge ok">แนะนำ · ปลอดภัยที่สุด</span>
      <h3>โอนออนไลน์ (StepUP พักเงินให้)</h3>
      <ul>
        <li>โอนเข้าบัญชีกลางของ StepUP ยอดเดียว</li>
        <li>เงินจะ<b>ยังไม่ถึงร้าน</b>จนกว่าคุณกด "ได้รับสินค้าแล้ว"</li>
        <li>ร้านไม่ส่งของใน <?= (int)$shipDays ?> วัน = คืนเงินอัตโนมัติ</li>
        <li>มีปัญหาแจ้งได้ เงินจะถูกพักไว้ให้ตรวจสอบ</li>
      </ul>
      <a class="btn" href="?id=<?= (int)$o['id'] ?>&method=online">ชำระออนไลน์</a>
    </div>
    <div class="method-card">
      <span class="badge warn">จ่ายเมื่อได้รับของ</span>
      <h3>เก็บเงินปลายทาง (COD)</h3>
      <ul>
        <li>จ่ายเงินให้ผู้ส่งตอนรับสินค้า</li>
        <li>รองรับยอดไม่เกิน <?= baht($codMax) ?></li>
        <li>ร้านต้องส่งภายใน <?= (int)$shipDays ?> วัน ไม่งั้นรายการถูกยกเลิก</li>
        <li>กรุณาอยู่รับของและเตรียมเงินสดให้พอดี</li>
      </ul>
      <?php if ((float)$o['total'] > $codMax): ?>
        <p class="hint">ยอดคำสั่งซื้อนี้เกินวงเงินเก็บเงินปลายทาง</p>
      <?php else: ?>
        <form method="post" action="<?= BASE_URL ?>/actions/choose_cod.php" onsubmit="return confirm('ยืนยันเลือกเก็บเงินปลายทาง?');">
          <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <button class="btn">เลือกเก็บเงินปลายทาง</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($o['status'] === 'pending'): ?>
<div class="pay-wrap">
  <div class="pay-box">
    <span class="badge ok">StepUP พักเงินไว้ให้</span>
    <div><b>ยอดที่ต้องโอน</b></div>
    <div class="pay-amount"><?= baht($o['total']) ?></div>
    <?php if ($payload): ?>
      <div id="qr"></div>
      <p class="hint">ชื่อบัญชี: <b><?= e($platformName) ?></b><br>พร้อมเพย์: <b><?= e($platformId) ?></b></p>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
      <script>new QRCode(document.getElementById('qr'), {text: <?= json_encode($payload) ?>, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M});</script>
    <?php else: ?>
      <p class="hint">ระบบยังไม่ได้ตั้งค่าบัญชีรับเงินกลางของ StepUP (PROMPTPAY_ID) กรุณาติดต่อผู้ดูแลระบบ</p>
    <?php endif; ?>
    <p class="hint">เงินจะถูกพักไว้ที่ StepUP และโอนให้ร้านหลังคุณยืนยันรับสินค้า (หรือครบ <?= (int)$releaseDays ?> วันหลังร้านส่งของ โดยไม่มีการแจ้งปัญหา)</p>
    <?php if (count($stores) > 0): ?>
      <p class="hint"><b>รายการแยกตามร้าน:</b></p>
      <?php foreach ($stores as $sp): ?>
        <div class="store-pay"><b><?= e($sp['store_name'] ?: 'StepUP (ร้านกลาง)') ?></b> · <?= baht($sp['store_total']) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="pay-box">
    <b>แนบสลิปการโอนเงิน</b>
    <?php if (!empty($o['slip_image'])): ?>
      <?php if (($o['slip_result'] ?? null) === 'rejected'): ?>
        <p style="color:#e11d48"><b>สลิปไม่ผ่านการตรวจสอบ:</b> <?= e($o['slip_note']) ?></p>
        <p class="hint">กรุณาตรวจยอดเงินและเลขพร้อมเพย์ก่อนอัปโหลดสลิปใหม่</p>
      <?php else: ?>
        <p class="hint">ส่งสลิปแล้วเมื่อ <?= e($o['slip_at']) ?> · รอแอดมินตรวจสอบ</p>
      <?php endif; ?>
      <a href="<?= e($o['slip_image']) ?>" target="_blank"><img src="<?= e($o['slip_image']) ?>" alt="สลิป" style="max-width:100%;max-height:260px"></a>
      <p class="hint">ส่งผิดไฟล์? อัปโหลดใหม่ได้ด้านล่าง (ก่อนแอดมินยืนยัน)</p>
    <?php else: ?>
      <p class="hint">โอนเงินแล้วอัปโหลดรูปสลิป (JPG, PNG, WEBP ไม่เกิน 5MB) แอดมินจะตรวจสอบและยืนยันให้</p>
    <?php endif; ?>
    <form class="form" method="post" action="<?= BASE_URL ?>/actions/upload_slip.php" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
      <input type="file" name="slip" accept="image/jpeg,image/png,image/webp" required>
      <button class="btn"><?= !empty($o['slip_image']) ? 'อัปโหลดสลิปใหม่' : 'ส่งสลิป' ?></button>
    </form>
    <?php if (empty($o['slip_image'])): ?>
      <p class="hint"><a href="?id=<?= (int)$o['id'] ?>">← เปลี่ยนวิธีชำระเงิน</a></p>
    <?php endif; ?>
  </div>
</div>

<?php else: /* paid / shipped / cancelled: แสดงสถานะรายร้าน */ ?>
  <?php if ($o['status'] === 'cancelled'): ?>
    <p><span class="badge bad">คำสั่งซื้อนี้ถูกยกเลิกแล้ว</span></p>
  <?php else: ?>
    <p><span class="badge ok"><?= ($o['payment_method'] ?? '') === 'cod' ? 'เก็บเงินปลายทาง' : 'ชำระแล้ว · StepUP พักเงินไว้ให้' ?></span>
       ยอดรวม <b><?= baht($o['total']) ?></b></p>
  <?php endif; ?>

  <?php if (!$fulfil): ?>
    <p>คำสั่งซื้อนี้ชำระเงินเรียบร้อยแล้ว ขอบคุณที่ใช้บริการ</p>
  <?php endif; ?>

  <?php foreach ($fulfil as $p):
      $isCod = $p['method'] === 'cod';
      $final = in_array($p['status'], ['refunded', 'rejected'], true);
      $canAct = !$final && $p['shipped_at'] !== null && $p['received_at'] === null && in_array($p['escrow_status'], ['held', 'none'], true);
  ?>
    <div class="ship-card">
      <b><?= e($p['store_name'] ?: 'StepUP (ร้านกลาง)') ?></b> · <?= baht($p['amount']) ?>
      <span class="hint"> (<?= $isCod ? 'เก็บเงินปลายทาง' : 'โอนออนไลน์' ?>)</span><br>
      <?php if ($p['status'] === 'refunded'): ?>
        <span class="badge bad">ยกเลิก/คืนเงิน</span>
        <?= $p['refunded_at'] ? 'โอนเงินคืนให้คุณแล้วเมื่อ ' . e(fmt_dt($p['refunded_at'])) : 'แอดมินจะโอนเงินคืนให้ตามช่องทางที่คุณชำระ' ?>
        <?php if ($p['note']): ?><div class="hint"><?= e($p['note']) ?></div><?php endif; ?>
      <?php elseif ($p['status'] === 'rejected'): ?>
        <span class="badge bad">ยกเลิกรายการ</span> <?= e($p['note'] ?: '') ?>
      <?php elseif ($p['escrow_status'] === 'disputed'): ?>
        <span class="badge warn">อยู่ระหว่างตรวจสอบข้อพิพาท</span>
        <div class="hint"><?= e($p['note'] ?: '') ?></div>
      <?php elseif ($p['shipped_at'] === null): ?>
        <span class="badge warn">รอร้านจัดส่ง</span>
        กำหนดจัดส่งภายใน <b><?= e(fmt_dt($p['ship_by'])) ?></b>
        <?= $isCod ? '(ไม่ส่งตามกำหนดรายการจะถูกยกเลิก)' : '(ไม่ส่งตามกำหนดจะคืนเงินให้อัตโนมัติ)' ?>
      <?php else: ?>
        <span class="badge <?= $p['received_at'] || $p['status'] === 'verified' ? 'ok' : '' ?>">
          <?= $p['received_at'] || $p['status'] === 'verified' ? 'เสร็จสมบูรณ์' : 'จัดส่งแล้ว' ?></span>
        ส่งเมื่อ <?= e(fmt_dt($p['shipped_at'])) ?> · <?= e($p['carrier'] ?: 'ขนส่ง') ?> เลขพัสดุ <b><?= e($p['tracking_no']) ?></b>
        <?php if ($canAct): ?>
          <div class="hint">
            <?= $isCod ? 'จ่ายเงินให้ผู้ส่งเมื่อรับของ แล้วกดยืนยันด้านล่าง' : 'เงินยังอยู่ที่ StepUP ยังไม่ถึงร้าน' ?>
            · ถ้าไม่ทำรายการภายใน <?= e(fmt_dt($p['auto_release_at'])) ?> ระบบจะถือว่าคุณได้รับสินค้าแล้ว
          </div>
          <form method="post" action="<?= BASE_URL ?>/actions/escrow_action.php" onsubmit="return confirm('ยืนยันว่าได้รับสินค้าครบถ้วน?');">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="confirm"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
            <button class="btn">ได้รับสินค้าแล้ว</button>
          </form>
          <form method="post" action="<?= BASE_URL ?>/actions/escrow_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="dispute"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
            <input type="text" name="reason" placeholder="แจ้งปัญหา เช่น ไม่ได้รับของ / สินค้าไม่ตรงปก" minlength="5" maxlength="200" required style="min-width:260px">
            <button class="btn" style="background:#b42318">แจ้งปัญหา</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<p><a href="my_orders.php">← คำสั่งซื้อของฉัน</a></p>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
