-- ===================================================
-- Migration: add a phone number to users, so the SMS/WhatsApp reminder
-- layer has somewhere to send to. Optional -- reminders simply skip
-- anyone without one on file.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Safe to run on a live database -- it only adds a column.
-- ===================================================

ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email;
