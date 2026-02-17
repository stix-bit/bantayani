-- Cooperative Pool Management: pool pricing
-- Any farmer can contribute to any pool.
-- Run this on bantayani_db after the main schema.

USE bantayani_db;

-- Remove municipality if it was added previously (no longer used).
-- Requires MySQL 8.0.23+; on older MySQL, remove this line or run only if the column exists.
ALTER TABLE cooperative_pools DROP COLUMN IF EXISTS municipality;

-- Pool unit price for buyer display
ALTER TABLE cooperative_pools
  ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10,2) NULL DEFAULT NULL;

-- Add quantity per order line (for both inventory and pool items)
ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS quantity DECIMAL(10,2) NOT NULL DEFAULT 1;

-- Backfill: set quantity = 1 for existing rows (if column was just added)
UPDATE order_items SET quantity = 1 WHERE quantity IS NULL OR quantity = 0;
