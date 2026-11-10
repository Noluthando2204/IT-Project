<?php
declare(strict_types=1);
function eventnest_send_account_recovery(array $user): void {
 $db=eventnest_db();
 $s=$db->prepare('SELECT * FROM USERS WHERE UserID=? FOR UPDATE');$s->execute([$user['UserID']]);$row=$s->fetch();
 if(!$row || !$row['Is_email_verified']) throw new DomainException('Verify your email address before requesting account recovery.');
 $s=$db->prepare("SELECT CreatedAt FROM ACCOUNT_TOKENS WHERE UserID=? AND Purpose='reset' ORDER BY CreatedAt DESC LIMIT 1");$s->execute([$user['UserID']]);$last=$s->fetchColumn();
 if($last && strtotime($last)>time()-60) throw new DomainException('Please wait one minute before requesting another recovery link.');
 $token=eventnest_account_token($row,'reset');
 if(!eventnest_send_password_reset($row['Email'],eventnest_absolute_url('reset-password.php').'?token='.$token)) throw new DomainException('We could not send your recovery email. Please try again later.');
}
function eventnest_account_token(array $user,string $purpose,?string $payload=null,int $seconds=600):string{
 $db=eventnest_db();$token=bin2hex(random_bytes(32));
 $db->prepare('DELETE FROM ACCOUNT_TOKENS WHERE UserID=? AND Purpose=?')->execute([$user['UserID'],$purpose]);
 $db->prepare('INSERT INTO ACCOUNT_TOKENS(TokenHash,UserID,Purpose,Payload,PasswordMarker,ExpiresAt) VALUES(?,?,?,?,?,?)')->execute([hash('sha256',$token),$user['UserID'],$purpose,$payload,hash('sha256',(string)$user['PasswordHash']),date('Y-m-d H:i:s',time()+$seconds)]);return $token;
}
function eventnest_account_token_read(string $token,string $purpose):?array{
 if(!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
 $s=eventnest_db()->prepare('SELECT t.*,u.Email,u.PasswordHash,u.UserStatus FROM ACCOUNT_TOKENS t JOIN USERS u ON u.UserID=t.UserID WHERE TokenHash=? AND Purpose=? AND ExpiresAt>NOW()');$s->execute([hash('sha256',$token),$purpose]);$r=$s->fetch(PDO::FETCH_ASSOC);
 return $r && $r['UserStatus']==='active' && hash_equals($r['PasswordMarker'],hash('sha256',(string)$r['PasswordHash']))?$r:null;
}
function eventnest_mail_notice(string $email,string $subject,string $body):bool{
 return eventnest_send_mail($email,$subject,$body,null,'<h1>EventNest</h1><p>'.nl2br(escape_html($body)).'</p>');
}
function eventnest_mask_email(string $email):string{$parts=explode('@',$email,2);return substr($parts[0],0,1).'***@'.($parts[1]??'');}
function eventnest_login_failure(string $email):void{
 $db=eventnest_db();$key=hash('sha256',$email);$db->beginTransaction();
 try{$db->prepare('INSERT IGNORE INTO LOGIN_FAILURES(AccountKey,WindowStart) VALUES(?,NOW())')->execute([$key]);$s=$db->prepare('SELECT * FROM LOGIN_FAILURES WHERE AccountKey=? FOR UPDATE');$s->execute([$key]);$r=$s->fetch();$count=strtotime($r['WindowStart'])<time()-900?1:(int)$r['Attempts']+1;$alert=$count>=3 && (!$r['AlertAt'] || strtotime($r['AlertAt'])<time()-3600);$db->prepare('UPDATE LOGIN_FAILURES SET Attempts=?,WindowStart=?,AlertAt=? WHERE AccountKey=?')->execute([$count,$count===1?date('Y-m-d H:i:s'):$r['WindowStart'],$alert?date('Y-m-d H:i:s'):$r['AlertAt'],$key]);$db->commit();if($alert){$s=$db->prepare("SELECT Email FROM USERS WHERE Email=? AND Is_email_verified=1 AND UserStatus='active'");$s->execute([$email]);if($to=$s->fetchColumn())eventnest_mail_notice($to,'EventNest sign-in security alert','Three unsuccessful sign-in attempts were recorded at '.date('Y-m-d H:i').' SAST. If this was not you, reset your password at '.eventnest_absolute_url('forgot-password.php'));}}
 catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('EventNest login alert unavailable.');}
}
