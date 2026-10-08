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

function post_detail_id(): int
{
    $value = $_GET['post_id'] ?? '';
    return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : 0;
}

function post_detail_tags(?string $hashtags): array
{
    preg_match_all('/#[\p{L}\p{N}_]+/u', (string) $hashtags, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

$post_id = post_detail_id();
$stmt = $pdo->prepare(
    "SELECT p.PostID, p.Content, p.Media, p.Media_type, p.Hashtags, p.PostDate, p.OrganiserID, p.EventID,
            e.EventName, e.Status AS EventStatus,
            u.FirstName, u.LastName, u.Role, u.VerificationStatus, u.UserStatus
     FROM POSTS p
     INNER JOIN EVENTS e ON e.EventID = p.EventID
     INNER JOIN USERS u ON u.UserID = p.OrganiserID
     WHERE p.PostID = :post_id
       AND e.Status IN ('published','completed')
       AND u.Role = 'organiser'
       AND u.VerificationStatus = 'verified'
       AND u.UserStatus = 'active'
     LIMIT 1"
);
$stmt->execute([':post_id' => $post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    $page_title = 'Post not found';
    require_once __DIR__ . '/shared-components/header.php';
    echo '<main class="page-content"><div class="empty-state"><h1>Post not found</h1><p>This public post is not available.</p><a class="btn btn-outline" href="events.php">Browse events</a></div></main>';
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$total_stmt = $pdo->prepare("SELECT ReactionType, COUNT(*) AS Total FROM REACTIONS WHERE PostID = :post_id GROUP BY ReactionType");
$total_stmt->execute([':post_id' => $post_id]);
$totals = ['thumbs_up' => 0, 'clap' => 0, 'lightbulb' => 0];
foreach ($total_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (array_key_exists($row['ReactionType'], $totals)) {
        $totals[$row['ReactionType']] = (int) $row['Total'];
    }
}

$current_reaction = '';
$is_attendee = isset($_SESSION['user_id'], $_SESSION['role']) && $_SESSION['role'] === 'attendee' && is_numeric($_SESSION['user_id']);
if ($is_attendee) {
    $reaction_stmt = $pdo->prepare('SELECT ReactionType FROM REACTIONS WHERE PostID = :post_id AND UserID = :user_id LIMIT 1');
    $reaction_stmt->execute([':post_id' => $post_id, ':user_id' => (int) $_SESSION['user_id']]);
    $current_reaction = (string) ($reaction_stmt->fetchColumn() ?: '');
}

$safe_media = get_safe_public_media_path($post['Media']);
$organiser_name = trim((string) $post['FirstName'] . ' ' . (string) $post['LastName']) ?: 'Verified organiser';
$page_title = $post['Media_type'] ? ucfirst((string) $post['Media_type']) . ' post' : 'Event update';
$csrf_token = get_csrf_token();
require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content post-detail-page">
    <p><a href="organiser-posts.php?organiser_id=<?= (int) $post['OrganiserID'] ?>">Back to <?= escape_html($organiser_name) ?>'s posts and media</a></p>

    <article class="post-detail-card">
        <header>
            <h1><?= $post['Media_type'] ? escape_html(ucfirst((string) $post['Media_type']) . ' post') : 'Event update' ?></h1>
            <p>
                <a href="organiser-profile.php?organiser_id=<?= (int) $post['OrganiserID'] ?>"><?= escape_html($organiser_name) ?></a>
                <span class="status status-success">Verified organiser</span>
            </p>
            <p><a href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>"><?= escape_html($post['EventName']) ?></a> · <?= escape_html(format_datetime($post['PostDate'])) ?></p>
        </header>

        <?php if ($post['Media_type'] === 'image' && $safe_media): ?>
            <figure class="post-media">
                <img src="<?= escape_html(eventnest_url($safe_media)) ?>" alt="Image shared for <?= escape_html($post['EventName']) ?> by <?= escape_html($organiser_name) ?>">
            </figure>
        <?php elseif ($post['Media_type'] === 'video' && $safe_media): ?>
            <div class="post-media video-wrapper">
                <video controls preload="metadata" aria-label="Video post for <?= escape_html($post['EventName']) ?>">
                    <source src="<?= escape_html(eventnest_url($safe_media)) ?>">
                    Your browser cannot play this video. You can still read the post text and event information below.
                </video>
            </div>
        <?php elseif ($post['Media_type'] !== null): ?>
            <div class="alert alert-warning">The media file is unavailable or has an invalid public path.</div>
        <?php endif; ?>

        <?php if ($post['Content']): ?>
            <div class="post-content"><p><?= nl2br(escape_html((string) $post['Content'])) ?></p></div>
        <?php endif; ?>

        <?php $tags = post_detail_tags($post['Hashtags']); ?>
        <?php if ($tags): ?>
            <div class="post-tags" aria-label="Post hashtags">
                <?php foreach ($tags as $tag): ?>
                    <a href="tag.php?<?= http_build_query(['tag' => ltrim($tag, '#')], '', '&', PHP_QUERY_RFC3986) ?>"><?= escape_html($tag) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <section aria-labelledby="reaction-heading">
        <h2 id="reaction-heading">Reactions</h2>
        <div
            class="reaction-bar"
            data-reaction-widget
            data-post-id="<?= $post_id ?>"
            data-current-reaction="<?= escape_html($current_reaction) ?>"
            data-authenticated="<?= $is_attendee ? 'true' : 'false' ?>"
            data-login-url="login.php"
        >
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrf_token) ?>" data-csrf-token>

            <button type="button" class="reaction-button" data-reaction-type="thumbs_up" data-active="<?= $current_reaction === 'thumbs_up' ? 'true' : 'false' ?>" aria-pressed="<?= $current_reaction === 'thumbs_up' ? 'true' : 'false' ?>">
                <span aria-hidden="true">👍</span> <span>Thumbs up</span> <span data-reaction-count="thumbs_up"><?= $totals['thumbs_up'] ?></span>
            </button>
            <button type="button" class="reaction-button" data-reaction-type="clap" data-active="<?= $current_reaction === 'clap' ? 'true' : 'false' ?>" aria-pressed="<?= $current_reaction === 'clap' ? 'true' : 'false' ?>">
                <span aria-hidden="true">👏</span> <span>Applause</span> <span data-reaction-count="clap"><?= $totals['clap'] ?></span>
            </button>
            <button type="button" class="reaction-button" data-reaction-type="lightbulb" data-active="<?= $current_reaction === 'lightbulb' ? 'true' : 'false' ?>" aria-pressed="<?= $current_reaction === 'lightbulb' ? 'true' : 'false' ?>">
                <span aria-hidden="true">💡</span> <span>Lightbulb</span> <span data-reaction-count="lightbulb"><?= $totals['lightbulb'] ?></span>
            </button>
            <p class="sr-only" aria-live="polite" data-reaction-message></p>
        </div>

        <?php if (!$is_attendee): ?>
            <p>Reaction totals are public. <a href="login.php">Log in as an attendee</a> to react.</p>
        <?php endif; ?>
    </section>

    <section class="post-context">
        <h2>Post information</h2>
        <p>Linked event: <a href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>"><?= escape_html($post['EventName']) ?></a></p>
        <p>Post type: <?= $post['Media_type'] ? escape_html($post['Media_type']) : 'event update' ?></p>
    </section>
</main>
<script src="<?= escape_html(eventnest_url('scripts/reactions.js')) ?>" defer></script>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
