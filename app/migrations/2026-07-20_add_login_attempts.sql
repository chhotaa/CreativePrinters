-- ===================================================
-- Migration: login rate limiting.
--
-- Records every login attempt (success or failure). login.php refuses
-- to check the password once a username has hit 5 failed attempts
-- inside the last 15 minutes; the lock lifts automatically once the
-- 15-minute window passes with no new failures.
--
-- ip_address is stored for forensics only — the rate limit is keyed on
-- username. This deliberately matches the common "guess the admin
-- password" threat; per-IP limiting protects nothing when an attacker
-- rotates IPs.
--
-- The table grows unbounded; a monthly manual DELETE of rows older
-- than 30 days is a fine cleanup pattern, but not required for the
-- rate limit to work.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab.
-- ===================================================

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  ip_address VARCHAR(45) NULL,
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  success BOOLEAN NOT NULL DEFAULT 0,
  INDEX idx_login_attempts_username_time (username, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
