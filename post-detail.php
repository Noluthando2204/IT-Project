<?php

declare(strict_types=1);

require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';

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
            u.LastName
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

$totals = [
    'thumbs_up' => (int) ($reaction_totals['ThumbsUpTotal'] ?? 0),
    'clap' => (int) ($reaction_totals['ClapTotal'] ?? 0),
    'lightbulb' => (int) ($reaction_totals['LightbulbTotal'] ?? 0),
];

$current_user = null;
$current_attendee_id = null;
$current_reaction = null;
$reaction_blocked = false;

if (isset($_SESSION['user_id']) && filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT)) {
    $session_user_id = (int) $_SESSION['user_id'];

    try {
        $user_statement = $pdo->prepare(
            "SELECT
                UserID,
                Email,
                Role,
                UserStatus,
                Suspension_count,
                Suspension_start_date
             FROM USERS
             WHERE UserID = :user_id
             LIMIT 1"
        );
        $user_statement->execute([
            ':user_id' => $session_user_id,
        ]);
        $current_user = $user_statement->fetch(PDO::FETCH_ASSOC) ?: null;

        if (
            $current_user !== null &&
            ($current_user['Role'] ?? null) === 'attendee' &&
            ($current_user['UserStatus'] ?? null) === 'active' &&
            (int) ($current_user['Suspension_count'] ?? 0) < 2
        ) {
            $suspension_start = $current_user['Suspension_start_date'] ?? null;
            $suspension_active = false;

            if ($suspension_start !== null && $suspension_start !== '') {
                $started_at = strtotime((string) $suspension_start);
                if ($started_at !== false && $started_at > time() - (30 * 24 * 60 * 60)) {
                    $suspension_active = true;
                }
            }

            if (!$suspension_active) {
                $block_statement = $pdo->prepare(
                    "SELECT 1
                     FROM BLOCKS
                     WHERE OrganiserID = :organiser_id
                       AND BlockedEmail = :blocked_email
                     LIMIT 1"
                );
                $block_statement->execute([
                    ':organiser_id' => (int) $post['OrganiserID'],
                    ':blocked_email' => (string) $current_user['Email'],
                ]);
                $reaction_blocked = $block_statement->fetchColumn() !== false;

                if (!$reaction_blocked) {
                    $current_attendee_id = $session_user_id;

                    $current_reaction_statement = $pdo->prepare(
                        "SELECT ReactionType
                         FROM REACTIONS
                         WHERE PostID = :post_id
                           AND UserID = :user_id
                         LIMIT 1"
                    );
                    $current_reaction_statement->execute([
                        ':post_id' => $post_id,
                        ':user_id' => $current_attendee_id,
                    ]);

                    $reaction_value = $current_reaction_statement->fetchColumn();
                    if (is_string($reaction_value)) {
                        $current_reaction = $reaction_value;
                    }
                }
            }
        }
    } catch (PDOException $exception) {
        error_log('EventNest post-detail attendee state failed: ' . $exception->getMessage());
        $current_user = null;
        $current_attendee_id = null;
        $current_reaction = null;
        $reaction_blocked = false;
    }
}

$media_type = $post['Media_type'] !== null ? (string) $post['Media_type'] : null;
if (!in_array($media_type, [null, 'image', 'video'], true)) {
    $media_type = null;
}

$safe_media = eventnest_safe_media_path(
    $post['Media'] !== null ? (string) $post['Media'] : null,
    $media_type
);
$hashtags = eventnest_extract_hashtags(
    $post['Hashtags'] !== null ? (string) $post['Hashtags'] : null
);
$organiser_name = trim(
    (string) $post['FirstName'] . ' ' . (string) $post['LastName']
);

if ($organiser_name === '') {
    $organiser_name = 'EventNest organiser';
}

$reaction_options = [
    'thumbs_up' => ['icon' => '👍', 'label' => 'Thumbs up'],
    'clap' => ['icon' => '👏', 'label' => 'Applause'],
    'lightbulb' => ['icon' => '💡', 'label' => 'Lightbulb'],
];

$page_title = eventnest_post_type_label($media_type) . ' - ' . (string) $post['EventName'];
require_once __DIR__ . '/shared-components/header.php';
?>

