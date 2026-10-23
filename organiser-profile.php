<?php

declare(strict_types=1);

require_once __DIR__ . '/database-config/connection.php';
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

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function organiser_profile_positive_id(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : (int) $id;
}

function organiser_profile_tags(?string $hashtags): array
{
    preg_match_all('/#[\p{L}\p{N}_]{1,60}/u', (string) $hashtags, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

function organiser_profile_excerpt(?string $text, int $limit = 180): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $limit, '…', 'UTF-8');
    }

    return strlen($text) > $limit
        ? substr($text, 0, max(0, $limit - 3)) . '...'
        : $text;
}

function organiser_profile_render_not_found(string $message): never
{
    http_response_code(404);
    $page_title = 'Organiser not found';
    require __DIR__ . '/shared-components/header.php';
    ?>
    <main class="page-content organiser-profile-page">
        <div class="empty-state">
            <h1>Organiser not found</h1>
            <p><?= escape_html($message) ?></p>
            <a class="btn btn-outline" href="events.php">Browse events</a>
        </div>
    </main>
    <?php
    require __DIR__ . '/shared-components/footer.php';
    exit;
}

$organiser_id = organiser_profile_positive_id($_GET['organiser_id'] ?? null);
if ($organiser_id <= 0) {
    organiser_profile_render_not_found('The organiser link is invalid.');
}

$organiser_stmt = $pdo->prepare(
    "SELECT UserID, FirstName, LastName
       FROM USERS
      WHERE UserID = :organiser_id
        AND Role = 'organiser'
        AND VerificationStatus = 'verified'
        AND UserStatus = 'active'
      LIMIT 1"
);
$organiser_stmt->execute([':organiser_id' => $organiser_id]);
$organiser = $organiser_stmt->fetch();

if (!$organiser) {
    organiser_profile_render_not_found('This public organiser profile is not available.');
}

$upcoming_stmt = $pdo->prepare(
    "SELECT
        e.EventID,
        e.EventName,
        e.EventDate,
        e.Format,
        e.Province,
        e.Venue,
        e.Hashtags,
        (SELECT MIN(t.Price)
           FROM TICKETS t
          WHERE t.EventID = e.EventID
            AND t.QuantityAvailable > 0) AS MinPrice
     FROM EVENTS e
     WHERE e.OrganiserID = :organiser_id
       AND e.Status = 'published'
       AND e.EventDate >= NOW()
     ORDER BY e.EventDate ASC, e.EventID ASC
     LIMIT 12"
);
$upcoming_stmt->execute([':organiser_id' => $organiser_id]);
$upcoming_events = $upcoming_stmt->fetchAll();

$past_stmt = $pdo->prepare(
    "SELECT EventID, EventName, EventDate, Format, Province, Venue, Hashtags
       FROM EVENTS
      WHERE OrganiserID = :organiser_id
        AND Status = 'completed'
      ORDER BY EventDate DESC, EventID DESC
      LIMIT 12"
);
$past_stmt->execute([':organiser_id' => $organiser_id]);
$past_events = $past_stmt->fetchAll();

$post_stmt = $pdo->prepare(
    "SELECT
        p.PostID,
        p.Content,
        p.Media,
        p.Media_type,
        p.Hashtags,
        p.PostDate,
        e.EventID,
        e.EventName
     FROM POSTS p
     INNER JOIN EVENTS e
        ON e.EventID = p.EventID
       AND e.OrganiserID = p.OrganiserID
     WHERE p.OrganiserID = :organiser_id
       AND e.Status IN ('published','completed')
     ORDER BY p.PostDate DESC, p.PostID DESC
     LIMIT 4"
);
$post_stmt->execute([':organiser_id' => $organiser_id]);
$recent_posts = $post_stmt->fetchAll();

$organiser_name = trim(
    (string) $organiser['FirstName'] . ' ' . (string) $organiser['LastName']
);
if ($organiser_name === '') {
    $organiser_name = 'Verified organiser';
}

