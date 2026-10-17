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

function tag_text(mixed $value, int $max_length): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $max_length, 'UTF-8')
        : substr($value, 0, $max_length);
}

function tag_positive_int(mixed $value, int $default = 0): int
{
    $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $result === false ? $default : (int) $result;
}

function tag_list(?string $hashtags): array
{
    preg_match_all('/#[\p{L}\p{N}_]{1,60}/u', (string) $hashtags, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

function tag_excerpt(?string $text, int $limit = 220): string
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

function tag_build_url(array $base, array $changes = []): string
{
    $params = array_merge($base, $changes);
    $params = array_filter(
        $params,
        static fn(mixed $value): bool => $value !== '' && $value !== null
    );

    return 'tag.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

$raw_tag = tag_text($_GET['tag'] ?? '', 61);
if (str_starts_with($raw_tag, '#')) {
    $raw_tag = substr($raw_tag, 1);
}
$tag = $raw_tag;

if ($tag === '' || !preg_match('/^[\p{L}\p{N}_]{1,60}$/uD', $tag)) {
    http_response_code(400);
    $page_title = 'Invalid hashtag';
    require_once __DIR__ . '/shared-components/header.php';
    ?>
    <main class="page-content tag-page">
        <div class="empty-state">
            <h1>Invalid hashtag</h1>
            <p>Use letters, numbers or underscores in a hashtag.</p>
            <a class="btn btn-outline" href="events.php">Browse events</a>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$view = tag_text($_GET['view'] ?? 'events', 10);
if (!in_array($view, ['events', 'posts'], true)) {
    $view = 'events';
}

$category_id = tag_positive_int($_GET['category_id'] ?? null);
$province = tag_text($_GET['province'] ?? '', 100);
$format = tag_text($_GET['format'] ?? '', 20);
$post_type = tag_text($_GET['post_type'] ?? 'all', 20);
$page = tag_positive_int($_GET['page'] ?? 1, 1);
$page_size = 10;

if (!in_array($format, ['', 'online', 'physical', 'hybrid'], true)) {
    $format = '';
}

if (!in_array($post_type, ['all', 'updates', 'images', 'videos'], true)) {
    $post_type = 'all';
}

$categories = $pdo->query(
    'SELECT CategoryID, CategoryName FROM CATEGORIES ORDER BY CategoryName ASC'
)->fetchAll();

$province_stmt = $pdo->query(
    "SELECT DISTINCT Province
       FROM EVENTS
      WHERE Status = 'published'
        AND Province IS NOT NULL
        AND TRIM(Province) <> ''
      ORDER BY Province ASC"
);
$provinces = $province_stmt->fetchAll(PDO::FETCH_COLUMN);

/*
 * Hashtags are stored as text. The requested tag is restricted to letters/numbers/_ so
 * it can safely be inserted into the boundary pattern; the full pattern is still bound as
 * a prepared parameter rather than concatenated into SQL.
 */
$tag_regex = '(^|[[:space:],])#' . $tag . '([[:space:],]|$)';

$rows = [];
$total_results = 0;
$total_pages = 1;

if ($view === 'events') {
    $conditions = [
        "e.Status = 'published'",
        "COALESCE(e.Hashtags, '') REGEXP :tag_regex",
    ];
    $params = [':tag_regex' => $tag_regex];

    if ($category_id > 0) {
        $conditions[] = 'e.CategoryID = :category_id';
        $params[':category_id'] = $category_id;
    }

    if ($province !== '') {
        $conditions[] = 'e.Province = :province';
        $params[':province'] = $province;
    }

    if ($format !== '') {
        $conditions[] = 'e.Format = :format';
        $params[':format'] = $format;
    }

    $where_sql = implode(' AND ', $conditions);

    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM EVENTS e WHERE {$where_sql}");
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_results = (int) $count_stmt->fetchColumn();
    $total_pages = max(1, (int) ceil($total_results / $page_size));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $page_size;

    $sql = "SELECT
                e.EventID,
                e.OrganiserID,
                e.EventName,
                e.EventDate,
                e.Format,
                e.Province,
                e.Venue,
                e.Hashtags,
                c.CategoryName,
                u.FirstName,
                u.LastName,
                (SELECT MIN(t.Price)
                   FROM TICKETS t
                  WHERE t.EventID = e.EventID
                    AND t.QuantityAvailable > 0) AS MinPrice
            FROM EVENTS e
            INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
            INNER JOIN USERS u ON u.UserID = e.OrganiserID
            WHERE {$where_sql}
            ORDER BY e.EventDate ASC, e.EventID ASC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $page_size, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
} else {
    $conditions = [
        "e.Status IN ('published','completed')",
        "e.OrganiserID = p.OrganiserID",
        "u.Role = 'organiser'",
        "u.VerificationStatus = 'verified'",
        "u.UserStatus = 'active'",
        "COALESCE(p.Hashtags, '') REGEXP :tag_regex",
    ];

    if ($post_type === 'updates') {
        $conditions[] = 'p.Media IS NULL AND p.Media_type IS NULL';
    } elseif ($post_type === 'images') {
        $conditions[] = "p.Media_type = 'image' AND p.Media IS NOT NULL";
    } elseif ($post_type === 'videos') {
        $conditions[] = "p.Media_type = 'video' AND p.Media IS NOT NULL";
    }

    $where_sql = implode(' AND ', $conditions);

    $count_stmt = $pdo->prepare(
        "SELECT COUNT(*)
           FROM POSTS p
           INNER JOIN EVENTS e ON e.EventID = p.EventID
           INNER JOIN USERS u ON u.UserID = p.OrganiserID
          WHERE {$where_sql}"
    );
    $count_stmt->execute([':tag_regex' => $tag_regex]);
    $total_results = (int) $count_stmt->fetchColumn();
    $total_pages = max(1, (int) ceil($total_results / $page_size));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $page_size;

    $sql = "SELECT
                p.PostID,
                p.OrganiserID,
                p.Content,
                p.Media,
                p.Media_type,
                p.Hashtags,
                p.PostDate,
                e.EventID,
                e.EventName,
                u.FirstName,
                u.LastName,
                COALESCE(SUM(CASE WHEN r.ReactionType = 'thumbs_up' THEN 1 ELSE 0 END), 0) AS ThumbsUpTotal,
                COALESCE(SUM(CASE WHEN r.ReactionType = 'clap' THEN 1 ELSE 0 END), 0) AS ClapTotal,
                COALESCE(SUM(CASE WHEN r.ReactionType = 'lightbulb' THEN 1 ELSE 0 END), 0) AS LightbulbTotal
            FROM POSTS p
            INNER JOIN EVENTS e ON e.EventID = p.EventID
            INNER JOIN USERS u ON u.UserID = p.OrganiserID
            LEFT JOIN REACTIONS r ON r.PostID = p.PostID
            WHERE {$where_sql}
            GROUP BY
                p.PostID,
                p.OrganiserID,
                p.Content,
                p.Media,
                p.Media_type,
                p.Hashtags,
                p.PostDate,
                e.EventID,
                e.EventName,
                u.FirstName,
                u.LastName
            ORDER BY p.PostDate DESC, p.PostID DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':tag_regex', $tag_regex, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $page_size, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
}

$base = [
    'tag' => $tag,
    'view' => $view,
    'category_id' => $category_id ?: '',
    'province' => $province,
    'format' => $format,
    'post_type' => $post_type,
];

$page_title = '#' . $tag;
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content tag-page">
    <section class="page-heading">
        <h1>Results for #<?= escape_html($tag) ?></h1>
        <p>Matching public EventNest events and organiser posts.</p>
    </section>

    <nav class="tabs" aria-label="Hashtag result type">
        <a
            class="<?= $view === 'events' ? 'active' : '' ?>"
            href="<?= escape_html(tag_build_url($base, ['view' => 'events', 'page' => 1, 'post_type' => ''])) ?>"
            <?= $view === 'events' ? 'aria-current="page"' : '' ?>
        >Events</a>
        <a
            class="<?= $view === 'posts' ? 'active' : '' ?>"
            href="<?= escape_html(tag_build_url($base, ['view' => 'posts', 'page' => 1, 'category_id' => '', 'province' => '', 'format' => ''])) ?>"
            <?= $view === 'posts' ? 'aria-current="page"' : '' ?>
        >Posts</a>
    </nav>

    <section class="filter-panel" aria-labelledby="tag-filter-heading">
        <h2 id="tag-filter-heading">Filter hashtag results</h2>

        <?php if ($view === 'events'): ?>
            <form action="tag.php" method="get" class="filter-form">
                <input type="hidden" name="tag" value="<?= escape_html($tag) ?>">
                <input type="hidden" name="view" value="events">

                <label for="category_id">Career field</label>
                <select id="category_id" name="category_id">
                    <option value="">All fields</option>
                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= (int) $category['CategoryID'] ?>"
                            <?= $category_id === (int) $category['CategoryID'] ? 'selected' : '' ?>
                        ><?= escape_html($category['CategoryName']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="province">Province</label>
                <select id="province" name="province">
                    <option value="">All provinces</option>
                    <?php foreach ($provinces as $province_option): ?>
                        <option
                            value="<?= escape_html($province_option) ?>"
                            <?= $province === $province_option ? 'selected' : '' ?>
                        ><?= escape_html($province_option) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="format">Event format</label>
                <select id="format" name="format">
                    <option value="">All formats</option>
                    <option value="online" <?= $format === 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="physical" <?= $format === 'physical' ? 'selected' : '' ?>>In-person</option>
                    <option value="hybrid" <?= $format === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                </select>

                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a class="btn btn-outline" href="<?= escape_html(tag_build_url(['tag' => $tag, 'view' => 'events'], ['page' => 1])) ?>">
                    Clear filters
                </a>
            </form>
        <?php else: ?>
            <form action="tag.php" method="get" class="filter-form">
                <input type="hidden" name="tag" value="<?= escape_html($tag) ?>">
                <input type="hidden" name="view" value="posts">

                <label for="post_type">Post type</label>
                <select id="post_type" name="post_type">
                    <option value="all" <?= $post_type === 'all' ? 'selected' : '' ?>>All posts</option>
                    <option value="updates" <?= $post_type === 'updates' ? 'selected' : '' ?>>Event updates</option>
                    <option value="images" <?= $post_type === 'images' ? 'selected' : '' ?>>Images</option>
                    <option value="videos" <?= $post_type === 'videos' ? 'selected' : '' ?>>Videos</option>
                </select>

                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a class="btn btn-outline" href="<?= escape_html(tag_build_url(['tag' => $tag, 'view' => 'posts'], ['page' => 1])) ?>">
                    Clear filters
                </a>
            </form>
        <?php endif; ?>
    </section>

    <section aria-labelledby="tag-results-heading">
        <h2 id="tag-results-heading">
            <?= $view === 'events' ? 'Events' : 'Posts' ?> (<?= $total_results ?>)
        </h2>

        <?php if (!$rows): ?>
            <div class="empty-state">
                <p>No public <?= $view === 'events' ? 'events' : 'posts' ?> match this hashtag and the selected filters.</p>
            </div>
        <?php elseif ($view === 'events'): ?>
            <div class="event-grid">
                <?php foreach ($rows as $event): ?>
                    <?php
                    $image = resolve_event_image($pdo, (int) $event['EventID']);
                    $organiser_name = trim((string) $event['FirstName'] . ' ' . (string) $event['LastName']);
                    if ($organiser_name === '') {
                        $organiser_name = 'Verified organiser';
                    }
                    ?>
                    <article class="event-card">
                        <img
                            class="event-card__image"
                            src="<?= escape_html(eventnest_url($image)) ?>"
                            alt="Image for <?= escape_html($event['EventName']) ?>"
                            loading="lazy"
                        >
                        <div class="event-card__content">
                            <h3><?= escape_html($event['EventName']) ?></h3>
                            <p><?= escape_html(format_datetime($event['EventDate'])) ?> · <?= escape_html(ucfirst((string) $event['Format'])) ?></p>
                            <p><?= escape_html($event['CategoryName']) ?> · <?= escape_html($event['Province'] ?: ($event['Venue'] ?: 'Location to be confirmed')) ?></p>
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
                            <p>
                                Organiser:
                                <a href="organiser-profile.php?organiser_id=<?= (int) $event['OrganiserID'] ?>">
                                    <?= escape_html($organiser_name) ?>
                                </a>
                            </p>
                            <a class="btn btn-secondary" href="event-detail.php?event_id=<?= (int) $event['EventID'] ?>">View event</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="post-grid">
                <?php foreach ($rows as $post): ?>
                    <?php
                    $safe_media = get_safe_public_media_path($post['Media']);
                    $organiser_name = trim((string) $post['FirstName'] . ' ' . (string) $post['LastName']);
                    if ($organiser_name === '') {
                        $organiser_name = 'Verified organiser';
                    }
                    $excerpt = tag_excerpt($post['Content']);
                    $post_tags = tag_list($post['Hashtags']);
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
                            <a href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">
                                <?= $post['Media_type']
                                    ? escape_html(ucfirst((string) $post['Media_type']) . ' post')
                                    : 'Event update' ?>
                            </a>
                        </h3>

                        <?php if ($excerpt !== ''): ?>
                            <p><?= escape_html($excerpt) ?></p>
                        <?php endif; ?>

                        <p>
                            By
                            <a href="organiser-profile.php?organiser_id=<?= (int) $post['OrganiserID'] ?>">
                                <?= escape_html($organiser_name) ?>
                            </a>
                            · Linked to
                            <a href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>">
                                <?= escape_html($post['EventName']) ?>
                            </a>
                        </p>

                        <?php if ($post_tags): ?>
                            <p class="post-tags">
                                <?php foreach ($post_tags as $post_tag): ?>
                                    <a href="tag.php?<?= http_build_query(['tag' => ltrim($post_tag, '#'), 'view' => 'posts'], '', '&', PHP_QUERY_RFC3986) ?>">
                                        <?= escape_html($post_tag) ?>
                                    </a>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>

                        <p class="reaction-summary" aria-label="Reaction totals">
                            <span>Thumbs up <?= (int) $post['ThumbsUpTotal'] ?></span>
                            <span>Applause <?= (int) $post['ClapTotal'] ?></span>
                            <span>Lightbulb <?= (int) $post['LightbulbTotal'] ?></span>
                        </p>

                        <a class="btn btn-secondary" href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">View post</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($total_pages > 1): ?>
        <nav class="pagination" aria-label="Hashtag result pages">
            <?php if ($page > 1): ?>
                <a class="btn btn-outline" href="<?= escape_html(tag_build_url($base, ['page' => $page - 1])) ?>">Previous</a>
            <?php endif; ?>

            <span>Page <?= $page ?> of <?= $total_pages ?></span>

            <?php if ($page < $total_pages): ?>
                <a class="btn btn-secondary" href="<?= escape_html(tag_build_url($base, ['page' => $page + 1])) ?>">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
