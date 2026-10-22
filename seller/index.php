<?php
$pageTitle = 'ร้านของฉัน';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_seller();
require_once __DIR__ . '/../includes/header.php';
$sid = (int)$_SESSION['user']['id'];

/* ---------- helper: query แบบปลอดภัย ถ้า column ไม่ตรงจะไม่ทำให้หน้าพัง ---------- */
function q($pdo, $sql, $params = [], $all = false) {
    try {
        $s = $pdo->prepare($sql);
        $s->execute($params);
        return $all ? $s->fetchAll(PDO::FETCH_ASSOC) : $s->fetchColumn();
    } catch (Throwable $e) {
        return $all ? [] : 0;
    }
}

/* ---------- ตัวเลขสรุป ---------- */
$products = q($pdo, 'SELECT COUNT(*) FROM products WHERE seller_id = ?', [$sid]);
$toShip   = q($pdo, "SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.seller_id = ? AND oi.ship_status = 'pending' AND o.status = 'paid'", [$sid]);
$waitPay  = q($pdo, "SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.seller_id = ? AND o.status = 'pending'", [$sid]);
$sales    = q($pdo, "SELECT COALESCE(SUM(oi.price * oi.qty),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.seller_id = ? AND o.status IN ('paid','shipped')", [$sid]);
$orderCnt = q($pdo, "SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.seller_id = ? AND o.status IN ('paid','shipped')", [$sid]);
$avg      = $orderCnt > 0 ? $sales / $orderCnt : 0;

/* ---------- ยอดขาย 7 วันล่าสุด ---------- */
$rows = q($pdo, "SELECT DATE(o.created_at) d, SUM(oi.price * oi.qty) t
                 FROM order_items oi JOIN orders o ON o.id = oi.order_id
                 WHERE oi.seller_id = ? AND o.status IN ('paid','shipped')
                   AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                 GROUP BY DATE(o.created_at)", [$sid], true);
$byDay = [];
foreach ($rows as $r) $byDay[$r['d']] = (float)$r['t'];
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $days[] = ['label' => date('d/m', strtotime($d)), 'total' => $byDay[$d] ?? 0];
}
$maxDay = max(1, max(array_column($days, 'total')));
$week   = array_sum(array_column($days, 'total'));

