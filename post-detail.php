<?php
/**
 * EventNest - post-detail.php
 * Public detail page for one event-linked written/image/video organiser post.
 */

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

function post_detail_positive_id(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : (int) $id;
}

function post_detail_tags(?string $hashtags): array
{
    preg_match_all('/#[\p{L}\p{N}_]{1,60}/u', (string) $hashtags, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

function post_detail_can_react(?array $user): bool
{
    if (!$user) {
        return false;
    }

    if (($user['Role'] ?? null) !== 'attendee' || ($user['UserStatus'] ?? null) !== 'active') {
        return false;
    }

    if ((int) ($user['Suspension_count'] ?? 0) >= 2) {
        return false;
    }

    $suspension_start = $user['Suspension_start_date'] ?? null;
    if ($suspension_start !== null && $suspension_start !== '') {
        $started_at = strtotime((string) $suspension_start);
        if ($started_at !== false && $started_at > time() - (30 * 24 * 60 * 60)) {
            return false;
        }
    }

    return true;
}

$post_id = post_detail_positive_id($_GET['post_id'] ?? null);
if ($post_id <= 0) {
    http_response_code(400);
    exit('Invalid post.');
}

$post_stmt = $pdo->prepare(
    "SELECT
        p.PostID,
        p.Content,
        p.Media,
        p.Media_type,
        p.Hashtags,
        p.PostDate,
        p.OrganiserID,
        p.EventID,
        e.EventName,
        e.Status AS EventStatus,
        u.FirstName,
        u.LastName,
        u.Role,
        u.VerificationStatus,
        u.UserStatus
     FROM POSTS p
     INNER JOIN EVENTS e
        ON e.EventID = p.EventID
       AND e.OrganiserID = p.OrganiserID
     INNER JOIN USERS u ON u.UserID = p.OrganiserID
     WHERE p.PostID = :post_id
       AND e.Status IN ('published','completed')
       AND u.Role = 'organiser'
       AND u.VerificationStatus = 'verified'
       AND u.UserStatus = 'active'
     LIMIT 1"
);
$post_stmt->execute([':post_id' => $post_id]);
$post = $post_stmt->fetch();

if (!$post) {
    http_response_code(404);
    $page_title = 'Post not found';
    require_once __DIR__ . '/shared-components/header.php';
    ?>
    <main class="page-content post-detail-page">
        <div class="empty-state">
            <h1>Post not found</h1>
            <p>This public post is not available.</p>
            <a class="btn btn-outline" href="events.php">Browse events</a>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/shared-components/footer.php';
    exit;
}

$total_stmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN ReactionType = 'thumbs_up' THEN 1 ELSE 0 END), 0) AS ThumbsUpTotal,
        COALESCE(SUM(CASE WHEN ReactionType = 'clap' THEN 1 ELSE 0 END), 0) AS ClapTotal,
        COALESCE(SUM(CASE WHEN ReactionType = 'lightbulb' THEN 1 ELSE 0 END), 0) AS LightbulbTotal
     FROM REACTIONS
     WHERE PostID = :post_id"
);
$total_stmt->execute([':post_id' => $post_id]);
$totals = $total_stmt->fetch() ?: [
    'ThumbsUpTotal' => 0,
    'ClapTotal' => 0,
    'LightbulbTotal' => 0,
];

$current_user = null;
$current_reaction = '';
$can_react = false;

