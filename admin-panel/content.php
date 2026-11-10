<?php
declare(strict_types=1);
require_once __DIR__.'/../shared-components/auth.php';
$user=auth_require_admin();eventnest_require_post();
if(($_POST['copy_key']??null)!=='career_intro'){http_response_code(422);exit('This content is not editable.');}
$value=trim_input($_POST['content']??'');if($value===''||mb_strlen($value)>1000){http_response_code(422);exit('Use 1–1000 characters.');}
$pdo=eventnest_db();$old=eventnest_public_copy('career_intro','');
$pdo->beginTransaction();try{$pdo->prepare('INSERT INTO PUBLIC_COPY(CopyKey,Content) VALUES(?,?) ON DUPLICATE KEY UPDATE Content=VALUES(Content)')->execute(['career_intro',$value]);$pdo->prepare('INSERT INTO SECURITY_AUDIT(ActorID,EventName,Outcome,Summary) VALUES(?,?,?,?)')->execute([$user['UserID'],'public_copy','saved',json_encode(['key'=>'career_intro','old'=>$old,'new'=>$value])]);$pdo->commit();auth_set_flash('Content saved.','success');}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();auth_set_flash('Content could not be saved.');}auth_redirect('career-fields.php');
