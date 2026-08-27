<?php

// Connect to the EventNest database.
require_once '../database-config/connection.php';

// Get the event ID from the URL.
// If no valid ID is provided, use 0.
$event_id = isset($_GET['event_id'])
    ? (int) $_GET['event_id']
    : 0;

// Store the selected event here.
$event = null;

// Only query the database when a valid event ID is provided.
if ($event_id > 0) {

    try {

        // Retrieve the selected approved event and its category.
        $sql = "
            SELECT
                e.event_id,
                e.title,
                e.description,
                e.location,
                e.province,
                e.event_date,
                e.ticket_price,
                e.capacity,
                e.tickets_remaining,
                e.format,
                e.status,
                c.category_name
            FROM EVENTS e
            LEFT JOIN CATEGORIES c
                ON e.category_id = c.category_id
            WHERE e.event_id = :event_id
              AND e.status = 'approved'
            LIMIT 1
        ";

        // Prepare the database query.
        $stmt = $conn->prepare($sql);

        // Safely provide the event ID to the prepared statement.
        $stmt->execute([
            ':event_id' => $event_id
        ]);

        // Retrieve the event as an associative array.
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        // If the database query fails, treat the event as unavailable.
        $event = null;

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Defines the character encoding for the webpage. -->
    <meta charset="UTF-8">

    <!-- Makes the page responsive on different screen sizes. -->
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <!-- Displays the event title in the browser tab when available. -->
    <title>
        <?= htmlspecialchars(
            $event['title'] ?? 'Event Details'
        ) ?>
        - EventNest
    </title>

</head>

<body>

    <!-- Main EventNest website header. -->
    <header>

        <h1>EventNest</h1>

        <!-- Website navigation links. -->
        <nav>

            <!-- Link back to the homepage. -->
            <a href="../index.php">
                Home
            </a>

            <!-- Link back to the event listing page. -->
            <a href="events.php">
                Events
            </a>

        </nav>

    </header>


    <!-- Main content area. -->
    <main>

        <?php if ($event): ?>

            <!-- Displays the details of the selected event. -->
            <article class="event-detail">

                <h2>
                    <?= htmlspecialchars(
                        $event['title'] ?? 'Untitled Event'
                    ) ?>
                </h2>


                <!-- Event category. -->
                <p>

                    <strong>
                        Category:
                    </strong>

                    <?= htmlspecialchars(
                        $event['category_name'] ?? 'Uncategorized'
                    ) ?>

                </p>


                <!-- Event description. -->
                <p>

                    <strong>
                        Description:
                    </strong>

                </p>

                <p>
                    <?= nl2br(
                        htmlspecialchars(
                            $event['description'] ?? ''
                        )
                    ) ?>
                </p>


                <!-- Event date. -->
                <p>

                    <strong>
                        Date:
                    </strong>

                    <?= htmlspecialchars(
                        $event['event_date'] ?? 'Date not available'
                    ) ?>

                </p>


                <!-- Event location. -->
                <p>

                    <strong>
                        Location:
                    </strong>

                    <?= htmlspecialchars(
                        $event['location'] ?? 'Location not available'
                    ) ?>

                </p>


                <!-- Event province. -->
                <p>

                    <strong>
                        Province:
                    </strong>

                    <?= htmlspecialchars(
                        $event['province'] ?? 'Province not available'
                    ) ?>

                </p>


                <!-- Event format. -->
                <p>

                    <strong>
                        Format:
                    </strong>

                    <?= htmlspecialchars(
                        $event['format'] ?? 'Format not available'
                    ) ?>

                </p>


                <!-- Event ticket price. -->
                <p>

                    <strong>
                        Ticket Price:
                    </strong>

                    R<?= number_format(
                        (float) ($event['ticket_price'] ?? 0),
                        2
                    ) ?>

                </p>


                <!-- Maximum event capacity. -->
                <p>

                    <strong>
                        Capacity:
                    </strong>

                    <?= htmlspecialchars(
                        $event['capacity'] ?? 'Not specified'
                    ) ?>

                </p>


                <!-- Number of tickets still available. -->
                <p>

                    <strong>
                        Tickets Remaining:
                    </strong>

                    <?= htmlspecialchars(
                        $event['tickets_remaining'] ?? 'Not specified'
                    ) ?>

                </p>


                <!-- Booking section for the selected event. -->
                <section class="booking-section">

                    <h3>
                        Book This Event
                    </h3>

                    <p>
                        Ready to attend this event?
                    </p>

                    <!-- Sends the selected event ID to the booking page. -->
                    <a
                        href="booking.php?event_id=<?= (int) $event['event_id'] ?>"
                    >
                        Book Now
                    </a>

                </section>


                <!-- Link back to the complete event listing. -->
                <p>

                    <a href="events.php">
                        ← Back to Events
                    </a>

                </p>

            </article>


        <?php else: ?>

            <!-- Message shown when the requested event cannot be found. -->
            <section class="event-not-found">

                <h2>
                    Event Not Found
                </h2>

                <p>
                    The event you are looking for could not be
                    found or is no longer available.
                </p>

                <!-- Return to the event listing page. -->
                <a href="events.php">
                    ← Back to Events
                </a>

            </section>

        <?php endif; ?>

    </main>


    <!-- Website footer. -->
    <footer>

        <p>
            &copy; 2026 EventNest
        </p>

    </footer>

</body>

</html>
