<?php

declare(strict_types=1);

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/shared-components/functions.php';

$pdo = eventnest_db();

function organiser_posts_url(int $organiser_id, string $post_type, int $page = 1): string
{
    $query = [
        'organiser_id' => $organiser_id,
        'post_type' => $post_type,
    ];

    if ($page > 1) {
        $query['page'] = $page;
    }

    return 'organiser-posts.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

$organiser_id = filter_input(INPUT_GET, 'organiser_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($organiser_id === false || $organiser_id === null) {
    http_response_code(400);
    $page_title = 'Organiser posts';
    require_once __DIR__ . '/shared-components/header.php';
    ?>
    <main class="page-shell organiser-posts-page">
        <section class="empty-state" aria-labelledby="invalid-organiser-heading">
            <h1 id="invalid-organiser-heading">Invalid organiser</h1>
            <p>The organiser link is not valid.</p>
            <a class="btn btn-outline" href="events.php">Browse events</a>
        </section>
    </main>
    <?php
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$post_type = isset($_GET['post_type'])
    ? strtolower(trim_input($_GET['post_type']))
    : 'all';

$allowed_post_types = ['all', 'updates', 'images', 'videos'];

if (!in_array($post_type, $allowed_post_types, true)) {
    $post_type = 'all';
}

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($page === false || $page === null) {
    $page = 1;
}

try {
    $organiser_statement = $pdo->prepare(
        "SELECT UserID, FirstName, LastName, OrganisationName
         FROM USERS
         WHERE UserID = :organiser_id
           AND Role = 'organiser'
           AND VerificationStatus = 'verified'
           AND UserStatus = 'active'
         LIMIT 1"
    );
    $organiser_statement->execute([
        ':organiser_id' => $organiser_id,
    ]);
    $organiser = $organiser_statement->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('EventNest organiser-posts organiser lookup failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('EventNest is temporarily unavailable.');
}

if (!$organiser) {
    http_response_code(404);
    $page_title = 'Organiser posts';
    require_once __DIR__ . '/shared-components/header.php';
    ?>
    <main class="page-shell organiser-posts-page">
        <section class="empty-state" aria-labelledby="organiser-not-found-heading">
            <h1 id="organiser-not-found-heading">Organiser posts unavailable</h1>
            <p>This organiser is not available for public viewing.</p>
            <a class="btn btn-outline" href="events.php">Browse events</a>
        </section>
    </main>
    <?php
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$where = [
    'p.OrganiserID = :organiser_id',
    'e.OrganiserID = p.OrganiserID',
    "e.Status IN ('published', 'completed')",
];

if ($post_type === 'updates') {
    $where[] = 'p.Media IS NULL';
    $where[] = 'p.Media_type IS NULL';
} elseif ($post_type === 'images') {
    $where[] = "p.Media_type = 'image'";
    $where[] = 'p.Media IS NOT NULL';
} elseif ($post_type === 'videos') {
    $where[] = "p.Media_type = 'video'";
    $where[] = 'p.Media IS NOT NULL';
}

$where_sql = implode(' AND ', $where);
$per_page = 12;

try {
    $count_statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM POSTS p
         INNER JOIN EVENTS e ON e.EventID = p.EventID
         WHERE {$where_sql}"
    );
    $count_statement->execute([
        ':organiser_id' => $organiser_id,
    ]);

    $total_posts = (int) $count_statement->fetchColumn();
    $total_pages = max(1, (int) ceil($total_posts / $per_page));
    $page = min((int) $page, $total_pages);
    $offset = ($page - 1) * $per_page;

    $posts_statement = $pdo->prepare(
        "SELECT
            p.PostID,
            p.Content,
            p.Media,
            p.Media_type,
            p.Hashtags,
            p.PostDate,
            p.EventID,
            e.EventName,
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
         WHERE {$where_sql}
         ORDER BY p.PostDate DESC, p.PostID DESC
         LIMIT :limit OFFSET :offset"
    );
    $posts_statement->bindValue(':organiser_id', (int) $organiser_id, PDO::PARAM_INT);
    $posts_statement->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $posts_statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $posts_statement->execute();
    $posts = $posts_statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('EventNest organiser-posts query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('EventNest is temporarily unavailable.');
}

// Keep public post pages consistent with the organiser profile name.
$organiser_name = eventnest_organiser_display_name($organiser);

$tabs = [
    'all' => 'All posts',
    'updates' => 'Event updates',
    'images' => 'Images',
    'videos' => 'Videos',
];

$page_title = $organiser_name . ' - Posts and media';
require_once __DIR__ . '/shared-components/header.php';
?>

<main class="page-shell organiser-posts-page">
    <header class="page-header">
        <p class="eyebrow">Verified organiser</p>
        <h1>Posts and media by <?= escape_html($organiser_name) ?></h1>
        <p>Public event updates, images and videos linked to this organiser's published or completed events.</p>
        <a class="btn btn-outline" href="organiser-profile.php?organiser_id=<?= (int) $organiser_id ?>">
            Back to organiser profile
        </a>
    </header>

    <nav class="tabs" aria-label="Post type filters">
        <?php foreach ($tabs as $value => $label): ?>
            <a
                class="btn <?= $post_type === $value ? 'btn-secondary' : 'btn-outline' ?>"
                href="<?= escape_html(organiser_posts_url((int) $organiser_id, $value, 1)) ?>"
                <?= $post_type === $value ? 'aria-current="page"' : '' ?>
            >
                <?= escape_html($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <section aria-labelledby="organiser-post-results-heading">
        <div class="section-heading">
            <h2 id="organiser-post-results-heading">
                <?= $total_posts === 1 ? '1 public post' : escape_html((string) $total_posts) . ' public posts' ?>
            </h2>
        </div>

        <?php if (empty($posts)): ?>
            <div class="empty-state">
                <h3>No posts found</h3>
                <p>There are no public posts in this category yet.</p>
            </div>
        <?php else: ?>
            <div class="post-grid">
                <?php foreach ($posts as $post): ?>
                    <?php
                    $media_type = $post['Media_type'] !== null ? (string) $post['Media_type'] : null;
                    $safe_media = eventnest_safe_media_path(
                        $post['Media'] !== null ? (string) $post['Media'] : null,
                        $media_type
                    );
                    $hashtags = eventnest_extract_hashtags(
                        $post['Hashtags'] !== null ? (string) $post['Hashtags'] : null
                    );
                    ?>
                    <article class="post-card">
                        <?php if ($media_type === 'image' && $safe_media !== null): ?>
                            <a href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">
                                <img
                                    class="post-card__media"
                                    src="<?= escape_html(eventnest_url($safe_media)) ?>"
                                    alt="Image for <?= escape_html((string) $post['EventName']) ?>"
                                    loading="lazy"
                                >
                            </a>
                        <?php elseif ($media_type === 'video' && $safe_media !== null): ?>
                            <video
                                class="post-card__media"
                                controls
                                preload="metadata"
                                poster="<?= escape_html(eventnest_url('images/eventnest-logo.png')) ?>"
                            >
                                <source src="<?= escape_html(eventnest_url($safe_media)) ?>">
                                Your browser does not support this video.
                            </video>
                        <?php elseif ($media_type !== null): ?>
                            <div class="media-fallback" role="status">Media unavailable</div>
                        <?php endif; ?>

                        <div class="post-card__body">
                            <p class="eyebrow"><?= escape_html(eventnest_post_type_label($media_type)) ?></p>
                            <h3>
                                <a href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">
                                    <?= escape_html((string) $post['EventName']) ?>
                                </a>
                            </h3>

                            <?php if (trim((string) ($post['Content'] ?? '')) !== ''): ?>
                                <p><?= escape_html(eventnest_excerpt((string) $post['Content'], 220)) ?></p>
                            <?php endif; ?>

                            <p class="meta-text">
                                <?= escape_html(format_datetime((string) $post['PostDate'])) ?>
                                ·
                                <a href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>">
                                    <?= escape_html((string) $post['EventName']) ?>
                                </a>
                            </p>

                            <?php if (!empty($hashtags)): ?>
                                <div class="tag-list" aria-label="Hashtags">
                                    <?php foreach ($hashtags as $tag): ?>
                                        <a class="tag" href="tag.php?tag=<?= rawurlencode($tag) ?>">
                                            #<?= escape_html($tag) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <p class="reaction-summary" aria-label="Reaction totals">
                                <span>Thumbs up <?= (int) $post['ThumbsUpTotal'] ?></span>
                                <span>Applause <?= (int) $post['ClapTotal'] ?></span>
                                <span>Lightbulb <?= (int) $post['LightbulbTotal'] ?></span>
                            </p>

                            <a class="btn btn-secondary" href="post-detail.php?post_id=<?= (int) $post['PostID'] ?>">
                                View post
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($total_pages > 1): ?>
        <nav class="pagination" aria-label="Post pages">
            <?php if ($page > 1): ?>
                <a
                    class="btn btn-outline"
                    href="<?= escape_html(organiser_posts_url((int) $organiser_id, $post_type, $page - 1)) ?>"
                >
                    Previous
                </a>
            <?php endif; ?>

            <span>Page <?= (int) $page ?> of <?= (int) $total_pages ?></span>

            <?php if ($page < $total_pages): ?>
                <a
                    class="btn btn-outline"
                    href="<?= escape_html(organiser_posts_url((int) $organiser_id, $post_type, $page + 1)) ?>"
                >
                    Next
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
