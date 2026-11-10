<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_login();eventnest_require_post();
if(!$user['Is_email_verified']){
 try{$s=eventnest_db()->prepare('SELECT * FROM USERS WHERE UserID=?');$s->execute([$user['UserID']]);$row=$s->fetch();$token=eventnest_account_token($row,'verify',null,3600);$accepted=eventnest_mail_notice($row['Email'],'EventNest email verification','Confirm your email ownership within 60 minutes: '.eventnest_absolute_url('verify-email.php').'?token='.$token);auth_set_flash($accepted?'Verification email accepted by the mail service.':'Verification mail is unavailable. Contact support.',$accepted?'success':'error');}
 catch(Throwable $e){auth_set_flash('Verification mail is unavailable. Contact support.');}
}auth_redirect(auth_dashboard_path($user['Role']));
