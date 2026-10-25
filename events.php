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

function events_get_string(string $key, int $max = 100): string
{
    $value = $_GET[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return mb_substr(trim($value), 0, $max, 'UTF-8');
}

function events_positive_int(string $key): int
{
    $value = events_get_string($key, 20);
    return ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function events_valid_date(string $value): string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        return '';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : '';
}

function events_query_url(array $params): string
{
    $clean = [];
    foreach ($params as $key => $value) {
        if ($value !== '' && $value !== 0 && $value !== null) {
            $clean[$key] = $value;
        }
    }
    return 'events.php' . ($clean ? '?' . http_build_query($clean, '', '&', PHP_QUERY_RFC3986) : '');
}

function events_tag_list(?string $hashtags): array
{
    preg_match_all('/#[\p{L}\p{N}_]+/u', (string) $hashtags, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

$category_id = events_positive_int('category_id');
$province = events_get_string('province', 100);
$format = events_get_string('format', 20);
$date_from = events_valid_date(events_get_string('date_from', 10));
$date_to = events_valid_date(events_get_string('date_to', 10));
$price_type = events_get_string('price_type', 10);
$ticket_type = events_get_string('ticket_type', 20);
$sort = events_get_string('sort', 20) ?: 'newest';
$page = max(1, events_positive_int('page') ?: 1);

if (!in_array($format, ['', 'online', 'physical', 'hybrid'], true)) {
    $format = '';
}
if (!in_array($price_type, ['', 'free', 'paid'], true)) {
    $price_type = '';
}
if (!in_array($ticket_type, ['', 'General', 'VIP', 'Early Bird', 'Student'], true)) {
    $ticket_type = '';
}
if (!in_array($sort, ['newest', 'event_date', 'event_date_desc'], true)) {
    $sort = 'newest';
}
if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
    $date_from = '';
    $date_to = '';
}

$page_size = 12;
$offset = ($page - 1) * $page_size;

$categories = $pdo->query('SELECT CategoryID, CategoryName FROM CATEGORIES ORDER BY CategoryName')->fetchAll(PDO::FETCH_ASSOC);
$provinces = eventnest_provinces();
if ($province !== '' && !in_array($province, $provinces, true)) {
    $province = '';
}

$where = ["e.Status = 'published'"];
$params = [];

if ($category_id > 0) {
    $where[] = 'e.CategoryID = :category_id';
    $params[':category_id'] = $category_id;
}
if ($province !== '') {
    $where[] = 'e.Province = :province';
    $params[':province'] = $province;
}
if ($format !== '') {
    $where[] = 'e.Format = :format';
    $params[':format'] = $format;
}
if ($date_from !== '') {
    $where[] = 'e.EventDate >= :date_from';
    $params[':date_from'] = $date_from . ' 00:00:00';
}
if ($date_to !== '') {
    $where[] = 'e.EventDate <= :date_to';
    $params[':date_to'] = $date_to . ' 23:59:59';
}
if ($price_type === 'free') {
    $where[] = 'EXISTS (SELECT 1 FROM TICKETS tf WHERE tf.EventID = e.EventID AND tf.Price = 0 AND tf.QuantityAvailable > 0)';
} elseif ($price_type === 'paid') {
    $where[] = 'EXISTS (SELECT 1 FROM TICKETS tp WHERE tp.EventID = e.EventID AND tp.Price > 0 AND tp.QuantityAvailable > 0)';
}
if ($ticket_type !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM TICKETS tt WHERE tt.EventID = e.EventID AND tt.TicketType = :ticket_type AND tt.QuantityAvailable > 0)';
    $params[':ticket_type'] = $ticket_type;
}

$where_sql = implode(' AND ', $where);
$order_sql = match ($sort) {
    'event_date' => 'e.EventDate ASC, e.EventID ASC',
    'event_date_desc' => 'e.EventDate DESC, e.EventID DESC',
    default => 'e.Created_at DESC, e.EventID DESC',
};

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM EVENTS e WHERE {$where_sql}");
$count_stmt->execute($params);
$total = (int) $count_stmt->fetchColumn();
$pages = max(1, (int) ceil($total / $page_size));
if ($page > $pages) {
    $page = $pages;
    $offset = ($page - 1) * $page_size;
}

$sql = "
    SELECT
        e.EventID, e.OrganiserID, e.EventName, e.EventDate, e.Format,
        e.Province, e.Venue, e.Hashtags, c.CategoryName,
        (SELECT MIN(t.Price) FROM TICKETS t WHERE t.EventID = e.EventID AND t.QuantityAvailable > 0) AS MinPrice,
        (SELECT MAX(t.Price) FROM TICKETS t WHERE t.EventID = e.EventID AND t.QuantityAvailable > 0) AS MaxPrice
    FROM EVENTS e
    INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
    WHERE {$where_sql}
    ORDER BY {$order_sql}
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $page_size, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$base_params = compact('category_id', 'province', 'format', 'date_from', 'date_to', 'price_type', 'ticket_type', 'sort');
$page_title = 'Events';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content events-page">
    <section class="page-heading">
        <h1>Career events</h1>
        <p>Browse published EventNest events and narrow the list using the filters below.</p>
    </section>

    <div class="search-layout">
        <aside class="filter-panel" aria-labelledby="events-filter-heading">
            <h2 id="events-filter-heading">Filter events</h2>
            <form action="events.php" method="get">
                <label for="category_id">Career field</label>
                <select id="category_id" name="category_id">
                    <option value="">All career fields</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['CategoryID'] ?>" <?= $category_id === (int) $category['CategoryID'] ? 'selected' : '' ?>>
                            <?= escape_html($category['CategoryName']) ?>
                        </option>
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
                    <option value="online" <?= $format === 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="physical" <?= $format === 'physical' ? 'selected' : '' ?>>In-person</option>
                    <option value="hybrid" <?= $format === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                </select>

                <label for="date_from">From</label>
                <input type="date" id="date_from" name="date_from" value="<?= escape_html($date_from) ?>">

                <label for="date_to">To</label>
                <input type="date" id="date_to" name="date_to" value="<?= escape_html($date_to) ?>">

                <label for="price_type">Ticket price</label>
                <select id="price_type" name="price_type">
                    <option value="">Free or paid</option>
                    <option value="free" <?= $price_type === 'free' ? 'selected' : '' ?>>Free</option>
                    <option value="paid" <?= $price_type === 'paid' ? 'selected' : '' ?>>Paid</option>
                </select>

                <label for="ticket_type">Ticket type</label>
                <select id="ticket_type" name="ticket_type">
                    <option value="">All ticket types</option>
                    <?php foreach (['General', 'VIP', 'Early Bird', 'Student'] as $type): ?>
                        <option value="<?= escape_html($type) ?>" <?= $ticket_type === $type ? 'selected' : '' ?>><?= escape_html($type) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="sort">Sort</label>
                <select id="sort" name="sort">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                    <option value="event_date" <?= $sort === 'event_date' ? 'selected' : '' ?>>Event date: earliest</option>
                    <option value="event_date_desc" <?= $sort === 'event_date_desc' ? 'selected' : '' ?>>Event date: latest</option>
                </select>

                <button type="submit" class="btn btn-primary">Apply filters</button>
                <a class="btn btn-outline" href="events.php">Clear filters</a>
            </form>
        </aside>

        <section class="search-results" aria-label="Published events">
            <h2><?= $total ?> <?= $total === 1 ? 'event' : 'events' ?></h2>

            <?php if (!$events): ?>
                <div class="empty-state">
                    <h3>No published events match these filters</h3>
                    <p>Clear one or more filters to see other career events.</p>
                    <a class="btn btn-outline" href="events.php">Clear filters</a>
                </div>
            <?php else: ?>
                <div class="event-grid">
                    <?php foreach ($events as $event): ?>
                        <?php $image = resolve_event_image($pdo, (int) $event['EventID']); ?>
                        <article class="event-card">
                            <img class="event-card__image" src="<?= escape_html(eventnest_url($image)) ?>" alt="Image for <?= escape_html($event['EventName']) ?>" loading="lazy">
                            <div class="event-card__content">
                                <h3><?= escape_html($event['EventName']) ?></h3>
                                <p><?= escape_html(format_datetime($event['EventDate'])) ?></p>
                                <p><?= escape_html(ucfirst($event['Format'])) ?> · <?= escape_html(eventnest_event_location($event)) ?></p>
                                <p><?= escape_html($event['CategoryName']) ?></p>

                                <?php if ($event['MinPrice'] === null): ?>
                                    <p>Ticket prices not available</p>
                                <?php elseif ((float) $event['MinPrice'] <= 0): ?>
                                    <p><strong><?= (float) $event['MaxPrice'] > 0 ? 'Free and paid tickets' : 'Free' ?></strong></p>
                                <?php else: ?>
                                    <p><strong>From <?= escape_html(format_money($event['MinPrice'])) ?></strong></p>
                                <?php endif; ?>

                                <?php $tags = array_slice(events_tag_list($event['Hashtags']), 0, 5); ?>
                                <?php if ($tags): ?>
                                    <div class="event-card__tags" aria-label="Event hashtags">
                                        <?php foreach ($tags as $tag): ?>
                                            <a href="tag.php?<?= http_build_query(['tag' => ltrim($tag, '#')], '', '&', PHP_QUERY_RFC3986) ?>"><?= escape_html($tag) ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <p><a href="organiser-profile.php?organiser_id=<?= (int) $event['OrganiserID'] ?>">View organiser</a></p>
                                <a class="btn btn-secondary" href="event-detail.php?event_id=<?= (int) $event['EventID'] ?>">View details</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($pages > 1): ?>
                    <nav class="pagination" aria-label="Event pages">
                        <?php if ($page > 1): ?>
                            <a href="<?= escape_html(events_query_url(array_merge($base_params, ['page' => $page - 1]))) ?>">Previous</a>
                        <?php endif; ?>
                        <span>Page <?= $page ?> of <?= $pages ?></span>
                        <?php if ($page < $pages): ?>
                            <a href="<?= escape_html(events_query_url(array_merge($base_params, ['page' => $page + 1]))) ?>">Next</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
