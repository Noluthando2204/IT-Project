<?php
// create-post.php
// Form for an approved organiser to write an event-linked post.
// Actual saving is done by post.php.
//
// Contract (coding guide p.25):
// create-post.php -> post.php
// POST event_id, content, media, media_type, hashtags, csrf_token

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';

requireOrganiserVerified();

$userId = (int)$_SESSION['user_id'];
$page_title = 'Create post';

// CSRF token. TODO: replace with Cynthia's csrfField() once it lands.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Only load events owned by this organiser.
$stmt = $pdo->prepare(
    'SELECT EventID, EventName
     FROM EVENTS
     WHERE OrganiserID = ?
     ORDER BY EventDate DESC'
);
$stmt->execute([$userId]);
$events = $stmt->fetchAll();

$formEventId = (int)($_GET['event_id'] ?? 0);
$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/shared-components/header.php';
?>

<div class="dashboard-shell">
    <?php en_render_sidebar(); ?>

    <main id="main-content" class="dashboard-main">
        <h1>Create a post</h1>

        <?php if ($msg === 'saved'): ?>
            <div class="form-notice form-notice-success" role="status">Post published.</div>
        <?php elseif ($msg === 'invalid'): ?>
            <div class="form-notice form-notice-error" role="alert">
                Post could not be saved. Please check the fields and try again.
            </div>
        <?php endif; ?>

        <?php if (empty($events)): ?>
            <div class="empty-state">
                <p>You need at least one event before you can create a post.</p>
                <p><a class="btn btn-primary" href="create-event.php">Create an event</a></p>
            </div>
        <?php else: ?>
        <form method="post" action="post.php"
              enctype="multipart/form-data"
              class="dashboard-panel">

            <input type="hidden" name="csrf_token"
                   value="<?php echo en_e($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="event_id">Event</label>
                <select id="event_id" name="event_id" required>
                    <option value="">Select an event…</option>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?php echo (int)$ev['EventID']; ?>"
                            <?php echo $formEventId === (int)$ev['EventID'] ? 'selected' : ''; ?>>
                            <?php echo en_e($ev['EventName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="content">Post text (required for a written Event update)</label>
                <textarea id="content" name="content" rows="6"
                          maxlength="5000"></textarea>
            </div>

            <div class="form-group">
                <label for="media_type">Media type</label>
                <select id="media_type" name="media_type">
                    <option value="">None — written Event update</option>
                    <option value="image">Image</option>
                    <option value="video">Video</option>
                </select>
            </div>

            <div class="form-group">
                <label for="media">Media file (image or video)</label>
                <input id="media" name="media" type="file"
                       accept="image/png,image/jpeg,image/webp,video/mp4,video/webm">
                <span class="field-hint">Max 10 MB. PNG, JPG, WebP, MP4 or WebM.</span>
            </div>

            <div class="form-group">
                <label for="hashtags">Hashtags</label>
                <input id="hashtags" name="hashtags" type="text"
                       maxlength="255" placeholder="#Finance #Tech">
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Publish post</button>
                <a class="btn btn-outline" href="business-dashboard.php">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
