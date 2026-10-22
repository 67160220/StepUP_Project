<?php
/**
 * ข้อมูลสำหรับผู้ช่วยร้านค้า (แชทบอทของเจ้าของร้าน)
 * หลักการ: ดึงเฉพาะข้อมูลของ "ร้านที่ล็อกอินอยู่" จาก DB เท่านั้น แล้วส่งให้ AI สรุป
 * ไม่ส่งข้อมูลส่วนตัวของผู้ซื้อ (ชื่อ เบอร์ ที่อยู่) ให้ AI เด็ดขาด
 */

function sa_q(PDO $pdo, string $sql, array $params = [], bool $all = false) {
    try {
        $s = $pdo->prepare($sql);
        $s->execute($params);
        return $all ? $s->fetchAll(PDO::FETCH_ASSOC) : $s->fetchColumn();
    } catch (Throwable $e) {
        error_log('seller_assistant query failed: ' . $e->getMessage());
        return $all ? [] : 0;
    }
}

/** ความรู้เรื่องวิธีใช้ระบบ (สรุปจากกติกาจริงในโค้ด) ให้ AI ตอบคำถามแนว "ทำยังไง" */
function sa_platform_guide(): string {
    $shipDays = function_exists('escrow_ship_days') ? escrow_ship_days() : 3;
    $relDays  = function_exists('escrow_release_days') ? escrow_release_days() : 7;
    $codMax   = function_exists('escrow_cfg') ? (int)escrow_cfg('COD_MAX_AMOUNT', 3000) : 3000;
    return "- เพิ่ม/แก้สินค้า: เมนู ร้านของฉัน > จัดการสินค้า (รูปได้สูงสุด 10 รูป JPG/PNG/WEBP ไม่เกิน 2MB ต่อรูป). ลบสินค้าคือซ่อนสินค้า (status=hidden) ไม่ได้ลบถาวร\n"
         . "- การชำระเงิน: ผู้ซื้อโอนเข้าบัญชีกลางของ StepUP ก่อน แอดมินตรวจสลิปแล้วระบบ \"พักเงิน\" (held) ไว้ให้ร้าน\n"
         . "- ร้านต้องจัดส่งภายใน {$shipDays} วันหลังชำระเงินยืนยันแล้ว ไม่เช่นนั้นระบบคืนเงินผู้ซื้ออัตโนมัติ\n"
         . "- หลังร้านส่งของ ถ้าผู้ซื้อไม่กดรับหรือแจ้งปัญหาภายใน {$relDays} วัน ระบบถือว่ารับของแล้วและเงินพร้อมจ่ายให้ร้าน (releasable)\n"
         . "- ผู้ซื้อกดยืนยันรับของ = เงินพร้อมจ่ายให้ร้าน; ผู้ซื้อแจ้งปัญหา = disputed แอดมินตัดสินคืนเงินหรือจ่ายร้าน\n"
         . "- เงินจะเข้าร้านเมื่อแอดมินโอนและกดจ่าย (released) ซึ่งทำด้วยมือ ผู้ช่วยไม่ทราบวันเวลาที่แอดมินจะโอน\n"
         . "- เก็บเงินปลายทาง (COD) ใช้ได้เมื่อยอดไม่เกิน {$codMax} บาท ร้านต้องส่งภายในกำหนดเช่นกัน\n"
         . "- สถานะคำสั่งซื้อ: pending=รอชำระ, paid=ชำระแล้วรอจัดส่ง, shipped=จัดส่งแล้ว, cancelled=ยกเลิก\n"
         . "- ที่อยู่/เบอร์ผู้ซื้อ เห็นได้เฉพาะออเดอร์ที่ชำระแล้ว ที่หน้า ร้านของฉัน > คำสั่งซื้อ\n"
         . "- ผู้ช่วยทำได้แค่ตอบคำถามและสรุปข้อมูล ไม่สามารถแก้สินค้า ปรับราคา หรือกดจัดส่งแทนร้านได้";
}

