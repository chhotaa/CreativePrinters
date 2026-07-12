-- ===================================================
-- Migration: allow the same po_number to repeat with a different
-- item_code, while still blocking an exact (po_number, item_code)
-- duplicate.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Safe to run even if you have existing purchase_orders
-- rows, AS LONG AS no two existing rows already share the same
-- po_number + item_code (the ADD UNIQUE KEY step below will fail
-- with a duplicate-entry error if they do — see the check query
-- first).
-- ===================================================

-- 1. Sanity check: run this first. If it returns any rows, you have
--    existing duplicate (po_number, item_code) pairs that must be
--    resolved (e.g. delete/merge one of them) before continuing.
SELECT po_number, item_code, COUNT(*) AS cnt
FROM purchase_orders
GROUP BY po_number, item_code
HAVING COUNT(*) > 1;

-- 2. Drop the old single-column UNIQUE constraint on po_number.
--    MySQL names an inline `UNIQUE` column constraint after the
--    column by default; if this errors with "check that column/key
--    exists", run `SHOW INDEX FROM purchase_orders;` and drop
--    whichever index covers po_number alone.
ALTER TABLE purchase_orders DROP INDEX po_number;

-- 3. Add the new composite UNIQUE constraint.
ALTER TABLE purchase_orders ADD UNIQUE KEY unique_po_item (po_number, item_code);
