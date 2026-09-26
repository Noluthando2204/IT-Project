<?php
// post.php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$submitted_csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (empty($submitted_csrf) || $submitted_csrf !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$user_id = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $content = trim(strip_tags($_POST['content'] ?? ''));

    if ($content === '') {
        http_response_code(400);
        exit('Posts must contain text content.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO posts (user_id, content, created_at, updated_at)
         VALUES (:user_id, :content, NOW(), NOW())'
    );
    $stmt->execute([
        'user_id' => $user_id,
        'content' => $content
    ]);

    header('Location: /profile.php?posted=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);

    if (!$post_id) {
        http_response_code(400);
        exit('Invalid post ID.');
    }

    // Relying on ON DELETE CASCADE in the final schema for reactions
    $stmt = $pdo->prepare(
        'DELETE FROM posts WHERE post_id = :post_id AND user_id = :user_id'
    );
    $stmt->execute(['post_id' => $post_id, 'user_id' => $user_id]);

    header('Location: /profile.php?deleted=1');
    exit;
}

header('Location: /profile.php');
exit;
