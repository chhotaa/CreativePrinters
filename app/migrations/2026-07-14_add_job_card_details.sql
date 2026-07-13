-- ===================================================
-- Migration: add a "details" field to job_cards, for the free-text
-- DETAILS section at the bottom of the paper form (previously left
-- as blank print space, now a real fillable field).
--
-- Run this ONCE via hPanel > phpMyAdmin > SQL tab on your existing
-- database. Safe to run on a live database -- it only adds a column.
-- ===================================================

ALTER TABLE job_cards ADD COLUMN details TEXT NULL AFTER pasting_double_board;
