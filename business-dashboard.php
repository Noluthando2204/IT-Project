<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$organiser = auth_require_verified_organiser();
$pdo = auth_db();
function bd_h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$id = (int)$organiser['UserID'];
try {
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM EVENTS WHERE OrganiserID=:id"); $stmt->execute([':id'=>$id]); $eventCount=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare("SELECT COUNT(DISTINCT b.BookingID) FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID WHERE e.OrganiserID=:id AND b.Status IN ('pending','paid')"); $stmt->execute([':id'=>$id]); $bookingCount=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare("SELECT e.EventID,e.EventName,e.EventDate,e.Status,e.Venue,COUNT(b.BookingID) AS booking_count FROM EVENTS e LEFT JOIN BOOKINGS b ON b.EventID=e.EventID AND b.Status IN ('pending','paid') WHERE e.OrganiserID=:id GROUP BY e.EventID,e.EventName,e.EventDate,e.Status,e.Venue ORDER BY e.Created_at DESC LIMIT 10"); $stmt->execute([':id'=>$id]); $events=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM POSTS WHERE OrganiserID=:id"); $stmt->execute([':id'=>$id]); $postCount=(int)$stmt->fetchColumn();
} catch(PDOException $e) { error_log('EventNest business dashboard failed: '.$e->getMessage()); http_response_code(500); exit('Your dashboard could not be loaded.'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Organiser dashboard | EventNest</title><link rel="stylesheet" href="assets/css/style.css"></head><body><main class="container"><h1>Organiser dashboard</h1><p>Welcome, <?= bd_h(trim(($organiser['FirstName']??'').' '.($organiser['LastName']??''))) ?>.</p><section class="dashboard-cards"><article class="card"><h2>Your events</h2><p><?= $eventCount ?></p></article><article class="card"><h2>Active bookings</h2><p><?= $bookingCount ?></p></article><article class="card"><h2>Your posts</h2><p><?= $postCount ?></p></article></section><nav aria-label="Organiser actions"><a href="create-event.php">Create event</a> | <a href="create-post.php">Create post</a> | <a href="manage-reviews.php">Manage reviews</a> | <a href="block-user.php">Block attendee</a> | <a href="logout.php">Log out</a></nav><h2>Your recent events</h2><?php if(!$events): ?><p>You have not created any events yet.</p><?php else: ?><div class="table-responsive"><table><thead><tr><th>Event</th><th>Date</th><th>Venue</th><th>Status</th><th>Bookings</th><th>Actions</th></tr></thead><tbody><?php foreach($events as $e): ?><tr><td><?= bd_h($e['EventName']) ?></td><td><?= bd_h($e['EventDate']) ?></td><td><?= bd_h($e['Venue']??'—') ?></td><td><?= bd_h($e['Status']) ?></td><td><?= (int)$e['booking_count'] ?></td><td><a href="create-post.php?event_id=<?= (int)$e['EventID'] ?>">Post update</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></main></body></html>


