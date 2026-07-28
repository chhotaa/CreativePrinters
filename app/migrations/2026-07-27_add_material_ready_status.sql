-- ===================================================
-- Migration: add 'Material Ready' as a new deliveries.status value,
-- sitting between 'Pending' and 'Shipped' in the workflow.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Safe to run on a live database -- it only widens the
-- allowed status values; no rows are modified.
--
-- MariaDB names inline column-level CHECK constraints after the
-- column itself (here: `status`). MySQL 8 auto-names them
-- `<table>_chk_N`. To find the actual name on your DB, run:
--   SELECT CONSTRAINT_NAME, CHECK_CLAUSE
--   FROM information_schema.CHECK_CONSTRAINTS
--   WHERE TABLE_NAME = 'deliveries';
-- Then drop whichever row still shows the OLD clause without
-- 'Material Ready' and add the new one.
-- ===================================================

-- MariaDB (most cPanel/hPanel hosts). Drops the inline check named
-- after the column, then adds a widened named constraint.
ALTER TABLE deliveries
  DROP CONSTRAINT `status`;

ALTER TABLE deliveries
  ADD CONSTRAINT deliveries_status_chk
  CHECK (status IN ('Pending','Material Ready','Shipped','Delivered'));

-- If you are on MySQL 8 (not MariaDB) and the above DROP fails with
-- "constraint doesn't exist", the inline check is auto-named instead.
-- Use these two lines in place of the ones above:
--   ALTER TABLE deliveries DROP CONSTRAINT deliveries_chk_1;
--   ALTER TABLE deliveries ADD CONSTRAINT deliveries_status_chk
--     CHECK (status IN ('Pending','Material Ready','Shipped','Delivered'));
