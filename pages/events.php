<?php

require_once '../database-config/connection.php';

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
            e.format,
            c.category_name
        FROM EVENTS e
        LEFT JOIN CATEGORIES c
            ON e.category_id = c.category_id
        WHERE e.status = 'approved'
        ORDER BY e.event_date ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $events = [];

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Events - EventNest</title>

</head>

<body>

    <header>

        <h1>EventNest</h1>

        <nav>

            <a href="../index.php">Home</a>

            <a href="events.php">Events</a>

        </nav>

    </header>


    <main>

        <h2>Browse Events</h2>

        <p>
            Explore career, professional development and
            networking events available on EventNest.
        </p>




        <section class="event-filters">

            <h3>Find an Event</h3>

            <form action="search-results.php" method="GET">

                <label for="search">
                    Search:
                </label>

                <input type="text" id="search" name="q" placeholder="Search for an event">


                <label for="province">
                    Province:
                </label>

                <select id="province" name="province">

                    <option value="">
                        All Provinces
                    </option>

                    <option value="Gauteng">
                        Gauteng
                    </option>

                    <option value="Mpumalanga">
                        Mpumalanga
                    </option>

                    <option value="Limpopo">
                        Limpopo
                    </option>

                    <option value="KwaZulu-Natal">
                        KwaZulu-Natal
                    </option>

                    <option value="Western Cape">
                        Western Cape
                    </option>

                    <option value="Eastern Cape">
                        Eastern Cape
                    </option>

                    <option value="Free State">
                        Free State
                    </option>

                    <option value="North West">
                        North West
                    </option>

                    <option value="Northern Cape">
                        Northern Cape
                    </option>

                </select>


                <label for="format">
                    Event Format:
                </label>

                <select id="format" name="format">

                    <option value="">
                        All Formats
                    </option>

                    <option value="in-person">
                        In-person
                    </option>

                    <option value="online">
                        Online
                    </option>

                    <option value="hybrid">
                        Hybrid
                    </option>

                </select>


                <button type="submit">
                    Search Events
                </button>

            </form>

        </section>


    

        <section class="event-list">

            <h3>Available Events</h3>


            <?php if (count($events) > 0): ?>

                <?php foreach ($events as $event): ?>

                

                    <div class="event-card">

                        <h4>
                            <?= htmlspecialchars($event['title']) ?>
                        </h4>


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

                            <span class="format-badge">

                                <?= htmlspecialchars(
                                    $event['format']
                                ) ?>

                            </span>

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


                        <a href="event-detail.php?event_id=<?= (int) $event['event_id'] ?>">
                            View Event
                        </a>

                    </div>

                <?php endforeach; ?>


            <?php else: ?>

                <p>
                    No approved events are currently available.
                </p>

            <?php endif; ?>

        </section>

    </main>


    <footer>

        <p>
            &copy; 2026 EventNest
        </p>

    </footer>

</body>

</html>
