<?php

// Connect to the EventNest database.
require_once '../database-config/connection.php';

// Store all approved events in this array.
$events = [];

try {

    // Retrieve approved events and their category names.
    // Events are displayed from the earliest date onwards.
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

    // Prepare the database query.
    $stmt = $conn->prepare($sql);

    // Execute the query.
    $stmt->execute();

    // Retrieve the results as associative arrays.
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    // If the database query fails, display an empty event list.
    $events = [];

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Defines the character encoding used by the webpage. -->
    <meta charset="UTF-8">

    <!-- Makes the page responsive on different screen sizes. -->
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <!-- Page title displayed in the browser tab. -->
    <title>Events - EventNest</title>

</head>

<body>

    <!-- Main EventNest website header. -->
    <header>

        <h1>EventNest</h1>

        <!-- Main website navigation. -->
        <nav>

            <!-- Link back to the homepage. -->
            <a href="../index.php">
                Home
            </a>

            <!-- Current events page. -->
            <a href="events.php">
                Events
            </a>

        </nav>

    </header>


    <!-- Main content of the events page. -->
    <main>

        <h2>Browse Events</h2>

        <p>
            Explore career, professional development and
            networking events available on EventNest.
        </p>


        <!-- Event search and filter section. -->
        <section class="event-filters">

            <h3>Find an Event</h3>

            <!--
                The selected search and filter values are sent
                to search-results.php using the GET method.
            -->
            <form
                action="search-results.php"
                method="GET"
            >

                <!-- Search by event name or keyword. -->
                <label for="search">
                    Search:
                </label>

                <input
                    type="text"
                    id="search"
                    name="q"
                    placeholder="Search for an event"
                >


                <!-- Filter events by South African province. -->
                <label for="province">
                    Province:
                </label>

                <select
                    id="province"
                    name="province"
                >

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


                <!-- Filter events by format. -->
                <label for="format">
                    Event Format:
                </label>

                <select
                    id="format"
                    name="format"
                >

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


                <!-- Submit the selected search and filters. -->
                <button type="submit">
                    Search Events
                </button>

            </form>

        </section>


        <!-- Section containing the available approved events. -->
        <section class="event-list">

            <h3>Available Events</h3>


            <?php if (count($events) > 0): ?>

                <!-- Display each approved event as an event card. -->
                <?php foreach ($events as $event): ?>

                    <div class="event-card">

                        <!-- Event title. -->
                        <h4>
                            <?= htmlspecialchars(
                                $event['title'] ?? 'Untitled Event'
                            ) ?>
                        </h4>


                        <!-- Event category. -->
                        <p>

                            <strong>
                                Category:
                            </strong>

                            <?= htmlspecialchars(
                                $event['category_name']
                                ?? 'Uncategorized'
                            ) ?>

                        </p>


                        <!-- Event date. -->
                        <p>

                            <strong>
                                Date:
                            </strong>

                            <?= htmlspecialchars(
                                $event['event_date']
                                ?? 'Date not available'
                            ) ?>

                        </p>


                        <!-- Event location. -->
                        <p>

                            <strong>
                                Location:
                            </strong>

                            <?= htmlspecialchars(
                                $event['location']
                                ?? 'Location not available'
                            ) ?>

                        </p>


                        <!-- Event province. -->
                        <p>

                            <strong>
                                Province:
                            </strong>

                            <?= htmlspecialchars(
                                $event['province']
                                ?? 'Province not available'
                            ) ?>

                        </p>


                        <!-- Event format. -->
                        <p>

                            <strong>
                                Format:
                            </strong>

                            <span class="format-badge">

                                <?= htmlspecialchars(
                                    $event['format']
                                    ?? 'Format not available'
                                ) ?>

                            </span>

                        </p>


                        <!-- Event ticket price. -->
                        <p>

                            <strong>
                                Ticket Price:
                            </strong>

                            R<?= number_format(
                                (float) (
                                    $event['ticket_price'] ?? 0
                                ),
                                2
                            ) ?>

                        </p>


                        <!-- Link to the full event details. -->
                        <a
                            href="event-detail.php?event_id=<?= (int) $event['event_id'] ?>"
                        >
                            View Event
                        </a>

                    </div>

                <?php endforeach; ?>


            <?php else: ?>

                <!-- Message displayed when no approved events exist. -->
                <p>
                    No approved events are currently available.
                </p>

            <?php endif; ?>

        </section>

    </main>


    <!-- Website footer. -->
    <footer>

        <p>
            &copy; 2026 EventNest
        </p>

    </footer>

</body>

</html>
