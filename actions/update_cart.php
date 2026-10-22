<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/seller_functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('pages/cart.php');
csrf_check();

if (isset($_POST['remove'])) {
    unset($_SESSION['cart'][$_POST['remove']]);
} else {
    foreach ((array)($_POST['qty'] ?? []) as $key => $qty) {
        $qty = (int)$qty;
        if (!isset($_SESSION['cart'][$key])) continue;
        if ($qty <= 0) unset($_SESSION['cart'][$key]);
        else $_SESSION['cart'][$key]['qty'] = min(10, $qty);
    }
}
redirect('pages/cart.php');
