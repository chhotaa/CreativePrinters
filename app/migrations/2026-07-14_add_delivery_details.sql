-- ===================================================
-- Migration: add DC Number, Invoice Number, DC Date, and Bill Date to
-- deliveries. These are collected when a delivery is marked "Delivered"
-- and shown via a details popup in the Delivery Schedule listing.
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Safe to run on a live database -- it only adds columns.
-- ===================================================

ALTER TABLE deliveries
  ADD COLUMN dc_number VARCHAR(100) NULL AFTER status,
  ADD COLUMN invoice_number VARCHAR(100) NULL AFTER dc_number,
  ADD COLUMN dc_date DATE NULL AFTER invoice_number,
  ADD COLUMN bill_date DATE NULL AFTER dc_date;
