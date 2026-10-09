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

function tag_string(string $key, int $max = 100): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? mb_substr(trim($value), 0, $max, 'UTF-8') : '';
}

function tag_positive_int(string $key): int
{
    $value = tag_string($key, 20);
    return ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function tag_list(?string $hashtags): array
{
    preg_match_all('/#[\p{L}\p{N}_]+/u', (string) $hashtags, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

$tag = ltrim(tag_string('tag', 61), '#');
if ($tag === '' || mb_strlen($tag, 'UTF-8') > 60 || !preg_match('/^[\p{L}\p{N}_]+$/uD', $tag)) {
    http_response_code(400);
    $page_title = 'Invalid hashtag';
    require_once __DIR__ . '/shared-components/header.php';
    echo '<main class="page-content"><div class="empty-state"><h1>Invalid hashtag</h1><p>Use letters, numbers or underscores in a hashtag.</p><a class="btn btn-outline" href="events.php">Browse events</a></div></main>';
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$view = tag_string('view', 10) ?: 'all';
if (!in_array($view, ['all', 'events', 'posts'], true)) {
    $view = 'all';
}
$category_id = tag_positive_int('category_id');
$province = tag_string('province', 100);
$format = tag_string('format', 20);
$post_type = tag_string('post_type', 20) ?: 'all';
$page = max(1, tag_positive_int('page') ?: 1);
if (!in_array($format, ['', 'online', 'physical', 'hybrid'], true)) {
    $format = '';
}
if (!in_array($post_type, ['all', 'updates', 'images', 'videos'], true)) {
    $post_type = 'all';
}

$categories = $pdo->query('SELECT CategoryID, CategoryName FROM CATEGORIES ORDER BY CategoryName')->fetchAll(PDO::FETCH_ASSOC);
$provinces = $pdo->query("SELECT DISTINCT Province FROM EVENTS WHERE Status = 'published' AND Province IS NOT NULL AND Province <> '' ORDER BY Province")->fetchAll(PDO::FETCH_COLUMN);
$regexp = '(^|[[:space:],])#' . $tag . '([[:space:],]|$)';

$event_where = ["e.Status = 'published'", 'COALESCE(e.Hashtags, \'\') REGEXP :tag_regex'];
$event_params = [':tag_regex' => $regexp];
if ($category_id > 0) {
    $event_where[] = 'e.CategoryID = :category_id';
    $event_params[':category_id'] = $category_id;
}
if ($province !== '') {
    $event_where[] = 'e.Province = :province';
    $event_params[':province'] = $province;
}
if ($format !== '') {
    $event_where[] = 'e.Format = :format';
    $event_params[':format'] = $format;
}
$event_where_sql = implode(' AND ', $event_where);

$post_where = ["e.Status IN ('published','completed')", "u.Role = 'organiser'", "u.VerificationStatus = 'verified'", 'COALESCE(p.Hashtags, \'\') REGEXP :post_tag_regex'];
$post_params = [':post_tag_regex' => $regexp];
if ($post_type === 'updates') {
    $post_where[] = 'p.Media IS NULL AND p.Media_type IS NULL';
} elseif ($post_type === 'images') {
    $post_where[] = "p.Media_type = 'image'";
} elseif ($post_type === 'videos') {
    $post_where[] = "p.Media_type = 'video'";
}
$post_where_sql = implode(' AND ', $post_where);

$page_size = 10;
$offset = ($page - 1) * $page_size;

$event_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM EVENTS e WHERE {$event_where_sql}");
$event_count_stmt->execute($event_params);
$event_total = (int) $event_count_stmt->fetchColumn();

$post_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM POSTS p INNER JOIN EVENTS e ON e.EventID = p.EventID INNER JOIN USERS u ON u.UserID = p.OrganiserID WHERE {$post_where_sql}");
$post_count_stmt->execute($post_params);
$post_total = (int) $post_count_stmt->fetchColumn();
$pages = max(1, (int) ceil(max($event_total, $post_total) / $page_size));
$page = min($page, $pages);
$offset = ($page - 1) * $page_size;

$event_sql = "SELECT e.EventID, e.OrganiserID, e.EventName, e.EventDate, e.Format, e.Province, e.Venue, e.Hashtags,
                     c.CategoryName,
                     (SELECT MIN(t.Price) FROM TICKETS t WHERE t.EventID = e.EventID AND t.QuantityAvailable > 0) AS MinPrice
              FROM EVENTS e
              INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
              WHERE {$event_where_sql}
              ORDER BY e.EventDate ASC, e.EventID ASC
              LIMIT :event_limit OFFSET :event_offset";
$event_stmt = $pdo->prepare($event_sql);
foreach ($event_params as $key => $value) {
    $event_stmt->bindValue($key, $value);
}
$event_stmt->bindValue(':event_limit', $page_size, PDO::PARAM_INT);
$event_stmt->bindValue(':event_offset', $offset, PDO::PARAM_INT);
$event_stmt->execute();
$events = $event_stmt->fetchAll(PDO::FETCH_ASSOC);

$post_sql = "SELECT p.PostID, p.OrganiserID, p.Content, p.Media, p.Media_type, p.Hashtags, p.PostDate,
                    e.EventID, e.EventName, u.FirstName, u.LastName,
                    SUM(CASE WHEN r.ReactionType = 'thumbs_up' THEN 1 ELSE 0 END) AS ThumbsUpTotal,
                    SUM(CASE WHEN r.ReactionType = 'clap' THEN 1 ELSE 0 END) AS ClapTotal,
                    SUM(CASE WHEN r.ReactionType = 'lightbulb' THEN 1 ELSE 0 END) AS LightbulbTotal
             FROM POSTS p
             INNER JOIN EVENTS e ON e.EventID = p.EventID
             INNER JOIN USERS u ON u.UserID = p.OrganiserID
             LEFT JOIN REACTIONS r ON r.PostID = p.PostID
             WHERE {$post_where_sql}
             GROUP BY p.PostID, p.OrganiserID, p.Content, p.Media, p.Media_type, p.Hashtags, p.PostDate,
                      e.EventID, e.EventName, u.FirstName, u.LastName
             ORDER BY p.PostDate DESC, p.PostID DESC
             LIMIT :post_limit OFFSET :post_offset";
$post_stmt = $pdo->prepare($post_sql);
foreach ($post_params as $key => $value) {
    $post_stmt->bindValue($key, $value);
}
$post_stmt->bindValue(':post_limit', $page_size, PDO::PARAM_INT);
$post_stmt->bindValue(':post_offset', $offset, PDO::PARAM_INT);
$post_stmt->execute();
$posts = $post_stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <p>Matching published events and public organiser posts are shown together.</p>
    </section>

    <nav class="tabs" aria-label="Hashtag result type">
        <?php foreach (['all' => 'All', 'events' => 'Events', 'posts' => 'Posts'] as $key => $label): ?>
            <a href="tag.php?<?= http_build_query(array_merge($base, ['view' => $key, 'page' => null]), '', '&', PHP_QUERY_RFC3986) ?>" <?= $view === $key ? 'aria-current="page" class="active"' : '' ?>><?= escape_html($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="filter-panel" aria-labelledby="tag-filter-heading">
        <h2 id="tag-filter-heading">Filter hashtag results</h2>
        <form action="tag.php" method="get">
            <input type="hidden" name="tag" value="<?= escape_html($tag) ?>">
            <input type="hidden" name="view" value="<?= escape_html($view) ?>">

            <label for="category_id">Career field</label>
            <select id="category_id" name="category_id">
                <option value="">All fields</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['CategoryID'] ?>" <?= $category_id === (int) $category['CategoryID'] ? 'selected' : '' ?>><?= escape_html($category['CategoryName']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="province">Province</label>
            <select id="province" name="province">
                <option value="">All provinces</option>
                <?php foreach ($provinces as $item): ?>
                    <option value="<?= escape_html($item) ?>" <?= $province === $item ? 'selected' : '' ?>><?= escape_html($item) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="format">Event format</label>
            <select id="format" name="format">
                <option value="">All formats</option>
                <?php foreach (['online' => 'Online', 'physical' => 'In-person', 'hybrid' => 'Hybrid'] as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $format === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <label for="post_type">Post media</label>
            <select id="post_type" name="post_type">
                <option value="all" <?= $post_type === 'all' ? 'selected' : '' ?>>All post types</option>
                <option value="updates" <?= $post_type === 'updates' ? 'selected' : '' ?>>Event updates</option>
                <option value="images" <?= $post_type === 'images' ? 'selected' : '' ?>>Images</option>
                <option value="videos" <?= $post_type === 'videos' ? 'selected' : '' ?>>Videos</option>
            </select>

            <button class="btn btn-primary" type="submit">Apply filters</button>
            <a class="btn btn-outline" href="tag.php?<?= http_build_query(['tag' => $tag], '', '&', PHP_QUERY_RFC3986) ?>">Clear filters</a>
        </form>
    </section>

    <?php if ($view !== 'posts'): ?>
        <section aria-labelledby="tag-events-heading">
            <h2 id="tag-events-heading">Events (<?= $event_total ?>)</h2>
            <?php if (!$events): ?>
                <div class="empty-state"><p>No published events match this hashtag and the selected event filters.</p></div>
            <?php else: ?>
                <div class="event-grid">
                    <?php foreach ($events as $event): ?>
                        <?php $image = resolve_event_image($pdo, (int) $event['EventID']); ?>
                        <article class="event-card">
                            <img class="event-card__image" src="<?= escape_html(eventnest_url($image)) ?>" alt="Image for <?= escape_html($event['EventName']) ?>" loading="lazy">
                            <div class="event-card__content">
                                <h3><?= escape_html($event['EventName']) ?></h3>
                                <p><?= escape_html(format_datetime($event['EventDate'])) ?> · <?= escape_html(ucfirst($event['Format'])) ?></p>
                                <p><?= escape_html($event['CategoryName']) ?> · <?= escape_html($event['Province'] ?: ($event['Venue'] ?: 'Location to be confirmed')) ?></p>
                                <p><strong><?= $event['MinPrice'] === null ? 'Tickets unavailable' : ((float) $event['MinPrice'] <= 0 ? 'Free' : 'From ' . escape_html(format_money($event['MinPrice']))) ?></strong></p>
                                <p><a href="organiser-profile.php?organiser_id=<?= (int) $event['OrganiserID'] ?>">View organiser</a></p>
                                <a class="btn btn-secondary" href="event-detail.php?event_id=<?= (int) $event['EventID'] ?>">View event</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($view !== 'events'): ?>
        <section aria-labelledby="tag-posts-heading">
            <h2 id="tag-posts-heading">Posts (<?= $post_total ?>)</h2>
            <?php if (!$posts): ?>
                <div class="empty-state"><p>No public posts match this hashtag and the selected post filter.</p></div>
            <?php else: ?>
                <div class="post-grid">
                    <?php foreach ($posts as $post): ?>
                        <?php $safe_media = get_safe_public_media_path($post['Media']); ?>
                        <?php $organiser_name = trim((string) $post['FirstName'] . ' ' . (string) $post['LastName']) ?: 'Verified organiser'; ?>
                        <article class="post-card">
                            <?php if ($post['Media_type'] === 'image' && $safe_media): ?>
                                <img src="<?= escape_html(eventnest_url($safe_media)) ?>" alt="Image post linked to <?= escape_html($post['EventName']) ?>" loading="lazy">
                            <?php elseif ($post['Media_type'] === 'video' && $safe_media): ?>
                                <div class="media-placeholder">Video post</div>
                            <?php endif; ?>
                            <h3><?= $post['Media_type'] ? escape_html(ucfirst($post['Media_type']) . ' post') : 'Event update' ?></h3>
                            <?php if ($post['Content']): ?><p><?= escape_html(mb_strimwidth((string) $post['Content'], 0, 220, '…', 'UTF-8')) ?></p><?php endif; ?>
                            <p>By <a href="organiser-profile.php?organiser_id=<?= (int) $post['OrganiserID'] ?>"><?= escape_html($organiser_name) ?></a> · Linked to <a href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>"><?= escape_html($post['EventName']) ?></a></p>
                            <p>Thumbs up <?= (int) $post['ThumbsUpTotal'] ?> · Lightbulb <?= (int) $post['LightbulbTotal'] ?> · Applause <?= (int) $post['ClapTotal'] ?></p>
                            <a class="btn btn-secondary" href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">View post</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Hashtag result pages">
            <?php if ($page > 1): ?><a href="tag.php?<?= http_build_query(array_merge($base, ['page' => $page - 1]), '', '&', PHP_QUERY_RFC3986) ?>">Previous</a><?php endif; ?>
            <span>Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a href="tag.php?<?= http_build_query(array_merge($base, ['page' => $page + 1]), '', '&', PHP_QUERY_RFC3986) ?>">Next</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>

