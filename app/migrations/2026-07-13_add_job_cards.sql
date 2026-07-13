-- ===================================================
-- Migration: add the "Job Cards" feature (digital production job card,
-- standalone - no link to purchase_orders, matching the paper form).
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Safe to run on a live database -- it only adds a new
-- table, no existing tables are touched.
-- ===================================================

CREATE TABLE IF NOT EXISTS job_cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_date DATE NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  design_name VARCHAR(150),
  board_name_gsm VARCHAR(100),
  board_size VARCHAR(100),
  cutting_size VARCHAR(100),
  board_quantity VARCHAR(50),
  copies VARCHAR(50),
  colour VARCHAR(255),
  lamination_varnish VARCHAR(150),
  order_type ENUM('Sample','Bulk Production','Repeat Order') NOT NULL DEFAULT 'Bulk Production',
  plate_type ENUM('New','Old') NOT NULL DEFAULT 'Old',
  die_punching ENUM('New','Old') NULL,
  pasting_perforation TINYINT(1) NOT NULL DEFAULT 0,
  pasting_double_board TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