if (!empty($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_stmt = $pdo->prepare(
        "SELECT UserID, Role, UserStatus, Suspension_count, Suspension_start_date
           FROM USERS
          WHERE UserID = :user_id
          LIMIT 1"
    );
    $user_stmt->execute([':user_id' => (int) $_SESSION['user_id']]);
    $current_user = $user_stmt->fetch() ?: null;
    $can_react = post_detail_can_react($current_user);

    if ($can_react) {
        $reaction_stmt = $pdo->prepare(
            "SELECT ReactionType
               FROM REACTIONS
              WHERE PostID = :post_id
                AND UserID = :user_id
              LIMIT 1"
        );
        $reaction_stmt->execute([
            ':post_id' => $post_id,
            ':user_id' => (int) $current_user['UserID'],
        ]);
        $current_reaction = (string) ($reaction_stmt->fetchColumn() ?: '');
    }
}

$safe_media = get_safe_public_media_path($post['Media']);
$organiser_name = trim((string) $post['FirstName'] . ' ' . (string) $post['LastName']);
if ($organiser_name === '') {
    $organiser_name = 'Verified organiser';
}

$csrf_token = get_csrf_token();
$reaction_definitions = [
    'thumbs_up' => [
        'label' => 'Thumbs up',
        'icon' => '👍',
        'count' => (int) $totals['ThumbsUpTotal'],
    ],
    'clap' => [
        'label' => 'Applause',
        'icon' => '👏',
        'count' => (int) $totals['ClapTotal'],
    ],
    'lightbulb' => [
        'label' => 'Lightbulb',
        'icon' => '💡',
        'count' => (int) $totals['LightbulbTotal'],
    ],
];

$page_title = $post['Media_type']
    ? ucfirst((string) $post['Media_type']) . ' post'
    : 'Event update';

require_once __DIR__ . '/shared-components/header.php';
?>
<main class="page-content post-detail-page">
    <p>
        <a class="btn btn-outline" href="organiser-posts.php?organiser_id=<?= (int) $post['OrganiserID'] ?>">
            Back to <?= escape_html($organiser_name) ?>'s posts and media
        </a>
    </p>

    <article class="post-detail-card" aria-labelledby="post-detail-heading">
        <header>
            <h1 id="post-detail-heading">
                <?= $post['Media_type']
                    ? escape_html(ucfirst((string) $post['Media_type']) . ' post')
                    : 'Event update' ?>
            </h1>
            <p>
                <a href="organiser-profile.php?organiser_id=<?= (int) $post['OrganiserID'] ?>">
                    <?= escape_html($organiser_name) ?>
                </a>
                <span class="status status-success">Verified organiser</span>
            </p>
            <p>
                Linked event:
                <a href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>">
                    <?= escape_html($post['EventName']) ?>
                </a>
                · <?= escape_html(format_datetime($post['PostDate'])) ?>
            </p>
        </header>

        <?php if ($post['Media_type'] === 'image'): ?>
            <?php if ($safe_media): ?>
                <figure class="post-media">
                    <img
                        class="post-media-image post-media-full"
                        src="<?= escape_html(eventnest_url($safe_media)) ?>"
                        alt="Image shared for <?= escape_html($post['EventName']) ?> by <?= escape_html($organiser_name) ?>"
                    >
                </figure>
            <?php else: ?>
                <div class="alert alert-warning">The image is unavailable or has an invalid public path.</div>
            <?php endif; ?>
        <?php elseif ($post['Media_type'] === 'video'): ?>
            <?php if ($safe_media): ?>
                <div class="post-media video-wrapper">
                    <video
                        class="post-media-video post-media-full"
                        controls
                        preload="metadata"
                        aria-label="Video post for <?= escape_html($post['EventName']) ?>"
                    >
                        <source src="<?= escape_html(eventnest_url($safe_media)) ?>">
                        Your browser cannot play this video. You can still read the post text and event information below.
                    </video>
                    <noscript>
                        <p>Video playback requires browser media support. The post text remains available below.</p>
                    </noscript>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">The video is unavailable or has an invalid public path.</div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (trim((string) $post['Content']) !== ''): ?>
            <div class="post-content">
                <p><?= nl2br(escape_html((string) $post['Content'])) ?></p>
            </div>
        <?php endif; ?>

        <?php $tags = post_detail_tags($post['Hashtags']); ?>
        <?php if ($tags): ?>
            <div class="post-tags" aria-label="Post hashtags">
                <?php foreach ($tags as $tag): ?>
                    <a href="tag.php?<?= http_build_query(['tag' => ltrim($tag, '#'), 'view' => 'posts'], '', '&', PHP_QUERY_RFC3986) ?>">
                        <?= escape_html($tag) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <section aria-labelledby="reaction-heading">
        <h2 id="reaction-heading">Reactions</h2>

        <div
            class="reaction-bar"
            data-reaction-widget
            data-reaction-controls
            data-post-id="<?= $post_id ?>"
            data-current-reaction="<?= escape_html($current_reaction) ?>"
            data-authenticated="<?= $can_react ? 'true' : 'false' ?>"
            data-login-url="<?= escape_html(eventnest_url('login.php')) ?>"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape_html($csrf_token) ?>"
                data-csrf-token
            >

            <?php foreach ($reaction_definitions as $reaction_type => $definition): ?>
                <?php
                $is_active = $current_reaction === $reaction_type;
                $reaction_action = $is_active
                    ? 'remove'
                    : ($current_reaction === '' ? 'add' : 'change');
                ?>

                <?php if ($can_react || !$current_user): ?>
                    <button
                        type="button"
                        class="reaction-button <?= $is_active ? 'is-active' : '' ?>"
                        data-reaction-button
                        data-post-id="<?= $post_id ?>"
                        data-reaction-type="<?= escape_html($reaction_type) ?>"
                        data-reaction-action="<?= escape_html($reaction_action) ?>"
                        data-active="<?= $is_active ? 'true' : 'false' ?>"
                        aria-pressed="<?= $is_active ? 'true' : 'false' ?>"
                    >
                        <span aria-hidden="true"><?= $definition['icon'] ?></span>
                        <span><?= escape_html($definition['label']) ?></span>
                        <span data-reaction-count="<?= escape_html($reaction_type) ?>"><?= (int) $definition['count'] ?></span>
                    </button>
                <?php else: ?>
                    <span class="reaction-total">
                        <span aria-hidden="true"><?= $definition['icon'] ?></span>
                        <span><?= escape_html($definition['label']) ?></span>
                        <span data-reaction-count="<?= escape_html($reaction_type) ?>"><?= (int) $definition['count'] ?></span>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>

            <p class="sr-only" aria-live="polite" data-reaction-message></p>
        </div>

        <?php if (!$current_user): ?>
            <p>Reaction totals are public. <a href="login.php">Log in as an attendee</a> to react.</p>
        <?php elseif (($current_user['Role'] ?? null) !== 'attendee'): ?>
            <p>Reaction controls are available to attendee accounts. Reaction totals remain public.</p>
        <?php elseif (!$can_react): ?>
            <p>Your account is not currently eligible to react. Reaction totals remain public.</p>
        <?php endif; ?>
    </section>

    <section class="post-context">
        <h2>Post information</h2>
        <p>
            Linked event:
            <a href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>">
                <?= escape_html($post['EventName']) ?>
            </a>
        </p>
        <p>Post type: <?= $post['Media_type'] ? escape_html((string) $post['Media_type']) : 'event update' ?></p>
    </section>
</main>
<script src="<?= escape_html(eventnest_url('scripts/reactions.js')) ?>" defer></script>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
