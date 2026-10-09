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

function home_tags(?string $value): array
{
    preg_match_all('/#[\p{L}\p{N}_]+/u', (string) $value, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

function home_price_text(array $event): string
{
    if ($event['MinimumPrice'] === null) {
        return 'No tickets listed';
    }

    $price = (float) $event['MinimumPrice'];
    if ($price <= 0.0) {
        return 'Free';
    }

    return 'From ' . format_money($price);
}

function home_fetch_event(PDO $pdo, int $event_id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT e.EventID, e.EventName, e.EventDate, e.Format, e.Venue, e.Province,
                e.Hashtags, e.OrganiserID, e.CategoryID, c.CategoryName,
                u.FirstName, u.LastName,
                (SELECT MIN(t.Price) FROM TICKETS t WHERE t.EventID = e.EventID AND t.QuantityAvailable > 0) AS MinimumPrice
         FROM EVENTS e
         INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
         INNER JOIN USERS u ON u.UserID = e.OrganiserID
         WHERE e.EventID = :event_id
           AND e.Status = 'published'
           AND e.EventDate > NOW()
           AND u.Role = 'organiser'
           AND u.VerificationStatus = 'verified'
           AND u.UserStatus = 'active'
         LIMIT 1"
    );
    $stmt->execute([':event_id' => $event_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function home_service_recommendations(PDO $pdo, int $user_id): array
{
    $service = __DIR__ . '/shared-components/ai-recommendation-service.php';
    if (!is_file($service)) {
        return ['events' => [], 'fallback' => true, 'message' => 'Personalised recommendations are temporarily unavailable.'];
    }

    require_once $service;

    try {
        if (function_exists('getPersonalisedRecommendations')) {
            $result = getPersonalisedRecommendations($pdo, $user_id, 5);
        } elseif (function_exists('get_personalised_recommendations')) {
            $result = get_personalised_recommendations($pdo, $user_id, 5);
        } else {
            return ['events' => [], 'fallback' => true, 'message' => 'Personalised recommendations are temporarily unavailable.'];
        }
    } catch (Throwable $exception) {
        error_log('EventNest homepage recommendation error: ' . get_class($exception));
        return ['events' => [], 'fallback' => true, 'message' => 'Personalised recommendations are temporarily unavailable.'];
    }

    if (!is_array($result)) {
        return ['events' => [], 'fallback' => true, 'message' => 'Personalised recommendations are temporarily unavailable.'];
    }

    $rows = [];
    foreach (['recommendations', 'results', 'events'] as $key) {
        if (isset($result[$key]) && is_array($result[$key])) {
            $rows = $result[$key];
            break;
        }
    }
    if ($rows === [] && array_is_list($result)) {
        $rows = $result;
    }

    $normalised = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (int) ($row['EventID'] ?? $row['event_id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $normalised[] = [
            'event_id' => $id,
            'reason' => trim((string) ($row['WhyRecommended'] ?? $row['why_recommended'] ?? $row['reason'] ?? 'Recommended from your selected career information.')),
        ];
    }

    $fallback = (bool) ($result['fallback'] ?? $result['is_fallback'] ?? false);
    $message = trim((string) ($result['message'] ?? ''));

    return ['events' => array_slice($normalised, 0, 5), 'fallback' => $fallback, 'message' => $message];
}

function home_sql_fallback(PDO $pdo, array $attendee, int $limit = 5): array
{
    $sql =
        "SELECT e.EventID, e.EventName, e.EventDate, e.Format, e.Venue, e.Province,
                e.Hashtags, e.OrganiserID, e.CategoryID, c.CategoryName,
                u.FirstName, u.LastName,
                (SELECT MIN(t.Price) FROM TICKETS t WHERE t.EventID = e.EventID AND t.QuantityAvailable > 0) AS MinimumPrice
         FROM EVENTS e
         INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
         INNER JOIN USERS u ON u.UserID = e.OrganiserID
         WHERE e.Status = 'published'
           AND e.EventDate > NOW()
           AND u.Role = 'organiser'
           AND u.VerificationStatus = 'verified'
           AND u.UserStatus = 'active'
           AND NOT EXISTS (
               SELECT 1 FROM BLOCKS b
               WHERE b.OrganiserID = e.OrganiserID
                 AND LOWER(b.BlockedEmail) = LOWER(:attendee_email)
           )
         ORDER BY (LOWER(c.CategoryName) = LOWER(:career_field)) DESC,
                  e.EventDate ASC, e.EventID ASC
         LIMIT " . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':attendee_email' => (string) $attendee['Email'],
        ':career_field' => (string) $attendee['CareerField'],
    ]);

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($events as &$event) {
        $same_field = trim((string) $attendee['CareerField']) !== ''
            && mb_strtolower(trim((string) $event['CategoryName']), 'UTF-8') === mb_strtolower(trim((string) $attendee['CareerField']), 'UTF-8');
        $event['WhyRecommended'] = $same_field
            ? 'Matches your ' . (string) $event['CategoryName'] . ' career field.'
            : 'An upcoming published career event you can explore.';
    }
    unset($event);

    return $events;
}

$current_user_id = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$current_role = (string) ($_SESSION['role'] ?? '');
$attendee = null;

if ($current_user_id > 0 && $current_role === 'attendee') {
    $user_stmt = $pdo->prepare(
        "SELECT UserID, FirstName, LastName, Email, CareerField, UserStatus, RecommendationOptIn
         FROM USERS
         WHERE UserID = :user_id AND Role = 'attendee' AND UserStatus = 'active'
         LIMIT 1"
    );
    $user_stmt->execute([':user_id' => $current_user_id]);
    $attendee = $user_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$hero_stmt = $pdo->query(
    "SELECT e.EventID, e.EventName, e.EventDate, e.Format, e.Venue, e.Province,
            e.Hashtags, e.OrganiserID, e.CategoryID, c.CategoryName,
            u.FirstName, u.LastName,
            (SELECT MIN(t.Price) FROM TICKETS t WHERE t.EventID = e.EventID AND t.QuantityAvailable > 0) AS MinimumPrice
     FROM EVENTS e
     INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
     INNER JOIN USERS u ON u.UserID = e.OrganiserID
     WHERE e.Status = 'published'
       AND e.IsFeatured = 1
       AND e.EventDate > NOW()
       AND u.Role = 'organiser'
       AND u.VerificationStatus = 'verified'
       AND u.UserStatus = 'active'
     ORDER BY e.Created_at DESC, e.EventID DESC
     LIMIT 1"
);
$hero_event = $hero_stmt->fetch(PDO::FETCH_ASSOC) ?: null;

$featured_sql =
    "SELECT e.EventID, e.EventName, e.EventDate, e.Format, e.Venue, e.Province,
            e.Hashtags, e.OrganiserID, e.CategoryID, c.CategoryName,
            u.FirstName, u.LastName, e.IsFeatured,
            (SELECT MIN(t.Price) FROM TICKETS t WHERE t.EventID = e.EventID AND t.QuantityAvailable > 0) AS MinimumPrice
     FROM EVENTS e
     INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
     INNER JOIN USERS u ON u.UserID = e.OrganiserID
     WHERE e.Status = 'published'
       AND e.EventDate > NOW()
       AND u.Role = 'organiser'
       AND u.VerificationStatus = 'verified'
       AND u.UserStatus = 'active'";

$featured_params = [];
if ($hero_event) {
    $featured_sql .= ' AND e.EventID <> :hero_id';
    $featured_params[':hero_id'] = (int) $hero_event['EventID'];
}
$featured_sql .= ' ORDER BY e.IsFeatured DESC, CASE WHEN e.IsFeatured = 1 THEN e.Created_at END DESC, e.Created_at DESC, e.EventID DESC LIMIT 4';
$featured_stmt = $pdo->prepare($featured_sql);
$featured_stmt->execute($featured_params);
$featured_events = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);

$hashtag_counts = [];
$tag_stmt = $pdo->query(
    "SELECT Hashtags FROM EVENTS WHERE Status = 'published' AND Hashtags IS NOT NULL AND Hashtags <> ''
     UNION ALL
     SELECT p.Hashtags FROM POSTS p INNER JOIN EVENTS e ON e.EventID = p.EventID
     WHERE e.Status IN ('published','completed') AND p.Hashtags IS NOT NULL AND p.Hashtags <> ''
     LIMIT 300"
);
foreach ($tag_stmt->fetchAll(PDO::FETCH_COLUMN) as $tag_string) {
    foreach (home_tags((string) $tag_string) as $tag) {
        $key = mb_strtolower($tag, 'UTF-8');
        if (!isset($hashtag_counts[$key])) {
            $hashtag_counts[$key] = ['tag' => $tag, 'count' => 0];
        }
        $hashtag_counts[$key]['count']++;
    }
}
usort($hashtag_counts, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
$popular_tags = array_slice($hashtag_counts, 0, 5);

$recommendation_events = [];
$recommendation_mode = '';
$recommendation_message = '';
$explore_events = [];

if ($attendee) {
    if ((int) $attendee['RecommendationOptIn'] === 1) {
        $service_result = home_service_recommendations($pdo, (int) $attendee['UserID']);
        $seen = [];
        foreach ($service_result['events'] as $recommendation) {
            $event = home_fetch_event($pdo, (int) $recommendation['event_id']);
            if (!$event || isset($seen[$event['EventID']])) {
                continue;
            }

            $block_stmt = $pdo->prepare('SELECT 1 FROM BLOCKS WHERE OrganiserID = :organiser_id AND LOWER(BlockedEmail) = LOWER(:email) LIMIT 1');
            $block_stmt->execute([':organiser_id' => (int) $event['OrganiserID'], ':email' => (string) $attendee['Email']]);
            if ($block_stmt->fetchColumn()) {
                continue;
            }

            $event['WhyRecommended'] = $recommendation['reason'] !== ''
                ? $recommendation['reason']
                : 'Recommended from your selected career information.';
            $recommendation_events[] = $event;
            $seen[$event['EventID']] = true;
            if (count($recommendation_events) >= 5) {
                break;
            }
        }

        if ($recommendation_events && !$service_result['fallback']) {
            $recommendation_mode = 'ai';
        } else {
            $recommendation_events = home_sql_fallback($pdo, $attendee, 5);
            $recommendation_mode = 'fallback';
            $recommendation_message = $service_result['message'] ?: 'Showing standard EventNest recommendations while personalised ranking is unavailable.';
        }
    } else {
        $recommendation_events = home_sql_fallback($pdo, $attendee, 5);
        $recommendation_mode = 'fallback';
        $recommendation_message = 'Personalised recommendations are off. These are standard EventNest suggestions.';
    }

    $explore_stmt = $pdo->prepare(
        "SELECT e.EventID, e.EventName, e.EventDate, e.Format, e.Venue, e.Province,
                e.OrganiserID, c.CategoryName,
                (SELECT MIN(t.Price) FROM TICKETS t WHERE t.EventID = e.EventID AND t.QuantityAvailable > 0) AS MinimumPrice
         FROM EVENTS e
         INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
         INNER JOIN USERS u ON u.UserID = e.OrganiserID
         WHERE e.Status = 'published'
           AND e.EventDate > NOW()
           AND u.VerificationStatus = 'verified'
           AND u.UserStatus = 'active'
           AND LOWER(c.CategoryName) <> LOWER(:career_field)
           AND NOT EXISTS (
               SELECT 1 FROM BLOCKS b
               WHERE b.OrganiserID = e.OrganiserID
                 AND LOWER(b.BlockedEmail) = LOWER(:attendee_email)
           )
         ORDER BY e.EventDate ASC, e.EventID ASC
         LIMIT 4"
    );
    $explore_stmt->execute([
        ':career_field' => (string) $attendee['CareerField'],
        ':attendee_email' => (string) $attendee['Email'],
    ]);
    $explore_events = $explore_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Home';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content home-page">
    <section class="hero" aria-labelledby="home-heading">
        <div class="hero__content">
            <?php if ($attendee): ?>
                <p class="eyebrow">Welcome back, <?= escape_html((string) $attendee['FirstName']) ?></p>
            <?php endif; ?>
            <h1 id="home-heading">Discover. Learn. Connect.</h1>
            <p>Find professional events that support your studies, career and industry connections.</p>
            <form action="search-results.php" method="get" class="search-form" role="search" data-live-search>
                <label for="home-search">Search events, career fields or hashtags</label>
                <div class="search-form__row">
                    <input id="home-search" name="q" type="search" maxlength="100" autocomplete="off" placeholder="Search EventNest" data-search-input>
                    <button class="btn btn-primary" type="submit">Search</button>
                </div>
                <div class="search-suggestions" data-search-suggestions hidden></div>
            </form>
        </div>

        <?php if ($hero_event): ?>
            <?php $hero_image = resolve_event_image($pdo, (int) $hero_event['EventID']); ?>
            <article class="hero-event">
                <img src="<?= escape_html(eventnest_url($hero_image)) ?>" alt="Image for <?= escape_html($hero_event['EventName']) ?>">
                <div>
                    <p class="eyebrow">Featured event</p>
                    <h2><?= escape_html($hero_event['EventName']) ?></h2>
                    <p><?= escape_html(format_datetime($hero_event['EventDate'])) ?> · <?= escape_html(ucfirst($hero_event['Format'])) ?></p>
                    <p><?= escape_html(home_price_text($hero_event)) ?></p>
                    <a class="btn btn-primary" href="event-detail.php?event_id=<?= (int) $hero_event['EventID'] ?>">View details</a>
                </div>
            </article>
        <?php endif; ?>
    </section>

    <?php if ($attendee): ?>
        <section aria-labelledby="recommended-heading">
            <div class="section-heading">
                <div>
                    <h2 id="recommended-heading">Recommended for you</h2>
                    <p>Search and browsing remain available across every eligible career field.</p>
                </div>
                <a class="btn btn-outline" href="settings.php">Recommendation settings</a>
            </div>

            <?php if ($recommendation_mode === 'ai'): ?>
                <p class="status status-info">AI-assisted ranking</p>
            <?php elseif ($recommendation_message !== ''): ?>
                <div class="alert alert-info" role="status"><?= escape_html($recommendation_message) ?></div>
            <?php endif; ?>

            <?php if (!$recommendation_events): ?>
                <div class="empty-state">
                    <p>No suitable upcoming events are available right now.</p>
                    <a class="btn btn-outline" href="events.php">Browse all events</a>
                </div>
            <?php else: ?>
                <div class="event-grid">
                    <?php foreach ($recommendation_events as $event): ?>
                        <?php $image = resolve_event_image($pdo, (int) $event['EventID']); ?>
                        <article class="event-card">
                            <img class="event-card__image" src="<?= escape_html(eventnest_url($image)) ?>" alt="Image for <?= escape_html($event['EventName']) ?>">
                            <div class="event-card__content">
                                <h3><?= escape_html($event['EventName']) ?></h3>
                                <p><?= escape_html(format_datetime($event['EventDate'])) ?> · <?= escape_html(ucfirst($event['Format'])) ?></p>
                                <p><?= escape_html($event['CategoryName']) ?> · <?= escape_html(home_price_text($event)) ?></p>
                                <p><strong>Why recommended:</strong> <?= escape_html((string) $event['WhyRecommended']) ?></p>
                                <a class="btn btn-secondary" href="event-detail.php?event_id=<?= (int) $event['EventID'] ?>">View details</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section aria-labelledby="explore-heading">
            <div class="section-heading">
                <h2 id="explore-heading">Explore other career fields</h2>
                <a href="career-fields.php">Browse all career fields</a>
            </div>
            <?php if ($explore_events): ?>
                <div class="event-grid">
                    <?php foreach ($explore_events as $event): ?>
                        <?php $image = resolve_event_image($pdo, (int) $event['EventID']); ?>
                        <article class="event-card">
                            <img class="event-card__image" src="<?= escape_html(eventnest_url($image)) ?>" alt="Image for <?= escape_html($event['EventName']) ?>">
                            <div class="event-card__content">
                                <h3><?= escape_html($event['EventName']) ?></h3>
                                <p><?= escape_html(format_datetime($event['EventDate'])) ?> · <?= escape_html($event['CategoryName']) ?></p>
                                <p><?= escape_html(home_price_text($event)) ?></p>
                                <a class="btn btn-secondary" href="event-detail.php?event_id=<?= (int) $event['EventID'] ?>">View details</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">No additional upcoming career fields are available yet.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section aria-labelledby="featured-heading">
        <div class="section-heading">
            <h2 id="featured-heading">Featured events</h2>
            <a href="events.php">View all events</a>
        </div>
        <?php if (!$featured_events): ?>
            <div class="empty-state"><p>No published featured events are available yet.</p></div>
        <?php else: ?>
            <div class="event-grid">
                <?php foreach ($featured_events as $event): ?>
                    <?php $image = resolve_event_image($pdo, (int) $event['EventID']); ?>
                    <article class="event-card">
                        <img class="event-card__image" src="<?= escape_html(eventnest_url($image)) ?>" alt="Image for <?= escape_html($event['EventName']) ?>">
                        <div class="event-card__content">
                            <h3><?= escape_html($event['EventName']) ?></h3>
                            <p><?= escape_html(format_datetime($event['EventDate'])) ?> · <?= escape_html(ucfirst($event['Format'])) ?></p>
                            <p><?= escape_html($event['CategoryName']) ?> · <?= escape_html(home_price_text($event)) ?></p>
                            <?php if ((int) $event['IsFeatured'] === 1): ?><span class="status status-info">Featured</span><?php endif; ?>
                            <a class="btn btn-secondary" href="event-detail.php?event_id=<?= (int) $event['EventID'] ?>">View details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section aria-labelledby="hashtags-heading">
        <h2 id="hashtags-heading">Popular hashtags</h2>
        <?php if ($popular_tags): ?>
            <div class="tag-list">
                <?php foreach ($popular_tags as $tag): ?>
                    <a class="tag-chip" href="tag.php?<?= http_build_query(['tag' => ltrim((string) $tag['tag'], '#')], '', '&', PHP_QUERY_RFC3986) ?>"><?= escape_html($tag['tag']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No public hashtags are available yet.</p>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
