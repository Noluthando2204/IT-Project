<?php

declare(strict_types=1);

$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$sslCa = getenv('DB_SSL_CA') ?: '/var/www/html/ca.pem';

if (is_file($sslCa)) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
}

try {
    if (!$host || !$dbname || !$username) { throw new PDOException('Database environment is incomplete.'); }
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        $options
    );
    // Match the application's South African event times and cancellation window.
    $pdo->exec("SET time_zone = '+02:00'");
} catch (PDOException $exception) {
    error_log('EventNest database connection failed: ' . $exception->getCode());
    http_response_code(503);
    exit('EventNest is temporarily unavailable.');
}
