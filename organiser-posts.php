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

function organiser_posts_positive_int(mixed $value, int $default = 0): int
{
    $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $result === false ? $default : (int) $result;
}

function organiser_posts_tags(?string $hashtags): array
{
    preg_match_all('/#[\p{L}\p{N}_]{1,60}/u', (string) $hashtags, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

function organiser_posts_excerpt(?string $text, int $limit = 240): string
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

function organiser_posts_url(int $organiser_id, string $post_type, int $page): string
{
    return 'organiser-posts.php?' . http_build_query(
        [
            'organiser_id' => $organiser_id,
            'post_type' => $post_type,
            'page' => max(1, $page),
        ],
        '',
        '&',
        PHP_QUERY_RFC3986
    );
}

$organiser_id = organiser_posts_positive_int($_GET['organiser_id'] ?? null);
if ($organiser_id <= 0) {
    http_response_code(400);
    exit('Invalid organiser.');
}

$allowed_types = ['all', 'updates', 'images', 'videos'];
$post_type = is_string($_GET['post_type'] ?? null)
    ? strtolower(trim((string) $_GET['post_type']))
    : 'all';

if (!in_array($post_type, $allowed_types, true)) {
    $post_type = 'all';
}

$page = organiser_posts_positive_int($_GET['page'] ?? 1, 1);
$page_size = 12;

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
    http_response_code(404);
    $page_title = 'Organiser posts unavailable';
    require_once __DIR__ . '/shared-components/header.php';
    ?>
    <main class="page-content organiser-posts-page">
        <div class="empty-state">
            <h1>Posts unavailable</h1>
            <p>This organiser profile is not public.</p>
            <a class="btn btn-outline" href="events.php">Browse events</a>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$type_sql = '';
if ($post_type === 'updates') {
    $type_sql = ' AND p.Media IS NULL AND p.Media_type IS NULL';
} elseif ($post_type === 'images') {
    $type_sql = " AND p.Media_type = 'image' AND p.Media IS NOT NULL";
} elseif ($post_type === 'videos') {
    $type_sql = " AND p.Media_type = 'video' AND p.Media IS NOT NULL";
}

$count_stmt = $pdo->prepare(
    "SELECT COUNT(*)
       FROM POSTS p
       INNER JOIN EVENTS e
          ON e.EventID = p.EventID
         AND e.OrganiserID = p.OrganiserID
      WHERE p.OrganiserID = :organiser_id
        AND e.Status IN ('published','completed')
        {$type_sql}"
);
$count_stmt->execute([':organiser_id' => $organiser_id]);
$total_posts = (int) $count_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_posts / $page_size));
$page = min($page, $total_pages);
$offset = ($page - 1) * $page_size;

$sql = "SELECT
            p.PostID,
            p.Content,
            p.Media,
            p.Media_type,
            p.Hashtags,
            p.PostDate,
            e.EventID,
            e.EventName,
            COALESCE(SUM(CASE WHEN r.ReactionType = 'thumbs_up' THEN 1 ELSE 0 END), 0) AS ThumbsUpTotal,
            COALESCE(SUM(CASE WHEN r.ReactionType = 'clap' THEN 1 ELSE 0 END), 0) AS ClapTotal,
            COALESCE(SUM(CASE WHEN r.ReactionType = 'lightbulb' THEN 1 ELSE 0 END), 0) AS LightbulbTotal
        FROM POSTS p
        INNER JOIN EVENTS e
           ON e.EventID = p.EventID
          AND e.OrganiserID = p.OrganiserID
        LEFT JOIN REACTIONS r ON r.PostID = p.PostID
        WHERE p.OrganiserID = :organiser_id
          AND e.Status IN ('published','completed')
          {$type_sql}
        GROUP BY
            p.PostID,
            p.Content,
            p.Media,
            p.Media_type,
            p.Hashtags,
            p.PostDate,
            e.EventID,
            e.EventName
        ORDER BY p.PostDate DESC, p.PostID DESC
        LIMIT :limit OFFSET :offset";

