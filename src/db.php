<?php
$host     = 'db-db';
$user     = 'mymy';
$password = 'myPassword';
$database = 'my-db';
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$database;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    exit('資料庫連線失敗：' . $e->getMessage());
}
