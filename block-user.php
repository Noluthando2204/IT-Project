<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$organiser = auth_require_verified_organiser();
$pdo = auth_db();
function block_h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!eventnest_verify_csrf_token(isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : null)) {
        http_response_code(400); $error = 'Your session token expired. Refresh the page and try again.';
    } else {
        $email = strtolower(trim((string)($_POST['blocked_email'] ?? '')));
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $error = 'Enter a valid email address.';
        elseif (strlen($reason) > 255) $error = 'The reason must be 255 characters or fewer.';
        elseif ($email === strtolower((string)$organiser['Email'])) $error = 'You cannot block your own account.';
        else {
            try {
                $pdo->beginTransaction();
                $targetStmt = $pdo->prepare('SELECT UserID, Email, Role, Blocks_count, Suspension_count FROM USERS WHERE LOWER(Email)=:email LIMIT 1 FOR UPDATE');
                $targetStmt->execute([':email'=>$email]);
                $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
                if (!$target || $target['Role'] !== 'attendee') {
                    $pdo->rollBack(); $error = 'Only an existing attendee account can be blocked.';
                } else {
                    $relation = $pdo->prepare("SELECT 1 FROM BOOKINGS b JOIN EVENTS e ON e.EventID=b.EventID WHERE e.OrganiserID=:organiser_id AND b.UserID=:target_id UNION SELECT 1 FROM REVIEWS r JOIN EVENTS e2 ON e2.EventID=r.EventID WHERE e2.OrganiserID=:organiser_id2 AND r.UserID=:target_id2 LIMIT 1");
                    $relation->execute([':organiser_id'=>(int)$organiser['UserID'],':target_id'=>(int)$target['UserID'],':organiser_id2'=>(int)$organiser['UserID'],':target_id2'=>(int)$target['UserID']]);
                    if (!$relation->fetchColumn()) { $pdo->rollBack(); $error = 'You can only block an attendee who has booked or reviewed one of your events.'; }
                    else {
                        $insert = $pdo->prepare('INSERT INTO BLOCKS (OrganiserID, BlockedEmail, Reason) VALUES (:organiser_id, :email, :reason)');
                        try { $insert->execute([':organiser_id'=>(int)$organiser['UserID'], ':email'=>$email, ':reason'=>$reason !== '' ? $reason : null]); }
                        catch (PDOException $e) { if ($e->getCode()==='23000') { $pdo->rollBack(); $error='You have already blocked this attendee.'; } else { throw $e; } }
                        if ($error === '') {
                            $countStmt = $pdo->prepare('SELECT COUNT(DISTINCT OrganiserID) FROM BLOCKS WHERE LOWER(BlockedEmail)=:email');
                            $countStmt->execute([':email'=>$email]); $count = (int)$countStmt->fetchColumn();
                            if ($count >= 3 && (int)$target['Suspension_count'] < 2) {
                                $upd = $pdo->prepare('UPDATE USERS SET Blocks_count=:blocks_count, Suspension_count=Suspension_count+1, Suspension_start_date=NOW() WHERE UserID=:user_id');
                                $upd->execute([':blocks_count'=>$count, ':user_id'=>(int)$target['UserID']]);
                            } else {
                                $upd = $pdo->prepare('UPDATE USERS SET Blocks_count=:blocks_count WHERE UserID=:user_id');
                                $upd->execute([':blocks_count'=>$count, ':user_id'=>(int)$target['UserID']]);
                            }
                            $pdo->commit(); $success = 'The attendee has been blocked successfully.';
                        }
                    }
                }
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('EventNest block-user failed: '.$e->getMessage()); $error='The block could not be saved. Please try again.'; }
        }
    }
}
$token = eventnest_csrf_token();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Block attendee | EventNest</title><link rel="stylesheet" href="assets/css/style.css"></head><body><main class="container"><h1>Block an attendee</h1><p>Only block attendees who have booked or reviewed one of your events.</p><?php if ($error): ?><p role="alert" class="error"><?= block_h($error) ?></p><?php endif; ?><?php if ($success): ?><p role="status" class="success"><?= block_h($success) ?></p><?php endif; ?><form method="post" action="block-user.php"><input type="hidden" name="csrf_token" value="<?= block_h($token) ?>"><label for="blocked_email">Attendee email</label><input id="blocked_email" name="blocked_email" type="email" maxlength="100" required value="<?= block_h($_POST['blocked_email'] ?? '') ?>"><label for="reason">Reason (optional)</label><textarea id="reason" name="reason" maxlength="255"><?= block_h($_POST['reason'] ?? '') ?></textarea><button type="submit">Block attendee</button></form><p><a href="business-dashboard.php">Back to dashboard</a></p></main></body></html>

        
           
