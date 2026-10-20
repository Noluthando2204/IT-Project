<?php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');

// Initialize mysqli without immediately connecting
$conn = mysqli_init();

// Set SSL certificate (Aiven requires this)
// The path is the absolute path inside the Render Docker container
mysqli_ssl_set($conn, NULL, NULL, '/var/www/html/ca.pem', NULL, NULL);

try {
    // Connect using SSL
    $conn->real_connect($host, $username, $password, $dbname, (int)$port);
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
