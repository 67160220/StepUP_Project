<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('pages/cart.php');
require_login();
csrf_check();
if (is_admin()) { flash('บัญชีแอดมินไม่สามารถสั่งซื้อสินค้าได้'); redirect('admin/index.php'); }

$cart = $_SESSION['cart'] ?? [];
if (!$cart) redirect('pages/cart.php');
$uid = (int)$_SESSION['user']['id'];
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
if ($name === '' || $phone === '' || $address === '') { flash('กรุณากรอกข้อมูลให้ครบ'); redirect('pages/checkout.php'); }
if (!preg_match('/^[0-9]{9,10}$/', $phone)) { flash('เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก'); redirect('pages/checkout.php'); }

try {
    $pdo->beginTransaction();
    // store_id เพิ่มโดย migration; seller_id เดิมคงไว้เพื่อความเข้ากันได้
    $lock = $pdo->prepare("SELECT id, name, price, stock, seller_id, store_id FROM products WHERE id = ? AND status = 'active' FOR UPDATE");
    $need = [];
    foreach ($cart as $it) {
        $pid = filter_var($it['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $qty = filter_var($it['qty'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
        if (!$pid || !$qty) throw new RuntimeException('ตะกร้ามีข้อมูลสินค้าไม่ถูกต้อง');
        $need[$pid] = ($need[$pid] ?? 0) + $qty;
    }
    $prod = [];
    foreach ($need as $pid => $qty) {
        $lock->execute([$pid]);
        $p = $lock->fetch();
        if (!$p || (int)$p['stock'] < $qty) {
            $pdo->rollBack(); flash('สินค้าบางรายการมีไม่พอหรือไม่พร้อมขายแล้ว กรุณาตรวจสอบตะกร้า'); redirect('pages/cart.php');
        }
        if ($why = purchase_block_reason($p)) {
            $pdo->rollBack(); flash($why . ' (' . $p['name'] . ') กรุณาลบออกจากตะกร้า'); redirect('pages/cart.php');
        }
        // ร้านที่ถูกระงับ/ไม่มีร้านไม่ควรขายสินค้าใน marketplace
        if (!empty($p['seller_id']) && empty($p['store_id'])) {
            throw new RuntimeException('สินค้าเจ้าของร้านยังไม่ได้ผูกกับร้านค้า กรุณาตรวจสอบ migration');
        }
        if (!empty($p['store_id'])) {
            $storeCheck = $pdo->prepare("SELECT id FROM stores WHERE id = ? AND status = 'active'");
            $storeCheck->execute([(int)$p['store_id']]);
            if (!$storeCheck->fetchColumn()) throw new RuntimeException('ร้านค้าของสินค้านี้ยังไม่พร้อมขาย');
        }
        $prod[$pid] = $p;
    }
    $total = 0.0;
    foreach ($cart as $it) $total += (float)$prod[(int)$it['id']]['price'] * (int)$it['qty'];

    $pdo->prepare('INSERT INTO orders (user_id, customer_name, phone, address, total) VALUES (?,?,?,?,?)')
        ->execute([$uid, $name, $phone, $address, $total]);
    $orderId = (int)$pdo->lastInsertId();

    $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, seller_id, store_id, product_name, size, color, price, qty) VALUES (?,?,?,?,?,?,?,?,?)');
    foreach ($cart as $it) {
        $p = $prod[(int)$it['id']];
        $ins->execute([$orderId, $p['id'], $p['seller_id'], $p['store_id'], $p['name'], (string)($it['size'] ?? ''), (string)($it['color'] ?? ''), $p['price'], (int)$it['qty']]);
    }
    $dec = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
    foreach ($need as $pid => $qty) {
        $dec->execute([$qty, $pid, $qty]);
        if ($dec->rowCount() !== 1) throw new RuntimeException('สต็อกสินค้าเปลี่ยนระหว่างสั่งซื้อ');
    }
    $pdo->commit();
    unset($_SESSION['cart']);
    flash("สร้างคำสั่งซื้อ #$orderId แล้ว กรุณาชำระเงินและแนบสลิปเพื่อยืนยันการสั่งซื้อ");
    redirect('pages/payment.php?id=' . $orderId);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('StepUP process order error: ' . $e->getMessage());
    flash('สร้างคำสั่งซื้อไม่สำเร็จ กรุณาลองใหม่ หากเกิดซ้ำให้แจ้งผู้ดูแลระบบ');
    redirect('pages/checkout.php');
}
