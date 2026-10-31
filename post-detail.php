<?php

declare(strict_types=1);

require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';
require_once __DIR__ . '/shared-components/post-card.php';

$pdo = eventnest_db();
eventnest_ensure_session();

$post_id = filter_input(INPUT_GET, 'post_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($post_id === false || $post_id === null) {
    http_response_code(400);
    $page_title = 'Invalid post';
    require_once __DIR__ . '/shared-components/header.php';
    ?>
    <main class="page-shell post-detail-page">
        <section class="empty-state" aria-labelledby="invalid-post-heading">
            <h1 id="invalid-post-heading">Invalid post</h1>
            <p>The post link is not valid.</p>
            <a class="btn btn-outline" href="events.php">Browse events</a>
        </section>
    </main>
    <?php
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

try {
    $post_statement = $pdo->prepare(
        "SELECT
            p.PostID,
            p.EventID,
            p.OrganiserID,
            p.Content,
            p.Media_type,
            p.Media,
            p.Hashtags,
            p.PostDate,
            e.EventName,
            e.Status AS EventStatus,
            u.FirstName,
            u.LastName,
            u.OrganisationName
         FROM POSTS p
         INNER JOIN EVENTS e
            ON e.EventID = p.EventID
           AND e.OrganiserID = p.OrganiserID
         INNER JOIN USERS u
            ON u.UserID = p.OrganiserID
           AND u.Role = 'organiser'
           AND u.VerificationStatus = 'verified'
           AND u.UserStatus = 'active'
         WHERE p.PostID = :post_id
           AND e.Status IN ('published', 'completed')
         LIMIT 1"
    );
    $post_statement->execute([
        ':post_id' => $post_id,
    ]);
    $post = $post_statement->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('EventNest post-detail lookup failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('EventNest is temporarily unavailable.');
}

if (!$post) {
    http_response_code(404);
    $page_title = 'Post not found';
    require_once __DIR__ . '/shared-components/header.php';
    ?>
    <main class="page-shell post-detail-page">
        <section class="empty-state" aria-labelledby="post-not-found-heading">
            <h1 id="post-not-found-heading">Post not found</h1>
            <p>This post is unavailable or belongs to an event that is not public.</p>
            <a class="btn btn-outline" href="events.php">Browse events</a>
        </section>
    </main>
    <?php
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

try {
    $reaction_statement = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN ReactionType = 'thumbs_up' THEN 1 ELSE 0 END), 0) AS ThumbsUpTotal,
            COALESCE(SUM(CASE WHEN ReactionType = 'clap' THEN 1 ELSE 0 END), 0) AS ClapTotal,
            COALESCE(SUM(CASE WHEN ReactionType = 'lightbulb' THEN 1 ELSE 0 END), 0) AS LightbulbTotal
         FROM REACTIONS
         WHERE PostID = :post_id"
    );
    $reaction_statement->execute([
        ':post_id' => $post_id,
    ]);
    $reaction_totals = $reaction_statement->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $exception) {
    error_log('EventNest post-detail reaction totals failed: ' . $exception->getMessage());
    $reaction_totals = [];
}

$post = array_merge($post, $reaction_totals);
$post_viewer = eventnest_post_viewer($pdo, [$post]);
$page_title = 'Post - ' . $post['EventName'];
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-shell post-detail-page">
    <h1 class="sr-only">Post for <?= e($post['EventName']) ?></h1>
    <?php eventnest_render_post($post, $post_viewer, true); ?>
    <nav class="button-row" aria-label="Post navigation">
        <a href="<?= e(eventnest_url('event-detail.php?event_id=' . (int) $post['EventID'])) ?>">View related event</a>
        <a href="<?= e(eventnest_url('organiser-posts.php?organiser_id=' . (int) $post['OrganiserID'])) ?>">More organiser posts</a>
    </nav>
</main>
<?php eventnest_post_scripts(); require_once __DIR__ . '/shared-components/footer.php'; ?>
