-- =====================================================================
-- StepUP Marketplace : โครงสร้างฐานข้อมูลรวม (ติดตั้งใหม่ทั้งหมด)
-- ใช้ได้กับ MySQL 5.7+ / MariaDB 10.3+
--
-- ไฟล์นี้รวมจาก:
--   database.sql                      (users, products, orders, order_items + ข้อมูลเริ่มต้น)
--   migrate_payment.sql               (orders.slip_image, slip_at  -> อยู่ใน orders แล้ว)
--   migrate_images.sql                (ตาราง product_images)
--   stepup_marketplace_migration.sql  (stores, products.store_id, order_items.store_id, payments)
--
-- วิธีใช้: เลือกฐานข้อมูลใน phpMyAdmin ก่อน แล้ว Import ไฟล์นี้ หรือวางในแท็บ SQL
--
-- คำเตือน: ไฟล์นี้ DROP ตารางทั้งหมดก่อนสร้างใหม่ ข้อมูลเดิมจะหาย
--          ห้ามรันบนฐานข้อมูลที่ใช้งานจริง ถ้าต้องการอัปเกรดฐานข้อมูลเดิมโดยไม่ให้ข้อมูลหาย
--          ให้ใช้ไฟล์ migrate_*.sql และ stepup_marketplace_migration.sql แทน (หลัง Export สำรอง)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS payouts;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS stores;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1) users : ผู้ใช้ทุกบทบาท
--    role      = customer (ผู้ใช้ทั่วไป) | admin (ผู้ดูแลระบบ)
--    is_seller / shop_name : คงไว้เพื่อความเข้ากันได้กับโค้ดเดิม (ข้อมูลร้านจริงอยู่ที่ตาราง stores)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL,
    password    VARCHAR(255) NOT NULL,                    -- เก็บ password_hash() เท่านั้น
    role        ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    is_seller   TINYINT(1) NOT NULL DEFAULT 0,
    shop_name   VARCHAR(60) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_seller (is_seller)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) stores : โปรไฟล์ร้านค้า + ข้อมูลรับเงินของแต่ละร้าน (1 ผู้ใช้ = 1 ร้าน)
