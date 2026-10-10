<?php
// post.php
// Backend handler for creating and deleting posts. Called by create-post.php.
//
// Contract:
//   action=create : event_id, content, media_type, hashtags, media, csrf_token
//   action=delete : post_id, csrf_token
//
// TODO: swap to requireOrganiserVerified() after Cynthia fixes auth.php.

require_once __DIR__ . '/shared-components/auth.php';
require_once __DIR__ . '/database-config/connection.php';
require_once __DIR__ . '/shared-components/functions.php';

requireRole('organiser');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// CSRF check. TODO: replace with Cynthia's requireValidCsrf() once ready.
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid request token.');
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? 'create';

/* ---------- DELETE ---------- */
if ($action === 'delete') {
    $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    if (!$postId) {
        header('Location: business-dashboard.php');
        exit;
    }

    // Only the organiser who owns the post may delete it.
    $stmt = $pdo->prepare(
        'SELECT PostID, Media FROM POSTS WHERE PostID = ? AND OrganiserID = ?'
    );
    $stmt->execute([$postId, $userId]);
    $post = $stmt->fetch();

    if (!$post) {
        http_response_code(403);
        exit('Not allowed.');
    }

    // Clean up linked rows first, then the post itself.
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM REACTIONS WHERE PostID = ?')->execute([$postId]);
        $pdo->prepare('DELETE FROM Post_Categories WHERE post_id = ?')->execute([$postId]);
        $pdo->prepare('DELETE FROM POSTS WHERE PostID = ?')->execute([$postId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('post.php delete failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Could not delete post.');
    }

    // Remove the uploaded file if there was one.
    if (!empty($post['Media'])) {
        $filePath = __DIR__ . '/' . ltrim($post['Media'], '/');
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    header('Location: business-dashboard.php?msg=deleted');
    exit;
}

/* ---------- CREATE ---------- */
$eventId   = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
$content   = trim((string)($_POST['content'] ?? ''));
$mediaType = $_POST['media_type'] ?? '';
$hashtags  = trim((string)($_POST['hashtags'] ?? ''));

if (!$eventId) {
    header('Location: create-post.php?msg=invalid');
    exit;
}

// Check the event belongs to this organiser.
$stmt = $pdo->prepare(
    'SELECT EventID FROM EVENTS WHERE EventID = ? AND OrganiserID = ?'
);
$stmt->execute([$eventId, $userId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    exit('Not allowed.');
}

// Media type must be one of the allowed values.
if (!in_array($mediaType, ['', 'image', 'video'], true)) {
    header('Location: create-post.php?msg=invalid');
    exit;
}

$mediaPath = null;

// Handle file upload when a file was chosen.
if (!empty($_FILES['media']['name'])) {
    if ($mediaType === '') {
        header('Location: create-post.php?msg=invalid');
        exit;
    }

    $file = $_FILES['media'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header('Location: create-post.php?msg=invalid');
        exit;
    }

    // 10 MB cap.
    if ($file['size'] > 10 * 1024 * 1024) {
        header('Location: create-post.php?msg=invalid');
        exit;
    }

    $allowed = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'video/mp4'  => 'mp4',
        'video/webm' => 'webm',
    ];

    // Check the real MIME type, not the file extension.
    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!isset($allowed[$mime])) {
        header('Location: create-post.php?msg=invalid');
        exit;
    }
    if ($mediaType === 'image' && strpos($mime, 'image/') !== 0) {
        header('Location: create-post.php?msg=invalid');
        exit;
    }
    if ($mediaType === 'video' && strpos($mime, 'video/') !== 0) {
        header('Location: create-post.php?msg=invalid');
        exit;
    }

    // Random filename so the uploads folder can't be guessed.
    $ext      = $allowed[$mime];
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destDir  = __DIR__ . '/uploaded-content';

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $dest = $destDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        header('Location: create-post.php?msg=invalid');
        exit;
    }
    $mediaPath = 'uploaded-content/' . $safeName;
} else {
    // No upload -> force a text-only post.
    $mediaType = '';
}

// A post must have either content or media.
if ($content === '' && $mediaPath === null) {
    header('Location: create-post.php?msg=invalid');
    exit;
}

// Clean up hashtags (only used for display and search, not for following).
if ($hashtags !== '') {
    $parts = preg_split('/\s+/', $hashtags, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $clean = [];
    foreach ($parts as $tag) {
        $tag = ltrim($tag, '#');
        $tag = preg_replace('/[^A-Za-z0-9_]/', '', $tag);
        if ($tag !== '') {
            $clean[] = '#' . $tag;
        }
    }
    $clean    = array_slice($clean, 0, 20);
    $hashtags = implode(' ', $clean);
    if (strlen($hashtags) > 255) {
        $hashtags = substr($hashtags, 0, 255);
    }
} else {
    $hashtags = null;
}

// Insert the post.
$stmt = $pdo->prepare(
    'INSERT INTO POSTS
        (EventID, OrganiserID, Content, Media_type, Media, Hashtags, PostDate)
     VALUES (?, ?, ?, ?, ?, ?, NOW())'
);
$stmt->execute([
    $eventId,
    $userId,
    $content !== '' ? $content : null,
    $mediaType !== '' ? $mediaType : null,
    $mediaPath,
    $hashtags,
]);

header('Location: business-dashboard.php?msg=posted');
exit;
