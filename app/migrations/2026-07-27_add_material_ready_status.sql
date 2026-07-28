-- ===================================================
-- Migration: add 'Material Ready' as a new deliveries.status value,
-- sitting between 'Pending' and 'Shipped' in the workflow.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Safe to run on a live database -- it only widens the
-- allowed status values; no rows are modified.
-- ===================================================

-- MySQL 8.0+ auto-names CHECK constraints (typically deliveries_chk_1).
-- If your DB uses a different name, adjust the DROP line accordingly --
-- SHOW CREATE TABLE deliveries; will reveal the actual constraint name.
ALTER TABLE deliveries
  DROP CONSTRAINT deliveries_chk_1;

ALTER TABLE deliveries
  ADD CONSTRAINT deliveries_chk_1
  CHECK (status IN ('Pending','Material Ready','Shipped','Delivered'));
