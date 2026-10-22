<?php
// ฟังก์ชันสำหรับระบบผู้ขาย (ต้อง require config/database.php ก่อน)
// ข้อมูลร้านจริงอยู่ใน stores; users.is_seller คงไว้ชั่วคราวเพื่อความเข้ากันได้กับโค้ดเก่า.
function is_seller() { return !empty($_SESSION['user']['is_seller']); }

function current_store(): ?array {
    global $pdo;
    if (!isset($_SESSION['user']['id'])) return null;
    static $cache = [];
    $uid = (int)$_SESSION['user']['id'];
    if (array_key_exists($uid, $cache)) return $cache[$uid];
    try {
        $st = $pdo->prepare("SELECT * FROM stores WHERE owner_user_id = ? LIMIT 1");
        $st->execute([$uid]);
        $store = $st->fetch();
        $cache[$uid] = $store ?: null;
        return $cache[$uid];
    } catch (PDOException $e) {
        // ถ้ายังไม่ได้รัน migration อย่ากลบข้อมูลหรือแสดง fatal error ในหน้าเว็บ
        return null;
    }
}

function require_login() {
    if (!isset($_SESSION['user'])) { flash('กรุณาเข้าสู่ระบบก่อน'); redirect('pages/login.php'); }
}
function require_seller() {
    require_login();
    $store = current_store();
    if (!is_seller() || !$store || ($store['status'] ?? '') !== 'active') {
        flash('กรุณาเปิดร้านหรือรอให้ร้านเปิดใช้งานก่อนเริ่มขายสินค้า');
        redirect('pages/open_shop.php');
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('คำขอไม่ถูกต้องหรือหมดอายุ กรุณากลับไปโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}

// กฎการซื้อ: ล็อกอินแล้ว, แอดมินซื้อไม่ได้, เจ้าของร้านซื้อสินค้าร้านตัวเองไม่ได้.
function purchase_block_reason(array $p): ?string {
    if (!isset($_SESSION['user'])) return 'กรุณาเข้าสู่ระบบก่อนสั่งซื้อ';
    if (is_admin()) return 'บัญชีแอดมินไม่สามารถสั่งซื้อสินค้าได้';
    $uid = (int)$_SESSION['user']['id'];
    if (isset($p['seller_id']) && $p['seller_id'] !== null && (int)$p['seller_id'] === $uid) {
        return 'ไม่สามารถซื้อสินค้าของร้านตัวเองได้';
    }
    $store = current_store();
    if ($store && isset($p['store_id']) && $p['store_id'] !== null && (int)$p['store_id'] === (int)$store['id']) {
        return 'ไม่สามารถซื้อสินค้าของร้านตัวเองได้';
    }
    return null;
}

// อัปโหลดรูปสินค้า คืนค่า URL ของรูป / null ถ้าไม่ได้เลือกไฟล์ / ตั้งค่า $error ถ้าผิดพลาด
function save_upload(array $f, ?string &$error = null, string $subdir = 'products', int $maxBytes = 2 * 1024 * 1024): ?string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) { $error = 'อัปโหลดไฟล์ไม่สำเร็จ'; return null; }
    if ($f['size'] > $maxBytes) { $error = 'ไฟล์รูปต้องไม่เกิน ' . round($maxBytes / 1048576) . 'MB'; return null; }
    $info = @getimagesize($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if (!$ext) { $error = 'รองรับเฉพาะไฟล์ JPG, PNG, WEBP'; return null; }
    $dir = __DIR__ . '/../assets/images/' . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;     // ตั้งชื่อใหม่เอง ไม่ใช้ชื่อไฟล์จากผู้ใช้
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) { $error = 'บันทึกไฟล์ไม่สำเร็จ'; return null; }
    return BASE_URL . '/assets/images/' . $subdir . '/' . $name;
}

const MAX_PRODUCT_IMAGES = 10;

// แปลง $_FILES['images'] (อัปโหลดหลายไฟล์) เป็นรายการทีละไฟล์ โดยข้ามช่องที่ไม่ได้เลือกไฟล์
function normalize_files(array $f): array {
    $out = [];
    if (!isset($f['name']) || !is_array($f['name'])) return $out;
    foreach ($f['name'] as $i => $n) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $n, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i], 'type' => $f['type'][$i] ?? ''];
    }
    return $out;
}

// ลบไฟล์รูปสินค้าออกจากเซิร์ฟเวอร์ (เฉพาะไฟล์ในโฟลเดอร์ products ที่ระบบอัปโหลดเอง ไม่แตะ URL ภายนอก)
function delete_product_file(?string $url): void {
    if (!$url || strpos($url, BASE_URL . '/assets/images/products/') !== 0) return;
    $f = __DIR__ . '/../assets/images/products/' . basename($url);
    if (is_file($f)) @unlink($f);
}

const PRODUCT_CATEGORIES = ['Running', 'Lifestyle', 'Sneakers', 'Training'];

// ตัวเลือกไซส์ / สี สำหรับฟอร์มลงขายสินค้า (ชื่อสี => รหัสสีที่ใช้แสดงจุดตัวอย่าง)
const SIZE_OPTIONS = ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47'];
const COLOR_OPTIONS = [
    'ดำ' => '#161616', 'ขาว' => '#ffffff', 'เทา' => '#9ca3af', 'ครีม' => '#f3e9d2', 'น้ำตาล' => '#8b5a2b',
    'แดง' => '#e52b3a', 'ส้ม' => '#f97316', 'เหลือง' => '#facc15', 'เขียว' => '#22c55e',
    'ฟ้า' => '#38bdf8', 'น้ำเงิน' => '#1d4ed8', 'ม่วง' => '#8b5cf6', 'ชมพู' => '#f472b6',
];

// รวมค่าจากช่องติ๊กเลือก + ช่องพิมพ์เพิ่ม เป็นข้อความคั่นจุลภาค (ตรวจค่า ตัดซ้ำ จำกัดความยาว 100 ตัวอักษรตามคอลัมน์)
function collect_list($picked, ?string $extra, array $allowed, string $extraPattern): string {
    $out = [];
    foreach ((array)$picked as $v) {
        $v = trim((string)$v);
        if (in_array($v, $allowed, true) && !in_array($v, $out, true)) $out[] = $v;
    }
    foreach (preg_split('/[,\n]+/u', (string)$extra) as $v) {
        $v = trim($v);
        if ($v !== '' && preg_match($extraPattern, $v) && !in_array($v, $out, true)) $out[] = $v;
    }
    $str = '';
    foreach ($out as $v) {
        $next = $str === '' ? $v : $str . ',' . $v;
        if (preg_match_all('/./us', $next) > 100) break;
        $str = $next;
    }
    return $str;
}
