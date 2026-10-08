<?php

declare(strict_types=1);

// Never expose PHP warnings/notices or database details to users/JSON clients.
ini_set('display_errors', '0');

function search_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function search_raw_input(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function search_input(string $key, int $max_length = 100): string
{
    return mb_substr(search_raw_input($key), 0, $max_length, 'UTF-8');
}

function search_enum(string $value, array $allowed, string $default = ''): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}

function search_id(string $value): int
{
    if ($value === '' || !ctype_digit($value)) {
        return 0;
    }

    $id = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    return $id === false ? 0 : $id;
}

function search_date(string $value): string
{
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value
        ? $value
        : '';
}

function search_like(string $value): string
{
    // Backslash is the explicit SQL LIKE escape character.
    return '%' . str_replace(
        ['\\', '%', '_'],
        ['\\\\', '\\%', '\\_'],
        $value
    ) . '%';
}

function search_hashtags(?string $value): array
{
    preg_match_all('/#[\pL\pN_]+/u', (string) $value, $matches);

    return array_values(array_unique($matches[0] ?? []));
}

function search_url(string $path, array $params = []): string
{
    if ($params === []) {
        return $path;
    }

    return $path . '?' . http_build_query(
        $params,
        '',
        '&',
        PHP_QUERY_RFC3986
    );
}

function search_query_url(array $base_params, array $changes = []): string
{
    $params = $base_params;

    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return search_url('search-results.php', $params);
}

/**
 * Resolve ordinary text such as "CloudCareers" to an existing public hashtag.
 * This is used only for full search, never for the JSON suggestion endpoint.
 */
function search_resolve_hashtag(PDO $conn, string $query): ?string
{
    $candidate = ltrim(trim($query), '#');

    if (
        $candidate === ''
        || mb_strlen($candidate, 'UTF-8') > 60
        || !preg_match('/^[\pL\pN_]+$/uD', $candidate)
    ) {
        return null;
    }

    $pattern = search_like('#' . $candidate);

    $stmt = $conn->prepare(
        "SELECT Tags
         FROM (
             SELECT e.Hashtags AS Tags
             FROM EVENTS e
             WHERE e.Status = 'published'
               AND e.Hashtags LIKE :event_term ESCAPE '\\\\'

             UNION ALL

             SELECT p.Hashtags AS Tags
             FROM POSTS p
             INNER JOIN EVENTS e ON e.EventID = p.EventID
             WHERE e.Status = 'published'
               AND p.Hashtags LIKE :post_term ESCAPE '\\\\'
         ) AS public_tags
         LIMIT 80"
    );

    $stmt->execute([
        'event_term' => $pattern,
        'post_term' => $pattern,
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tag_string) {
        foreach (search_hashtags((string) $tag_string) as $tag) {
            $name = mb_substr($tag, 1, null, 'UTF-8');

            if (mb_strtolower($name, 'UTF-8') === mb_strtolower($candidate, 'UTF-8')) {
                return $name;
            }
        }
    }

    return null;
}

header('X-Content-Type-Options: nosniff');

$raw_q = search_raw_input('q');
$q_too_long = mb_strlen($raw_q, 'UTF-8') > 100;
$q = mb_substr($raw_q, 0, 100, 'UTF-8');

$mode = search_enum(
    search_input('mode', 24),
    ['suggest', 'suggestions', 'full'],
    'full'
);
$is_suggest = in_array($mode, ['suggest', 'suggestions'], true);

$raw_category_id = search_input('category_id', 16);
$raw_province = search_input('province', 100);
$raw_format = search_input('format', 15);
$raw_date_from = search_input('date_from', 10);
$raw_date_to = search_input('date_to', 10);
$raw_price_type = search_input('price_type', 10);
$raw_ticket_type = search_input('ticket_type', 20);
$raw_sort = search_input('sort', 20);
$raw_page = search_input('page', 12);

$category_id = search_id($raw_category_id);
$province = $raw_province;
$format = search_enum($raw_format, ['online', 'physical', 'hybrid']);
$date_from = search_date($raw_date_from);
$date_to = search_date($raw_date_to);
$price_type = search_enum($raw_price_type, ['free', 'paid']);
$ticket_type = search_enum(
    $raw_ticket_type,
    ['General', 'VIP', 'Early Bird', 'Student']
);
$sort = search_enum(
    $raw_sort,
    ['newest', 'event_date', 'event_date_desc'],
    'newest'
);
$page = search_id($raw_page) ?: 1;
$page = min($page, 100000);

$page_size = 12;
$errors = [];

if ($q_too_long) {
    $errors[] = 'Search text was limited to 100 characters.';
}

if ($raw_date_from !== '' && $date_from === '') {
    $errors[] = 'The start date was invalid and was ignored.';
}

if ($raw_date_to !== '' && $date_to === '') {
    $errors[] = 'The end date was invalid and was ignored.';
}

if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
    $errors[] = 'The end date must be on or after the start date.';
    $date_from = '';
    $date_to = '';
}

