-- ===================================================
-- Stock & Delivery Manager - Database Schema
-- Import this via hPanel > Databases > phpMyAdmin
-- (select your database, click "Import", upload this file)
-- ===================================================

-- Roles are fixed: Super Admin (is_system=1, always full access, hardcoded
-- in code, not part of the configurable matrix) plus four assignable
-- roles whose per-module access (None/View/Edit) Super Admin configures
-- via the Roles & Permissions page.
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  is_system BOOLEAN NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- module_key is one of: stock, purchase_orders, deliveries,
-- restock_orders, job_cards (fixed list maintained in code, not a table).
CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT NOT NULL,
  module_key VARCHAR(50) NOT NULL,
  access_level VARCHAR(20) NOT NULL DEFAULT 'none'
    CHECK (access_level IN ('none','view','edit')),
  PRIMARY KEY (role_id, module_key),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO roles (name, is_system) VALUES
  ('Super Admin', 1), ('Owner', 0), ('Accountant', 0), ('Sales', 0), ('Delivery', 0);

INSERT INTO role_permissions (role_id, module_key, access_level)
SELECT r.id, x.module_key, x.access_level FROM roles r
JOIN (
  SELECT 'Owner' AS role_name, 'stock' AS module_key, 'edit' AS access_level
  UNION ALL SELECT 'Owner', 'purchase_orders', 'edit'
  UNION ALL SELECT 'Owner', 'deliveries', 'edit'
  UNION ALL SELECT 'Owner', 'restock_orders', 'edit'
  UNION ALL SELECT 'Owner', 'job_cards', 'edit'
  UNION ALL SELECT 'Accountant', 'stock', 'view'
  UNION ALL SELECT 'Accountant', 'purchase_orders', 'view'
  UNION ALL SELECT 'Accountant', 'deliveries', 'view'
  UNION ALL SELECT 'Accountant', 'restock_orders', 'view'
  UNION ALL SELECT 'Accountant', 'job_cards', 'view'
  UNION ALL SELECT 'Sales', 'stock', 'view'
  UNION ALL SELECT 'Sales', 'purchase_orders', 'edit'
  UNION ALL SELECT 'Sales', 'deliveries', 'view'
  UNION ALL SELECT 'Sales', 'job_cards', 'edit'
  UNION ALL SELECT 'Delivery', 'purchase_orders', 'view'
  UNION ALL SELECT 'Delivery', 'deliveries', 'edit'
) x ON x.role_name = r.name;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  email VARCHAR(150),
  phone VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_name VARCHAR(150) NOT NULL UNIQUE,
  quantity INT NOT NULL DEFAULT 0,
  reorder_level INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit trail for stock quantity changes. See migration
-- 2026-07-20_add_stock_movements.sql for full column notes.
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

-- A po_number can repeat across multiple item codes (e.g. one customer PO
-- covering several products), but the same po_number + item_code pair
-- can't be entered twice.
CREATE TABLE IF NOT EXISTS purchase_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_number VARCHAR(100) NOT NULL,
  po_date DATE NULL,
  customer_name VARCHAR(150) NOT NULL,
  customer_id INT NULL,
  item_code VARCHAR(100),
  description VARCHAR(255),
  total_quantity INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_po_item (po_number, item_code),
  INDEX idx_purchase_orders_customer_id (customer_id)
  -- FK on customer_id added at end of file (customers table is defined later)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A single PO can have MANY delivery due dates (split/batch deliveries)
CREATE TABLE IF NOT EXISTS deliveries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_id INT NOT NULL,
  due_date DATE NOT NULL,
  quantity INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Pending'
    CHECK (status IN ('Pending','Shipped','Delivered')),
  dc_number VARCHAR(100) NULL,
  invoice_number VARCHAR(100) NULL,
  dc_date DATE NULL,
  bill_date DATE NULL,
  reminder_sent VARCHAR(3) NOT NULL DEFAULT 'No'
    CHECK (reminder_sent IN ('No','Yes')),
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
  supplier_id INT NULL,
  notes VARCHAR(255),
  status VARCHAR(20) NOT NULL DEFAULT 'Pending'
    CHECK (status IN ('Pending','Purchased','Confirmed','Cancelled')),
  received_quantity INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_restock_orders_supplier_id (supplier_id)
  -- FK on supplier_id added at end of file (suppliers table is defined later)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Production job cards - standalone (not linked to purchase_orders, matching
-- the paper form which has no PO/customer field). Any logged-in user (admin
-- or "user" role) can create one; id doubles as the printed Sl.No.
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
  order_type VARCHAR(20) NOT NULL DEFAULT 'Bulk Production'
    CHECK (order_type IN ('Sample','Bulk Production','Repeat Order')),
  plate_type VARCHAR(10) NOT NULL DEFAULT 'Old'
    CHECK (plate_type IN ('New','Old')),
  die_punching VARCHAR(10) NULL
    CHECK (die_punching IS NULL OR die_punching IN ('New','Old')),
  pasting_perforation BOOLEAN NOT NULL DEFAULT 0,
  pasting_double_board BOOLEAN NOT NULL DEFAULT 0,
  details TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit trail of mutating actions (create/update/delete/status-change)
-- plus login/logout. username/role_name are denormalized snapshots so
-- entries stay readable after a user is deleted or their role changes.
CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  username VARCHAR(50) NULL,
  role_name VARCHAR(50) NULL,
  action VARCHAR(100) NOT NULL,
  description VARCHAR(500) NOT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Self-service "forgot password" flow. Only a hash of the reset token is
-- stored, never the raw token.
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customer and Supplier master records. New lookups going forward only --
-- existing purchase_orders.customer_name / restock_orders.supplier_name
-- stay free text, unlinked.
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  contact_person VARCHAR(150),
  phone VARCHAR(20),
  email VARCHAR(150),
  address VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  contact_person VARCHAR(150),
  phone VARCHAR(20),
  email VARCHAR(150),
  address VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- File attachments for Deliveries and Job Cards. Files live outside
-- public_html (see app/includes/attachments.php) -- this table only
-- holds metadata, never a web-servable path.
CREATE TABLE IF NOT EXISTS attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  record_type VARCHAR(20) NOT NULL
    CHECK (record_type IN ('delivery','job_card')),
  record_id INT NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT NOT NULL,
  uploaded_by INT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cross-table FKs added last, once all referenced tables exist.
ALTER TABLE purchase_orders
  ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
ALTER TABLE restock_orders
  ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;
