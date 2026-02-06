<?php
// config/db.php

// Load environment configuration
require_once __DIR__ . '/env.php';

// Database configuration
// Uses environment variables from .env file or system environment
$host = Env::get('DB_HOST', 'localhost');
$db   = Env::get('DB_NAME', 'crm');
$user = Env::get('DB_USER', 'root');
$pass = Env::get('DB_PASS', '');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];


try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Check if the error is "Unknown database"
    if ($e->getCode() == 1049) {
        // Attempt to create the database if it doesn't exist
        try {
            $dsn_no_db = "mysql:host=$host;charset=$charset";
            $pdo_temp = new PDO($dsn_no_db, $user, $pass, $options);
            $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci");
            // Re-connect with the new database
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e2) {
            throw new \PDOException($e2->getMessage(), (int)$e2->getCode());
        }
    } else {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}

// Provide a getDb() function for all includes
if (!function_exists('getDb')) {
    function getDb() {
        global $pdo;
        return $pdo;
    }
}
