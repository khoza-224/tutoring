<?php
// Database connection using PDO (prepared statements = SQL injection protection)
const DB_HOST = 'localhost';
const DB_NAME = 'tutoring_system';
const DB_USER = 'root';      // XAMPP default. See Part E for a safer restricted account.
const DB_PASS = 'mysql';          // XAMPP default: empty password

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    exit('Database connection failed.');
}