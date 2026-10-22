-- StepUP Marketplace: non-destructive migration
-- Target: MySQL 5.7+ / MariaDB 10.3+
--
-- IMPORTANT:
-- 1) Export a full SQL backup in phpMyAdmin before running this file.
-- 2) Run this migration ONCE against the existing StepUP database.
-- 3) Do NOT import database.sql/database(1).sql over the live database:
--    those files DROP existing tables.
-- 4) This migration keeps seller_id, users.is_seller, users.shop_name and
--    the existing order/payment columns for compatibility during transition.
-- 5) This migration does not configure PromptPay or change checkout behavior.
--    Application code must be updated and tested separately.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Store profile and per-store receiving details.
CREATE TABLE IF NOT EXISTS stores (
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

-- 2. Migrate existing sellers into stores.
-- The generated slug is deterministic and unique (store-<user id>).
-- Existing shop_name is preserved; missing names get a temporary label.
INSERT IGNORE INTO stores (owner_user_id, store_name, slug, status)
SELECT
    u.id,
    COALESCE(NULLIF(TRIM(u.shop_name), ''), CONCAT('ร้านค้า ', u.id)),
    CONCAT('store-', u.id),
    'active'
FROM users u
WHERE u.is_seller = 1
  AND u.role <> 'admin';

-- 3. Add store_id to products without removing seller_id.
ALTER TABLE products
    ADD COLUMN store_id INT UNSIGNED NULL AFTER seller_id,
    ADD KEY idx_products_store_status (store_id, status),
    ADD CONSTRAINT fk_products_store FOREIGN KEY (store_id)
        REFERENCES stores (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Link seller-owned products to the corresponding migrated store.
UPDATE products p
JOIN stores s ON s.owner_user_id = p.seller_id
SET p.store_id = s.id
WHERE p.seller_id IS NOT NULL;

-- 4. Add store_id to order_items without removing seller_id.
ALTER TABLE order_items
    ADD COLUMN store_id INT UNSIGNED NULL AFTER seller_id,
    ADD KEY idx_items_store_ship (store_id, ship_status),
    ADD CONSTRAINT fk_items_store FOREIGN KEY (store_id)
        REFERENCES stores (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Prefer the store linked to the purchased product, preserving the historic item.
UPDATE order_items oi
JOIN products p ON p.id = oi.product_id
SET oi.store_id = p.store_id
WHERE p.store_id IS NOT NULL;

-- Fallback for historic rows whose product no longer has a store_id.
UPDATE order_items oi
JOIN stores s ON s.owner_user_id = oi.seller_id
SET oi.store_id = s.id
WHERE oi.store_id IS NULL
  AND oi.seller_id IS NOT NULL;

-- 5. Per-store payment records.
-- Existing orders.slip_* columns remain untouched for backwards compatibility.
-- New checkout/payment code must create one payment row per store and must
-- not mark a multi-store order fully paid until all required payments are verified.
CREATE TABLE IF NOT EXISTS payments (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id       INT UNSIGNED NOT NULL,
    store_id       INT UNSIGNED NULL,
    amount         DECIMAL(10,2) NOT NULL,
    status         ENUM('pending','submitted','verified','rejected','refunded') NOT NULL DEFAULT 'pending',
    slip_image     VARCHAR(255) NULL,
    slip_ref       VARCHAR(64) NULL,
    slip_at        TIMESTAMP NULL DEFAULT NULL,
    verified_at    TIMESTAMP NULL DEFAULT NULL,
    note           VARCHAR(255) NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payments_order_status (order_id, status),
    KEY idx_payments_store_status (store_id, status),
    UNIQUE KEY uq_payments_slip_ref (slip_ref),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_payments_store FOREIGN KEY (store_id)
        REFERENCES stores (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Post-migration checks to run manually in phpMyAdmin.
-- SELECT COUNT(*) AS legacy_sellers FROM users WHERE is_seller = 1 AND role <> 'admin';
-- SELECT COUNT(*) AS migrated_stores FROM stores;
-- SELECT COUNT(*) AS seller_products_missing_store
--   FROM products WHERE seller_id IS NOT NULL AND store_id IS NULL;
-- SELECT COUNT(*) AS seller_items_missing_store
--   FROM order_items WHERE seller_id IS NOT NULL AND store_id IS NULL;
--
-- Do not remove old columns or legacy orders.slip_* fields until the updated
-- application has been tested against a backup copy and verified in production.
