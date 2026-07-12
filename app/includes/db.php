<?php
// ============================================================
// This file holds NO real credentials and is safe to commit.
// Real values live in db_credentials.php, created ONCE manually
// at your Hostinger account root (one level ABOVE public_html -
// the same folder that contains public_html itself). Keeping it
// there means Git deployment (which only manages public_html/)
// can never overwrite, delete, or reset it.
//
// See app/includes/db_credentials.example.php for the format to
// copy into that file.
// ============================================================
require __DIR__ . '/../../../db_credentials.php';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Please check db_credentials.php at your account root.');
}
