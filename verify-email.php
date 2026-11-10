<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
$token=trim_input($_POST['token']??$_GET['token']??'');$r=eventnest_account_token_read($token,'verify');$done=false;
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){eventnest_require_post();$pdo=eventnest_db();$pdo->beginTransaction();try{$s=$pdo->prepare("DELETE FROM ACCOUNT_TOKENS WHERE TokenHash=? AND Purpose='verify' AND ExpiresAt>NOW()");$s->execute([hash('sha256',$token)]);if($r&&$s->rowCount()===1){$pdo->prepare('UPDATE USERS SET Is_email_verified=1 WHERE UserID=?')->execute([$r['UserID']]);$done=true;}$pdo->commit();}catch(Throwable $e){$pdo->rollBack();}}
$page_title='Verify email';require __DIR__.'/shared-components/header.php';?>
<main class="auth-container content-card"><h1>Verify email</h1><?php if($done): ?><p>Email verified. Organiser approval remains a separate process.</p><?php elseif($r): ?><form method="post"><?php eventnest_csrf_field(); ?><input type="hidden" name="token" value="<?= escape_html($token) ?>"><button class="btn btn-blue">Confirm email ownership</button></form><?php else: ?><p>This verification link is invalid or expired.</p><?php endif; ?></main><?php require __DIR__.'/shared-components/footer.php'; ?>
