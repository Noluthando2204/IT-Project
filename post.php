<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_verified_organiser();
eventnest_require_post();
$pdo=eventnest_db();
$saved=null;
try {
    $pdo->beginTransaction();
    if(($_POST['action'] ?? '')==='delete') {
        $stmt=$pdo->prepare('SELECT PostID,Media,Media_type FROM POSTS WHERE PostID=? AND OrganiserID=? FOR UPDATE');
        $stmt->execute([eventnest_id($_POST['PostID'] ?? null),$user['UserID']]);
        $post=$stmt->fetch();
        if(!$post) {
            throw new DomainException('Post not found for your account.');
        }
        $media=eventnest_safe_media_path($post['Media'],$post['Media_type']);
        // Remove dependent rows first; the approved foreign keys restrict direct deletion.
        foreach(['DELETE FROM REACTIONS WHERE PostID=?','DELETE FROM Post_Categories WHERE post_id=?','DELETE FROM POSTS WHERE PostID=?'] as $sql) {
            $stmt=$pdo->prepare($sql);
            $stmt->execute([$post['PostID']]);
        }
        $pdo->commit();
        if($media) {
            @unlink(__DIR__.'/'.$media);
        }
        auth_set_flash('Post deleted.','success');
        auth_redirect('create-post.php');
    }
    if(($_POST['action'] ?? '')!=='create') {
        throw new DomainException('Invalid post action.');
    }
    $event_id=eventnest_id($_POST['EventID'] ?? null);
    $content=trim_input($_POST['Content'] ?? '');
    $hashtags=eventnest_normalise_hashtags(trim_input($_POST['Hashtags'] ?? ''));
    $stmt=$pdo->prepare("SELECT EventID FROM EVENTS WHERE EventID=? AND OrganiserID=? AND Status IN ('draft','published','completed') FOR UPDATE");
    $stmt->execute([$event_id,$user['UserID']]);
    if(!$stmt->fetchColumn() || mb_strlen($content)>10000) {
        throw new DomainException('Select one of your available events and keep text within 10,000 characters.');
    }
    $media=null;
    $type=null;
    $file=$_FILES['Media'] ?? null;
    if(is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
        if(($file['error'] ?? null)!==UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null) || !is_int($file['size'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new DomainException('The uploaded media is invalid.');
        }
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed=['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp'],'video/mp4'=>['mp4'],'video/webm'=>['webm']];
        if(!isset($allowed[$mime]) || !in_array(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)),$allowed[$mime],true)) {
            throw new DomainException('Choose an approved image or video with a matching file extension.');
        }
        $type=str_starts_with($mime,'image/')?'image':'video';
        $max=$type==='image'?5*1024*1024:20*1024*1024;
        if($file['size']<1 || $file['size']>$max || filesize($file['tmp_name'])>$max || ($type==='image' && @getimagesize($file['tmp_name'])===false)) {
            throw new DomainException('Media is empty, invalid or exceeds its size limit.');
        }
        // Keep organiser post media separate from event banners and profile pictures.
        $target=eventnest_build_upload_path('posts',eventnest_generate_upload_filename('post',$mime));
        if(!is_writable(dirname($target['absolute'])) || !move_uploaded_file($file['tmp_name'],$target['absolute'])) {
            throw new DomainException('Media storage is unavailable.');
        }
        $saved=$target['absolute'];
        $media=$target['relative'];
    }
    if($content==='' && $media===null) {
        throw new DomainException('Add an event update or approved media file.');
    }
    $stmt=$pdo->prepare('INSERT INTO POSTS (EventID,OrganiserID,Content,Media,Media_type,Hashtags) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$event_id,$user['UserID'],$content ?: null,$media,$type,$hashtags]);
    $pdo->commit();
    auth_set_flash('Post saved. Draft-event posts remain private until publication.','success');
}
catch(Throwable $exception) {
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if($saved && is_file($saved)) {
        @unlink($saved);
    }
    auth_set_flash($exception instanceof DomainException?$exception->getMessage():'Unable to save the post.');
}
auth_redirect('create-post.php');
