-- ===================================================
-- Migration: stock request approval workflow.
--
-- Requesters (any role with stock_requests:edit) raise multi-line
-- "cart" requests. Approval is hardcoded to Owner + Super Admin
-- roles in app/stock_requests.php; on approve, stock is atomically
-- reduced and rows are written into the existing stock_movements
-- table. On reject, review_notes captures why. Requesters can
-- soft-delete their own Pending requests with a reason (status
-- flips to 'Deleted by User', delete_reason stores the reason,
-- row is preserved for audit).
--
-- Optional link to a purchase_order or job_card gives context so
-- approvers can see *why* the material is being requested.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Safe to run on a live database -- it only creates two
-- new tables; nothing existing is touched.
-- ===================================================

CREATE TABLE IF NOT EXISTS stock_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  requested_by_user_id INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Pending',
  purpose VARCHAR(255) NULL,
  linked_po_id INT NULL,
  linked_job_card_id INT NULL,
  reviewed_by_user_id INT NULL,
  reviewed_at TIMESTAMP NULL,
  review_notes VARCHAR(500) NULL,
  delete_reason VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT stock_requests_status_chk
    CHECK (status IN ('Pending','Approved','Rejected','Deleted by User')),
  FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (linked_po_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
  FOREIGN KEY (linked_job_card_id) REFERENCES job_cards(id) ON DELETE SET NULL,
  INDEX idx_stock_requests_status (status),
  INDEX idx_stock_requests_requester (requested_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_request_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  stock_id INT NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  quantity INT NOT NULL,
  FOREIGN KEY (request_id) REFERENCES stock_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (stock_id) REFERENCES stock(id) ON DELETE RESTRICT,
  INDEX idx_stock_request_items_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
