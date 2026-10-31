<?php

declare(strict_types=1);

require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';
require_once __DIR__ . '/shared-components/post-card.php';

$pdo = eventnest_db();

function eventnest_tag_url(array $base, array $changes = []): string
{
    $query = array_merge($base, $changes);

    foreach ($query as $key => $value) {
        if ($value === null || $value === '' || $value === 0) {
            unset($query[$key]);
        }
    }

    return 'tag.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function eventnest_valid_date(string $value): bool
{
    if ($value === '') {
        return true;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

$raw_tag = isset($_GET['tag']) ? (string) $_GET['tag'] : '';
$tag = eventnest_normalise_hashtag($raw_tag);

if ($tag === null) {
    http_response_code(400);
    $page_title = 'Invalid hashtag';
    require_once __DIR__ . '/shared-components/header.php';
    ?>
    <main class="page-shell tag-page">
        <section class="empty-state" aria-labelledby="invalid-tag-heading">
            <h1 id="invalid-tag-heading">Invalid hashtag</h1>
            <p>Use letters, numbers or underscores, up to 50 characters.</p>
            <a class="btn btn-outline" href="events.php">Browse events</a>
        </section>
    </main>
    <?php
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$view = isset($_GET['view']) ? strtolower(trim((string) $_GET['view'])) : 'events';
if (!in_array($view, ['events', 'posts'], true)) {
    $view = 'events';
}

$post_type = isset($_GET['post_type']) ? strtolower(trim((string) $_GET['post_type'])) : 'all';
if (!in_array($post_type, ['all', 'updates', 'images', 'videos'], true)) {
    $post_type = 'all';
}

$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($category_id === false) {
    $category_id = null;
}

$province = isset($_GET['province']) ? trim((string) $_GET['province']) : '';
if (strlen($province) > 100) {
    $province = substr($province, 0, 100);
}

$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : '';
if (!in_array($format, ['', 'online', 'physical', 'hybrid'], true)) {
    $format = '';
}

$date_from = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';

if (!eventnest_valid_date($date_from)) {
    $date_from = '';
}
if (!eventnest_valid_date($date_to)) {
    $date_to = '';
}

if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
    $date_from = '';
    $date_to = '';
}

$price_type = isset($_GET['price_type']) ? strtolower(trim((string) $_GET['price_type'])) : '';
if (!in_array($price_type, ['', 'free', 'paid'], true)) {
    $price_type = '';
}

$ticket_type = isset($_GET['ticket_type']) ? trim((string) $_GET['ticket_type']) : '';
$allowed_ticket_types = ['', 'General', 'VIP', 'Early Bird', 'Student'];
if (!in_array($ticket_type, $allowed_ticket_types, true)) {
    $ticket_type = '';
}

$sort = isset($_GET['sort']) ? strtolower(trim((string) $_GET['sort'])) : 'event_date';
if (!in_array($sort, ['event_date', 'newest'], true)) {
    $sort = 'event_date';
}

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($page === false || $page === null) {
    $page = 1;
}

$tag_regex = eventnest_hashtag_regex($tag);
$per_page = 12;

try {
    $categories = $pdo->query(
        'SELECT CategoryID, CategoryName FROM CATEGORIES ORDER BY CategoryName ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $provinces = eventnest_provinces();
} catch (PDOException $exception) {
    error_log('EventNest tag filter-data query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('EventNest is temporarily unavailable.');
}

$event_where = [
    "e.Status = 'published'",
    "COALESCE(e.Hashtags, '') REGEXP :event_tag_regex",
    "u.Role = 'organiser'",
    "u.VerificationStatus = 'verified'",
    "u.UserStatus = 'active'",
];
$event_params = [
    ':event_tag_regex' => $tag_regex,
];

if ($category_id !== null) {
    $event_where[] = 'e.CategoryID = :category_id';
    $event_params[':category_id'] = (int) $category_id;
}

if ($province !== '') {
    $event_where[] = 'e.Province = :province';
    $event_params[':province'] = $province;
}

if ($format !== '') {
    $event_where[] = 'e.Format = :format';
    $event_params[':format'] = $format;
}

if ($date_from !== '') {
    $event_where[] = 'e.EventDate >= :date_from';
    $event_params[':date_from'] = $date_from . ' 00:00:00';
}

if ($date_to !== '') {
    $event_where[] = 'e.EventDate <= :date_to';
    $event_params[':date_to'] = $date_to . ' 23:59:59';
}

if ($price_type === 'free') {
    $event_where[] = 'EXISTS (SELECT 1 FROM TICKETS tf WHERE tf.EventID = e.EventID AND tf.Price = 0)';
} elseif ($price_type === 'paid') {
    $event_where[] = 'EXISTS (SELECT 1 FROM TICKETS tp WHERE tp.EventID = e.EventID AND tp.Price > 0)';
}

if ($ticket_type !== '') {
    $event_where[] = 'EXISTS (SELECT 1 FROM TICKETS tt WHERE tt.EventID = e.EventID AND tt.TicketType = :ticket_type)';
    $event_params[':ticket_type'] = $ticket_type;
}

$event_where_sql = implode(' AND ', $event_where);

$post_where = [
    "e.Status IN ('published', 'completed')",
    'e.OrganiserID = p.OrganiserID',
    "COALESCE(p.Hashtags, '') REGEXP :post_tag_regex",
    "u.Role = 'organiser'",
    "u.VerificationStatus = 'verified'",
    "u.UserStatus = 'active'",
];
$post_params = [
    ':post_tag_regex' => $tag_regex,
];

if ($post_type === 'updates') {
    $post_where[] = 'p.Media IS NULL';
    $post_where[] = 'p.Media_type IS NULL';
} elseif ($post_type === 'images') {
    $post_where[] = "p.Media_type = 'image'";
    $post_where[] = 'p.Media IS NOT NULL';
} elseif ($post_type === 'videos') {
    $post_where[] = "p.Media_type = 'video'";
    $post_where[] = 'p.Media IS NOT NULL';
}

$post_where_sql = implode(' AND ', $post_where);

try {
    $event_count_statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM EVENTS e
         INNER JOIN USERS u ON u.UserID = e.OrganiserID
         WHERE {$event_where_sql}"
    );
    foreach ($event_params as $key => $value) {
        $event_count_statement->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }
    $event_count_statement->execute();
    $total_events = (int) $event_count_statement->fetchColumn();

    $post_count_statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM POSTS p
         INNER JOIN EVENTS e ON e.EventID = p.EventID
         INNER JOIN USERS u ON u.UserID = p.OrganiserID
         WHERE {$post_where_sql}"
    );
    foreach ($post_params as $key => $value) {
        $post_count_statement->bindValue($key, $value, PDO::PARAM_STR);
    }
    $post_count_statement->execute();
    $total_posts = (int) $post_count_statement->fetchColumn();
} catch (PDOException $exception) {
    error_log('EventNest tag count query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('EventNest is temporarily unavailable.');
}

$current_total = $view === 'events' ? $total_events : $total_posts;
$total_pages = max(1, (int) ceil($current_total / $per_page));
$page = min((int) $page, $total_pages);
$offset = ($page - 1) * $per_page;

$events = [];
$posts = [];

try {
    if ($view === 'events') {
        $order_sql = $sort === 'newest'
            ? 'e.Created_at DESC, e.EventID DESC'
            : 'e.EventDate ASC, e.EventID ASC';

        $event_statement = $pdo->prepare(
            "SELECT
                e.EventID,
                e.EventName,
                e.Description,
                e.EventDate,
                e.Format,
                e.Venue,
                e.Province,
                e.Hashtags,
                e.OrganiserID,
                c.CategoryName,
                u.FirstName,
                u.LastName,
                u.OrganisationName,
                (SELECT MIN(t.Price)
                 FROM TICKETS t
                 WHERE t.EventID = e.EventID
                   AND t.QuantityAvailable > 0) AS MinPrice
             FROM EVENTS e
             INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
             INNER JOIN USERS u ON u.UserID = e.OrganiserID
             WHERE {$event_where_sql}
             ORDER BY {$order_sql}
             LIMIT :limit OFFSET :offset"
        );

        foreach ($event_params as $key => $value) {
            $event_statement->bindValue(
                $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
        $event_statement->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $event_statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $event_statement->execute();
        $events = $event_statement->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $post_statement = $pdo->prepare(
            "SELECT
                p.PostID,
                p.Content,
                p.Media_type,
                p.Media,
                p.Hashtags,
                p.PostDate,
                p.EventID,
                p.OrganiserID,
                e.EventName,
                u.FirstName,
                u.LastName,
                u.OrganisationName,
                (SELECT COUNT(*)
                 FROM REACTIONS r1
                 WHERE r1.PostID = p.PostID
                   AND r1.ReactionType = 'thumbs_up') AS ThumbsUpTotal,
                (SELECT COUNT(*)
                 FROM REACTIONS r2
                 WHERE r2.PostID = p.PostID
                   AND r2.ReactionType = 'clap') AS ClapTotal,
                (SELECT COUNT(*)
                 FROM REACTIONS r3
                 WHERE r3.PostID = p.PostID
                   AND r3.ReactionType = 'lightbulb') AS LightbulbTotal
             FROM POSTS p
             INNER JOIN EVENTS e ON e.EventID = p.EventID
             INNER JOIN USERS u ON u.UserID = p.OrganiserID
             WHERE {$post_where_sql}
             ORDER BY p.PostDate DESC, p.PostID DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($post_params as $key => $value) {
            $post_statement->bindValue($key, $value, PDO::PARAM_STR);
        }
        $post_statement->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $post_statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $post_statement->execute();
        $posts = $post_statement->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $exception) {
    error_log('EventNest tag result query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('EventNest is temporarily unavailable.');
}

$base = [
    'tag' => $tag,
    'view' => $view,
    'category_id' => $category_id,
    'province' => $province,
    'format' => $format,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'price_type' => $price_type,
    'ticket_type' => $ticket_type,
    'sort' => $sort,
    'post_type' => $post_type,
];

$page_title = '#' . $tag;
$post_viewer = eventnest_post_viewer($pdo, $posts ?? []);
require_once __DIR__ . '/shared-components/header.php';
?>

<main class="page-shell tag-page">
    <header class="page-header">
        <p class="eyebrow">Hashtag</p>
        <h1>#<?= escape_html($tag) ?></h1>
        <p>Public EventNest events and organiser posts using this hashtag.</p>
    </header>

    <nav class="tabs" aria-label="Hashtag result types">
        <a
            class="btn <?= $view === 'events' ? 'btn-secondary' : 'btn-outline' ?>"
            href="<?= escape_html(eventnest_tag_url($base, [
                'view' => 'events',
                'page' => 1,
                'post_type' => null,
            ])) ?>"
            <?= $view === 'events' ? 'aria-current="page"' : '' ?>
        >
            Events (<?= (int) $total_events ?>)
        </a>
        <a
            class="btn <?= $view === 'posts' ? 'btn-secondary' : 'btn-outline' ?>"
            href="<?= escape_html(eventnest_tag_url($base, [
                'view' => 'posts',
                'page' => 1,
                'category_id' => null,
                'province' => null,
                'format' => null,
                'date_from' => null,
                'date_to' => null,
                'price_type' => null,
                'ticket_type' => null,
                'sort' => null,
            ])) ?>"
            <?= $view === 'posts' ? 'aria-current="page"' : '' ?>
        >
            Posts (<?= (int) $total_posts ?>)
        </a>
    </nav>

    <?php if ($view === 'events'): ?>
        <section class="filter-panel" aria-labelledby="event-filter-heading">
            <h2 id="event-filter-heading">Filter events</h2>
            <form action="tag.php" method="get" class="filter-form">
                <input type="hidden" name="tag" value="<?= escape_html($tag) ?>">
                <input type="hidden" name="view" value="events">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="tag-category">Career field</label>
                        <select id="tag-category" name="category_id">
                            <option value="">All fields</option>
                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= (int) $category['CategoryID'] ?>"
                                    <?= $category_id === (int) $category['CategoryID'] ? 'selected' : '' ?>
                                >
                                    <?= escape_html((string) $category['CategoryName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tag-province">Province</label>
                        <select id="tag-province" name="province">
                            <option value="">All provinces</option>
                            <?php foreach ($provinces as $province_option): ?>
                                <option
                                    value="<?= escape_html((string) $province_option) ?>"
                                    <?= $province === (string) $province_option ? 'selected' : '' ?>
                                >
                                    <?= escape_html((string) $province_option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tag-format">Format</label>
                        <select id="tag-format" name="format">
                            <option value="">All formats</option>
                            <option value="online" <?= $format === 'online' ? 'selected' : '' ?>>Online</option>
                            <option value="physical" <?= $format === 'physical' ? 'selected' : '' ?>>Physical</option>
                            <option value="hybrid" <?= $format === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tag-date-from">From date</label>
                        <input id="tag-date-from" name="date_from" type="date" value="<?= escape_html($date_from) ?>">
                    </div>

                    <div class="form-group">
                        <label for="tag-date-to">To date</label>
                        <input id="tag-date-to" name="date_to" type="date" value="<?= escape_html($date_to) ?>">
                    </div>

                    <div class="form-group">
                        <label for="tag-price">Price</label>
                        <select id="tag-price" name="price_type">
                            <option value="">Free or paid</option>
                            <option value="free" <?= $price_type === 'free' ? 'selected' : '' ?>>Free</option>
                            <option value="paid" <?= $price_type === 'paid' ? 'selected' : '' ?>>Paid</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tag-ticket-type">Ticket type</label>
                        <select id="tag-ticket-type" name="ticket_type">
                            <option value="">Any ticket type</option>
                            <?php foreach (['General', 'VIP', 'Early Bird', 'Student'] as $type): ?>
                                <option value="<?= escape_html($type) ?>" <?= $ticket_type === $type ? 'selected' : '' ?>>
                                    <?= escape_html($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tag-sort">Sort</label>
                        <select id="tag-sort" name="sort">
                            <option value="event_date" <?= $sort === 'event_date' ? 'selected' : '' ?>>Event date</option>
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                        </select>
                    </div>
                </div>

                <div class="button-row">
                    <button class="btn btn-primary" type="submit">Apply filters</button>
                    <a class="btn btn-outline" href="<?= escape_html(eventnest_tag_url([
                        'tag' => $tag,
                        'view' => 'events',
                    ])) ?>">Clear filters</a>
                </div>
            </form>
        </section>

        <section aria-labelledby="tag-events-heading">
            <h2 id="tag-events-heading">Events (<?= (int) $total_events ?>)</h2>

            <?php if (empty($events)): ?>
                <div class="empty-state">
                    <h3>No matching events</h3>
                    <p>No published events match this hashtag and the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="event-grid">
                    <?php foreach ($events as $event): ?>
                        <?php
                        $event_image = resolve_event_image($pdo, (int) $event['EventID']);
                        $organiser_name = eventnest_organiser_display_name($event);
                        $event_tags = eventnest_extract_hashtags(
                            $event['Hashtags'] !== null ? (string) $event['Hashtags'] : null
                        );
                        ?>
                        <article class="event-card">
                            <img
                                class="event-card__image"
                                src="<?= escape_html(eventnest_url($event_image)) ?>"
                                alt="Image for <?= escape_html((string) $event['EventName']) ?>"
                                loading="lazy"
                            >
                            <div class="event-card__body">
                                <p class="eyebrow"><?= escape_html((string) $event['CategoryName']) ?></p>
                                <h3><?= escape_html((string) $event['EventName']) ?></h3>
                                <p><?= escape_html(format_datetime((string) $event['EventDate'])) ?></p>
                                <p>
                                    <?= escape_html(ucfirst((string) $event['Format'])) ?>
                                    ? <?= escape_html(eventnest_event_location($event)) ?>
                                </p>
                                <p>
                                    Organised by
                                    <a href="organiser-profile.php?organiser_id=<?= (int) $event['OrganiserID'] ?>">
                                        <?= escape_html($organiser_name !== '' ? $organiser_name : 'EventNest organiser') ?>
                                    </a>
                                </p>
                                <p>
                                    <?php if ($event['MinPrice'] === null): ?>
                                        No tickets currently available
                                    <?php elseif ((float) $event['MinPrice'] <= 0): ?>
                                        Free tickets available
                                    <?php else: ?>
                                        From <?= escape_html(format_money($event['MinPrice'])) ?>
                                    <?php endif; ?>
                                </p>

                                <?php if (!empty($event_tags)): ?>
                                    <div class="tag-list" aria-label="Event hashtags">
                                        <?php foreach ($event_tags as $event_tag): ?>
                                            <a class="tag" href="tag.php?tag=<?= rawurlencode($event_tag) ?>">
                                                #<?= escape_html($event_tag) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
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
    <?php else: ?>
        <section class="filter-panel" aria-labelledby="post-filter-heading">
            <h2 id="post-filter-heading">Filter posts</h2>
            <form action="tag.php" method="get" class="filter-form">
                <input type="hidden" name="tag" value="<?= escape_html($tag) ?>">
                <input type="hidden" name="view" value="posts">

                <div class="form-group">
                    <label for="post-type">Post type</label>
                    <select id="post-type" name="post_type">
                        <option value="all" <?= $post_type === 'all' ? 'selected' : '' ?>>All posts</option>
                        <option value="updates" <?= $post_type === 'updates' ? 'selected' : '' ?>>Event updates</option>
                        <option value="images" <?= $post_type === 'images' ? 'selected' : '' ?>>Images</option>
                        <option value="videos" <?= $post_type === 'videos' ? 'selected' : '' ?>>Videos</option>
                    </select>
                </div>

                <div class="button-row">
                    <button class="btn btn-primary" type="submit">Apply filters</button>
                    <a class="btn btn-outline" href="<?= escape_html(eventnest_tag_url([
                        'tag' => $tag,
                        'view' => 'posts',
                    ])) ?>">Clear filters</a>
                </div>
            </form>
        </section>

        <section aria-labelledby="tag-posts-heading">
            <h2 id="tag-posts-heading">Posts (<?= (int) $total_posts ?>)</h2>

            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <h3>No matching posts</h3>
                    <p>No public organiser posts match this hashtag and post type.</p>
                </div>
            <?php else: ?>
                <div class="post-grid">
                    <?php foreach ($posts as $post): ?>
                        <?php eventnest_render_post($post, $post_viewer); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($total_pages > 1): ?>
        <nav class="pagination" aria-label="Hashtag result pages">
            <?php if ($page > 1): ?>
                <a class="btn btn-outline" href="<?= escape_html(eventnest_tag_url($base, ['page' => $page - 1])) ?>">
                    Previous
                </a>
            <?php endif; ?>

            <span>Page <?= (int) $page ?> of <?= (int) $total_pages ?></span>

            <?php if ($page < $total_pages): ?>
                <a class="btn btn-outline" href="<?= escape_html(eventnest_tag_url($base, ['page' => $page + 1])) ?>">
                    Next
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</main>

<?php eventnest_post_scripts(); require_once __DIR__ . '/shared-components/footer.php'; ?>
