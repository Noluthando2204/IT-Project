<?php

require_once __DIR__ . '/config/db.php';

$stmt = $pdo->query(
    "SELECT * FROM events ORDER BY event_date ASC"
);

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<h1>Discover Events</h1>

<?php foreach ($events as $event): ?>

    <div class="event-card">

        <h2>
            <?= htmlspecialchars($event["title"]) ?>
        </h2>

        <p>
            <?= htmlspecialchars($event["description"]) ?>
        </p>

        <p>
            Location:
            <?= htmlspecialchars($event["location"]) ?>
        </p>

        <p>
            Date:
            <?= htmlspecialchars($event["event_date"]) ?>
        </p>

    </div>

<?php endforeach; ?>