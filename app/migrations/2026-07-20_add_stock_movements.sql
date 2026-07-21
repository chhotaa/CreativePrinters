-- ===================================================
-- Migration: stock movement audit trail.
--
-- Every change to stock.quantity from now on writes a row here,
-- so "why did this number change?" always has an answer.
--
-- Notes on the columns:
--   * stock_id is nullable — when a product is deleted from stock
--     the historical rows stay readable via product_name (snapshot).
--   * delta is signed: +N for stock coming in, -N for going out.
--   * quantity_after captures the resulting on-hand qty at the
--     time of the movement, so the timeline reads without having
--     to sum deltas.
--   * reason_code is a short machine label (fixed set below);
--     reason_text is optional free text from the user.
--   * source_type / source_id link back to the originating record
--     when the movement was driven by another table (e.g. a
--     restock_order confirmation).
--   * username is a snapshot so entries stay readable if the user
--     is later deleted or renamed (same pattern as activity_log).
--
-- reason_code values maintained in code (see includes/stock_movements.php):
--   restock_confirm  — restock order confirmed, adds received qty
--   manual_save      — quantity changed via Add/Update Stock form
--   stock_deleted    — product row deleted from stock
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab.
-- ===================================================

CREATE TABLE IF NOT EXISTS stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_id INT NULL,
  product_name VARCHAR(150) NOT NULL,
  delta INT NOT NULL,
  quantity_after INT NOT NULL,
  reason_code VARCHAR(50) NOT NULL,
  reason_text VARCHAR(255) NULL,
  source_type VARCHAR(50) NULL,
  source_id INT NULL,
  user_id INT NULL,
  username VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (stock_id) REFERENCES stock(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_stock_movements_stock_id (stock_id),
  INDEX idx_stock_movements_product_name (product_name),
  INDEX idx_stock_movements_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
