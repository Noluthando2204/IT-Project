<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared-components/config.php';

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
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "EventNest database connection failed. Verify DB environment settings and connectivity.\n");
        exit(1);
    }
    http_response_code(503);
    exit('EventNest is temporarily unavailable.');
}

// Self-healing upgrade: add the image columns on first start if the database predates them.
// Does nothing once the columns exist, and never stops the site if it is not allowed to alter tables.
try {
    $missing = $pdo->query(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'ImagePath'
            AND TABLE_NAME IN ('CATEGORIES', 'EVENTS')"
    )->fetchAll(PDO::FETCH_COLUMN);
    $have = array_map('strtoupper', $missing);
    if (!in_array('CATEGORIES', $have, true)) {
        $pdo->exec('ALTER TABLE CATEGORIES ADD COLUMN ImagePath VARCHAR(255) NULL AFTER Description');
    }
    if (!in_array('EVENTS', $have, true)) {
        $pdo->exec('ALTER TABLE EVENTS ADD COLUMN ImagePath VARCHAR(255) NULL AFTER Province');
    }
} catch (Throwable $exception) {
    error_log('EventNest image column upgrade skipped: ' . get_class($exception));
}
