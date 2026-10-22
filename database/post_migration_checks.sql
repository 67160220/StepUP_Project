-- Run after stepup_marketplace_migration.sql in phpMyAdmin.
-- These are read-only validation queries.
SELECT COUNT(*) AS legacy_sellers
FROM users WHERE is_seller = 1 AND role <> 'admin';

SELECT COUNT(*) AS migrated_stores
FROM stores;

SELECT COUNT(*) AS seller_products_missing_store
FROM products WHERE seller_id IS NOT NULL AND store_id IS NULL;

SELECT COUNT(*) AS seller_items_missing_store
FROM order_items WHERE seller_id IS NOT NULL AND store_id IS NULL;

SELECT id, owner_user_id, store_name, slug, status
FROM stores ORDER BY id;
