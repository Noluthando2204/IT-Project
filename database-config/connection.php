<?php
/*
|--------------------------------------------------------------------------
| PDO DATABASE CONNECTION
|--------------------------------------------------------------------------
| This file connects the website to the MySQL database using PDO.
| Other backend files will include this file whenever database access
| is needed.
|--------------------------------------------------------------------------
*/

$host = "localhost";
$dbname = "event_platform";
$username = "root";
$password = "";

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname",
        $username,
        $password
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Database connection failed.");
}
?>