/** รวมข้อมูลร้านเป็นก้อนเดียวสำหรับตอบคำถาม ทุก query ผูกกับ seller_id/store_id ของผู้ล็อกอิน */
function sa_snapshot(PDO $pdo, int $uid, array $store): array {
    $sid = (int)$store['id'];
    $own = '(oi.seller_id = ? OR oi.store_id = ?)';   // order_items ของร้านนี้
    $ownP = '(p.seller_id = ? OR p.store_id = ?)';    // products ของร้านนี้
    $ids = [$uid, $sid];
    $paidish = "o.status IN ('paid','shipped')";

    $snap = ['generated_at' => date('Y-m-d H:i'), 'store' => [
        'name' => (string)$store['store_name'],
        'description' => mb_substr((string)($store['description'] ?? ''), 0, 300),
        'status' => (string)$store['status'],
    ]];

    // ---- สินค้า ----
    $snap['products_summary'] = [
        'total'        => (int)sa_q($pdo, "SELECT COUNT(*) FROM products p WHERE $ownP", $ids),
        'active'       => (int)sa_q($pdo, "SELECT COUNT(*) FROM products p WHERE $ownP AND p.status='active'", $ids),
        'hidden'       => (int)sa_q($pdo, "SELECT COUNT(*) FROM products p WHERE $ownP AND p.status='hidden'", $ids),
        'out_of_stock' => (int)sa_q($pdo, "SELECT COUNT(*) FROM products p WHERE $ownP AND p.status='active' AND p.stock<=0", $ids),
    ];
    $snap['products'] = array_map(fn($r) => [
        'id' => (int)$r['id'], 'name' => $r['name'], 'brand' => $r['brand'], 'category' => $r['category'],
        'price' => (float)$r['price'], 'stock' => (int)$r['stock'], 'sizes' => $r['sizes'], 'colors' => $r['colors'],
        'status' => $r['status'], 'has_description' => trim((string)$r['description']) !== '', 'image_count' => (int)$r['imgs'],
    ], sa_q($pdo, "SELECT p.id,p.name,p.brand,p.category,p.price,p.stock,p.sizes,p.colors,p.status,p.description,
                          (SELECT COUNT(*) FROM product_images pi WHERE pi.product_id=p.id) AS imgs
                   FROM products p WHERE $ownP ORDER BY p.status ASC, p.stock ASC, p.id DESC LIMIT 60", $ids, true));

    // ---- ยอดขาย (ยอดที่ชำระแล้ว/จัดส่งแล้ว ตามหน้าแดชบอร์ดเดิม) ----
    $sales = function (string $extra) use ($pdo, $own, $paidish, $ids) {
        return (float)sa_q($pdo, "SELECT COALESCE(SUM(oi.price*oi.qty),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id
                                  WHERE $own AND $paidish $extra", $ids);
    };
    $snap['sales'] = [
        'today'      => $sales('AND DATE(o.created_at)=CURDATE()'),
        'last_7_days'  => $sales('AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'),
        'last_30_days' => $sales('AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)'),
        'all_time'   => $sales(''),
        'orders_all_time' => (int)sa_q($pdo, "SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE $own AND $paidish", $ids),
    ];
    $snap['sales_by_day_last_7'] = sa_q($pdo, "SELECT DATE(o.created_at) AS day, SUM(oi.price*oi.qty) AS total FROM order_items oi
                                               JOIN orders o ON o.id=oi.order_id
                                               WHERE $own AND $paidish AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                                               GROUP BY DATE(o.created_at) ORDER BY day", $ids, true);
    $snap['top_products_by_qty'] = sa_q($pdo, "SELECT oi.product_name AS name, SUM(oi.qty) AS qty, SUM(oi.price*oi.qty) AS revenue
                                               FROM order_items oi JOIN orders o ON o.id=oi.order_id
                                               WHERE $own AND $paidish GROUP BY oi.product_id, oi.product_name ORDER BY qty DESC LIMIT 8", $ids, true);
    $snap['top_sizes_colors'] = sa_q($pdo, "SELECT oi.size, oi.color, SUM(oi.qty) AS qty FROM order_items oi JOIN orders o ON o.id=oi.order_id
                                            WHERE $own AND $paidish GROUP BY oi.size, oi.color ORDER BY qty DESC LIMIT 8", $ids, true);

    // ---- ออเดอร์: งานที่ต้องทำ ----
    $snap['todo'] = [
        'paid_waiting_to_ship_items' => (int)sa_q($pdo, "SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id=oi.order_id
                                                         WHERE $own AND oi.ship_status='pending' AND o.status='paid'", $ids),
        'waiting_payment_items'      => (int)sa_q($pdo, "SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id=oi.order_id
                                                         WHERE $own AND o.status='pending'", $ids),
    ];
    // ไม่ส่งชื่อ/เบอร์/ที่อยู่ผู้ซื้อให้ AI
    $snap['recent_orders'] = sa_q($pdo, "SELECT oi.order_id AS order_id, o.status, o.payment_method, o.created_at,
                                                GROUP_CONCAT(CONCAT(oi.product_name,' ไซส์',oi.size,' ',oi.color,' x',oi.qty) SEPARATOR '; ') AS items,
                                                SUM(oi.price*oi.qty) AS total
                                         FROM order_items oi JOIN orders o ON o.id=oi.order_id
                                         WHERE $own GROUP BY oi.order_id, o.status, o.payment_method, o.created_at
                                         ORDER BY o.created_at DESC LIMIT 15", $ids, true);

    // ---- เงินพัก (escrow) รายร้าน ----
    $snap['payments_by_escrow_status'] = sa_q($pdo, "SELECT escrow_status, method, COUNT(*) AS n, COALESCE(SUM(amount),0) AS amount
                                                     FROM payments WHERE store_id=? GROUP BY escrow_status, method", [$sid], true);
    $snap['ship_deadlines'] = sa_q($pdo, "SELECT order_id, amount, method, ship_by FROM payments
                                          WHERE store_id=? AND shipped_at IS NULL AND ship_by IS NOT NULL
                                            AND ((method='promptpay' AND escrow_status='held') OR (method='cod' AND status='pending'))
                                          ORDER BY ship_by ASC LIMIT 10", [$sid], true);
    $snap['payouts_total_received'] = (float)sa_q($pdo, "SELECT COALESCE(SUM(amount),0) FROM payouts WHERE store_id=?", [$sid]);
    return $snap;
}

/** คำตอบสำรองเมื่อไม่มี AI: จับคีย์เวิร์ดแล้วตอบจากข้อมูลจริง (ไม่แต่งข้อมูล) */
function sa_fallback_answer(array $s, string $q): string {
    $q = mb_strtolower($q);
    $has = fn(array $ws) => (bool)array_filter($ws, fn($w) => mb_strpos($q, $w) !== false);
    $baht = fn($n) => '฿' . number_format((float)$n);
    $out = [];

    if ($has(['ยอดขาย', 'รายได้', 'ขายได้', 'sales', 'revenue'])) {
        $x = $s['sales'];
        $out[] = "ยอดขาย (ที่ชำระแล้ว/จัดส่งแล้ว)\n• วันนี้ {$baht($x['today'])}\n• 7 วันล่าสุด {$baht($x['last_7_days'])}\n• 30 วันล่าสุด {$baht($x['last_30_days'])}\n• ทั้งหมด {$baht($x['all_time'])} จาก {$x['orders_all_time']} ออเดอร์";
    }
    if ($has(['ขายดี', 'ยอดนิยม', 'best', 'top'])) {
        if ($s['top_products_by_qty']) {
            $l = array_map(fn($t) => "• {$t['name']} — {$t['qty']} คู่ ({$baht($t['revenue'])})", $s['top_products_by_qty']);
            $out[] = "สินค้าขายดี\n" . implode("\n", array_slice($l, 0, 5));
        } else $out[] = 'ยังไม่มียอดขาย จึงยังจัดอันดับสินค้าขายดีไม่ได้';
    }
    if ($has(['สต็อก', 'สต๊อก', 'ใกล้หมด', 'หมด', 'เหลือ', 'stock'])) {
        $low = array_filter($s['products'], fn($p) => $p['status'] === 'active' && $p['stock'] <= 5);
        if ($low) {
            usort($low, fn($a, $b) => $a['stock'] <=> $b['stock']);
            $l = array_map(fn($p) => '• ' . $p['name'] . ($p['stock'] <= 0 ? ' — หมดแล้ว' : " — เหลือ {$p['stock']} ชิ้น"), array_slice($low, 0, 10));
            $out[] = "สินค้าที่สต็อกต่ำ/หมด\n" . implode("\n", $l);
        } else $out[] = 'ตอนนี้ไม่มีสินค้าที่เปิดขายแล้วสต็อกต่ำกว่า 6 ชิ้น';
    }
    if ($has(['ส่งของ', 'จัดส่ง', 'รอส่ง', 'ต้องส่ง', 'ship'])) {
        $n = $s['todo']['paid_waiting_to_ship_items'];
        $t = $n > 0 ? "มี {$n} รายการที่ชำระแล้วและรอจัดส่ง" : 'ไม่มีรายการที่รอจัดส่งตอนนี้';
        if ($s['ship_deadlines']) {
            $d = $s['ship_deadlines'][0];
            $t .= "\nกำหนดส่งใกล้สุด: ออเดอร์ #{$d['order_id']} ภายใน " . date('d/m/Y H:i', strtotime($d['ship_by']));
        }
        $out[] = $t;
    }
    if ($has(['เงิน', 'พักเงิน', 'escrow', 'โอน', 'payout', 'ถอน'])) {
        $held = 0; $rel = 0; $done = 0;
        foreach ($s['payments_by_escrow_status'] as $r) {
            if ($r['escrow_status'] === 'held') $held += $r['amount'];
            if ($r['escrow_status'] === 'releasable') $rel += $r['amount'];
            if ($r['escrow_status'] === 'released') $done += $r['amount'];
        }
        $out[] = "เงินของร้าน\n• พักไว้รอผู้ซื้อรับของ {$baht($held)}\n• พร้อมจ่ายให้ร้าน (รอแอดมินโอน) {$baht($rel)}\n• ได้รับโอนแล้ว {$baht($s['payouts_total_received'])}";
    }
    if ($has(['ออเดอร์', 'คำสั่งซื้อ', 'order', 'ล่าสุด'])) {
        if ($s['recent_orders']) {
            $th = ['pending' => 'รอชำระเงิน', 'paid' => 'ชำระแล้ว รอจัดส่ง', 'shipped' => 'จัดส่งแล้ว', 'cancelled' => 'ยกเลิก'];
            $l = array_map(fn($o) => "• #{$o['order_id']} " . ($th[$o['status']] ?? $o['status']) . " {$baht($o['total'])} (" . date('d/m H:i', strtotime($o['created_at'])) . ')', array_slice($s['recent_orders'], 0, 5));
            $out[] = "ออเดอร์ล่าสุด\n" . implode("\n", $l);
        } else $out[] = 'ยังไม่มีคำสั่งซื้อ';
    }
    if ($out) return implode("\n\n", $out);

    return "ตอนนี้ผู้ช่วย AI ยังไม่พร้อมใช้งาน จึงตอบได้เฉพาะเรื่องพื้นฐาน ลองถามเรื่อง ยอดขาย, สินค้าขายดี, สต็อกใกล้หมด, รอจัดส่ง, เงินพัก หรือออเดอร์ล่าสุด ได้เลยครับ";
}
