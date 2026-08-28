<?php

// Connect to the EventNest database.
require_once '../database-config/connection.php';

// Get the search keyword from the URL.
$search = trim($_GET['q'] ?? '');

// Get the selected province from the URL.
$province = trim($_GET['province'] ?? '');

// Get the selected event format from the URL.
$format = trim($_GET['format'] ?? '');

// Store the search results in this array.
$events = [];

// Store any database error message.
$errorMessage = '';

try {

    // Start with the conditions that must always apply.
    // Only approved events should appear on the public search page.
    $conditions = [
        "e.status = 'approved'"
    ];

    // Store values that will safely be passed into the SQL query.
    $parameters = [];

    /*
     * Search by event-related information and organiser name.
     *
     * The database structure provides:
     * - Event title
     * - Event description
     * - Event location
     * - Organiser full name
     * - Category name
     */
    if ($search !== '') {

        $conditions[] = "(
            e.title LIKE :search
            OR e.description LIKE :search
            OR e.location LIKE :search
            OR u.full_name LIKE :search
            OR c.category_name LIKE :search
        )";

        $parameters[':search'] = '%' . $search . '%';
    }

    // Filter the results by province when a province was selected.
    if ($province !== '') {

        $conditions[] = "e.province = :province";

        $parameters[':province'] = $province;
    }

    // Filter the results by event format when a format was selected.
    if ($format !== '') {

        $conditions[] = "e.format = :format";

        $parameters[':format'] = $format;
    }

    /*
     * Build the final SQL query.
     *
     * EVENTS is connected to:
     * - USERS through organiser_id
     * - CATEGORIES through category_id
     */
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
            u.full_name AS organiser_name,
            c.category_name
        FROM EVENTS e
        LEFT JOIN USERS u
            ON e.organiser_id = u.user_id
        LEFT JOIN CATEGORIES c
            ON e.category_id = c.category_id
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY e.event_date ASC
    ";

    // Prepare the SQL statement.
    $stmt = $conn->prepare($sql);

    // Execute the query using the safely bound parameters.
    $stmt->execute($parameters);

    // Retrieve all matching events.
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    // Keep the page working if the database query fails.
    $events = [];

    // Show a simple user-friendly error message.
    $errorMessage = 'Unable to load search results at this time.';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Defines the character encoding used by the webpage. -->
    <meta charset="UTF-8">

    <!-- Makes the page responsive on different screen sizes. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Page title displayed in the browser tab. -->
    <title>Search Results - EventNest</title>

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

            <!-- Link back to the events page. -->
            <a href="events.php">
                Events
            </a>

        </nav>

    </header>


    <!-- Main search-results content. -->
    <main>

        <h2>Search Results</h2>

        <!--
            Display the search term when one was entered.
            htmlspecialchars prevents user input from being
            interpreted as HTML.
        -->
        <?php if ($search !== ''): ?>

            <p>
                Search results for:
                <strong>
                    <?= htmlspecialchars($search) ?>
                </strong>
            </p>

        <?php endif; ?>


        <!-- Display an error if the database could not be accessed. -->
        <?php if ($errorMessage !== ''): ?>

            <p>
                <?= htmlspecialchars($errorMessage) ?>
            </p>

        <?php elseif (count($events) > 0): ?>


            <!-- Section containing matching events. -->
            <section class="event-list">

                <h3>Events</h3>


                <!--
                    Display every matching event using the
                    same basic card structure as events.php.
                -->
                <?php foreach ($events as $event): ?>

                    <div class="event-card">

                        <!-- Event title. -->
                        <h4>
                            <?= htmlspecialchars(
                                $event['title'] ?? 'Untitled Event'
                            ) ?>
                        </h4>


                        <!-- Event organiser. -->
                        <p>

                            <strong>
                                Organiser:
                            </strong>

                            <?= htmlspecialchars(
                                $event['organiser_name']
                                ?? 'Organiser not available'
                            ) ?>

                        </p>


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
                        <a href="event-detail.php?event_id=<?= (int) $event['event_id'] ?>">
                            View Event
                        </a>

                    </div>

                <?php endforeach; ?>

            </section>


        <?php else: ?>


            <!--
                Displayed when the search/filter combination
                does not produce any approved events.
            -->
            <section class="no-results">

                <h3>No Results Found</h3>

                <p>
                    No approved events match your search or selected filters.
                </p>

                <p>
                    <a href="events.php">
                        Back to Events
                    </a>
                </p>

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
