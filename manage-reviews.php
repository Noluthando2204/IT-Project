<?php
// manage-reviews.php
// Private review page for the logged-in organiser. Only shows reviews on
// events this organiser owns.


require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';

requireOrganiserVerified();

$userId = (int)$_SESSION['user_id'];
$page_title = 'Private reviews';

// Filters from the URL.
$filterEvent   = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT) ?: 0;
$filterRating  = filter_input(INPUT_GET, 'rating', FILTER_VALIDATE_INT) ?: 0;
$filterContact = isset($_GET['contact_permission'])
    ? (int)$_GET['contact_permission']
    : -1;

// Events for the filter dropdown.
$stmt = $pdo->prepare(
    'SELECT EventID, EventName FROM EVENTS
     WHERE OrganiserID = ? ORDER BY EventDate DESC'
);
$stmt->execute([$userId]);
$events = $stmt->fetchAll();

// Build the main query.
$sql = 'SELECT r.ReviewID, r.Rating, r.Comment, r.Tags,
               r.WantsContact, r.ContactEmail, r.ReviewDate,
               r.EventID, r.UserID,
               e.EventName,
               u.FirstName, u.LastName
        FROM REVIEWS r
        JOIN EVENTS e ON e.EventID = r.EventID
        JOIN USERS  u ON u.UserID  = r.UserID
        WHERE e.OrganiserID = ?';
$params = [$userId];

if ($filterEvent > 0) {
    $sql .= ' AND r.EventID = ?';
    $params[] = $filterEvent;
}
if ($filterRating >= 1 && $filterRating <= 5) {
    $sql .= ' AND r.Rating = ?';
    $params[] = $filterRating;
}
if ($filterContact === 0 || $filterContact === 1) {
    $sql .= ' AND r.WantsContact = ?';
    $params[] = $filterContact;
}

$sql .= ' ORDER BY r.ReviewDate DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Summary for the top cards.
$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS total,
            ROUND(AVG(r.Rating), 2) AS avg_rating,
            SUM(CASE WHEN r.WantsContact = 1 THEN 1 ELSE 0 END) AS wants_contact
     FROM REVIEWS r
     JOIN EVENTS e ON e.EventID = r.EventID
     WHERE e.OrganiserID = ?'
);
$stmt->execute([$userId]);
$summary = $stmt->fetch() ?: ['total' => 0, 'avg_rating' => null, 'wants_contact' => 0];

require_once __DIR__ . '/shared-components/header.php';
?>

<div class="dashboard-shell">
    <?php en_render_sidebar(); ?>

    <main id="main-content" class="dashboard-main">
        <h1>Private attendee feedback</h1>

        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Total reviews</span>
                    <strong class="stat-value"><?php echo (int)$summary['total']; ?></strong>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Average rating</span>
                    <strong class="stat-value">
                        <?php
                        echo $summary['avg_rating'] !== null
                            ? en_e(number_format((float)$summary['avg_rating'], 2))
                            : '—';
                        ?>
                    </strong>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Wants contact</span>
                    <strong class="stat-value"><?php echo (int)$summary['wants_contact']; ?></strong>
                </div>
            </article>
        </section>

        <form method="get" class="filter-panel">
            <div class="form-row">
                <div class="form-group">
                    <label for="event_id">Event</label>
                    <select id="event_id" name="event_id">
                        <option value="">All events</option>
                        <?php foreach ($events as $ev): ?>
                            <option value="<?php echo (int)$ev['EventID']; ?>"
                                <?php echo $filterEvent === (int)$ev['EventID'] ? 'selected' : ''; ?>>
                                <?php echo en_e($ev['EventName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="rating">Rating</label>
                    <select id="rating" name="rating">
                        <option value="">Any rating</option>
                        <?php for ($r = 5; $r >= 1; $r--): ?>
                            <option value="<?php echo $r; ?>"
                                <?php echo $filterRating === $r ? 'selected' : ''; ?>>
                                <?php echo $r; ?> star<?php echo $r === 1 ? '' : 's'; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="contact_permission">Contact requested</label>
                    <select id="contact_permission" name="contact_permission">
                        <option value="">Any</option>
                        <option value="1" <?php echo $filterContact === 1 ? 'selected' : ''; ?>>Yes</option>
                        <option value="0" <?php echo $filterContact === 0 ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a class="btn btn-outline" href="manage-reviews.php">Clear</a>
            </div>
        </form>

        <section class="dashboard-panel">
            <?php if (empty($reviews)): ?>
                <p class="empty-state">No reviews match the current filters.</p>
            <?php else: ?>
                <div class="post-list">
                    <?php foreach ($reviews as $r): ?>
                        <article class="post-card">
                            <div class="panel-header">
                                <h2><?php echo en_e($r['EventName']); ?></h2>
                                <span class="status-badge status-<?php echo $r['Rating'] >= 4 ? 'active' : 'pending'; ?>">
                                    <?php echo (int)$r['Rating']; ?> / 5
                                </span>
                            </div>

                            <p class="post-meta">
                                By <?php
                                    echo en_e(trim(
                                        ($r['FirstName'] ?? '') . ' ' . ($r['LastName'] ?? '')
                                    ));
                                ?>
                                on <?php echo en_e(formatDate($r['ReviewDate'])); ?>
                            </p>

                            <?php if (!empty($r['Tags'])): ?>
                                <p class="post-content">
                                    <strong>Compliments:</strong>
                                    <?php echo en_e($r['Tags']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($r['Comment'])): ?>
                                <p class="post-content">
                                    <strong>Comment:</strong>
                                    <?php echo en_e($r['Comment']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ((int)$r['WantsContact'] === 1): ?>
                                <p class="post-meta">
                                    Follow-up requested: <?php echo en_e($r['ContactEmail']); ?>
                                </p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
