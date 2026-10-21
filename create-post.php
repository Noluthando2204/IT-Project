<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$organiser=auth_require_verified_organiser(); $pdo=auth_db();
function cp_h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$error=''; $success='';
$stmt=$pdo->prepare("SELECT EventID,EventName,Status FROM EVENTS WHERE OrganiserID=:id ORDER BY EventDate DESC"); $stmt->execute([':id'=>(int)$organiser['UserID']]); $events=$stmt->fetchAll(PDO::FETCH_ASSOC);
$selectedEvent=(int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!eventnest_verify_csrf_token(isset($_POST['csrf_token'])?(string)$_POST['csrf_token']:null)) { http_response_code(400); $error='Your session token expired. Refresh and try again.'; }
    else {
        $eventId=(int)($_POST['event_id']??0); $content=trim((string)($_POST['content']??'')); $hashtags=trim((string)($_POST['hashtags']??''));
        if($eventId<=0) $error='Choose an event.';
        elseif(mb_strlen($content)>5000) $error='Post text must be 5,000 characters or fewer.';
        elseif(mb_strlen($hashtags)>255) $error='Hashtags must be 255 characters or fewer.';
        else {
            $ev=$pdo->prepare('SELECT EventID FROM EVENTS WHERE EventID=:event_id AND OrganiserID=:organiser_id LIMIT 1'); $ev->execute([':event_id'=>$eventId,':organiser_id'=>(int)$organiser['UserID']]);
            if(!$ev->fetchColumn()) $error='You can only post to one of your own events.';
            else {
                $mediaType=null; $mediaPath=null;
                try {
                    if(isset($_FILES['media']) && (int)($_FILES['media']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
                        $file=$_FILES['media']; if((int)$file['error']!==UPLOAD_ERR_OK) throw new RuntimeException('The media upload failed.');
                        if((int)$file['size']<=0 || (int)$file['size']>10*1024*1024) throw new RuntimeException('Media must be no larger than 10 MB.');
                        $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file((string)$file['tmp_name']);
                        $allowed=['image/jpeg'=>['image','jpg'],'image/png'=>['image','png'],'image/webp'=>['image','webp'],'video/mp4'=>['video','mp4'],'video/webm'=>['video','webm']];
                        if(!isset($allowed[$mime])) throw new RuntimeException('Upload a JPG, PNG, WebP, MP4, or WebM file.');
                        [$mediaType,$ext]=$allowed[$mime]; $dir=__DIR__.'/uploaded-content/posts';
                        if(!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('The upload directory could not be created.');
                        $name=bin2hex(random_bytes(16)).'.'.$ext; $destination=$dir.'/'.$name;
                        if(!move_uploaded_file((string)$file['tmp_name'],$destination)) throw new RuntimeException('The media file could not be saved.');
                        $mediaPath='uploaded-content/posts/'.$name;
                    }
                    if($content==='' && $mediaPath===null) throw new RuntimeException('Add post text or attach media.');
                    $tags=preg_replace('/[^\p{L}\p{N}_#\s-]/u','',$hashtags) ?? ''; $tags=trim(preg_replace('/\s+/u',' ',$tags) ?? '');
                    $ins=$pdo->prepare('INSERT INTO POSTS (EventID,OrganiserID,Content,Media_type,Media,Hashtags) VALUES (:event_id,:organiser_id,:content,:media_type,:media,:hashtags)');
                    $ins->execute([':event_id'=>$eventId,':organiser_id'=>(int)$organiser['UserID'],':content'=>$content!==''?$content:null,':media_type'=>$mediaType,':media'=>$mediaPath,':hashtags'=>$tags!==''?$tags:null]);
                    $success='Your post has been published.'; $selectedEvent=$eventId;
                } catch(Throwable $e) { if($mediaPath && is_file(__DIR__.'/'.$mediaPath)) @unlink(__DIR__.'/'.$mediaPath); if($e instanceof RuntimeException) $error=$e->getMessage(); else { error_log('EventNest create-post failed: '.$e->getMessage()); $error='The post could not be saved. Please try again.'; } }
            }
        }
    }
}
$token=eventnest_csrf_token();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create post | EventNest</title><link rel="stylesheet" href="assets/css/style.css"></head><body><main class="container"><h1>Create an event post</h1><?php if($error): ?><p role="alert" class="error"><?= cp_h($error) ?></p><?php endif; ?><?php if($success): ?><p role="status" class="success"><?= cp_h($success) ?></p><?php endif; ?><?php if(!$events): ?><p>Create an event before publishing a post.</p><?php else: ?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= cp_h($token) ?>"><label for="event_id">Event</label><select id="event_id" name="event_id" required><option value="">Choose an event</option><?php foreach($events as $event): ?><option value="<?= (int)$event['EventID'] ?>" <?= $selectedEvent===(int)$event['EventID']?'selected':'' ?>><?= cp_h($event['EventName'].' ('.$event['Status'].')') ?></option><?php endforeach; ?></select><label for="content">Post text</label><textarea id="content" name="content" maxlength="5000"><?= cp_h($_POST['content']??'') ?></textarea><label for="hashtags">Hashtags (optional)</label><input id="hashtags" name="hashtags" maxlength="255" value="<?= cp_h($_POST['hashtags']??'') ?>" placeholder="#networking #career"><label for="media">Image or video (optional, maximum 10 MB)</label><input id="media" name="media" type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"><button type="submit">Publish post</button></form><?php endif; ?><p><a href="business-dashboard.php">Back to dashboard</a></p></main></body></html>