<main class="page-shell post-detail-page">
    <article class="post-detail" aria-labelledby="post-detail-heading">
        <header class="page-header">
            <p class="eyebrow"><?= escape_html(eventnest_post_type_label($media_type)) ?></p>
            <h1 id="post-detail-heading"><?= escape_html((string) $post['EventName']) ?></h1>
            <p>
                Posted by
                <a href="organiser-profile.php?organiser_id=<?= (int) $post['OrganiserID'] ?>">
                    <?= escape_html($organiser_name) ?>
                </a>
                · <?= escape_html(format_datetime((string) $post['PostDate'])) ?>
            </p>
            <div class="button-row">
                <a class="btn btn-outline" href="event-detail.php?event_id=<?= (int) $post['EventID'] ?>">
                    View related event
                </a>
                <a class="btn btn-outline" href="organiser-posts.php?organiser_id=<?= (int) $post['OrganiserID'] ?>">
                    More organiser posts
                </a>
            </div>
        </header>

        <?php if ($media_type === 'image'): ?>
            <?php if ($safe_media !== null): ?>
                <figure class="post-detail__media">
                    <img
                        class="post-media-full"
                        src="<?= escape_html(eventnest_url($safe_media)) ?>"
                        alt="Image shared for <?= escape_html((string) $post['EventName']) ?> by <?= escape_html($organiser_name) ?>"
                    >
                </figure>
            <?php else: ?>
                <div class="media-fallback" role="status">
                    <p>This image is currently unavailable.</p>
                </div>
            <?php endif; ?>
        <?php elseif ($media_type === 'video'): ?>
            <?php if ($safe_media !== null): ?>
                <div class="post-detail__media media-fallback">
                    <video
                        class="post-media-full"
                        controls
                        preload="metadata"
                        poster="<?= escape_html(eventnest_url('images/eventnest-logo.png')) ?>"
                        aria-label="Video for <?= escape_html((string) $post['EventName']) ?>"
                    >
                        <source src="<?= escape_html(eventnest_url($safe_media)) ?>">
                        Your browser could not load this video. The post text and event information remain available below.
                    </video>
                </div>
            <?php else: ?>
                <div class="media-fallback" role="status">
                    <p>This video is currently unavailable.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (trim((string) ($post['Content'] ?? '')) !== ''): ?>
            <section class="content-card" aria-labelledby="post-content-heading">
                <h2 id="post-content-heading">Update</h2>
                <p><?= nl2br(escape_html((string) $post['Content'])) ?></p>
            </section>
        <?php endif; ?>

        <?php if (!empty($hashtags)): ?>
            <section class="content-card" aria-labelledby="post-tags-heading">
                <h2 id="post-tags-heading">Hashtags</h2>
                <div class="tag-list" aria-label="Post hashtags">
                    <?php foreach ($hashtags as $tag): ?>
                        <a class="tag" href="tag.php?tag=<?= rawurlencode($tag) ?>&amp;view=posts">
                            #<?= escape_html($tag) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="content-card" aria-labelledby="reaction-heading">
            <h2 id="reaction-heading">Reactions</h2>

            <?php if ($current_attendee_id !== null): ?>
                <?php $csrf_token = eventnest_csrf_token(); ?>
                <div
                    class="reaction-controls"
                    data-reaction-widget
                    data-reaction-controls
                    data-post-id="<?= (int) $post_id ?>"
                    data-current-reaction="<?= escape_html((string) ($current_reaction ?? '')) ?>"
                    data-authenticated="true"
                    data-login-url="<?= escape_html(eventnest_url('login.php')) ?>"
                    data-csrf-token="<?= escape_html($csrf_token) ?>"
                    data-endpoint="<?= escape_html(eventnest_url('reaction.php')) ?>"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escape_html($csrf_token) ?>"
                        data-csrf-token
                    >

                    <?php foreach ($reaction_options as $reaction_type => $details): ?>
                        <?php
                        if ($current_reaction === $reaction_type) {
                            $reaction_action = 'remove';
                        } elseif ($current_reaction === null) {
                            $reaction_action = 'add';
                        } else {
                            $reaction_action = 'change';
                        }
                        ?>
                        <button
                            class="reaction-button <?= $current_reaction === $reaction_type ? 'is-selected is-active' : '' ?>"
                            type="button"
                            data-reaction-button
                            data-post-id="<?= (int) $post_id ?>"
                            data-reaction-type="<?= escape_html($reaction_type) ?>"
                            data-reaction-action="<?= escape_html($reaction_action) ?>"
                            data-active="<?= $current_reaction === $reaction_type ? 'true' : 'false' ?>"
                            aria-pressed="<?= $current_reaction === $reaction_type ? 'true' : 'false' ?>"
                        >
                            <span aria-hidden="true"><?= escape_html($details['icon']) ?></span>
                            <span><?= escape_html($details['label']) ?></span>
                            <span data-reaction-count="<?= escape_html($reaction_type) ?>">
                                <?= (int) $totals[$reaction_type] ?>
                            </span>
                        </button>
                    <?php endforeach; ?>

                    <p class="sr-only" aria-live="polite" data-reaction-message></p>
                </div>
                <p class="form-help">You can have one active reaction on this post. Selecting the same reaction again removes it.</p>
            <?php else: ?>
                <div class="reaction-summary" aria-label="Reaction totals">
                    <span>👍 Thumbs up <?= (int) $totals['thumbs_up'] ?></span>
                    <span>👏 Applause <?= (int) $totals['clap'] ?></span>
                    <span>💡 Lightbulb <?= (int) $totals['lightbulb'] ?></span>
                </div>

                <?php if ($current_user === null): ?>
                    <p><a href="login.php">Log in with an attendee account</a> to react.</p>
                <?php elseif (($current_user['Role'] ?? null) !== 'attendee'): ?>
                    <p>Reaction controls are available to attendee accounts. Reaction totals remain public.</p>
                <?php elseif ($reaction_blocked): ?>
                    <p>You cannot react to posts from this organiser. Reaction totals remain public.</p>
                <?php else: ?>
                    <p>Your account is not currently eligible to react. Reaction totals remain public.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </article>
</main>

<script src="<?= escape_html(eventnest_url('scripts/reactions.js')) ?>" defer></script>
<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
