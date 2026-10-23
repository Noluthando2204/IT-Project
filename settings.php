<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_attendee();
$pdo=eventnest_db();
$message='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    eventnest_require_post();
    try {
        $pdo->beginTransaction();
        if(($_POST['action'] ?? '')==='password') {
            $current=is_string($_POST['current_password'] ?? null)?$_POST['current_password']:'';
            $password=is_string($_POST['new_password'] ?? null)?$_POST['new_password']:'';
            $stmt=$pdo->prepare('SELECT PasswordHash FROM USERS WHERE UserID=? FOR UPDATE');
            $stmt->execute([$user['UserID']]);
            if(!password_verify($current,(string)$stmt->fetchColumn()) || !eventnest_password_valid($password) || $password!==($_POST['confirm_password'] ?? null)) {
                throw new DomainException('Verify your current password and matching new passwords (8?72 bytes).');
            }
            $stmt=$pdo->prepare('UPDATE USERS SET PasswordHash=? WHERE UserID=?');
            $stmt->execute([password_hash($password,PASSWORD_DEFAULT),$user['UserID']]);
            session_regenerate_id(true);
        }
        elseif(($_POST['action'] ?? '')==='recommendations') {
            $opt_in=($_POST['recommendation_opt_in'] ?? '')==='1'?1:0;
            $stmt=$pdo->prepare('UPDATE USERS SET RecommendationOptIn=? WHERE UserID=?');
            $stmt->execute([$opt_in,$user['UserID']]);
            // Clear cached personal rankings immediately when the preference changes.
            $stmt=$pdo->prepare('DELETE FROM USER_EVENT_RECOMMENDATIONS WHERE UserID=?');
            $stmt->execute([$user['UserID']]);
        }
        else {
            throw new DomainException('Invalid settings action.');
        }
        $pdo->commit();
        $user=auth_current_user();
        $message='Settings saved.';
    }
    catch(Throwable $exception) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message=$exception instanceof DomainException?$exception->getMessage():'Settings could not be saved.';
    }
}
$page_title='Settings';
require __DIR__.'/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('attendee'); ?><main class="dashboard-main"><h1>Settings</h1><?php if($message): ?><p class="alert alert-info" role="status"><?= escape_html($message) ?></p><?php endif; ?>
<form class="content-card" method="post"><?php eventnest_csrf_field(); ?><input type="hidden" name="action" value="password"><h2>Change password</h2>
<?php foreach(['current_password'=>'Current password','new_password'=>'New password','confirm_password'=>'Confirm new password'] as $field=>$label): ?><div class="field"><label for="<?= $field ?>"><?= $label ?></label><input id="<?= $field ?>" type="password" name="<?= $field ?>" maxlength="72" autocomplete="<?= $field==='current_password'?'current-password':'new-password' ?>" required></div><?php endforeach; ?><button class="btn btn-green">Update password</button></form>
<form class="content-card" method="post"><?php eventnest_csrf_field(); ?><input type="hidden" name="action" value="recommendations"><h2>Personalised recommendations</h2><p>Opting in allows OpenAI to compare your selected career category with event categories and formats. Ordinary search and event discovery remain available when this is off.</p><label><input type="checkbox" name="recommendation_opt_in" value="1" <?= $user['RecommendationOptIn']?'checked':'' ?>> Enable personalised recommendations</label><p><button class="btn btn-blue">Save preference</button></p></form>
<section class="content-card"><h2>Session</h2><a class="btn btn-outline" href="logout.php">Log out</a></section>
<section class="content-card danger-zone"><h2>Delete account</h2><p>Deletion is blocked by upcoming bookings, unpaid refunds and moderation records needing review. Booking and payment history is retained. Contact support first about unresolved emailed reports.</p><form method="post" action="delete-account.php" data-confirm="Permanently anonymise and disable this account?"><?php eventnest_csrf_field(); ?><div class="field"><label for="delete-password">Current password</label><input id="delete-password" type="password" name="current_password" autocomplete="current-password" required></div><button class="btn btn-red">Delete account</button></form></section></main></div>
<?php require __DIR__.'/shared-components/footer.php'; ?>
