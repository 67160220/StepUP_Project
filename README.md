# StepUP Store

https://angsila.informatics.buu.ac.th/~67160220/StepUP/pages/home.php
Marketplace ขายรองเท้าภาษาไทย รวมหลายร้านไว้ในเว็บเดียว พร้อมระบบ **พักเงิน (Escrow)** เพื่อความปลอดภัยของผู้ซื้อ, รองรับ **เก็บเงินปลายทาง (COD)** และมี **AI Shoe Finder** ผู้ช่วยแนะนำรองเท้า

---

## สารบัญ

1. [ภาพรวม](#1-ภาพรวม)
2. [ฟีเจอร์หลัก](#2-ฟีเจอร์หลัก)
3. [เริ่มต้นใช้งาน](#3-เริ่มต้นใช้งาน)
4. [โครงสร้างโปรเจกต์](#4-โครงสร้างโปรเจกต์)
5. [บทบาทผู้ใช้](#5-บทบาทผู้ใช้)
6. [ฐานข้อมูล](#6-ฐานข้อมูล)
7. [การทำงานของระบบ](#7-การทำงานของระบบ)
8. [การตั้งค่า](#8-การตั้งค่า)

---

## 1. ภาพรวม

| หัวข้อ | รายละเอียด |
|---|---|
| ประเภท | เว็บ Marketplace หลายร้านค้า |
| ภาษา / สกุลเงิน | ไทยทั้งหมด (`<html lang="th">`, ฟอนต์ Prompt) / บาท |
| Stack | PHP ล้วน (ไม่มี framework, ไม่มี Composer, ไม่มี build step) + MySQL/MariaDB ผ่าน PDO + CSS/JS ธรรมดา |
| รันบน | Apache + PHP 8.1+ (ใช้ `never` return type, `match`, arrow functions) |
| ส่วนขยาย PHP | `pdo_mysql`, `curl`, `mbstring` |
| ฐานข้อมูล | MySQL 5.7+ / MariaDB 10.3+ (InnoDB, utf8mb4) |
| State | PHP session: `user`, `cart`, `flash`, `csrf` |

**รูปแบบโค้ด**

- `pages/*.php` คือหน้าแสดงผล (GET)
- `actions/*.php` คือตัวรับ POST แล้ว redirect
- `includes/*.php` คือฟังก์ชันและส่วนประกอบที่ใช้ร่วมกัน

---

## 2. ฟีเจอร์หลัก

- **หลายร้านในเว็บเดียว:** ผู้ใช้เปิดร้านได้เอง (1 ผู้ใช้ = 1 ร้าน) ลงสินค้าได้สูงสุด 10 รูปต่อสินค้า
- **ตะกร้าและสั่งซื้อ:** ออเดอร์เดียวซื้อสินค้าจากหลายร้านได้ ราคาและสต็อกอ่านจาก DB เสมอ ไม่เชื่อค่าจากตะกร้า
- **ชำระเงิน 2 ทาง:**
  - โอนผ่าน PromptPay QR เข้าบัญชีกลางของแพลตฟอร์ม แล้วแพลตฟอร์ม **พักเงิน** ไว้จนผู้ซื้อยืนยันรับของ
  - เก็บเงินปลายทาง (COD) วงเงินไม่เกินที่กำหนด (ดีฟอลต์ 3,000 บาท)
- **Escrow:** ร้านต้องส่งของภายในกำหนด ผู้ซื้อยืนยันรับของหรือแจ้งปัญหาได้ แอดมินตัดสินข้อพิพาทและจ่ายเงินร้านค้า
- **AI Shoe Finder:** แชตแนะนำรองเท้า โดย AI ช่วยแยกเงื่อนไขและสรุปภาษา ส่วนสินค้าที่แนะนำเลือกจากฐานข้อมูลจริงเท่านั้น ถ้า AI ใช้ไม่ได้จะสลับไปโหมด keyword อัตโนมัติ
- **หลังบ้าน 2 ฝั่ง:** แดชบอร์ดร้านค้า (สินค้า/ออเดอร์/ส่งของ) และแอดมิน (ตรวจสลิป/ข้อพิพาท/จ่ายร้าน/ดูแลสินค้า)

---

## 3. เริ่มต้นใช้งาน

### สิ่งที่ต้องมี

- Apache + PHP 8.1 ขึ้นไป (เช่น XAMPP / MAMP / Laragon)
- MySQL 5.7+ หรือ MariaDB 10.3+

### ขั้นตอนติดตั้ง

1. วางโปรเจกต์ไว้ในโฟลเดอร์ของ web server (เช่น `htdocs/StepUP`)
2. สร้างฐานข้อมูล แล้ว import `database/database.sql`
   > ⚠ ไฟล์นี้มี `DROP TABLE` ทุกตาราง **ห้ามรันบน DB ที่ใช้งานจริง**
3. แก้ `config/database.php`: ตั้ง `BASE_URL` ให้ตรงกับ path ของโปรเจกต์ และตั้งค่า `DB_*`
4. แก้ `config/payment.php`: ใส่ `PROMPTPAY_ID` (เบอร์โทร 10 หลัก หรือเลขบัตร/ภาษี 13 หลัก) และ `PAY_ACCOUNT_NAME` ถ้าเว้นว่างไว้ ระบบจะไม่แสดง QR
5. (ถ้าต้องการ AI) ตั้ง environment variable `GEMINI_API_KEY` ถ้าไม่ตั้ง AI Shoe Finder จะใช้โหมด keyword fallback
6. ให้ web server เขียนโฟลเดอร์ `assets/images/products/` และ `assets/images/slips/` ได้
7. ตรวจค่า `php.ini` ให้รองรับการอัปโหลดหลายรูป: `upload_max_filesize`, `post_max_size` (10 รูป × 2MB เกือบ 20MB), `max_file_uploads`
8. เข้าสู่ระบบแอดมิน `admin@shoestore.local` แล้ว **เปลี่ยนรหัสผ่านทันที**

### ทดลอง flow ครบวงจร

1. สมัครสมาชิก 2 บัญชี (บัญชี A และ B)
2. บัญชี A เปิดร้านที่ `pages/open_shop.php` แล้วลงสินค้า
3. บัญชี B สั่งซื้อสินค้าของร้าน A แล้วชำระเงิน
4. แอดมินตรวจสลิปที่ `admin/escrow.php`
5. ร้าน A ส่งของ → บัญชี B ยืนยันรับของ → แอดมินกดจ่ายเงินร้าน

---

## 4. โครงสร้างโปรเจกต์

```
StepUP/
├── config/
│   ├── database.php               # session_start, BASE_URL, PDO ($pdo) และ helper ทั่วไป
│   ├── payment.php                # บัญชีรับเงินกลางของแพลตฟอร์ม
│   ├── payment.config.example.php # ตัวอย่างค่า escrow/COD/สลิปครบชุด
│   ├── easyslip.php               # ค่า EasySlip API (ยังไม่ถูกเรียกใช้)
│   └── ai_shoe_finder.php         # ค่า Gemini (provider, api_url, api_key, model, timeout)
├── includes/
│   ├── header.php / footer.php    # layout + navbar
│   ├── product_card.php           # การ์ดสินค้า (ใช้ตัวแปร $p)
│   ├── seller_functions.php       # auth guards, CSRF, save_upload, ค่าคงที่หมวด/ไซส์/สี
│   ├── escrow_functions.php       # หัวใจระบบพักเงิน
│   ├── payment_functions.php      # สร้าง PromptPay QR payload (EMVCo + CRC16)
│   └── slip_verify.php            # ตรวจสลิปผ่าน EasySlip v2 (ยังไม่ผูกกับ flow)
├── pages/                         # หน้าฝั่งผู้ใช้
├── actions/                       # ตัวรับ POST (ดูตารางในหัวข้อ 7.6)
├── seller/                        # หลังบ้านร้านค้า: index, products, orders
├── admin/                         # หลังบ้านแอดมิน: index, manage_products, manage_orders, escrow
├── assets/{css,js,images}         # images/products และ images/slips เป็นที่เก็บไฟล์อัปโหลด
└── database/database.sql          # สคริปต์สร้าง DB ทั้งหมด + seed (แหล่งความจริงของ schema)
```

**helper หลักใน `config/database.php`:** `e()`, `baht()`, `redirect()`, `flash()`, `is_admin()`, `require_admin()`, `cart_count()`

**หน้าฝั่งผู้ใช้ใน `pages/`:** home, products, product_detail, cart, checkout, payment, my_orders, login, register, open_shop, ai_shoe_finder, contact

---

## 5. บทบาทผู้ใช้

| บทบาท | วิธีระบุ | ทำอะไรได้ |
|---|---|---|
| **Guest** | ไม่มี `$_SESSION['user']` | ดูสินค้า, ใช้ AI Shoe Finder (ซื้อไม่ได้) |
| **Customer** | `users.role='customer'` | ซื้อ, ชำระเงิน, ยืนยันรับของ/แจ้งปัญหา, เปิดร้านได้ |
| **Seller** | `users.is_seller=1` **และ** มีร้านใน `stores` ที่ `status='active'` | ลงสินค้า, ดูออเดอร์ของร้าน, ส่งของ (`require_seller()`) |
| **Admin** | `users.role='admin'` | ตรวจสลิป, ตัดสินข้อพิพาท, จ่ายเงินร้าน, ซ่อน/ลบสินค้า, เปลี่ยนสถานะออเดอร์ (`require_admin()`) |

**กฎสำคัญ**

- ซื้อได้เฉพาะผู้ที่ล็อกอิน (`purchase_block_reason()`)
- แอดมินซื้อสินค้าและเปิดร้านไม่ได้ และเพิ่มสินค้าเองไม่ได้ (ทำได้แค่ซ่อน/แสดง/ลบ)
- เจ้าของร้านซื้อสินค้าของร้านตัวเองไม่ได้
- 1 ผู้ใช้เปิดร้านได้สูงสุด 1 ร้าน (`stores.owner_user_id` เป็น UNIQUE)
- สินค้าที่ `seller_id/store_id = NULL` คือ "ร้านกลาง StepUP" (มีแค่ seed 4 ชิ้น)

---

## 6. ฐานข้อมูล

8 ตาราง

```
users 1──1 stores 1──* products 1──* product_images
  │                       │
  │ (ผู้ซื้อ)              │ (snapshot ตอนซื้อ)
  └──* orders 1──* order_items ──(store_id)──> stores
          │
          └──* payments (1 แถวต่อ 1 ร้านต่อ 1 ออเดอร์) ──> payouts (รอบจ่ายเงินร้าน)
```

| ตาราง | ข้อมูลสำคัญ |
|---|---|
| `users` | `role` (customer/admin), `is_seller`, `shop_name` (**legacy**), รหัสผ่านเก็บด้วย `password_hash()` |
| `stores` | `store_name`, `slug` (`store-{userId}`), `promptpay_id`, `promptpay_account_name`, `status` (pending/active/suspended) |
| `products` | `seller_id` + `store_id` (ซ้ำซ้อนกัน), `category` (Running/Lifestyle/Sneakers/Training), `sizes`/`colors` เป็นสตริงคั่นจุลภาค, `status` (active/hidden), `image` = รูปปก |
| `product_images` | สูงสุด 10 รูปต่อสินค้า (`MAX_PRODUCT_IMAGES`) เรียงด้วย `sort_order` |
| `orders` | `status` (pending/paid/shipped/cancelled), `payment_method` (promptpay/cod), `slip_*` (**legacy**) |
| `order_items` | เก็บ `product_name`, `price`, `size`, `color`, `qty` ณ ตอนซื้อ และ `ship_status` (pending/shipped) |
| `payments` | **ตารางหลักของ escrow** (ดูด้านล่าง) |
| `payouts` | บันทึกยอดที่แอดมินโอนให้ร้านแต่ละรอบ |

**คอลัมน์สำคัญใน `payments`:** `status` (pending/submitted/verified/rejected/refunded), `escrow_status` (none/held/releasable/released/refunded/disputed), `ship_by`, `tracking_no`, `carrier`, `shipped_at`, `received_at`, `auto_release_at`, `payout_id`

> `payments` คือแหล่งความจริงของเรื่องเงิน ส่วน `orders.status` เป็นแค่สถานะสรุป

---

## 7. การทำงานของระบบ

### 7.1 สั่งซื้อ

1. `actions/add_to_cart.php` เก็บใน `$_SESSION['cart']` คีย์ `"{id}-{size}-{color}"` (ไม่เกิน 10 ชิ้นต่อรายการ, เช็กสต็อกรวมทุกไซส์/สี)
2. `pages/checkout.php` กรอกชื่อ เบอร์โทร (9–10 หลัก) และที่อยู่
3. `actions/process_order.php` ทำงานใน **transaction เดียว**
   - `SELECT ... FOR UPDATE` ล็อกสินค้า
   - ตรวจสต็อก, สิทธิ์ซื้อ, ร้านต้อง active
   - อ่านราคาจาก DB
   - สร้าง `orders` + `order_items` และตัดสต็อก
   - ไปหน้า `payment.php`

### 7.2 ชำระเงินแบบโอน (PromptPay + Escrow)

```
ผู้ซื้อโอนยอดรวมเข้าบัญชีกลางของ StepUP ครั้งเดียว (QR สร้างจาก PROMPTPAY_ID)
 → upload_slip.php        เก็บสลิป, สร้าง payments รายร้าน status='submitted'
 → แอดมินกด approve_slip  ที่ admin/escrow.php
 → escrow_mark_paid()     payments.status='verified', escrow_status='held'
                          ตั้ง ship_by = ตอนนี้ + ESCROW_SHIP_DAYS, orders.status='paid'
                          (ร้านกลางข้ามไป 'released' เลย)
 → ร้านส่งของ (ต้องใส่เลขพัสดุ) → ตั้ง shipped_at และ auto_release_at = ตอนนี้ + ESCROW_AUTO_RELEASE_DAYS
 → ผู้ซื้อกด "ได้รับแล้ว" (do=confirm) → escrow_status='releasable'
   หรือแจ้งปัญหา (do=dispute)         → 'disputed' → แอดมินตัดสิน: คืนเงิน (refunded) หรือจ่ายร้าน (releasable)
 → แอดมินโอนให้ร้านแล้วกด "payout" → สร้างแถว payouts, escrow_status='released'
```

> 🔍 ถ้า `SLIP_AUTO_VERIFY=true` เอกสารเดิมระบุว่าจะผ่านสลิปทันทีโดยไม่ตรวจเงินจริง (โหมดเดโม) แต่ระบบตรวจสลิปอัตโนมัติยังไม่ผูกกับ flow ให้ตรวจใน `upload_slip.php` ว่าค่านี้ถูกอ่านจริงหรือไม่

### 7.3 ชำระเงินแบบ COD

`actions/choose_cod.php` ยอดต้องไม่เกิน `COD_MAX_AMOUNT`

- สร้าง `payments` แบบ `method='cod'` และตั้ง `orders.status='paid'` ทันที (⚠ ทั้งที่ยังไม่ได้รับเงิน ให้แยกด้วย `payment_method='cod'`)
- ร้านต้องส่งภายใน `ship_by`
- เมื่อผู้ซื้อยืนยันรับของ `payments.status='verified'`

### 7.4 สถานะของ `payments.escrow_status`

| จาก | ไป | ใครเป็นผู้ทำ / เงื่อนไข |
|---|---|---|
| none | held | แอดมินอนุมัติสลิป (`escrow_mark_paid`) |
| none | released | อนุมัติสลิปของร้านกลาง StepUP (ข้ามขั้นพักเงิน) |
| held | releasable | ผู้ซื้อกดยืนยันรับของ หรือครบ `auto_release_at` โดยผู้ซื้อเงียบ |
| held | refunded | ร้านไม่ส่งภายใน `ship_by` (อัตโนมัติ) หรือแอดมินตัดสินคืนเงิน |
| held | disputed | ผู้ซื้อแจ้งปัญหา |
| disputed | refunded / releasable | แอดมินตัดสิน |
| releasable | released | แอดมินโอนให้ร้านแล้วกด payout |

> ตารางนี้สรุปจาก flow ในโค้ด 🔍 ควรตรวจกับ `escrow_functions.php` อีกครั้งว่ามีเส้นทางอื่นเพิ่มเติมหรือไม่

### 7.5 กฎอัตโนมัติ `escrow_run_auto()`

ไม่มี cron ระบบเรียกแบบ lazy เมื่อมีคนเปิด `pages/payment.php` และ `admin/escrow.php` เท่านั้น

| เงื่อนไข | ผล |
|---|---|
| ออนไลน์, held, ร้านไม่ส่งภายใน `ship_by` | `refunded` (แอดมินต้องโอนคืนเอง แล้วกด `refund_done`) |
| COD, ร้านไม่ส่งภายใน `ship_by` | `rejected` |
| ส่งแล้ว ผู้ซื้อเงียบจนถึง `auto_release_at` | `releasable` (ออนไลน์) / `verified` (COD) |
| ทุกแถว payments ของออเดอร์ถูก refunded/rejected | `orders.status='cancelled'` |

> ระบบนี้ **ไม่โอนเงินจริงเลย** แอดมินเป็นคนโอนเข้า/ออกด้วยมือ แล้วบันทึกผลในหน้า `admin/escrow.php`

### 7.6 รายการ Action ที่ทราบ

| ไฟล์ | หน้าที่ |
|---|---|
| `add_to_cart.php` | เพิ่มสินค้าลงตะกร้า |
| `process_order.php` | สร้างออเดอร์ (transaction + ล็อกสต็อก) |
| `upload_slip.php` | อัปโหลดสลิป สร้าง payments รายร้าน |
| `choose_cod.php` | เลือกชำระแบบ COD |
| `escrow_action.php` | ผู้ซื้อยืนยันรับของ (`do=confirm`) / แจ้งปัญหา (`do=dispute`) |
| `open_shop_process.php` | เปิดร้าน (ได้สถานะ active ทันที) |
| `seller_product_save.php` | เพิ่ม/แก้สินค้า (JPG/PNG/WEBP ≤2MB ต่อรูป) |
| `seller_product_delete.php` | ลบสินค้าแบบ soft delete (`status='hidden'`) |
| `seller_ship_item.php` | ส่งของ (**legacy** ไม่มีเลขพัสดุ ดูหัวข้อ 9 ข้อ 1) |
| `seller_mark_shipped.php` | ส่งของพร้อมเลขพัสดุ (ถูกต้องตาม escrow แต่ยังไม่มีหน้าเรียกใช้) |
| `login_process.php` | เข้าสู่ระบบ (ยังไม่มี CSRF check) |
| `ai_shoe_finder.php` | JSON API ของแชต AI |

> 🔍 รายการนี้ไม่ครบทุกไฟล์ในโฟลเดอร์ `actions/` ควรเติมให้ครบพร้อมคอลัมน์ guard และตารางที่แก้ไข

### 7.7 AI Shoe Finder

หน้าแชต (`pages/ai_shoe_finder.php`) เรียก JSON API (`actions/ai_shoe_finder.php`) ด้วย `POST {message, product_id, shown_ids, history}`

- **โหมดค้นหา** (`product_id=0`)
  1. ให้ Gemini แยกเงื่อนไขเป็น JSON (budget, brand, color, size, category, keywords)
  2. ถ้า AI ล่มหรือไม่มี key ใช้ `fallback_intent()` (regex ไทย/อังกฤษ)
  3. query DB แล้วให้คะแนนด้วย `score_product()`
  4. คืน 3 อันดับพร้อมเหตุผล
- **โหมดถามสินค้า** (`product_id>0`) ตอบจากข้อมูลสินค้านั้น ถ้า AI ล่มใช้ `answer_from_data()`
- **หลักการออกแบบ:** AI ไม่ได้เลือกสินค้าเอง สินค้าและเหตุผล (`reason`) มาจากข้อมูลจริงใน DB AI ทำแค่แยกเงื่อนไขและสรุปภาษา จึงไม่แต่งสินค้าขึ้นมา
- **ข้อจำกัด:** ข้อความ ≤700 ตัวอักษร, rate limit 1 วินาทีต่อ session, response มี `mode` เป็น `ai` หรือ `keyword_fallback`

---

## 8. การตั้งค่า

| ไฟล์ / ค่า | ความหมาย |
|---|---|
| `config/database.php` → `BASE_URL`, `DB_*` | path ของโปรเจกต์บนเซิร์ฟเวอร์ และข้อมูลต่อ DB |
| `config/payment.php` → `PROMPTPAY_ID`, `PAY_ACCOUNT_NAME` | บัญชีรับเงินกลาง (ว่าง = ไม่แสดง QR) |
| `ESCROW_SHIP_DAYS` (3) | จำนวนวันที่ร้านต้องส่งของ |
| `ESCROW_AUTO_RELEASE_DAYS` (7) | จำนวนวันหลังส่งของที่ปล่อยเงินอัตโนมัติ |
| `COD_MAX_AMOUNT` (3000) | ยอดสูงสุดที่ใช้ COD ได้ |
| `SLIP_AUTO_VERIFY` (false) | ผ่านสลิปอัตโนมัติ (เดโมเท่านั้น) |
| `GEMINI_API_KEY`, `GEMINI_MODEL` (`gemini-2.5-flash`), `GEMINI_API_URL` | ตั้งเป็น **environment variable** ไม่มี key = โหมด fallback |
| `config/easyslip.php` | ยังไม่มีผลกับระบบ |

ค่า escrow/COD ไม่ต้อง `define` ครบ โค้ดอ่านผ่าน `escrow_cfg('ชื่อค่า', ดีฟอลต์)` ตัวอย่างอยู่ใน `config/payment.config.example.php`

> 🔍 **Timezone:** `ship_by` และ `auto_release_at` คำนวณจากเวลา ควรตั้ง `date_default_timezone_set('Asia/Bangkok')` และ time zone ของ DB ให้ตรงกัน ตรวจว่าโปรเจกต์ตั้งไว้แล้วหรือยัง

---