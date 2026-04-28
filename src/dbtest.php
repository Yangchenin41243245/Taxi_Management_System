<?php
$host     = 'db-db';
$user     = 'mymy';
$password = 'myPassword';
$database = 'my-db';
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$database;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 開啟錯誤異常拋出
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // 設定預設回傳陣列格式
    PDO::ATTR_EMULATE_PREPARES   => false,                  // 停用模擬預處理
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
    echo "✅ 成功透過 PDO 連線至 MariaDB！";
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>