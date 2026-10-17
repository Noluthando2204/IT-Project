<?php

$bookings = [
    [
        "event_name" => "Technology Career Expo",
        "event_date" => "25 September 2026",
        "status" => "Confirmed"
    ],
    [
        "event_name" => "Business Networking Event",
        "event_date" => "10 October 2026",
        "status" => "Attended"
    ],
    [
        "event_name" => "Music and Entertainment Conference",
        "event_date" => "20 October 2026",
        "status" => "Cancelled"
    ]
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Bookings - EventNest</title>

    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/responsive.css">

</head>

<body>

<header class="navbar">

    <h1>EventNest</h1>

    <nav>
        <a href="index.php">Home</a>
        <a href="events.php">Events</a>
        <a href="my-bookings.php">My Bookings</a>
    </nav>

</header>

<main class="container">

    <h2>My Bookings</h2>

    <?php foreach ($bookings as $booking): ?>

        <div class="booking-card">

            <h3>
                <?= htmlspecialchars($booking["event_name"]) ?>
            </h3>

            <p>
                <strong>Date:</strong>
                <?= htmlspecialchars($booking["event_date"]) ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?= htmlspecialchars($booking["status"]) ?>
            </p>

            <?php if ($booking["status"] === "Attended"): ?>

                <a
                    href="leave-review.php"
                    class="button"
                >
                    Leave Review
                </a>

            <?php elseif ($booking["status"] === "Cancelled"): ?>

                <p class="status-message">
                    This booking has been cancelled.
                </p>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>

</main>

</body>
</html>
