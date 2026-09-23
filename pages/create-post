<?php
/*
|--------------------------------------------------------------------------
| EventNest - Event Updates and Media Posts (Organiser)
|--------------------------------------------------------------------------
| Temporary frontend version. Placeholder events and posts stand in for
| POSTS/EVENTS queries until the database is finalised. Every post must
| link to an event (POSTS.EventID is required in the current ERD).
|--------------------------------------------------------------------------
*/

require_once '../shared-components/auth.php';

$current_role = $_SESSION['role'] ?? 'attendee';
$is_organiser = ($current_role === 'organiser');

// Placeholder: organiser's own events, to populate the "Link post to
// event" dropdown. Will be replaced by a query scoped to the logged-in
// organiser's EVENTS rows.
$organiser_events = [
    1 => 'Cloud Careers',
    2 => 'Security Forum',
    3 => 'Graduate Expo',
];

// Placeholder: published posts for this organiser, newest first.
$published_posts = [
    [
        'content'      => 'Venue doors open at 08:15. Bring your booking QR code.',
        'event_name'   => 'Cloud Careers',
        'posted_at'    => '2 hours ago',
        'reactions'    => ['like' => 32, 'idea' => 18, 'applause' => 9],
    ],
    [
        'content'      => 'Speaker programme has been updated in the event details.',
        'event_name'   => 'Security Forum',
        'posted_at'    => 'Yesterday',
        'reactions'    => ['like' => 21, 'idea' => 7, 'applause' => 12],
    ],
];

$current_page = 'posts';
require_once '../shared-components/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventNest - Event Updates and Media Posts</title>
    <link rel="stylesheet" href="../styles/main.css">
    <link rel="stylesheet" href="../styles/responsive.css">
</head>

<body>

    <div class="dashboard-shell">

        <?php require_once '../shared-components/navbar.php'; ?>

        <main class="dashboard-main">

            <header class="dashboard-page-title">
                <div>
                    <span class="eyebrow">EventNest</span>
                    <h1>Event Updates and Media Posts</h1>
                </div>
            </header>

            <?php if (!$is_organiser): ?>

                <div class="form-notice form-notice-warning">
                    This page is intended for verified organisers. Posting will be
                    blocked until organiser verification is confirmed.
                </div>

            <?php endif; ?>

            <!-- ==================================================
                 POST COMPOSER
            =================================================== -->

            <section class="dashboard-panel post-composer">

                <form action="create-post.php" method="post" enctype="multipart/form-data" novalidate>

                    <div class="form-row">

                        <div class="form-group form-group-grow">
                            <label for="linked_event">Link post to event (required)</label>
                            <select id="linked_event" name="event_id" required>
                                <option value="" disabled selected>Select an event</option>
                                <?php foreach ($organiser_events as $event_id => $event_name): ?>
                                    <option value="<?php echo (int) $event_id; ?>">
                                        <?php echo htmlspecialchars($event_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="media-buttons">

                            <label class="btn btn-outline media-upload-btn" for="post_image">
                                Add image
                                <input type="file" id="post_image" name="post_image"
                                       accept="image/*" class="visually-hidden-input">
                            </label>

                            <label class="btn btn-outline media-upload-btn" for="post_video">
                                Add video
                                <input type="file" id="post_video" name="post_video"
                                       accept="video/*" class="visually-hidden-input">
                            </label>

                        </div>

                    </div>

                    <div class="form-group">
                        <label for="post_content" class="visually-hidden">Content</label>
                        <textarea id="post_content" name="content" rows="2"
                                  placeholder="Write text, or add image/video media"></textarea>
                    </div>

                    <div class="form-actions form-actions-right">
                        <button type="submit" class="btn btn-primary">Publish post</button>
                    </div>

                </form>

            </section>


            <!-- ==================================================
                 PUBLISHED POSTS
            =================================================== -->

            <section class="dashboard-panel">

                <div class="panel-header">
                    <h2>Published posts</h2>
                </div>

                <?php if (empty($published_posts)): ?>

                    <p class="empty-state">
                        No posts yet. Updates and media you publish will appear here.
                    </p>

                <?php else: ?>

                    <div class="post-list">

                        <?php foreach ($published_posts as $post): ?>

                            <article class="post-card">

                                <p class="post-content">
                                    <?php echo htmlspecialchars($post['content']); ?>
                                </p>

                                <p class="post-meta">
                                    <?php echo htmlspecialchars($post['event_name']); ?>
                                    &middot;
                                    <?php echo htmlspecialchars($post['posted_at']); ?>
                                </p>

                                <div class="reaction-row">
                                    <span class="reaction-pill">
                                        Like <?php echo (int) $post['reactions']['like']; ?>
                                    </span>
                                    <span class="reaction-pill">
                                        Idea <?php echo (int) $post['reactions']['idea']; ?>
                                    </span>
                                    <span class="reaction-pill">
                                        Applause <?php echo (int) $post['reactions']['applause']; ?>
                                    </span>
                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        </main>

    </div>

    <footer class="dashboard-footer">
        <p>&copy; <?php echo date('Y'); ?> EventNest. Connecting People Through Events.</p>
    </footer>

</body>

</html>