--    status = pending (รออนุมัติ) | active | suspended (ระงับ)
-- ---------------------------------------------------------------------
CREATE TABLE stores (
    id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id            INT UNSIGNED NOT NULL,
    store_name               VARCHAR(60) NOT NULL,
    slug                     VARCHAR(90) NOT NULL,
    description              TEXT NULL,
    logo                     VARCHAR(255) NULL,
    contact_phone            VARCHAR(20) NULL,
    address                  TEXT NULL,
    promptpay_id             VARCHAR(80) NULL,
    promptpay_account_name   VARCHAR(100) NULL,
    status                   ENUM('pending','active','suspended') NOT NULL DEFAULT 'active',
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stores_owner (owner_user_id),
    UNIQUE KEY uq_stores_slug (slug),
    KEY idx_stores_status (status),
    CONSTRAINT fk_stores_owner FOREIGN KEY (owner_user_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) products : สินค้า
--    seller_id / store_id = NULL หมายถึงสินค้าของร้านกลาง (แอดมินเพิ่ม)
--    status = active (แสดงขาย) | hidden (ซ่อน)
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    seller_id    INT UNSIGNED NULL,
    store_id     INT UNSIGNED NULL,
    name         VARCHAR(150) NOT NULL,
    brand        VARCHAR(80)  NOT NULL,
    category     VARCHAR(50)  NOT NULL DEFAULT '',        -- Running | Lifestyle | Sneakers | Training
    price        DECIMAL(10,2) NOT NULL,
    stock        INT NOT NULL DEFAULT 0,
    image        VARCHAR(255) NULL,                       -- รูปปก (รูปทั้งหมดอยู่ใน product_images)
    description  TEXT NULL,
    sizes        VARCHAR(100) NOT NULL DEFAULT '38,39,40,41,42,43',
    colors       VARCHAR(100) NOT NULL DEFAULT 'ดำ,ขาว',
    status       ENUM('active','hidden') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_products_seller (seller_id),
    KEY idx_products_store_status (store_id, status),
    KEY idx_products_status_created (status, created_at),
    KEY idx_products_category (category),
    KEY idx_products_brand (brand),
    CONSTRAINT fk_products_seller FOREIGN KEY (seller_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_products_store FOREIGN KEY (store_id)
        REFERENCES stores (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) product_images : รูปสินค้าได้หลายรูป (สูงสุด 10 รูปต่อสินค้า ควบคุมที่โค้ด)
--    products.image ยังเก็บ "รูปปก" เหมือนเดิม
-- ---------------------------------------------------------------------
CREATE TABLE product_images (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  INT UNSIGNED NOT NULL,
    image       VARCHAR(255) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_pimg_product (product_id, sort_order),
    CONSTRAINT fk_pimg_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) orders : คำสั่งซื้อ (1 ใบ ต่อ 1 การกดสั่งซื้อ)
--    user_id = ผู้ซื้อ ถ้าลบผู้ใช้จะเป็น NULL แต่ออเดอร์ยังอยู่
--    status  = pending (รอชำระ) | paid | shipped | cancelled
--    slip_*  = คอลัมน์สลิปแบบเดิม คงไว้เพื่อความเข้ากันได้
--              (การชำระรายร้านใหม่ให้ใช้ตาราง payments)
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NULL,
    customer_name  VARCHAR(100) NOT NULL,
    phone          VARCHAR(20)  NOT NULL,
    address        TEXT NOT NULL,
    total          DECIMAL(10,2) NOT NULL DEFAULT 0,
    status         ENUM('pending','paid','shipped','cancelled') NOT NULL DEFAULT 'pending',
    payment_method ENUM('promptpay','cod') NULL,             -- promptpay = โอนเข้าบัญชีกลาง (พักเงิน) | cod = เก็บเงินปลายทาง
    slip_image     VARCHAR(255) NULL,                        -- รูปสลิปโอนเงินที่ผู้ซื้อแนบ
    slip_at        TIMESTAMP NULL DEFAULT NULL,
    slip_ref       VARCHAR(64) NULL,                         -- เลขอ้างอิงจากสลิป (UNIQUE กันใช้สลิปซ้ำ)
    slip_result    ENUM('manual','rejected','verified') NULL,
    slip_note      VARCHAR(255) NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_user (user_id),
    KEY idx_orders_status (status),
    KEY idx_orders_created (created_at),
    UNIQUE KEY uq_orders_slip_ref (slip_ref),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) order_items : รายการสินค้าในคำสั่งซื้อ (แยกตามร้านผ่าน store_id / seller_id)
--    เก็บ product_name / price ณ ตอนซื้อไว้เอง เพื่อให้ประวัติไม่เปลี่ยน
--    ship_status = pending | shipped (ร้านค้าเป็นคนกดจัดส่ง)
-- ---------------------------------------------------------------------
CREATE TABLE order_items (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id      INT UNSIGNED NOT NULL,
    product_id    INT UNSIGNED NULL,
    seller_id     INT UNSIGNED NULL,
    store_id      INT UNSIGNED NULL,
    product_name  VARCHAR(150) NOT NULL,
    size          VARCHAR(20)  NOT NULL DEFAULT '',
    color         VARCHAR(50)  NOT NULL DEFAULT '',
    price         DECIMAL(10,2) NOT NULL,
    qty           INT NOT NULL DEFAULT 1,
    ship_status   ENUM('pending','shipped') NOT NULL DEFAULT 'pending',
    PRIMARY KEY (id),
    KEY idx_items_order (order_id),
    KEY idx_items_product (product_id),
    KEY idx_items_seller (seller_id, ship_status),
    KEY idx_items_store_ship (store_id, ship_status),
    CONSTRAINT fk_items_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_items_seller FOREIGN KEY (seller_id)
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_items_store FOREIGN KEY (store_id)
        REFERENCES stores (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) payouts : บันทึกรอบการจ่ายเงินที่พักไว้ให้ร้านค้า
-- ---------------------------------------------------------------------
CREATE TABLE payouts (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    store_id   INT UNSIGNED NOT NULL,
    amount     DECIMAL(10,2) NOT NULL,
    note       VARCHAR(255) NULL,
    paid_by    INT UNSIGNED NULL,
    paid_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payouts_store (store_id, paid_at),
    CONSTRAINT fk_payouts_store FOREIGN KEY (store_id)
        REFERENCES stores (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8) payments : การชำระเงินรายร้าน (1 ออเดอร์ที่มีหลายร้าน = หลายแถว)
--    ออเดอร์หลายร้านต้องไม่ถูกตั้งเป็น paid จนกว่าทุกแถวที่จำเป็นจะ verified
--    escrow_status: none | held (พักเงิน) | releasable (พร้อมจ่ายร้าน) | released | refunded | disputed
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id       INT UNSIGNED NOT NULL,
    store_id       INT UNSIGNED NULL,
    method         ENUM('promptpay','cod') NOT NULL DEFAULT 'promptpay',
    amount         DECIMAL(10,2) NOT NULL,
    status         ENUM('pending','submitted','verified','rejected','refunded') NOT NULL DEFAULT 'pending',
    escrow_status  ENUM('none','held','releasable','released','refunded','disputed') NOT NULL DEFAULT 'none',
    slip_image     VARCHAR(255) NULL,
    slip_ref       VARCHAR(64) NULL,
    slip_at        TIMESTAMP NULL DEFAULT NULL,
    verified_at    TIMESTAMP NULL DEFAULT NULL,
    ship_by           TIMESTAMP NULL DEFAULT NULL,
    carrier           VARCHAR(40) NULL,
    tracking_no       VARCHAR(60) NULL,
    shipped_at        TIMESTAMP NULL DEFAULT NULL,
    received_at       TIMESTAMP NULL DEFAULT NULL,
    auto_release_at   TIMESTAMP NULL DEFAULT NULL,
    refunded_at       TIMESTAMP NULL DEFAULT NULL,
    payout_id         INT UNSIGNED NULL,
    note           VARCHAR(255) NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payments_escrow (escrow_status, store_id),
    KEY idx_payments_ship_by (escrow_status, ship_by),
    KEY idx_payments_order_status (order_id, status),
    KEY idx_payments_store_status (store_id, status),
    UNIQUE KEY uq_payments_slip_ref (slip_ref),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_payments_store FOREIGN KEY (store_id)
        REFERENCES stores (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_payments_payout FOREIGN KEY (payout_id)
        REFERENCES payouts (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ข้อมูลเริ่มต้น
-- =====================================================================

-- บัญชีแอดมิน  อีเมล: admin@shoestore.local   รหัสผ่าน: Admin@12345
-- **ล็อกอินครั้งแรกแล้วเปลี่ยนรหัสผ่านทันที** (หรือแก้อีเมลก่อน import)
INSERT INTO users (name, email, password, role, is_seller, shop_name) VALUES
('Administrator', 'admin@shoestore.local',
 '$2y$10$5NM5mveguo/TCAV5puhSGutPVfTsTidHtvo0F1h3cReFflHMBspjG',
 'admin', 0, NULL);

-- สินค้าตัวอย่าง 4 รายการ (ร้านกลาง seller_id / store_id = NULL, ไม่มีรูป จะแสดงไอคอน 👟)
-- เพิ่มรูป/แก้ไขได้ที่หน้าแอดมิน > จัดการสินค้า
INSERT INTO products (seller_id, store_id, name, brand, category, price, stock, image, description, sizes, colors, status) VALUES
(NULL, NULL, 'AirRun Pro',     'Nike',   'Running',   3290.00, 20, '', 'รองเท้าวิ่งน้ำหนักเบา พื้นนุ่ม รองรับแรงกระแทกได้ดี', '38,39,40,41,42,43', 'ดำ,ขาว', 'active'),
(NULL, NULL, 'Urban Classic',  'Adidas', 'Lifestyle', 2490.00, 15, '', 'ดีไซน์เรียบ ใส่สบาย ใส่ได้ทุกวัน',                           '38,39,40,41,42,43', 'ขาว,เทา', 'active'),
(NULL, NULL, 'Street Low',     'Vans',   'Sneakers',  1990.00, 25, '', 'สนีกเกอร์ทรงเตี้ย ดีไซน์ไม่ตกยุค',                           '38,39,40,41,42,43', 'ดำ,ขาว', 'active'),
(NULL, NULL, 'PowerTrain X',   'Puma',   'Training',  2790.00, 10, '', 'รองเท้าเทรนนิ่ง กระชับ มั่นคงทุกการเคลื่อนไหว',               '39,40,41,42,43',    'ดำ,แดง', 'active');

-- =====================================================================
-- ตรวจสอบหลังนำเข้า (รันแยกได้ เป็นคำสั่งอ่านอย่างเดียว)
-- SELECT COUNT(*) AS tables_created FROM information_schema.tables
--   WHERE table_schema = DATABASE()
--     AND table_name IN ('users','stores','products','product_images','orders','order_items','payouts','payments');  -- ควรได้ 8
-- SELECT COUNT(*) AS seed_products FROM products;   -- ควรได้ 4
-- SELECT COUNT(*) AS admin_users FROM users WHERE role = 'admin';   -- ควรได้ 1
-- =====================================================================