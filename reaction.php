<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
header('Content-Type: application/json; charset=UTF-8');
function reaction_response(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    reaction_response(405, ['success' => false, 'message' => 'Method not allowed.']);
}
$user = auth_current_user();
if ($user === null) {
    reaction_response(401, ['success' => false, 'login_required' => true]);
}
if ($user['Role'] !== 'attendee' || !eventnest_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    reaction_response(403, ['success' => false, 'message' => 'The request is not permitted.']);
}
$post_id = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$type = $_POST['reaction_type'] ?? '';
$action = $_POST['reaction_action'] ?? 'add';
if (!$post_id || !in_array($type, ['thumbs_up', 'clap', 'lightbulb'], true)
    || !in_array($action, ['add', 'change', 'remove'], true)) {
    reaction_response(400, ['success' => false, 'message' => 'Invalid reaction.']);
}
$pdo = eventnest_db();
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT p.PostID FROM POSTS p
        INNER JOIN EVENTS e ON e.EventID = p.EventID AND e.OrganiserID = p.OrganiserID
        INNER JOIN USERS u ON u.UserID = p.OrganiserID
        WHERE p.PostID = ? AND e.Status IN ('published', 'completed')
        AND u.Role = 'organiser' AND u.UserStatus = 'active' AND u.VerificationStatus = 'verified'
        AND NOT EXISTS (SELECT 1 FROM BLOCKS b WHERE b.OrganiserID = p.OrganiserID AND b.BlockedEmail = ?)
        FOR UPDATE");
    $stmt->execute([$post_id, $user['Email']]);
    if (!$stmt->fetch()) {
        $pdo->rollBack();
        reaction_response(404, ['success' => false, 'message' => 'This post is unavailable.']);
    }
    if ($action === 'remove') {
        $stmt = $pdo->prepare('DELETE FROM REACTIONS WHERE PostID = ? AND UserID = ?');
        $stmt->execute([$post_id, $user['UserID']]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO REACTIONS (PostID, UserID, ReactionType, ReactionDate)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE ReactionType = VALUES(ReactionType), ReactionDate = CURRENT_TIMESTAMP');
        $stmt->execute([$post_id, $user['UserID'], $type]);
    }
    $totals = ['thumbs_up' => 0, 'clap' => 0, 'lightbulb' => 0];
    $stmt = $pdo->prepare('SELECT ReactionType, COUNT(*) AS Total FROM REACTIONS WHERE PostID = ? GROUP BY ReactionType');
    $stmt->execute([$post_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $totals[$row['ReactionType']] = (int) $row['Total'];
    }
    $pdo->commit();
    reaction_response(200, ['success' => true, 'totals' => $totals, 'current_reaction' => $action === 'remove' ? null : $type]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('EventNest reaction failed: ' . get_class($e));
    reaction_response(500, ['success' => false, 'message' => 'Unable to save your reaction.']);
}
