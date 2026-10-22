<?php
$pageTitle = 'แอดมิน · พักเงิน/จ่ายร้านค้า';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/../includes/payment_functions.php';
require_once __DIR__ . '/../includes/escrow_functions.php';
require_login();
if (($_SESSION['user']['role'] ?? '') !== 'admin') { flash('เฉพาะผู้ดูแลระบบ'); redirect('pages/my_orders.php'); }

try { escrow_run_auto($pdo); } catch (Throwable $e) { error_log('escrow_run_auto: ' . $e->getMessage()); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string)($_POST['act'] ?? '');
    $orderId = (int)($_POST['order_id'] ?? 0);
    $payId = (int)($_POST['payment_id'] ?? 0);
    $storeId = (int)($_POST['store_id'] ?? 0);
    $adminId = (int)$_SESSION['user']['id'];
    try {
        $pdo->beginTransaction();
        switch ($act) {
            case 'approve_slip':   // ยืนยันว่าเงินเข้าบัญชีกลางแล้ว -> พักเงิน + เริ่มนับเวลาจัดส่ง
                $q = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND status = 'pending' AND payment_method = 'promptpay' FOR UPDATE");
                $q->execute([$orderId]);
                if (!$q->fetchColumn()) throw new DomainException('ไม่พบออเดอร์ที่รอตรวจสลิป');
                escrow_mark_paid($pdo, $orderId);
                $pdo->prepare("UPDATE orders SET slip_result = 'verified', slip_note = 'แอดมินตรวจสอบแล้ว' WHERE id = ?")->execute([$orderId]);
                flash("อนุมัติสลิปออเดอร์ #$orderId แล้ว เงินถูกพักไว้และร้านเริ่มนับเวลาจัดส่ง");
                break;
            case 'reject_slip':
                $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255);
                if ($note === '') throw new DomainException('กรุณาระบุเหตุผลที่ไม่ผ่าน');
                $pdo->prepare("UPDATE orders SET slip_result = 'rejected', slip_note = ? WHERE id = ? AND status = 'pending'")->execute([$note, $orderId]);
                $pdo->prepare("UPDATE payments SET status = 'rejected', note = ? WHERE order_id = ? AND status IN ('pending','submitted')")->execute([$note, $orderId]);
                flash("ปฏิเสธสลิปออเดอร์ #$orderId แล้ว");
                break;
            case 'refund_done':    // แอดมินโอนเงินคืนลูกค้าเสร็จแล้ว
                $pdo->prepare("UPDATE payments SET refunded_at = NOW() WHERE id = ? AND status = 'refunded' AND method = 'promptpay' AND refunded_at IS NULL")->execute([$payId]);
                flash('บันทึกว่าคืนเงินลูกค้าแล้ว');
                break;
            case 'resolve_refund':
            case 'resolve_release':
                $q = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND escrow_status = 'disputed' FOR UPDATE");
                $q->execute([$payId]);
                $p = $q->fetch(PDO::FETCH_ASSOC);
                if (!$p) throw new DomainException('ไม่พบข้อพิพาทนี้');
                $suffix = ' | แอดมินตัดสิน: ' . ($act === 'resolve_refund' ? 'คืนเงินผู้ซื้อ' : 'ฝั่งร้านค้า');
                if ($act === 'resolve_refund') {
                    if ($p['method'] === 'promptpay') $pdo->prepare("UPDATE payments SET status = 'refunded', escrow_status = 'refunded', note = ? WHERE id = ?")->execute([mb_substr((string)$p['note'] . $suffix, 0, 255), $payId]);
                    else $pdo->prepare("UPDATE payments SET status = 'rejected', escrow_status = 'none', note = ? WHERE id = ?")->execute([mb_substr((string)$p['note'] . $suffix, 0, 255), $payId]);
                    escrow_cancel_fully_refunded($pdo);
                } else {
                    if ($p['method'] === 'promptpay') $pdo->prepare("UPDATE payments SET escrow_status = IF(store_id IS NULL, 'released', 'releasable'), received_at = COALESCE(received_at, NOW()), note = ? WHERE id = ?")->execute([mb_substr((string)$p['note'] . $suffix, 0, 255), $payId]);
                    else $pdo->prepare("UPDATE payments SET status = 'verified', verified_at = NOW(), escrow_status = 'none', received_at = COALESCE(received_at, NOW()), note = ? WHERE id = ?")->execute([mb_substr((string)$p['note'] . $suffix, 0, 255), $payId]);
                }
                flash('บันทึกคำตัดสินแล้ว');
                break;
            case 'payout':         // โอนเงินให้ร้านเสร็จแล้ว -> บันทึกรอบจ่าย
                $q = $pdo->prepare("SELECT id, amount FROM payments WHERE store_id = ? AND escrow_status = 'releasable' FOR UPDATE");
                $q->execute([$storeId]);
                $rows = $q->fetchAll(PDO::FETCH_ASSOC);
                if (!$rows) throw new DomainException('ไม่มียอดรอจ่ายของร้านนี้');
                $sum = 0.0; $ids = [];
                foreach ($rows as $r) { $sum += (float)$r['amount']; $ids[] = (int)$r['id']; }
                $pdo->prepare('INSERT INTO payouts (store_id, amount, note, paid_by) VALUES (?, ?, ?, ?)')
                    ->execute([$storeId, $sum, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255) ?: null, $adminId]);
                $pid = (int)$pdo->lastInsertId();
                $in = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("UPDATE payments SET escrow_status = 'released', payout_id = ? WHERE id IN ($in)")->execute(array_merge([$pid], $ids));
                flash('บันทึกการจ่ายเงินให้ร้านแล้ว ' . baht($sum) . ' (' . count($ids) . ' รายการ)');
                break;
            default:
                throw new DomainException('คำสั่งไม่ถูกต้อง');
        }
        $pdo->commit();
    } catch (DomainException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($e->getMessage());
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('admin escrow error: ' . $e->getMessage());
        flash('ทำรายการไม่สำเร็จ กรุณาลองใหม่');
    }
    redirect('admin/escrow.php');
}