$page_title = 'Events by ' . $organiser_name;
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content organiser-profile-page">
    <section class="page-heading">
        <h1>Events by <?= escape_html($organiser_name) ?></h1>
        <p><span class="status status-success">Verified organiser</span></p>
        <p>Published events, completed event history, and public event-linked posts.</p>
    </section>

    <section aria-labelledby="upcoming-events-heading">
        <h2 id="upcoming-events-heading">Upcoming events</h2>

        <?php if (!$upcoming_events): ?>
            <div class="empty-state">
                <p>No upcoming published events are available.</p>
            </div>
        <?php else: ?>
            <div class="event-grid">
                <?php foreach ($upcoming_events as $event): ?>
                    <?php $image = resolve_event_image($pdo, (int) $event['EventID']); ?>
                    <article class="event-card">
                        <img
                            class="event-card__image"
                            src="<?= escape_html(eventnest_url($image)) ?>"
                            alt="Image for <?= escape_html($event['EventName']) ?>"
                            loading="lazy"
                        >
                        <div class="event-card__content">
                            <h3><?= escape_html($event['EventName']) ?></h3>
                            <p><?= escape_html(format_datetime($event['EventDate'])) ?></p>
                            <p>
                                <?= escape_html(ucfirst((string) $event['Format'])) ?>
                                ·
                                <?= escape_html($event['Province'] ?: ($event['Venue'] ?: 'Location to be confirmed')) ?>
                            </p>
                            <p>
                                <strong>
                                    <?php if ($event['MinPrice'] === null): ?>
                                        Tickets unavailable
                                    <?php elseif ((float) $event['MinPrice'] <= 0): ?>
                                        Free
                                    <?php else: ?>
                                        From <?= escape_html(format_money((float) $event['MinPrice'])) ?>
                                    <?php endif; ?>
                                </strong>
                            </p>

                            <?php $event_tags = organiser_profile_tags($event['Hashtags']); ?>
                            <?php if ($event_tags): ?>
                                <p class="post-tags">
                                    <?php foreach ($event_tags as $tag): ?>
                                        <a href="tag.php?<?= http_build_query(['tag' => ltrim($tag, '#')], '', '&', PHP_QUERY_RFC3986) ?>">
                                            <?= escape_html($tag) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </p>
                            <?php endif; ?>

                            <a class="btn btn-secondary" href="event-detail.php?event_id=<?= (int) $event['EventID'] ?>">
                                View details
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section aria-labelledby="latest-posts-heading">
        <div class="section-heading-row">
            <h2 id="latest-posts-heading">Latest posts and media</h2>
            <a class="btn btn-outline" href="organiser-posts.php?organiser_id=<?= $organiser_id ?>">
                View all posts and media
            </a>
        </div>

        <?php if (!$recent_posts): ?>
            <div class="empty-state">
                <p>No public event-linked posts are available.</p>
            </div>
        <?php else: ?>
            <div class="post-grid">
                <?php foreach ($recent_posts as $post): ?>
                    <?php
                    $safe_media = eventnest_safe_media_path($post['Media']);
                    $post_excerpt = organiser_profile_excerpt($post['Content']);
                    ?>
                    <article class="post-card">
                        <?php if ($post['Media_type'] === 'image' && $safe_media): ?>
                            <a href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">
                                <img
                                    src="<?= escape_html(eventnest_url($safe_media)) ?>"
                                    alt="Image post linked to <?= escape_html($post['EventName']) ?>"
                                    loading="lazy"
                                >
                            </a>
                        <?php elseif ($post['Media_type'] === 'video' && $safe_media): ?>
                            <video controls preload="metadata" aria-label="Video post linked to <?= escape_html($post['EventName']) ?>">
                                <source src="<?= escape_html(eventnest_url($safe_media)) ?>">
                                Your browser cannot play this video.
                            </video>
                        <?php elseif ($post['Media_type'] !== null): ?>
                            <div class="media-placeholder">Media unavailable</div>
                        <?php endif; ?>

                        <h3>
                            <?= $post['Media_type']
                                ? escape_html(ucfirst((string) $post['Media_type']) . ' post')
                                : 'Event update' ?>
                        </h3>

                        <?php if ($post_excerpt !== ''): ?>
                            <p><?= escape_html($post_excerpt) ?></p>
                        <?php endif; ?>

                        <p>
                            Linked to
                            <a href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>">
                                <?= escape_html($post['EventName']) ?>
                            </a>
                            · <?= escape_html(format_datetime($post['PostDate'])) ?>
                        </p>

                        <?php $post_tags = organiser_profile_tags($post['Hashtags']); ?>
                        <?php if ($post_tags): ?>
                            <p class="post-tags">
                                <?php foreach ($post_tags as $tag): ?>
                                    <a href="tag.php?<?= http_build_query(['tag' => ltrim($tag, '#')], '', '&', PHP_QUERY_RFC3986) ?>">
                                        <?= escape_html($tag) ?>
                                    </a>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>

                        <a class="btn btn-secondary" href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">
                            View post
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section aria-labelledby="past-events-heading">
        <h2 id="past-events-heading">Past events</h2>

        <?php if (!$past_events): ?>
            <div class="empty-state">
                <p>No completed events are available in this public history.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Format</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past_events as $event): ?>
                            <tr>
                                <td><?= escape_html($event['EventName']) ?></td>
                                <td><?= escape_html(format_date($event['EventDate'])) ?></td>
                                <td><?= escape_html(ucfirst((string) $event['Format'])) ?></td>
                                <td>
                                    <a href="event-detail.php?event_id=<?= (int) $event['EventID'] ?>">View details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