/* ---------- สินค้าขายดี / สต็อกใกล้หมด / ออเดอร์ล่าสุด ---------- */
$top = q($pdo, "SELECT oi.product_name AS name, SUM(oi.qty) qty
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                WHERE oi.seller_id = ? AND o.status IN ('paid','shipped')
                GROUP BY oi.product_id, oi.product_name ORDER BY qty DESC LIMIT 5", [$sid], true);
$maxQty = $top ? max(1, (int)$top[0]['qty']) : 1;

$low = q($pdo, 'SELECT name, stock FROM products WHERE seller_id = ? AND stock <= 5 ORDER BY stock ASC LIMIT 5', [$sid], true);

$recent = q($pdo, "SELECT oi.order_id, o.status, o.created_at, SUM(oi.price * oi.qty) total
                   FROM order_items oi JOIN orders o ON o.id = oi.order_id
                   WHERE oi.seller_id = ?
                   GROUP BY oi.order_id, o.status, o.created_at
                   ORDER BY o.created_at DESC LIMIT 5", [$sid], true);

$statusTh = ['pending' => 'รอชำระเงิน', 'paid' => 'ชำระแล้ว', 'shipped' => 'จัดส่งแล้ว', 'cancelled' => 'ยกเลิก'];
?>
<style>
  .dash-alert{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
    background:#fff;border:1px solid #e5e5e5;border-left:4px solid #e11d48;padding:14px 18px;margin:0 0 20px}
  .dash-grid{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-top:16px}
  .dash-box{background:#fff;border:1px solid #e5e5e5;padding:20px}
  .dash-box h3{margin:0 0 14px;font-size:16px}
  .dash-sub{color:#777;font-size:13px;margin:-8px 0 14px}
  .bars{display:flex;align-items:flex-end;gap:10px;height:170px;padding-top:8px}
  .bar-col{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;min-width:0}
  .bar{width:100%;max-width:44px;background:#111;min-height:2px}
  .bar.today{background:#e11d48}
  .bar-val{font-size:11px;color:#777;margin-bottom:4px;white-space:nowrap}
  .bar-lbl{font-size:12px;color:#555;margin-top:6px}
  .rank{list-style:none;margin:0;padding:0}
  .rank li{margin-bottom:12px;font-size:14px}
  .rank .row{display:flex;justify-content:space-between;gap:8px}
  .rank .track{height:6px;background:#eee;margin-top:5px}
  .rank .fill{height:100%;background:#111}
  .dash-table{width:100%;border-collapse:collapse;font-size:14px}
  .dash-table th,.dash-table td{text-align:left;padding:10px 8px;border-bottom:1px solid #eee}
  .dash-table th{color:#777;font-weight:500;font-size:13px}
  .tag{display:inline-block;padding:2px 10px;font-size:12px;border:1px solid #ccc}
  .tag.pending{border-color:#f59e0b;color:#b45309}
  .tag.paid{border-color:#2563eb;color:#1d4ed8}
  .tag.shipped{border-color:#16a34a;color:#15803d}
  .tag.cancelled{border-color:#aaa;color:#777}
  .empty{color:#888;font-size:14px;padding:8px 0}
  .low-n{color:#e11d48;font-weight:700}
  @media(max-width:860px){.dash-grid{grid-template-columns:1fr}}
</style>

<h2>ร้าน <?= e($_SESSION['user']['shop_name']) ?></h2>

<?php if ($toShip > 0 || $waitPay > 0): ?>
<div class="dash-alert">
  <span>
    <?php if ($toShip > 0): ?>มี <b><?= (int)$toShip ?></b> รายการที่ชำระเงินแล้วและรอจัดส่ง<?php endif; ?>
    <?php if ($toShip > 0 && $waitPay > 0): ?> · <?php endif; ?>
    <?php if ($waitPay > 0): ?><b><?= (int)$waitPay ?></b> รายการรอยืนยันชำระเงิน<?php endif; ?>
  </span>
  <a class="btn" href="orders.php">ไปจัดการคำสั่งซื้อ</a>
</div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><b><?= (int)$products ?></b>สินค้าของฉัน</div>
  <div class="stat"><b><?= (int)$toShip ?></b>รอจัดส่ง</div>
  <div class="stat"><b><?= (int)$waitPay ?></b>รอยืนยันชำระเงิน</div>
  <div class="stat"><b><?= baht($sales) ?></b>ยอดขายที่ยืนยันแล้ว</div>
</div>

<div class="dash-grid">
  <div class="dash-box">
    <h3>ยอดขาย 7 วันล่าสุด</h3>
    <p class="dash-sub">รวม <?= baht($week) ?> · เฉลี่ยต่อออเดอร์ <?= baht($avg) ?></p>
    <div class="bars">
      <?php foreach ($days as $i => $d):
        $h = $d['total'] > 0 ? max(3, round($d['total'] / $maxDay * 100)) : 0; ?>
        <div class="bar-col">
          <?php if ($d['total'] > 0): ?><div class="bar-val"><?= number_format($d['total']) ?></div><?php endif; ?>
          <div class="bar<?= $i === 6 ? ' today' : '' ?>" style="height:<?= $h ?>%"></div>
          <div class="bar-lbl"><?= e($d['label']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="dash-box">
    <h3>สินค้าขายดี</h3>
    <?php if ($top): ?>
      <ul class="rank">
        <?php foreach ($top as $t): ?>
          <li>
            <div class="row"><span><?= e($t['name']) ?></span><b><?= (int)$t['qty'] ?> คู่</b></div>
            <div class="track"><div class="fill" style="width:<?= round($t['qty'] / $maxQty * 100) ?>%"></div></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="empty">ยังไม่มียอดขาย เมื่อมีคนสั่งซื้อและชำระเงิน สินค้าขายดีจะแสดงที่นี่</p>
    <?php endif; ?>
  </div>
</div>

<div class="dash-grid">
  <div class="dash-box">
    <h3>คำสั่งซื้อล่าสุด</h3>
    <?php if ($recent): ?>
      <table class="dash-table">
        <tr><th>เลขที่</th><th>วันที่</th><th>สถานะ</th><th>ยอดรวม</th></tr>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['order_id'] ?></td>
            <td><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
            <td><span class="tag <?= e($r['status']) ?>"><?= e($statusTh[$r['status']] ?? $r['status']) ?></span></td>
            <td><?= baht($r['total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p class="empty">ยังไม่มีคำสั่งซื้อ</p>
    <?php endif; ?>
  </div>

  <div class="dash-box">
    <h3>สต็อกใกล้หมด</h3>
    <?php if ($low): ?>
      <ul class="rank">
        <?php foreach ($low as $l): ?>
          <li><div class="row"><span><?= e($l['name']) ?></span><span class="low-n">เหลือ <?= (int)$l['stock'] ?></span></div></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="empty">สินค้าทุกรายการมีสต็อกเพียงพอ</p>
    <?php endif; ?>
  </div>
</div>

<p style="margin-top:22px">
  <a class="btn" href="products.php">จัดการสินค้า</a>
  <a class="btn secondary" href="orders.php">คำสั่งซื้อที่ต้องจัดส่ง</a>
  <a class="btn secondary" href="assistant.php">🤖 ถามผู้ช่วยร้าน</a>
</p>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>