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

function organiser_posts_int(string $key): int
{
    $value = $_GET[$key] ?? '';
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function organiser_posts_tags(?string $hashtags): array
{
    preg_match_all('/#[\p{L}\p{N}_]+/u', (string) $hashtags, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

$organiser_id = organiser_posts_int('organiser_id');
$post_type = isset($_GET['post_type']) && is_string($_GET['post_type']) ? strtolower(trim($_GET['post_type'])) : 'all';
$page = max(1, organiser_posts_int('page') ?: 1);
$allowed_types = ['all', 'updates', 'images', 'videos'];
if (!in_array($post_type, $allowed_types, true)) {
    $post_type = 'all';
}

$org_stmt = $pdo->prepare("SELECT UserID, FirstName, LastName FROM USERS WHERE UserID = :id AND Role = 'organiser' AND VerificationStatus = 'verified' AND UserStatus = 'active' LIMIT 1");
$org_stmt->execute([':id' => $organiser_id]);
$organiser = $org_stmt->fetch(PDO::FETCH_ASSOC);
if (!$organiser) {
    http_response_code(404);
    $page_title = 'Organiser posts unavailable';
    require_once __DIR__ . '/shared-components/header.php';
    echo '<main class="page-content"><div class="empty-state"><h1>Posts unavailable</h1><p>This organiser profile is not public.</p><a class="btn btn-outline" href="events.php">Browse events</a></div></main>';
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$where_type = '';
if ($post_type === 'updates') {
    $where_type = ' AND p.Media IS NULL AND p.Media_type IS NULL';
} elseif ($post_type === 'images') {
    $where_type = " AND p.Media_type = 'image'";
} elseif ($post_type === 'videos') {
    $where_type = " AND p.Media_type = 'video'";
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM POSTS p INNER JOIN EVENTS e ON e.EventID = p.EventID WHERE p.OrganiserID = :id AND e.OrganiserID = p.OrganiserID AND e.Status IN ('published','completed') {$where_type}");
$count_stmt->execute([':id' => $organiser_id]);
$total = (int) $count_stmt->fetchColumn();
$page_size = 12;
$pages = max(1, (int) ceil($total / $page_size));
$page = min($page, $pages);
$offset = ($page - 1) * $page_size;

$sql = "SELECT p.PostID, p.Content, p.Media, p.Media_type, p.Hashtags, p.PostDate,
               e.EventID, e.EventName,
               SUM(CASE WHEN r.ReactionType = 'thumbs_up' THEN 1 ELSE 0 END) AS ThumbsUpTotal,
               SUM(CASE WHEN r.ReactionType = 'clap' THEN 1 ELSE 0 END) AS ClapTotal,
               SUM(CASE WHEN r.ReactionType = 'lightbulb' THEN 1 ELSE 0 END) AS LightbulbTotal
        FROM POSTS p
        INNER JOIN EVENTS e ON e.EventID = p.EventID
        LEFT JOIN REACTIONS r ON r.PostID = p.PostID
        WHERE p.OrganiserID = :id
          AND e.OrganiserID = p.OrganiserID
          AND e.Status IN ('published','completed') {$where_type}
        GROUP BY p.PostID, p.Content, p.Media, p.Media_type, p.Hashtags, p.PostDate, e.EventID, e.EventName
        ORDER BY p.PostDate DESC, p.PostID DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $organiser_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $page_size, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$name = trim((string) $organiser['FirstName'] . ' ' . (string) $organiser['LastName']) ?: 'Verified organiser';
$page_title = $name . ' - Posts and media';
require_once __DIR__ . '/shared-components/header.php';

$tabs = [
    'all' => 'All posts',
    'updates' => 'Event updates',
    'images' => 'Images',
    'videos' => 'Videos',
];
?>
<main class="page-content organiser-posts-page">
    <section class="page-heading">
        <p><a href="organiser-profile.php?organiser_id=<?= $organiser_id ?>">Back to organiser overview</a></p>
        <h1>Events by <?= escape_html($name) ?></h1>
        <p><span class="status status-success">Verified organiser</span></p>
    </section>

    <nav class="tabs" aria-label="Post type">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="<?= $post_type === $key ? 'active' : '' ?>" href="organiser-posts.php?<?= http_build_query(['organiser_id' => $organiser_id, 'post_type' => $key], '', '&', PHP_QUERY_RFC3986) ?>" <?= $post_type === $key ? 'aria-current="page"' : '' ?>><?= escape_html($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$posts): ?>
        <div class="empty-state"><h2>No posts found</h2><p>No public event-linked posts match this view.</p></div>
    <?php else: ?>
        <div class="post-grid">
            <?php foreach ($posts as $post): ?>
                <?php $safe_media = get_safe_public_media_path($post['Media']); ?>
                <article class="post-card">
                    <?php if ($post['Media_type'] === 'image' && $safe_media): ?>
                        <img src="<?= escape_html(eventnest_url($safe_media)) ?>" alt="Image post linked to <?= escape_html($post['EventName']) ?>" loading="lazy">
                    <?php elseif ($post['Media_type'] === 'video' && $safe_media): ?>
                        <video controls preload="metadata"><source src="<?= escape_html(eventnest_url($safe_media)) ?>"></video>
                    <?php endif; ?>

                    <h2><?= $post['Media_type'] ? escape_html(ucfirst($post['Media_type']) . ' post') : 'Event update' ?></h2>
                    <?php if ($post['Content']): ?><p><?= escape_html(mb_strimwidth((string) $post['Content'], 0, 240, '…', 'UTF-8')) ?></p><?php endif; ?>
                    <p>Linked to <a href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>"><?= escape_html($post['EventName']) ?></a> · <?= escape_html(format_datetime($post['PostDate'])) ?></p>

                    <?php if ($post['Hashtags']): ?>
                        <p class="post-tags">
                        <?php foreach (organiser_posts_tags($post['Hashtags']) as $tag): ?>
                            <a href="tag.php?<?= http_build_query(['tag' => ltrim($tag, '#')], '', '&', PHP_QUERY_RFC3986) ?>"><?= escape_html($tag) ?></a>
                        <?php endforeach; ?>
                        </p>
                    <?php endif; ?>

                    <p>Thumbs up <?= (int) $post['ThumbsUpTotal'] ?> · Lightbulb <?= (int) $post['LightbulbTotal'] ?> · Applause <?= (int) $post['ClapTotal'] ?></p>
                    <a class="btn btn-secondary" href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">View post</a>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Post pages">
                <?php if ($page > 1): ?><a href="organiser-posts.php?<?= http_build_query(['organiser_id' => $organiser_id, 'post_type' => $post_type, 'page' => $page - 1], '', '&', PHP_QUERY_RFC3986) ?>">Previous</a><?php endif; ?>
                <span>Page <?= $page ?> of <?= $pages ?></span>
                <?php if ($page < $pages): ?><a href="organiser-posts.php?<?= http_build_query(['organiser_id' => $organiser_id, 'post_type' => $post_type, 'page' => $page + 1], '', '&', PHP_QUERY_RFC3986) ?>">Next</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
