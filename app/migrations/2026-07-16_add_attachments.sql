-- ===================================================
-- Migration: file attachments for Deliveries and Job Cards (scanned DC/
-- Invoice copies, design proofs, etc). Files themselves are stored
-- OUTSIDE public_html (see app/includes/attachments.php) -- this table
-- only holds metadata, never a web-servable path.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Safe to run on a live database -- it only adds a table.
-- ===================================================

CREATE TABLE IF NOT EXISTS attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_type ENUM('delivery','job_card') NOT NULL,
  record_id INT NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT NOT NULL,
  uploaded_by INT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
