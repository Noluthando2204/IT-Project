<?php

require_once '../database-config/connection.php';

$event_id = isset($_GET['event_id'])
    ? (int) $_GET['event_id']
    : 0;

$event = null;

if ($event_id > 0) {

    try {

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

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            ':event_id' => $event_id
        ]);

        $event = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        $event = null;

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= $event
            ? htmlspecialchars($event['title'])
            : 'Event Details'
        ?>
        - EventNest
    </title>

</head>

<body>

    <header>

        <h1>EventNest</h1>

        <nav>

            <a href="../index.php">
                Home
            </a>

            <a href="events.php">
                Events
            </a>

        </nav>

    </header>


    <main>

        <?php if ($event): ?>

            <article class="event-detail">

                <h2>
                    <?= htmlspecialchars($event['title']) ?>
                </h2>


                <p>

                    <strong>
                        Category:
                    </strong>

                    <?= htmlspecialchars(
                        $event['category_name'] ?? 'Uncategorized'
                    ) ?>

                </p>


                <p>

                    <strong>
                        Description:
                    </strong>

                </p>

                <p>
                    <?= nl2br(
                        htmlspecialchars($event['description'])
                    ) ?>
                </p>


                <p>

                    <strong>
                        Date:
                    </strong>

                    <?= htmlspecialchars(
                        $event['event_date']
                    ) ?>

                </p>


                <p>

                    <strong>
                        Location:
                    </strong>

                    <?= htmlspecialchars(
                        $event['location']
                    ) ?>

                </p>


                <p>

                    <strong>
                        Province:
                    </strong>

                    <?= htmlspecialchars(
                        $event['province']
                    ) ?>

                </p>


                <p>

                    <strong>
                        Format:
                    </strong>

                    <?= htmlspecialchars(
                        $event['format']
                    ) ?>

                </p>


                <p>

                    <strong>
                        Ticket Price:
                    </strong>

                    R<?= number_format(
                        (float) $event['ticket_price'],
                        2
                    ) ?>

                </p>


                <p>

                    <strong>
                        Capacity:
                    </strong>

                    <?= htmlspecialchars(
                        $event['capacity']
                    ) ?>

                </p>


                <p>

                    <strong>
                        Tickets Remaining:
                    </strong>

                    <?= htmlspecialchars(
                        $event['tickets_remaining']
                    ) ?>

                </p>


            

                <section class="booking-section">

                    <h3>
                        Book This Event
                    </h3>

                    <p>
                        Ready to attend this event?
                    </p>

                    <a
                        href="booking.php?event_id=<?= (int) $event['event_id'] ?>"
                    >
                        Book Now
                    </a>

                </section>


                <p>

                    <a href="events.php">
                        ← Back to Events
                    </a>

                </p>

            </article>


        <?php else: ?>

            <section class="event-not-found">

                <h2>
                    Event Not Found
                </h2>

                <p>
                    The event you are looking for could not be
                    found or is no longer available.
                </p>

                <a href="events.php">
                    ← Back to Events
                </a>

            </section>

        <?php endif; ?>

    </main>


    <footer>

        <p>
            &copy; 2026 EventNest
        </p>

    </footer>

</body>

</html>
