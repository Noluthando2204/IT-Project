<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// One viewer lookup and two batched queries, regardless of the number of cards.
function eventnest_post_viewer(PDO $pdo, array $posts): array
{
    $user = auth_current_user();
    $state = ['user' => $user, 'attendee' => $user !== null && $user['Role'] === 'attendee', 'blocked' => [], 'selected' => []];
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
            <a class="social-post__author" href="<?= e(eventnest_url('organiser-profile.php?organiser_id=' . $organiser)) ?>"><?= e($name) ?></a>
            <time datetime="<?= e(date('c', strtotime($post['PostDate']))) ?>"><?= e(format_datetime($post['PostDate'])) ?></time>
        </header>
        <?php if ($media !== null): ?>
            <div class="social-post__media">
                <?php if ($type === 'image'): ?>
                    <?php if (!$detail): ?><a href="<?= e($url) ?>"><?php endif; ?>
                    <img src="<?= e(eventnest_url($media)) ?>" alt="Image shared for <?= e($post['EventName']) ?> by <?= e($name) ?>" <?= $detail ? '' : 'loading="lazy"' ?>>
                    <?php if (!$detail): ?></a><?php endif; ?>
                <?php else: ?>
                    <video controls playsinline preload="metadata" aria-label="Video shared for <?= e($post['EventName']) ?>">
                        <source src="<?= e(eventnest_url($media)) ?>">
                        Your browser cannot play this video.
                    </video>
                <?php endif; ?>
            </div>
        <?php elseif ($type !== null): ?>
            <p class="social-post__fallback" role="status">This <?= $type === 'video' ? 'video' : 'image' ?> is currently unavailable.</p>
        <?php endif; ?>
        <div class="social-post__content">
            <?php if (trim((string) $post['Content']) !== ''): ?>
                <p class="social-post__caption"><?= nl2br(e($detail ? $post['Content'] : eventnest_excerpt($post['Content'], 400))) ?></p>
            <?php endif; ?>
            <?php $tags = eventnest_extract_hashtags($post['Hashtags']); ?>
            <?php if ($tags): ?>
                <div class="social-post__tags" aria-label="Post hashtags">
                    <?php foreach ($tags as $tag): ?>
                        <a href="<?= e(eventnest_url('tag.php?tag=' . rawurlencode($tag) . '&view=posts')) ?>">#<?= e($tag) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p class="social-post__event">Linked event: <a href="<?= e(eventnest_url('event-detail.php?event_id=' . (int) $post['EventID'])) ?>"><?= e($post['EventName']) ?></a></p>
        </div>
        <div class="social-post__actions">
            <div class="reaction-controls" <?= $can_react ? 'data-reaction-widget' : '' ?> data-post-id="<?= $id ?>" data-current-reaction="<?= e($selected ?? '') ?>" data-csrf-token="<?= $can_react ? e(eventnest_csrf_token()) : '' ?>" data-endpoint="<?= e(eventnest_url('reaction.php')) ?>" data-login-url="<?= e(eventnest_url('login.php')) ?>">
                <?php foreach ($options as $reaction => [$icon, $label, $count]): ?>
                    <?php if ($can_react): ?>
                        <button type="button" class="reaction-button<?= $selected === $reaction ? ' is-selected' : '' ?>" data-reaction-button data-reaction-type="<?= e($reaction) ?>" aria-pressed="<?= $selected === $reaction ? 'true' : 'false' ?>" aria-label="<?= e($label) ?>">
                            <span aria-hidden="true"><?= $icon ?></span> <span class="reaction-label"><?= e($label) ?></span> <span data-reaction-count="<?= e($reaction) ?>"><?= (int) ($post[$count] ?? 0) ?></span>
                        </button>
                    <?php elseif ($viewer['user'] === null): ?>
                        <a class="reaction-button" href="<?= e(eventnest_url('login.php')) ?>" aria-label="Log in to react: <?= e($label) ?>"><span aria-hidden="true"><?= $icon ?></span> <?= e($label) ?> <?= (int) ($post[$count] ?? 0) ?></a>
                    <?php else: ?>
                        <span class="social-post__total"><?= $icon ?> <?= e($label) ?> <?= (int) ($post[$count] ?? 0) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
                <span class="social-post__status" role="status" data-reaction-message></span>
            </div>
            <div class="social-post__share" data-post-share data-share-url="<?= e($share_url) ?>" data-share-title="<?= e($post['EventName'] . ' — ' . $name) ?>">
                <button type="button" class="reaction-button" data-share-native>Share</button>
                <button type="button" class="reaction-button" data-share-copy>Copy link</button>
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
