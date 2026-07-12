-- ===================================================
-- Stock & Delivery Manager - Database Schema
-- Import this via hPanel > Databases > phpMyAdmin
-- (select your database, click "Import", upload this file)
-- ===================================================

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  email VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_name VARCHAR(150) NOT NULL UNIQUE,
  quantity INT NOT NULL DEFAULT 0,
  reorder_level INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A po_number can repeat across multiple item codes (e.g. one customer PO
-- covering several products), but the same po_number + item_code pair
-- can't be entered twice.
CREATE TABLE IF NOT EXISTS purchase_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_number VARCHAR(100) NOT NULL,
  po_date DATE NULL,
  customer_name VARCHAR(150) NOT NULL,
  item_code VARCHAR(100),
  description VARCHAR(255),
  total_quantity INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_po_item (po_number, item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A single PO can have MANY delivery due dates (split/batch deliveries)
CREATE TABLE IF NOT EXISTS deliveries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_id INT NOT NULL,
  due_date DATE NOT NULL,
  quantity INT NOT NULL,
  status ENUM('Pending','Shipped','Delivered') NOT NULL DEFAULT 'Pending',
  reminder_sent ENUM('No','Yes') NOT NULL DEFAULT 'No',
  FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Internal restock orders: buying stock for our own inventory (distinct
-- from purchase_orders, which are customer orders). Workflow: admin
-- creates (Pending) -> a "user" role marks it Purchased once bought ->
-- admin gives final Confirmed (adding received_quantity into stock), or
-- can Cancel a Pending order, or reject a Purchased order back to Pending.
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
