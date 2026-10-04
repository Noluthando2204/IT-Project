<?php

require_once __DIR__ . '/config/db.php';

$career = trim($_GET['career'] ?? '');
$location = trim($_GET['location'] ?? '');

$sql = 'SELECT * FROM events WHERE 1=1';

$params = [];

if ($career !== '') {

    $sql .= ' AND career = ?';

    $params[] = $career;
}

if ($location !== '') {

    $sql .= ' AND location = ?';

    $params[] = $location;
}

$sql .= ' ORDER BY event_date ASC';

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$events = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';

?>

<main class="container">

    <h2>Find Events</h2>

    <form method="GET" class="filter-form">

        <div class="form-group">

            <label for="career">
                Career
            </label>

            <input
                type="text"
                id="career"
                name="career"
                value="<?php echo htmlspecialchars($career); ?>"
            >

        </div>

        <div class="form-group">

            <label for="location">
                Location
            </label>

            <input
                type="text"
                id="location"
                name="location"
                value="<?php echo htmlspecialchars($location); ?>"
            >

        </div>

        <button type="submit">
            Search
        </button>

    </form>


    <section class="event-grid">

        <?php if (count($events) === 0): ?>

            <p>No matching events were found.</p>

        <?php else: ?>

            <?php foreach ($events as $event): ?>

                <article class="event-card">

                    <h3>
                        <?php
                        echo htmlspecialchars(
                            $event['event_name']
                        );
                        ?>
                    </h3>

                    <p>
                        <?php
                        echo htmlspecialchars(
                            $event['description'] ?? ''
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Career:</strong>
                        <?php
                        echo htmlspecialchars(
                            $event['career'] ?? ''
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Location:</strong>
                        <?php
                        echo htmlspecialchars(
                            $event['location'] ?? ''
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Date:</strong>
                        <?php
                        echo htmlspecialchars(
                            $event['event_date'] ?? ''
                        );
                        ?>
                    </p>

                    <a
                        href="event-details.php?id=<?php echo (int) $event['id']; ?>"
                        class="button"
                    >
                        View Event
                    </a>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