$slips = $pdo->query("SELECT id, customer_name, total, slip_image, slip_at FROM orders
    WHERE status = 'pending' AND payment_method = 'promptpay' AND slip_image IS NOT NULL
      AND (slip_result IS NULL OR slip_result <> 'rejected') ORDER BY slip_at")->fetchAll(PDO::FETCH_ASSOC);
$refunds = $pdo->query("SELECT p.id, p.order_id, p.amount, p.note, o.customer_name, o.phone, s.store_name
    FROM payments p JOIN orders o ON o.id = p.order_id LEFT JOIN stores s ON s.id = p.store_id
    WHERE p.status = 'refunded' AND p.method = 'promptpay' AND p.refunded_at IS NULL ORDER BY p.id")->fetchAll(PDO::FETCH_ASSOC);
$disputes = $pdo->query("SELECT p.id, p.order_id, p.method, p.amount, p.note, p.tracking_no, p.carrier, o.customer_name, o.phone, s.store_name
    FROM payments p JOIN orders o ON o.id = p.order_id LEFT JOIN stores s ON s.id = p.store_id
    WHERE p.escrow_status = 'disputed' ORDER BY p.id")->fetchAll(PDO::FETCH_ASSOC);
$payouts = $pdo->query("SELECT p.store_id, s.store_name, s.promptpay_id, s.promptpay_account_name, COUNT(*) AS n, SUM(p.amount) AS amt
    FROM payments p JOIN stores s ON s.id = p.store_id WHERE p.escrow_status = 'releasable'
    GROUP BY p.store_id, s.store_name, s.promptpay_id, s.promptpay_account_name ORDER BY s.store_name")->fetchAll(PDO::FETCH_ASSOC);
$held = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE escrow_status = 'held'")->fetchColumn();
$recent = $pdo->query("SELECT po.id, po.amount, po.paid_at, po.note, s.store_name FROM payouts po JOIN stores s ON s.id = po.store_id ORDER BY po.id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

function post_btn(string $act, array $f, string $label, string $extra = '', string $confirm = ''): string {
    $h = '<form method="post" style="display:inline-block;margin:2px 6px 2px 0"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\');"' : '') . '>' . csrf_field()
       . '<input type="hidden" name="act" value="' . e($act) . '">';
    foreach ($f as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
    return $h . $extra . '<button class="btn">' . e($label) . '</button></form>';
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
  .adm-box{background:#fff;border:1px solid #e5e5e5;padding:18px;margin-bottom:18px}
  .adm-box h3{margin:0 0 10px}.adm-table{width:100%;border-collapse:collapse;font-size:14px}
  .adm-table th,.adm-table td{padding:8px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
</style>
<h2>พักเงิน / จ่ายร้านค้า</h2>
<p class="hint">เงินที่ StepUP ถืออยู่ตอนนี้ (รอร้านจัดส่ง/รอผู้ซื้อยืนยัน): <b><?= baht($held) ?></b></p>

<div class="adm-box"><h3>1) สลิปรอตรวจสอบ (<?= count($slips) ?>)</h3>
<?php if (!$slips): ?><p class="hint">ไม่มีรายการ</p><?php else: ?>
<table class="adm-table"><tr><th>ออเดอร์</th><th>ผู้ซื้อ</th><th>ยอด</th><th>สลิป</th><th>จัดการ</th></tr>
<?php foreach ($slips as $s): ?><tr>
  <td>#<?= (int)$s['id'] ?></td><td><?= e($s['customer_name']) ?></td><td><?= baht($s['total']) ?></td>
  <td><a href="<?= e($s['slip_image']) ?>" target="_blank">ดูสลิป</a><br><span class="hint"><?= e($s['slip_at']) ?></span></td>
  <td><?= post_btn('approve_slip', ['order_id' => $s['id']], 'เงินเข้าแล้ว · อนุมัติ', '', 'ยืนยันว่าตรวจแล้วว่ามียอดเงินเข้าบัญชีกลางจริง?') ?>
      <?= post_btn('reject_slip', ['order_id' => $s['id']], 'ไม่ผ่าน', '<input type="text" name="note" placeholder="เหตุผล" required maxlength="200">') ?></td>
</tr><?php endforeach; ?></table><?php endif; ?></div>

<div class="adm-box"><h3>2) ข้อพิพาท (<?= count($disputes) ?>)</h3>
<?php if (!$disputes): ?><p class="hint">ไม่มีรายการ</p><?php else: ?>
<table class="adm-table"><tr><th>ออเดอร์/ร้าน</th><th>ผู้ซื้อ</th><th>ยอด</th><th>รายละเอียด</th><th>ตัดสิน</th></tr>
<?php foreach ($disputes as $d): ?><tr>
  <td>#<?= (int)$d['order_id'] ?><br><?= e($d['store_name'] ?: 'StepUP') ?></td>
  <td><?= e($d['customer_name']) ?><br><?= e($d['phone']) ?></td>
  <td><?= baht($d['amount']) ?><br><span class="hint"><?= $d['method'] === 'cod' ? 'COD' : 'ออนไลน์' ?></span></td>
  <td><?= e($d['note']) ?><br><span class="hint"><?= e($d['carrier']) ?> <?= e($d['tracking_no']) ?></span></td>
  <td><?= post_btn('resolve_refund', ['payment_id' => $d['id']], $d['method'] === 'cod' ? 'ยกเลิกรายการ' : 'คืนเงินผู้ซื้อ', '', 'ยืนยันคำตัดสินฝั่งผู้ซื้อ?') ?>
      <?= post_btn('resolve_release', ['payment_id' => $d['id']], $d['method'] === 'cod' ? 'ปิดเรื่อง (ฝั่งร้าน)' : 'จ่ายให้ร้าน', '', 'ยืนยันคำตัดสินฝั่งร้านค้า?') ?></td>
</tr><?php endforeach; ?></table><?php endif; ?></div>

<div class="adm-box"><h3>3) ต้องโอนเงินคืนลูกค้า (<?= count($refunds) ?>)</h3>
<?php if (!$refunds): ?><p class="hint">ไม่มีรายการ</p><?php else: ?>
<table class="adm-table"><tr><th>ออเดอร์/ร้าน</th><th>ผู้ซื้อ</th><th>ยอดที่ต้องคืน</th><th>สาเหตุ</th><th></th></tr>
<?php foreach ($refunds as $r): ?><tr>
  <td>#<?= (int)$r['order_id'] ?><br><?= e($r['store_name'] ?: 'StepUP') ?></td>
  <td><?= e($r['customer_name']) ?><br><?= e($r['phone']) ?></td><td><?= baht($r['amount']) ?></td><td><?= e($r['note']) ?></td>
  <td><?= post_btn('refund_done', ['payment_id' => $r['id']], 'โอนคืนแล้ว', '', 'ยืนยันว่าโอนเงินคืนลูกค้าแล้ว?') ?></td>
</tr><?php endforeach; ?></table><?php endif; ?></div>

<div class="adm-box"><h3>4) รอจ่ายเงินให้ร้านค้า (<?= count($payouts) ?> ร้าน)</h3>
<p class="hint">โอนจากบัญชีกลางไปยังพร้อมเพย์ของร้านด้วยตนเอง แล้วกด "จ่ายแล้ว" เพื่อบันทึกรอบจ่าย</p>
<?php if (!$payouts): ?><p class="hint">ไม่มีรายการ</p><?php else: ?>
<table class="adm-table"><tr><th>ร้าน</th><th>พร้อมเพย์ร้าน</th><th>รายการ</th><th>ยอดที่ต้องจ่าย</th><th></th></tr>
<?php foreach ($payouts as $p): ?><tr>
  <td><?= e($p['store_name']) ?></td>
  <td><?= $p['promptpay_id'] ? e($p['promptpay_id']) . '<br><span class="hint">' . e($p['promptpay_account_name']) . '</span>' : '<span style="color:#b42318">ร้านยังไม่ตั้งพร้อมเพย์</span>' ?></td>
  <td><?= (int)$p['n'] ?></td><td><b><?= baht($p['amt']) ?></b></td>
  <td><?= post_btn('payout', ['store_id' => $p['store_id']], 'จ่ายแล้ว', '<input type="text" name="note" placeholder="หมายเหตุ/เลขอ้างอิงการโอน" maxlength="200">', 'ยืนยันว่าโอนเงินให้ร้านนี้แล้ว?') ?></td>
</tr><?php endforeach; ?></table><?php endif; ?></div>

<?php if ($recent): ?><div class="adm-box"><h3>รอบจ่ายล่าสุด</h3>
<table class="adm-table"><tr><th>#</th><th>ร้าน</th><th>ยอด</th><th>เวลา</th><th>หมายเหตุ</th></tr>
<?php foreach ($recent as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= e($r['store_name']) ?></td><td><?= baht($r['amount']) ?></td><td><?= e($r['paid_at']) ?></td><td><?= e($r['note']) ?></td></tr><?php endforeach; ?>
</table></div><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
