<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// One viewer lookup and two batched queries, regardless of the number of cards.
function eventnest_post_viewer(PDO $pdo, array $posts): array
{
    $user = auth_current_user();
    $state = ['user' => $user, 'attendee' => $user !== null && $user['Role'] === 'attendee', 'blocked' => [], 'selected' => [], 'events' => [], 'fields' => []];
    eventnest_post_context($pdo, $posts, $state);
    if (!$state['attendee'] || !$posts) { return $state; }
    $ids = array_values(array_unique(array_column($posts, 'PostID')));
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT PostID, ReactionType FROM REACTIONS WHERE UserID = ? AND PostID IN ($marks)");
    $stmt->execute(array_merge([$user['UserID']], $ids));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $state['selected'][(int) $row['PostID']] = $row['ReactionType']; }
    $organisers = array_values(array_unique(array_column($posts, 'OrganiserID')));
    $marks = implode(',', array_fill(0, count($organisers), '?'));
    $stmt = $pdo->prepare("SELECT OrganiserID FROM BLOCKS WHERE LOWER(BlockedEmail) = LOWER(?) AND OrganiserID IN ($marks)");
    $stmt->execute(array_merge([$user['Email']], $organisers));
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) { $state['blocked'][(int) $id] = true; }
    return $state;
}

// Batched look-ups for the preview cards: the linked event (cover, date, price) and, for general
// updates, the organiser's career field. Failures only hide the previews, never the posts.
function eventnest_post_context(PDO $pdo, array $posts, array &$state): void
{
    try {
        $event_ids = array_values(array_unique(array_filter(array_map(static fn ($p) => (int) ($p['EventID'] ?? 0), $posts))));
        if ($event_ids) {
            $marks = implode(',', array_fill(0, count($event_ids), '?'));
            $stmt = $pdo->prepare(
                "SELECT e.EventID, e.EventName, e.EventDate, e.Format, e.Venue, e.Province, e.ImagePath, e.Status,
                        c.CategoryName,
                        (SELECT MIN(t.Price) FROM TICKETS t WHERE t.EventID = e.EventID) AS MinimumPrice,
                        (SELECT COALESCE(SUM(t.QuantityAvailable), 0) FROM TICKETS t WHERE t.EventID = e.EventID) AS SeatsLeft
                 FROM EVENTS e LEFT JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
                 WHERE e.EventID IN ($marks)"
            );
            $stmt->execute($event_ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $state['events'][(int) $row['EventID']] = $row; }
        }
        $general = array_values(array_unique(array_map(static fn ($p) => (int) $p['OrganiserID'], array_filter($posts, static fn ($p) => empty($p['EventID'])))));
        foreach ($general as $organiser_id) {
            // Prefer the field the organiser actually runs upcoming events in; fall back to their career field.
            $stmt = $pdo->prepare(
                "SELECT c.CategoryID, c.CategoryName, c.ImagePath,
                        (SELECT COUNT(*) FROM EVENTS x WHERE x.CategoryID = c.CategoryID AND x.Status = 'published' AND x.EventDate > NOW()) AS Upcoming
                 FROM CATEGORIES c
                 LEFT JOIN EVENTS e ON e.CategoryID = c.CategoryID AND e.OrganiserID = :organiser AND e.Status = 'published' AND e.EventDate > NOW()
                 LEFT JOIN USERS u ON u.UserID = :organiser2 AND u.CareerField = c.CategoryName
                 WHERE e.EventID IS NOT NULL OR u.UserID IS NOT NULL
                 GROUP BY c.CategoryID, c.CategoryName, c.ImagePath
                 ORDER BY COUNT(e.EventID) DESC, c.CategoryID
                 LIMIT 1"
            );
            $stmt->execute([':organiser' => $organiser_id, ':organiser2' => $organiser_id]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $state['fields'][$organiser_id] = $row; }
        }
    } catch (Throwable $exception) {
        error_log('EventNest post preview context skipped: ' . get_class($exception));
    }
}

