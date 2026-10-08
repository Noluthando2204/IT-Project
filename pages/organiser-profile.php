<?php

declare(strict_types=1);

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/shared-components/functions.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } else {
        http_response_code(500);
        exit('EventNest is temporarily unavailable.');
    }
}

function organiser_profile_id(): int
{
    $value = $_GET['organiser_id'] ?? '';
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function organiser_profile_tags(?string $hashtags): array
{
    preg_match_all('/#[\p{L}\p{N}_]+/u', (string) $hashtags, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

$organiser_id = organiser_profile_id();
if ($organiser_id <= 0) {
    http_response_code(404);
    $page_title = 'Organiser not found';
    require_once __DIR__ . '/shared-components/header.php';
    echo '<main class="page-content"><div class="empty-state"><h1>Organiser not found</h1><p>The organiser link is invalid.</p><a class="btn btn-outline" href="events.php">Browse events</a></div></main>';
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT UserID, FirstName, LastName, VerificationStatus FROM USERS WHERE UserID = :id AND Role = 'organiser' AND VerificationStatus = 'verified' AND UserStatus = 'active' LIMIT 1");
$stmt->execute([':id' => $organiser_id]);
$organiser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$organiser) {
    http_response_code(404);
    $page_title = 'Organiser not found';
    require_once __DIR__ . '/shared-components/header.php';
    echo '<main class="page-content"><div class="empty-state"><h1>Organiser not found</h1><p>This public organiser profile is not available.</p><a class="btn btn-outline" href="events.php">Browse events</a></div></main>';
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$upcoming_stmt = $pdo->prepare(
    "SELECT e.EventID, e.EventName, e.EventDate, e.Format, e.Province, e.Venue,
            (SELECT MIN(t.Price) FROM TICKETS t WHERE t.EventID = e.EventID AND t.QuantityAvailable > 0) AS MinPrice
     FROM EVENTS e
     WHERE e.OrganiserID = :id AND e.Status = 'published' AND e.EventDate >= NOW()
     ORDER BY e.EventDate ASC, e.EventID ASC
     LIMIT 12"
);
$upcoming_stmt->execute([':id' => $organiser_id]);
$upcoming = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

$past_stmt = $pdo->prepare("SELECT EventID, EventName, EventDate, Format FROM EVENTS WHERE OrganiserID = :id AND Status = 'completed' ORDER BY EventDate DESC, EventID DESC LIMIT 12");
$past_stmt->execute([':id' => $organiser_id]);
$past = $past_stmt->fetchAll(PDO::FETCH_ASSOC);

$post_stmt = $pdo->prepare(
    "SELECT p.PostID, p.Content, p.Media, p.Media_type, p.Hashtags, p.PostDate,
            e.EventID, e.EventName
     FROM POSTS p
     INNER JOIN EVENTS e ON e.EventID = p.EventID
     WHERE p.OrganiserID = :id
       AND e.OrganiserID = p.OrganiserID
       AND e.Status IN ('published', 'completed')
     ORDER BY p.PostDate DESC, p.PostID DESC
     LIMIT 4"
);
$post_stmt->execute([':id' => $organiser_id]);
$posts = $post_stmt->fetchAll(PDO::FETCH_ASSOC);

$name = trim((string) $organiser['FirstName'] . ' ' . (string) $organiser['LastName']);
if ($name === '') {
    $name = 'Verified organiser';
}

$page_title = 'Events by ' . $name;
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content organiser-profile-page">
    <section class="page-heading">
        <h1>Events by <?= escape_html($name) ?></h1>
        <p><span class="status status-success">Verified organiser</span></p>
        <p>Public history assembled from this organiser's published/completed events and event-linked posts.</p>
    </section>

    <section aria-labelledby="upcoming-events-heading">
        <h2 id="upcoming-events-heading">Upcoming events</h2>
        <?php if (!$upcoming): ?>
            <div class="empty-state"><p>No upcoming published events are available.</p></div>
        <?php else: ?>
            <div class="event-grid">
                <?php foreach ($upcoming as $event): ?>
                    <?php $image = resolve_event_image($pdo, (int) $event['EventID']); ?>
                    <article class="event-card">
                        <img class="event-card__image" src="<?= escape_html(eventnest_url($image)) ?>" alt="Image for <?= escape_html($event['EventName']) ?>" loading="lazy">
                        <div class="event-card__content">
                            <h3><?= escape_html($event['EventName']) ?></h3>
                            <p><?= escape_html(format_datetime($event['EventDate'])) ?></p>
                            <p><?= escape_html(ucfirst($event['Format'])) ?> · <?= escape_html($event['Province'] ?: ($event['Venue'] ?: 'Location to be confirmed')) ?></p>
                            <p><strong><?= $event['MinPrice'] === null ? 'Tickets unavailable' : ((float) $event['MinPrice'] <= 0 ? 'Free' : 'From ' . escape_html(format_money($event['MinPrice']))) ?></strong></p>
                            <a class="btn btn-secondary" href="event-detail.php?event_id=<?= (int) $event['EventID'] ?>">View details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section aria-labelledby="latest-posts-heading">
        <div class="section-heading-row">
            <h2 id="latest-posts-heading">Latest posts and media</h2>
            <a class="btn btn-outline" href="organiser-posts.php?organiser_id=<?= $organiser_id ?>">View all posts and media</a>
        </div>

        <?php if (!$posts): ?>
            <div class="empty-state"><p>No public event-linked posts are available.</p></div>
        <?php else: ?>
            <div class="post-grid">
                <?php foreach ($posts as $post): ?>
                    <?php $safe_media = get_safe_public_media_path($post['Media']); ?>
                    <article class="post-card">
                        <?php if ($post['Media_type'] === 'image' && $safe_media): ?>
                            <img src="<?= escape_html(eventnest_url($safe_media)) ?>" alt="Image post for <?= escape_html($post['EventName']) ?>" loading="lazy">
                        <?php elseif ($post['Media_type'] === 'video' && $safe_media): ?>
                            <div class="media-placeholder">Video post</div>
                        <?php endif; ?>
                        <h3><?= $post['Media_type'] ? escape_html(ucfirst($post['Media_type']) . ' post') : 'Event update' ?></h3>
                        <?php if ($post['Content']): ?><p><?= escape_html(mb_strimwidth((string) $post['Content'], 0, 180, '…', 'UTF-8')) ?></p><?php endif; ?>
                        <p>Linked to <a href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>"><?= escape_html($post['EventName']) ?></a> · <?= escape_html(format_datetime($post['PostDate'])) ?></p>
                        <?php if ($post['Hashtags']): ?>
                            <p>
                                <?php foreach (organiser_profile_tags($post['Hashtags']) as $tag): ?>
                                    <a href="tag.php?<?= http_build_query(['tag' => ltrim($tag, '#')], '', '&', PHP_QUERY_RFC3986) ?>"><?= escape_html($tag) ?></a>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                        <a class="btn btn-secondary" href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">View post</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section aria-labelledby="past-events-heading">
        <h2 id="past-events-heading">Past events</h2>
        <?php if (!$past): ?>
            <div class="empty-state"><p>No completed events are available in this public history.</p></div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Event</th><th>Date</th><th>Format</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($past as $event): ?>
                        <tr>
                            <td><?= escape_html($event['EventName']) ?></td>
                            <td><?= escape_html(format_date($event['EventDate'])) ?></td>
                            <td><?= escape_html(ucfirst($event['Format'])) ?></td>
                            <td><a href="event-detail.php?event_id=<?= (int) $event['EventID'] ?>">View details</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>

