<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>EventNest</title>

    <link rel="stylesheet" href="/EventNest/css/main.css">
    <link rel="stylesheet" href="/EventNest/css/responsive.css">

</head>

<body>

<header class="navbar">

    <h1>EventNest</h1>

    <nav>

        <a href="/EventNest/index.php">Home</a>
        <a href="/EventNest/events.php">Events</a>

        <?php if (isset($_SESSION['user_id'])): ?>

            <a href="/EventNest/my-bookings.php">
                My Bookings
            </a>

            <a href="/EventNest/logout.php">
                Logout
            </a>

        <?php else: ?>

            <a href="/EventNest/login.php">
                Login
            </a>

            <a href="/EventNest/register.php">
                Register
            </a>

        <?php endif; ?>

    </nav>

</header>