// All callers have already applied the existing public-post visibility queries.
function eventnest_render_post(array $post, array $viewer, bool $detail = false): void
{
    $id = (int) $post['PostID'];
    $organiser = (int) $post['OrganiserID'];
    $name = eventnest_organiser_display_name($post);
    $type = $post['Media_type'];
    $media = eventnest_safe_media_path($post['Media'], $type);
    $url = eventnest_url('post-detail.php?post_id=' . $id);
    try { $share_url = eventnest_absolute_url('post-detail.php?post_id=' . $id); }
    catch (DomainException $exception) { $share_url = $url; }
    $share_title = ($post['EventName'] ?? 'General update') . ' — ' . $name;
    $blocked = !empty($viewer['blocked'][$organiser]);
    $can_react = $viewer['attendee'] && !$blocked;
    $selected = $viewer['selected'][$id] ?? null;
    $options = [
        'thumbs_up' => ['👍', 'Thumbs up', 'ThumbsUpTotal'],
        'clap' => ['👏', 'Applause', 'ClapTotal'],
        'lightbulb' => ['💡', 'Lightbulb', 'LightbulbTotal'],
    ];
    ?>
    <article class="post-card social-post<?= $detail ? ' social-post--detail' : '' ?>" aria-label="Post by <?= e($name) ?>">
        <header class="social-post__header">
            <?= eventnest_organiser_avatar($post) ?>
            <a class="social-post__author" href="<?= e(eventnest_url('organiser-profile.php?organiser_id=' . $organiser)) ?>"><?= e($name) ?></a>
            <time datetime="<?= e(date('c', strtotime($post['PostDate']))) ?>"><?= e(format_datetime($post['PostDate'])) ?></time>
        </header>
        <?php if ($media !== null): ?>
            <div class="social-post__media">
                <?php if ($type === 'image'): ?>
                    <?php if (!$detail): ?><a href="<?= e($url) ?>"><?php endif; ?>
                    <img src="<?= e(eventnest_url($media)) ?>" alt="Image shared for <?= e(($post['EventName'] ?? 'General update')) ?> by <?= e($name) ?>" <?= $detail ? '' : 'loading="lazy"' ?>>
                    <?php if (!$detail): ?></a><?php endif; ?>
                <?php else: ?>
                    <video controls playsinline preload="metadata" aria-label="Video shared for <?= e(($post['EventName'] ?? 'General update')) ?>">
                        <source src="<?= e(eventnest_url($media)) ?>">
                        Your browser cannot play this video.
                    </video>
                <?php endif; ?>
            </div>
        <?php elseif ($type !== null): ?>
            <p class="social-post__fallback" role="status">This <?= $type === 'video' ? 'video' : 'image' ?> is currently unavailable.</p>
        <?php endif; ?>
        <div class="social-post__content">
            <?php
            $tags = eventnest_extract_hashtags($post['Hashtags']);
            // Hashtags are shown once as tag links below, so drop trailing copies from the caption text.
            $caption = (string) $post['Content'];
            if ($tags) {
                $caption = rtrim((string) preg_replace('/(?:\s+#(?:' . implode('|', array_map(static fn ($t) => preg_quote((string) $t, '/'), $tags)) . '))+\s*$/iu', '', $caption));
            }
            ?>
            <?php if (trim($caption) !== ''): ?>
                <p class="social-post__caption"><?= nl2br(e($detail ? $caption : eventnest_excerpt($caption, 400))) ?></p>
            <?php endif; ?>
            <?php if ($tags): ?>
                <div class="social-post__tags" aria-label="Post hashtags">
                    <?php foreach ($tags as $tag): ?>
                        <a href="<?= e(eventnest_url('tag.php?tag=' . rawurlencode($tag) . '&view=posts')) ?>">#<?= e($tag) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php
            $linked = $post['EventID'] !== null ? ($viewer['events'][(int) $post['EventID']] ?? null) : null;
            $field = $post['EventID'] === null ? ($viewer['fields'][$organiser] ?? null) : null;
            ?>
            <?php if ($linked): ?>
                <?php
                $upcoming = $linked['Status'] === 'published' && strtotime((string) $linked['EventDate']) > time();
                $price = $linked['MinimumPrice'] === null ? '' : ((float) $linked['MinimumPrice'] <= 0 ? 'Free' : 'From ' . format_money($linked['MinimumPrice']));
                $state_label = $upcoming ? ((int) $linked['SeatsLeft'] > 0 ? 'Booking open' : 'Sold out · waitlist') : ($linked['Status'] === 'completed' ? 'Event completed' : ($linked['Status'] === 'canceled' ? 'Event canceled' : ''));
                ?>
                <a class="post-preview<?= $media === null ? ' post-preview--large' : '' ?>" href="<?= e(eventnest_url('event-detail.php?event_id=' . (int) $linked['EventID'])) ?>" aria-label="Linked event: <?= e($linked['EventName']) ?>">
                    <img class="post-preview__image" src="<?= e(eventnest_image_url($linked['ImagePath'] ?? null)) ?>" alt="" loading="lazy" width="1280" height="720">
                    <span class="post-preview__body">
                        <span class="post-preview__eyebrow"><?= e($linked['CategoryName'] ?? 'Linked event') ?></span>
                        <strong class="post-preview__title"><?= e($linked['EventName']) ?></strong>
                        <span class="post-preview__meta"><?= e(format_datetime($linked['EventDate'])) ?> · <?= e(ucfirst((string) $linked['Format'])) ?> · <?= e(eventnest_event_location($linked)) ?></span>
                        <span class="post-preview__foot">
                            <?php if ($price !== ''): ?><span class="post-preview__price"><?= e($price) ?></span><?php endif; ?>
                            <?php if ($state_label !== ''): ?><span class="post-preview__state<?= $upcoming ? ' is-open' : '' ?>"><?= e($state_label) ?></span><?php endif; ?>
                            <span class="post-preview__cta"><?= $upcoming ? 'View &amp; book →' : 'View event →' ?></span>
                        </span>
                    </span>
                </a>
            <?php elseif ($post['EventID'] !== null): ?>
                <p class="social-post__event">Linked event: <a href="<?= e(eventnest_url('event-detail.php?event_id=' . (int) $post['EventID'])) ?>"><?= e(($post['EventName'] ?? 'Event')) ?></a></p>
            <?php elseif ($field && $media === null): ?>
                <a class="post-preview post-preview--field" href="<?= e(eventnest_url('events.php?category_id=' . (int) $field['CategoryID'])) ?>">
                    <img class="post-preview__image" src="<?= e(eventnest_image_url($field['ImagePath'] ?? null, 'category')) ?>" alt="" loading="lazy" width="1280" height="720">
                    <span class="post-preview__body">
                        <span class="post-preview__eyebrow">Career field</span>
                        <strong class="post-preview__title"><?= e($field['CategoryName']) ?></strong>
                        <span class="post-preview__meta"><?= (int) $field['Upcoming'] === 1 ? '1 upcoming event' : (int) $field['Upcoming'] . ' upcoming events' ?> on EventNest</span>
                        <span class="post-preview__foot"><span class="post-preview__cta">Explore events →</span></span>
                    </span>
                </a>
            <?php else: ?>
                <p class="social-post__event">General update</p>
            <?php endif; ?>
        </div>
        <div class="social-post__actions">
            <div class="reaction-controls" <?= $can_react ? 'data-reaction-widget' : '' ?> data-post-id="<?= $id ?>" data-current-reaction="<?= e($selected ?? '') ?>" data-csrf-token="<?= $can_react ? e(eventnest_csrf_token()) : '' ?>" data-endpoint="<?= e(eventnest_url('reaction.php')) ?>" data-login-url="<?= e(eventnest_url('login.php')) ?>">
                <?php foreach ($options as $reaction => [$icon, $label, $count]): ?>
                    <?php if ($can_react): ?>
                        <button type="button" class="reaction-button<?= $selected === $reaction ? ' is-selected' : '' ?>" data-reaction-button data-reaction-type="<?= e($reaction) ?>" aria-pressed="<?= $selected === $reaction ? 'true' : 'false' ?>" aria-label="<?= e($label) ?>">
                            <span aria-hidden="true"><?= $icon ?></span> <span class="reaction-label"><?= e($label) ?></span> <span title="<?= (int) ($post[$count] ?? 0) ?> reactions" data-reaction-count="<?= e($reaction) ?>"><?= eventnest_compact_count((int) ($post[$count] ?? 0)) ?></span>
                        </button>
                    <?php elseif ($viewer['user'] === null): ?>
                        <a class="reaction-button" href="<?= e(eventnest_url('login.php')) ?>" aria-label="Log in to react: <?= e($label) ?>"><span aria-hidden="true"><?= $icon ?></span> <?= e($label) ?> <?= eventnest_compact_count((int) ($post[$count] ?? 0)) ?></a>
                    <?php else: ?>
                        <span class="social-post__total" title="<?= (int) ($post[$count] ?? 0) ?> reactions"><?= $icon ?> <?= e($label) ?> <?= eventnest_compact_count((int) ($post[$count] ?? 0)) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
                <span class="social-post__status" role="status" data-reaction-message></span>
            </div>
            <div class="social-post__share" data-post-share data-share-url="<?= e($share_url) ?>" data-share-title="<?= e(($post['EventName'] ?? 'General update') . ' — ' . $name) ?>">
                <button type="button" class="reaction-button" data-share-native>Share</button>
                <span class="social-post__status" role="status" data-share-message></span>
            </div>
        </div>
        <?php if ($blocked): ?><p class="social-post__notice">You cannot react to posts from this organiser.</p><?php endif; ?>
        <?php if (!$detail): ?><footer class="social-post__footer"><a href="<?= e($url) ?>">View full post →</a></footer><?php endif; ?>
    </article>
    <?php
}

function eventnest_post_scripts(): void
{
    foreach (['reactions', 'post-sharing'] as $script) {
        echo '<script src="' . e(eventnest_url('scripts/' . $script . '.js')) . '" defer></script>';
    }
}
