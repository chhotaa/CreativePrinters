-- ===================================================
-- Migration: portability cleanup
--
-- Removes MySQL-only column types so the schema can be moved to
-- Postgres / SQLite / MariaDB / SQL Server later with only minor,
-- mechanical tweaks (AUTO_INCREMENT, ENGINE=..., ON UPDATE).
--
-- Changes, all backwards-compatible (no PHP code changes needed):
--   1. Every ENUM('a','b',...) becomes VARCHAR(N) with a CHECK
--      constraint enforcing the same values. String values in the
--      column stay identical, so existing WHERE / UPDATE queries
--      that compare against 'Pending', 'Yes', 'Sample', etc. keep
--      working unchanged.
--   2. TINYINT(1) boolean columns become BOOLEAN. In MySQL/MariaDB
--      BOOLEAN is a synonym for TINYINT(1), so 0/1 values and any
--      PHP comparisons like `= 0` continue to work.
--
-- Note: CHECK constraints are enforced by MySQL 8.0.16+ and
-- MariaDB 10.2+. Older MySQL parses but ignores them, which is
-- still safe because the application layer already validates
-- allowed values before insert/update.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab.
-- ===================================================

-- roles.is_system
ALTER TABLE roles
  MODIFY is_system BOOLEAN NOT NULL DEFAULT 0;

-- role_permissions.access_level
ALTER TABLE role_permissions
  MODIFY access_level VARCHAR(20) NOT NULL DEFAULT 'none'
  CHECK (access_level IN ('none','view','edit'));

-- deliveries.status
ALTER TABLE deliveries
  MODIFY status VARCHAR(20) NOT NULL DEFAULT 'Pending'
  CHECK (status IN ('Pending','Shipped','Delivered'));

-- deliveries.reminder_sent
ALTER TABLE deliveries
  MODIFY reminder_sent VARCHAR(3) NOT NULL DEFAULT 'No'
  CHECK (reminder_sent IN ('No','Yes'));

-- restock_orders.status
ALTER TABLE restock_orders
  MODIFY status VARCHAR(20) NOT NULL DEFAULT 'Pending'
  CHECK (status IN ('Pending','Purchased','Confirmed','Cancelled'));

-- job_cards.order_type
ALTER TABLE job_cards
  MODIFY order_type VARCHAR(20) NOT NULL DEFAULT 'Bulk Production'
  CHECK (order_type IN ('Sample','Bulk Production','Repeat Order'));

-- job_cards.plate_type
ALTER TABLE job_cards
  MODIFY plate_type VARCHAR(10) NOT NULL DEFAULT 'Old'
  CHECK (plate_type IN ('New','Old'));

-- job_cards.die_punching (nullable)
ALTER TABLE job_cards
  MODIFY die_punching VARCHAR(10) NULL
  CHECK (die_punching IS NULL OR die_punching IN ('New','Old'));

-- job_cards.pasting_perforation
ALTER TABLE job_cards
  MODIFY pasting_perforation BOOLEAN NOT NULL DEFAULT 0;

-- job_cards.pasting_double_board
ALTER TABLE job_cards
  MODIFY pasting_double_board BOOLEAN NOT NULL DEFAULT 0;

-- attachments.record_type
ALTER TABLE attachments
  MODIFY record_type VARCHAR(20) NOT NULL
  CHECK (record_type IN ('delivery','job_card'));
