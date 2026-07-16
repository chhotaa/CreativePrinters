-- ===================================================
-- Migration: add an activity log recording who did what (create/update/
-- delete/status-change actions plus login/logout), viewable via the new
-- Activity Log page. Access to that page is configurable per role like
-- every other module, via Roles & Permissions.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Safe to run on a live database -- it only adds a table.
-- ===================================================

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
