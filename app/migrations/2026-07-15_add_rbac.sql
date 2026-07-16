-- ===================================================
-- Migration: replace the binary admin/user role with a full RBAC system
-- (Super Admin, Owner, Accountant, Sales, Delivery), with per-role,
-- per-module access levels (None/View/Edit) configurable by Super Admin
-- via the new Roles & Permissions page.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Existing 'admin' accounts become Super Admin; existing
-- 'user' accounts become Sales (reassign real roles afterward via the
-- Users page).
-- ===================================================

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  is_system TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT NOT NULL,
  module_key VARCHAR(50) NOT NULL,
  access_level ENUM('none','view','edit') NOT NULL DEFAULT 'none',
  PRIMARY KEY (role_id, module_key),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO roles (name, is_system) VALUES
  ('Super Admin', 1),
  ('Owner', 0),
  ('Accountant', 0),
  ('Sales', 0),
  ('Delivery', 0);

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

ALTER TABLE users ADD COLUMN role_id INT NULL AFTER role;
UPDATE users u JOIN roles r ON r.name = 'Super Admin' SET u.role_id = r.id WHERE u.role = 'admin';
UPDATE users u JOIN roles r ON r.name = 'Sales' SET u.role_id = r.id WHERE u.role = 'user';
ALTER TABLE users MODIFY role_id INT NOT NULL;
ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES roles(id);
ALTER TABLE users DROP COLUMN role;
