-- ===================================================
-- Migration: link purchase_orders -> customers and
-- restock_orders -> suppliers.
--
-- Additive and safe:
--   * Adds nullable customer_id / supplier_id FK columns.
--   * Backfills them by matching the existing free-text
--     customer_name / supplier_name against the masters,
--     case-insensitive and trim-tolerant.
--   * Keeps the legacy text columns intact as a fallback
--     for any historical row that didn't match a master.
--
-- After this migration, new inserts (done via the app)
-- will always populate customer_id / supplier_id, auto-
-- creating the master row if it doesn't exist yet.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab.
-- ===================================================

-- 1. Add the FK columns (nullable).
ALTER TABLE purchase_orders
  ADD COLUMN customer_id INT NULL AFTER customer_name,
  ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

ALTER TABLE restock_orders
  ADD COLUMN supplier_id INT NULL AFTER supplier_name,
  ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;

-- 2. Backfill: link to existing master records where the name matches
--    (case-insensitive, trim-tolerant). Unmatched rows stay NULL and
--    the app will fall back to the legacy text column when displaying.
UPDATE purchase_orders po
JOIN customers c ON LOWER(TRIM(c.name)) = LOWER(TRIM(po.customer_name))
SET po.customer_id = c.id
WHERE po.customer_id IS NULL;

UPDATE restock_orders ro
JOIN suppliers s ON LOWER(TRIM(s.name)) = LOWER(TRIM(ro.supplier_name))
SET ro.supplier_id = s.id
WHERE ro.supplier_id IS NULL;

-- 3. Helpful lookup indexes.
CREATE INDEX idx_purchase_orders_customer_id ON purchase_orders (customer_id);
CREATE INDEX idx_restock_orders_supplier_id  ON restock_orders  (supplier_id);
