<?php
// ============================================================
// Fill these in with the values from hPanel > Databases > MySQL Databases
// (Database name, Database username, and the password you set)
// ============================================================
$DB_HOST = 'localhost';                 // Hostinger MySQL is almost always 'localhost'
$DB_NAME = 'u123456789_stockdb';        // <-- replace with your actual database name
$DB_USER = 'u123456789_stockuser';      // <-- replace with your actual database username
$DB_PASS = 'REPLACE_WITH_YOUR_PASSWORD';// <-- replace with your actual database password

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
    die('Database connection failed. Please check your credentials in includes/db.php.');
}
