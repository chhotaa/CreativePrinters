-- ===================================================
-- Migration: add the "Restock Orders" feature (internal inventory
-- purchasing workflow, distinct from customer-facing purchase_orders).
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Safe to run on a live database -- it only adds a new
-- table, no existing tables are touched.
-- ===================================================

CREATE TABLE IF NOT EXISTS restock_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_name VARCHAR(150) NOT NULL,
  quantity INT NOT NULL,
  supplier_name VARCHAR(150) NOT NULL,
  notes VARCHAR(255),
  status ENUM('Pending','Purchased','Confirmed','Cancelled') NOT NULL DEFAULT 'Pending',
  received_quantity INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