// A search beginning with # belongs on tag.php.
// Only the first valid hashtag token is used.
if (
    !$is_suggest
    && preg_match('/^#([\pL\pN_]{1,60})(?:\s|$)/u', $q, $direct_tag)
) {
    header(
        'Location: ' . search_url('tag.php', ['tag' => $direct_tag[1]]),
        true,
        302
    );
    exit;
}

if ($is_suggest) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

try {
    // connection.php is local-only and must provide PDO in $conn.
    require_once __DIR__ . '/database-config/connection.php';

    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /*
     * JSON LIVE-SUGGESTION MODE
     * Minimum query length: 2 characters.
     * Maximum response: 10 published/valid entries.
     */
    if ($is_suggest) {
        $suggestions = [];

        if (!$q_too_long && mb_strlen($q, 'UTF-8') >= 2) {
            $pattern = search_like($q);

            // Published event names only.
            $stmt = $conn->prepare(
                "SELECT EventID, EventName
                 FROM EVENTS
                 WHERE Status = 'published'
                   AND EventName LIKE :term ESCAPE '\\\\'
                 ORDER BY EventDate ASC, EventID ASC
                 LIMIT 5"
            );
            $stmt->execute(['term' => $pattern]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $event) {
                $suggestions[] = [
                    'type' => 'event',
                    'label' => (string) $event['EventName'],
                    'url' => search_url(
                        'event-detail.php',
                        ['event_id' => (int) $event['EventID']]
                    ),
                ];
            }

            // Only categories that currently have a published event.
            $stmt = $conn->prepare(
                "SELECT DISTINCT c.CategoryID, c.CategoryName
                 FROM CATEGORIES c
                 INNER JOIN EVENTS e
                    ON e.CategoryID = c.CategoryID
                   AND e.Status = 'published'
                 WHERE c.CategoryName LIKE :term ESCAPE '\\\\'
                 ORDER BY c.CategoryName ASC
                 LIMIT 3"
            );
            $stmt->execute(['term' => $pattern]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $category) {
                $suggestions[] = [
                    'type' => 'category',
                    'label' => (string) $category['CategoryName'],
                    'url' => search_url(
                        'events.php',
                        ['category_id' => (int) $category['CategoryID']]
                    ),
                ];
            }

            /*
             * Hashtags are stored as strings.
             * Search tags on published events and posts linked to published events.
             */
            $hashtag_term = search_like(ltrim($q, '#'));

            $stmt = $conn->prepare(
                "SELECT e.Hashtags AS Tags
                 FROM EVENTS e
                 WHERE e.Status = 'published'
                   AND e.Hashtags LIKE :event_term ESCAPE '\\\\'

                 UNION ALL

                 SELECT p.Hashtags AS Tags
                 FROM POSTS p
                 INNER JOIN EVENTS e ON e.EventID = p.EventID
                 WHERE e.Status = 'published'
                   AND p.Hashtags LIKE :post_term ESCAPE '\\\\'

                 LIMIT 40"
            );
            $stmt->execute([
                'event_term' => $hashtag_term,
                'post_term' => $hashtag_term,
            ]);

            $seen_tags = [];

            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tag_string) {
                foreach (search_hashtags((string) $tag_string) as $tag) {
                    $name = mb_substr($tag, 1, null, 'UTF-8');
                    $needle = ltrim($q, '#');

                    if (
                        $needle !== ''
                        && mb_stripos($name, $needle, 0, 'UTF-8') === false
                    ) {
                        continue;
                    }

                    $key = mb_strtolower($name, 'UTF-8');

                    if (isset($seen_tags[$key])) {
                        continue;
                    }

                    $seen_tags[$key] = true;

                    $suggestions[] = [
                        'type' => 'hashtag',
                        'label' => '#' . $name,
                        'url' => search_url('tag.php', ['tag' => $name]),
                    ];

                    if (count($seen_tags) >= 3) {
                        break 2;
                    }
                }
            }
        }

        echo json_encode(
            ['suggestions' => array_slice($suggestions, 0, 10)],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    /*
     * If ordinary text exactly matches an existing public hashtag,
     * use events-and-posts hashtag page.
     */
    if ($q !== '' && !str_starts_with($q, '#')) {
        $resolved_tag = search_resolve_hashtag($conn, $q);

        if ($resolved_tag !== null) {
            header(
                'Location: ' . search_url('tag.php', ['tag' => $resolved_tag]),
                true,
                302
            );
            exit;
        }
    }

    // Filter data shown in the full result page.
    $categories = $conn
        ->query(
            "SELECT CategoryID, CategoryName
             FROM CATEGORIES
             ORDER BY CategoryName ASC"
        )
        ->fetchAll(PDO::FETCH_ASSOC);

    $provinces = $conn
        ->query(
            "SELECT DISTINCT Province
             FROM EVENTS
             WHERE Status = 'published'
               AND Province IS NOT NULL
               AND Province <> ''
             ORDER BY Province ASC"
        )
        ->fetchAll(PDO::FETCH_COLUMN);

    // Validate category against the approved category table.
    $valid_category_ids = array_map(
        'intval',
        array_column($categories, 'CategoryID')
    );

    if ($category_id > 0 && !in_array($category_id, $valid_category_ids, true)) {
        $category_id = 0;
        $errors[] = 'Unknown career field filter was ignored.';
    }

    // Validate province against public EventNest event data.
    if ($province !== '' && !in_array($province, $provinces, true)) {
        $province = '';
        $errors[] = 'Unknown province filter was ignored.';
    }

    $where = ["e.Status = 'published'"];
    $bindings = [];

    if ($q !== '') {
        $where[] =
            "(e.EventName LIKE :name ESCAPE '\\\\'
              OR e.Description LIKE :description ESCAPE '\\\\'
              OR e.Hashtags LIKE :hashtags ESCAPE '\\\\'
              OR c.CategoryName LIKE :category_term ESCAPE '\\\\')";

        $pattern = search_like($q);

        $bindings['name'] = $pattern;
        $bindings['description'] = $pattern;
        $bindings['hashtags'] = $pattern;
        $bindings['category_term'] = $pattern;
    }

    if ($category_id > 0) {
        $where[] = 'e.CategoryID = :category_id';
        $bindings['category_id'] = $category_id;
    }

    if ($province !== '') {
        $where[] = 'e.Province = :province';
        $bindings['province'] = $province;
    }

    if ($format !== '') {
        $where[] = 'e.Format = :format';
        $bindings['format'] = $format;
    }

    if ($date_from !== '') {
        $where[] = 'e.EventDate >= :date_from';
        $bindings['date_from'] = $date_from . ' 00:00:00';
    }

    if ($date_to !== '') {
        $date_to_exclusive = (new DateTimeImmutable($date_to))
            ->modify('+1 day')
            ->format('Y-m-d 00:00:00');

        $where[] = 'e.EventDate < :date_to_exclusive';
        $bindings['date_to_exclusive'] = $date_to_exclusive;
    }

    if ($price_type === 'free') {
        $where[] =
            'EXISTS (
                SELECT 1
                FROM TICKETS tx
                WHERE tx.EventID = e.EventID
                  AND tx.Price = 0
            )';
    } elseif ($price_type === 'paid') {
        $where[] =
            'EXISTS (
                SELECT 1
                FROM TICKETS tx
                WHERE tx.EventID = e.EventID
                  AND tx.Price > 0
            )';
    }

    if ($ticket_type !== '') {
        $where[] =
            'EXISTS (
                SELECT 1
                FROM TICKETS tt
                WHERE tt.EventID = e.EventID
                  AND tt.TicketType = :ticket_type
            )';
        $bindings['ticket_type'] = $ticket_type;
    }

    $where_sql = implode(' AND ', $where);
    $from_sql =
        ' FROM EVENTS e
          INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID ';

    $count_stmt = $conn->prepare(
        'SELECT COUNT(*)' . $from_sql . ' WHERE ' . $where_sql
    );
    $count_stmt->execute($bindings);

    $total = (int) $count_stmt->fetchColumn();
    $pages = max(1, (int) ceil($total / $page_size));
    $page = min($page, $pages);
    $offset = ($page - 1) * $page_size;

    $order_sql = match ($sort) {
        'event_date' => 'e.EventDate ASC, e.EventID ASC',
        'event_date_desc' => 'e.EventDate DESC, e.EventID DESC',
        default => 'e.Created_at DESC, e.EventID DESC',
    };

    /*
     * Event images come from linked POSTS.Media.
     */
    $sql =
        'SELECT
            e.EventID,
            e.EventName,
            e.Description,
            e.EventDate,
            e.Venue,
            e.Province,
            e.Format,
            e.Hashtags,
            e.OrganiserID,
            e.AgeRestriction,
            c.CategoryName,
            (
                SELECT MIN(t.Price)
                FROM TICKETS t
                WHERE t.EventID = e.EventID
            ) AS MinPrice,
            (
                SELECT MAX(t.Price)
                FROM TICKETS t
                WHERE t.EventID = e.EventID
            ) AS MaxPrice,
            (
                SELECT p.Media
                FROM POSTS p
                WHERE p.EventID = e.EventID
                  AND p.Media_type = \'image\'
                  AND p.Media IS NOT NULL
                ORDER BY p.PostDate DESC, p.PostID DESC
                LIMIT 1
            ) AS LatestImage'
        . $from_sql
        . ' WHERE ' . $where_sql
        . ' ORDER BY ' . $order_sql
        . ' LIMIT :limit OFFSET :offset';

    $stmt = $conn->prepare($sql);

    foreach ($bindings as $key => $value) {
        $stmt->bindValue(
            ':' . $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':limit', $page_size, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log('EventNest search-results.php: ' . $error->getMessage());

    if ($is_suggest) {
        http_response_code(503);

        echo json_encode(
            [
                'suggestions' => [],
                'error' => 'Search suggestions are temporarily unavailable.',
            ],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    http_response_code(503);

    $errors[] = 'Search is temporarily unavailable. Please try again later.';
    $events = [];
    $categories = [];
    $provinces = [];
    $total = 0;
    $pages = 1;
    $page = 1;
}

/*
 * Build links from validated/canonical values only.
 * This preserves the approved filters without echoing unknown raw GET keys.
 */
$base_params = [];

if ($q !== '') {
    $base_params['q'] = $q;
}
if ($category_id > 0) {
    $base_params['category_id'] = $category_id;
}
if ($province !== '') {
    $base_params['province'] = $province;
}
if ($format !== '') {
    $base_params['format'] = $format;
}
if ($date_from !== '') {
    $base_params['date_from'] = $date_from;
}
if ($date_to !== '') {
    $base_params['date_to'] = $date_to;
}
if ($price_type !== '') {
    $base_params['price_type'] = $price_type;
}
if ($ticket_type !== '') {
    $base_params['ticket_type'] = $ticket_type;
}
if ($sort !== 'newest') {
    $base_params['sort'] = $sort;
}

$clear_filter_params = $q !== '' ? ['q' => $q] : [];

// Use the shared EventNest shell. scripts/main.js owns filter-panel behaviour.
$page_title = 'Search Events';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="container search-results-page" id="main-content">
    <section class="page-heading" aria-labelledby="search-heading">
        <h1 id="search-heading">
            <?php if ($q === ''): ?>
                Find career events
            <?php else: ?>
                Search results for “<?= search_escape($q) ?>”
            <?php endif; ?>
        </h1>

        <p>
            Explore published career and professional-development events across South Africa.
        </p>

        <form
            action="search-results.php"
            method="get"
            role="search"
            class="search-form"
        >
            <label for="search-q">
                Search career events, career fields or hashtags
            </label>

            <div class="search-form__controls">
                <input
                    id="search-q"
                    name="q"
                    type="search"
                    maxlength="100"
                    value="<?= search_escape($q) ?>"
                    placeholder="Search events, categories or #hashtags"
                    autocomplete="off"
                >

                <button type="submit" class="btn btn-primary">
                    Search
                </button>

                <button
                    type="button"
                    class="btn btn-outline"
                    id="toggle-search-filters"
                    aria-controls="search-filters"
                    aria-expanded="true"
                >
                    Hide filters
                </button>
            </div>
        </form>
    </section>

    <?php if ($errors !== []): ?>
        <div class="alert alert-warning" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= search_escape($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="search-layout">
        <aside
            class="filter-panel"
            id="search-filters"
            aria-labelledby="filter-heading"
        >
            <h2 id="filter-heading">Filter results</h2>

            <form action="search-results.php" method="get">
                <?php if ($q !== ''): ?>
                    <input
                        type="hidden"
                        name="q"
                        value="<?= search_escape($q) ?>"
                    >
                <?php endif; ?>

                <label for="category_id">Career field</label>
                <select id="category_id" name="category_id">
                    <option value="">All fields</option>

                    <?php foreach ($categories as $category): ?>
                        <?php $this_category_id = (int) $category['CategoryID']; ?>
                        <option
                            value="<?= $this_category_id ?>"
                            <?= $category_id === $this_category_id ? 'selected' : '' ?>
                        >
                            <?= search_escape((string) $category['CategoryName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="province">Province</label>
                <select id="province" name="province">
                    <option value="">All provinces</option>

                    <?php foreach ($provinces as $location): ?>
                        <?php $location = (string) $location; ?>
                        <option
                            value="<?= search_escape($location) ?>"
                            <?= $province === $location ? 'selected' : '' ?>
                        >
                            <?= search_escape($location) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="format">Event format</label>
                <select id="format" name="format">
                    <option value="">All formats</option>
                    <option value="online" <?= $format === 'online' ? 'selected' : '' ?>>
                        Online
                    </option>
                    <option value="physical" <?= $format === 'physical' ? 'selected' : '' ?>>
                        In-person
                    </option>
                    <option value="hybrid" <?= $format === 'hybrid' ? 'selected' : '' ?>>
                        Hybrid
                    </option>
                </select>

                <label for="date_from">From date</label>
                <input
                    type="date"
                    id="date_from"
                    name="date_from"
                    value="<?= search_escape($date_from) ?>"
                >

                <label for="date_to">To date</label>
                <input
                    type="date"
                    id="date_to"
                    name="date_to"
                    value="<?= search_escape($date_to) ?>"
                >

                <label for="price_type">Ticket price</label>
                <select id="price_type" name="price_type">
                    <option value="">Free or paid</option>
                    <option value="free" <?= $price_type === 'free' ? 'selected' : '' ?>>
                        Free
                    </option>
                    <option value="paid" <?= $price_type === 'paid' ? 'selected' : '' ?>>
                        Paid
                    </option>
                </select>

                <label for="ticket_type">Ticket type</label>
                <select id="ticket_type" name="ticket_type">
                    <option value="">All ticket types</option>

                    <?php foreach (['General', 'VIP', 'Early Bird', 'Student'] as $type): ?>
                        <option
                            value="<?= search_escape($type) ?>"
                            <?= $ticket_type === $type ? 'selected' : '' ?>
                        >
                            <?= search_escape($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="sort">Sort results</label>
                <select id="sort" name="sort">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>
                        Newest first
                    </option>
                    <option value="event_date" <?= $sort === 'event_date' ? 'selected' : '' ?>>
                        Event date: earliest
                    </option>
                    <option
                        value="event_date_desc"
                        <?= $sort === 'event_date_desc' ? 'selected' : '' ?>
                    >
                        Event date: latest
                    </option>
                </select>

                <button type="submit" class="btn btn-primary">
                    Apply filters
                </button>

                <a
                    href="<?= search_escape(search_url('search-results.php', $clear_filter_params)) ?>"
                    class="btn btn-outline"
                >
                    Clear filters
                </a>
            </form>
        </aside>

        <section class="search-results" aria-label="Matching career events">
            <h2>
                <?= (int) $total ?>
                matching published
                <?= $total === 1 ? 'event' : 'events' ?>
            </h2>

            <?php
            $chips = [];

            foreach ($categories as $category) {
                if ($category_id === (int) $category['CategoryID']) {
                    $chips['category_id'] = (string) $category['CategoryName'];
                    break;
                }
            }

            if ($province !== '') {
                $chips['province'] = $province;
            }
            if ($format !== '') {
                $chips['format'] = ucfirst($format);
            }
            if ($date_from !== '') {
                $chips['date_from'] = 'From ' . $date_from;
            }
            if ($date_to !== '') {
                $chips['date_to'] = 'To ' . $date_to;
            }
            if ($price_type !== '') {
                $chips['price_type'] = ucfirst($price_type);
            }
            if ($ticket_type !== '') {
                $chips['ticket_type'] = $ticket_type;
            }
            ?>

            <?php if ($chips !== []): ?>
                <div class="selected-filters" aria-label="Active filters">
                    <strong>Active filters:</strong>

                    <?php foreach ($chips as $key => $label): ?>
                        <a
                            class="filter-chip"
                            href="<?= search_escape(search_query_url($base_params, [$key => null, 'page' => null])) ?>"
                            aria-label="Remove <?= search_escape($label) ?> filter"
                        >
                            <?= search_escape($label) ?> ×
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($events === []): ?>
                <div class="empty-state">
                    <h3>No matching events found</h3>
                    <p>
                        Try a different keyword, remove a filter,
                        or browse all published events.
                    </p>

                    <a
                        href="<?= search_escape(search_url('search-results.php', $clear_filter_params)) ?>"
                        class="btn btn-outline"
                    >
                        Clear filters
                    </a>

                    <a href="events.php" class="btn btn-secondary">
                        Browse all events
                    </a>
                </div>
            <?php else: ?>
                <div class="event-grid">
                    <?php foreach ($events as $event): ?>
                        <article class="event-card">
                            <?php
                            $image = (string) ($event['LatestImage'] ?? '');

                            /*
                             * Database media is still treated as untrusted output.
                             * Only a local uploaded image path is rendered.
                             */
                            $valid_image =
                                preg_match(
                                    '~^uploaded-content/[a-zA-Z0-9_./-]+\.(?:jpg|jpeg|png|webp)$~iD',
                                    $image
                                ) === 1
                                && !str_contains($image, '..');

                            $image_src = $valid_image
                                ? $image
                                : 'images/eventnest-logo.png';

                            $image_alt = $valid_image
                                ? 'Image for ' . (string) $event['EventName']
                                : 'EventNest event image';
                            ?>

                            <img
                                class="event-card__image"
                                src="<?= search_escape($image_src) ?>"
                                alt="<?= search_escape($image_alt) ?>"
                                loading="lazy"
                            >

                            <div class="event-card__content">
                                <h3>
                                    <?= search_escape((string) $event['EventName']) ?>
                                </h3>

                                <p>
                                    <?= search_escape(
                                        date(
                                            'd M Y, H:i',
                                            strtotime((string) $event['EventDate'])
                                        )
                                    ) ?>
                                </p>

                                <p>
                                    <?= search_escape(ucfirst((string) $event['Format'])) ?>
                                    ·
                                    <?= search_escape(
                                        (string) (
                                            $event['Province']
                                            ?: ($event['Venue'] ?: 'Location to be confirmed')
                                        )
                                    ) ?>
                                </p>

                                <p>
                                    <?= search_escape((string) $event['CategoryName']) ?>
                                </p>

                                <?php if ($event['MinPrice'] === null): ?>
                                    <p>Ticket prices not available</p>
                                <?php elseif ((float) $event['MinPrice'] === 0.0): ?>
                                    <p>
                                        <strong>
                                            Free<?= (float) $event['MaxPrice'] > 0 ? ' tickets available' : '' ?>
                                        </strong>
                                    </p>
                                <?php else: ?>
                                    <p>
                                        <strong>
                                            From R<?= search_escape(
                                                number_format(
                                                    (float) $event['MinPrice'],
                                                    2
                                                )
                                            ) ?>
                                        </strong>
                                    </p>
                                <?php endif; ?>

                                <?php
                                $event_tags = array_slice(
                                    search_hashtags((string) $event['Hashtags']),
                                    0,
                                    5
                                );
                                ?>

                                <?php if ($event_tags !== []): ?>
                                    <div
                                        class="event-card__tags"
                                        aria-label="Event hashtags"
                                    >
                                        <?php foreach ($event_tags as $tag): ?>
                                            <?php
                                            $tag_name = mb_substr(
                                                $tag,
                                                1,
                                                null,
                                                'UTF-8'
                                            );
                                            ?>
                                            <a
                                                href="<?= search_escape(
                                                    search_url(
                                                        'tag.php',
                                                        ['tag' => $tag_name]
                                                    )
                                                ) ?>"
                                            >
                                                <?= search_escape($tag) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <p>
                                    <a
                                        href="<?= search_escape(
                                            search_url(
                                                'organiser-profile.php',
                                                [
                                                    'organiser_id' =>
                                                        (int) $event['OrganiserID'],
                                                ]
                                            )
                                        ) ?>"
                                    >
                                        View organiser
                                    </a>
                                </p>

                                <a
                                    class="btn btn-secondary"
                                    href="<?= search_escape(
                                        search_url(
                                            'event-detail.php',
                                            ['event_id' => (int) $event['EventID']]
                                        )
                                    ) ?>"
                                >
                                    View details
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($pages > 1): ?>
                    <nav class="pagination" aria-label="Search results pages">
                        <?php if ($page > 1): ?>
                            <a
                                href="<?= search_escape(
                                    search_query_url(
                                        $base_params,
                                        ['page' => $page - 1]
                                    )
                                ) ?>"
                            >
                                Previous
                            </a>
                        <?php endif; ?>

                        <span>
                            Page <?= (int) $page ?> of <?= (int) $pages ?>
                        </span>

                        <?php if ($page < $pages): ?>
                            <a
                                href="<?= search_escape(
                                    search_query_url(
                                        $base_params,
                                        ['page' => $page + 1]
                                    )
                                ) ?>"
                            >
                                Next
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php
require_once __DIR__ . '/shared-components/footer.php';