$post_stmt = $pdo->prepare($sql);
$post_stmt->bindValue(':organiser_id', $organiser_id, PDO::PARAM_INT);
$post_stmt->bindValue(':limit', $page_size, PDO::PARAM_INT);
$post_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$post_stmt->execute();
$posts = $post_stmt->fetchAll();

$organiser_name = trim(
    (string) $organiser['FirstName'] . ' ' . (string) $organiser['LastName']
);
if ($organiser_name === '') {
    $organiser_name = 'Verified organiser';
}

$tabs = [
    'all' => 'All posts',
    'updates' => 'Event updates',
    'images' => 'Images',
    'videos' => 'Videos',
];

$page_title = $organiser_name . ' - Posts and media';
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content organiser-posts-page">
    <section class="page-heading">
        <p>
            <a class="btn btn-outline" href="organiser-profile.php?organiser_id=<?= $organiser_id ?>">
                Back to organiser overview
            </a>
        </p>
        <h1>Posts by <?= escape_html($organiser_name) ?></h1>
        <p><span class="status status-success">Verified organiser</span></p>
    </section>

    <nav class="tabs" aria-label="Post type">
        <?php foreach ($tabs as $key => $label): ?>
            <a
                class="<?= $post_type === $key ? 'active' : '' ?>"
                href="<?= escape_html(organiser_posts_url($organiser_id, $key, 1)) ?>"
                <?= $post_type === $key ? 'aria-current="page"' : '' ?>
            >
                <?= escape_html($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <section aria-labelledby="organiser-post-list-heading">
        <h2 id="organiser-post-list-heading" class="visually-hidden">Posts and media</h2>

        <?php if (!$posts): ?>
            <div class="empty-state">
                <h2>No posts found</h2>
                <p>No public event-linked posts match this view.</p>
            </div>
        <?php else: ?>
            <div class="post-grid">
                <?php foreach ($posts as $post): ?>
                    <?php
                    $safe_media = get_safe_public_media_path($post['Media']);
                    $excerpt = organiser_posts_excerpt($post['Content']);
                    $tags = organiser_posts_tags($post['Hashtags']);
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

                        <h2>
                            <a href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">
                                <?= $post['Media_type']
                                    ? escape_html(ucfirst((string) $post['Media_type']) . ' post')
                                    : 'Event update' ?>
                            </a>
                        </h2>

                        <?php if ($excerpt !== ''): ?>
                            <p><?= escape_html($excerpt) ?></p>
                        <?php endif; ?>

                        <p>
                            Linked to
                            <a href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>">
                                <?= escape_html($post['EventName']) ?>
                            </a>
                            · <?= escape_html(format_datetime($post['PostDate'])) ?>
                        </p>

                        <?php if ($tags): ?>
                            <p class="post-tags">
                                <?php foreach ($tags as $tag): ?>
                                    <a href="tag.php?<?= http_build_query(['tag' => ltrim($tag, '#')], '', '&', PHP_QUERY_RFC3986) ?>">
                                        <?= escape_html($tag) ?>
                                    </a>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>

                        <p class="reaction-summary" aria-label="Reaction totals">
                            <span>Thumbs up <?= (int) $post['ThumbsUpTotal'] ?></span>
                            <span>Applause <?= (int) $post['ClapTotal'] ?></span>
                            <span>Lightbulb <?= (int) $post['LightbulbTotal'] ?></span>
                        </p>

                        <a class="btn btn-secondary" href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">
                            View post
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($total_pages > 1): ?>
        <nav class="pagination" aria-label="Post pages">
            <?php if ($page > 1): ?>
                <a class="btn btn-outline" href="<?= escape_html(organiser_posts_url($organiser_id, $post_type, $page - 1)) ?>">
                    Previous
                </a>
            <?php endif; ?>

            <span>Page <?= $page ?> of <?= $total_pages ?></span>

            <?php if ($page < $total_pages): ?>
                <a class="btn btn-secondary" href="<?= escape_html(organiser_posts_url($organiser_id, $post_type, $page + 1)) ?>">
                    Next
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
