<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_seller();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('seller/orders.php');
csrf_check();

// เปลี่ยนสถานะได้เฉพาะรายการของร้านตัวเอง และเฉพาะคำสั่งซื้อที่แอดมินยืนยันชำระเงินแล้ว
$up = $pdo->prepare("UPDATE order_items oi JOIN orders o ON o.id = oi.order_id
                     SET oi.ship_status = 'shipped'
                     WHERE oi.id = ? AND oi.seller_id = ? AND o.status IN ('paid','shipped')");
$up->execute([(int)($_POST['id'] ?? 0), (int)$_SESSION['user']['id']]);
flash($up->rowCount() ? 'อัปเดตสถานะเป็นจัดส่งแล้ว' : 'ไม่สามารถอัปเดตได้ (รายการอาจจัดส่งแล้ว หรือยังไม่ได้ยืนยันชำระเงิน)');
redirect('seller/orders.php');
