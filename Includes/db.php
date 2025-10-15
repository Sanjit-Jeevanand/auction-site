<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';       // You can try 'localhost' if needed
$db   = 'auction_db';
$user = 'auction_user';
$pass = '12345678';         // Your password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Optional debug line — remove later:
    // echo "✅ Database connection successful!";
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}