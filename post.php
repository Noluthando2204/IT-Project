<?php
declare(strict_types=1);
require_once __DIR__ . '/shared-components/auth.php';
$user=auth_require_login(); $pdo=auth_db();
function post_h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$error=''; $success='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!eventnest_verify_csrf_token(isset($_POST['csrf_token'])?(string)$_POST['csrf_token']:null)) { http_response_code(400); $error='Your session token expired. Refresh and try again.'; }
    elseif(isset($_POST['delete_post_id'])) {
        $postId=(int)$_POST['delete_post_id'];
        if($postId<=0) $error='Invalid post selected.';
        else {
            try {
                $pdo->beginTransaction();
                $stmt=$pdo->prepare('SELECT PostID,OrganiserID,Media FROM POSTS WHERE PostID=:id FOR UPDATE'); $stmt->execute([':id'=>$postId]); $post=$stmt->fetch(PDO::FETCH_ASSOC);
                if(!$post || ((string)$user['Role']!=='admin' && (int)$post['OrganiserID']!==(int)$user['UserID'])) { $pdo->rollBack(); http_response_code(403); $error='You are not allowed to delete this post.'; }
                else {
                    $d=$pdo->prepare('DELETE FROM REACTIONS WHERE PostID=:id'); $d->execute([':id'=>$postId]);
                    $d=$pdo->prepare('DELETE FROM Post_Categories WHERE post_id=:id'); $d->execute([':id'=>$postId]);
                    $d=$pdo->prepare('DELETE FROM POSTS WHERE PostID=:id'); $d->execute([':id'=>$postId]); $pdo->commit();
                    if(!empty($post['Media'])) { $relative=str_replace('\\','/',(string)$post['Media']); if(str_starts_with($relative,'uploaded-content/posts/') && !str_contains($relative,'..')) { $path=__DIR__.'/'.$relative; if(is_file($path)) @unlink($path); } }
                    $success='Post deleted successfully.';
                }
            } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); error_log('EventNest post deletion failed: '.$e->getMessage()); $error='The post could not be deleted.'; }
        }
    } else { http_response_code(400); $error='Unsupported post action.'; }
}
try {
    $sql="SELECT p.PostID,p.EventID,p.OrganiserID,p.Content,p.Media_type,p.Media,p.Hashtags,p.PostDate,e.EventName,u.FirstName,u.LastName FROM POSTS p JOIN EVENTS e ON e.EventID=p.EventID JOIN USERS u ON u.UserID=p.OrganiserID ORDER BY p.PostDate DESC LIMIT 100";
    $posts=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) { error_log('EventNest post list failed: '.$e->getMessage()); http_response_code(500); exit('Posts could not be loaded.'); }
$token=eventnest_csrf_token();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Event posts | EventNest</title><link rel="stylesheet" href="assets/css/style.css"></head><body><main class="container"><h1>Event posts</h1><?php if($error): ?><p role="alert" class="error"><?= post_h($error) ?></p><?php endif; ?><?php if($success): ?><p role="status" class="success"><?= post_h($success) ?></p><?php endif; ?><?php if(($user['Role']??'')==='organiser' && ($user['VerificationStatus']??'')==='verified'): ?><p><a href="create-post.php">Create a post</a></p><?php endif; ?><?php if(!$posts): ?><p>No posts have been published.</p><?php else: ?><section class="posts"><?php foreach($posts as $p): ?><article class="post"><h2><?= post_h($p['EventName']) ?></h2><p>By <?= post_h(trim($p['FirstName'].' '.$p['LastName'])) ?> · <?= post_h($p['PostDate']) ?></p><?php if($p['Content']): ?><p><?= nl2br(post_h($p['Content'])) ?></p><?php endif; ?><?php if($p['Media']): $media=post_h($p['Media']); if(str_starts_with((string)$p['Media'],'uploaded-content/posts/') && !str_contains((string)$p['Media'],'..')): ?><?php if($p['Media_type']==='image'): ?><img src="<?= $media ?>" alt="Media for <?= post_h($p['EventName']) ?>" loading="lazy" style="max-width:100%;height:auto"><?php elseif($p['Media_type']==='video'): ?><video controls preload="metadata" style="max-width:100%"><source src="<?= $media ?>"></video><?php endif; ?><?php endif; ?><?php endif; ?><?php if($p['Hashtags']): ?><p><?= post_h($p['Hashtags']) ?></p><?php endif; ?><?php if(($user['Role']??'')==='admin' || (int)$p['OrganiserID']===(int)$user['UserID']): ?><form method="post" onsubmit="return confirm('Delete this post? This action cannot be undone.');"><input type="hidden" name="csrf_token" value="<?= post_h($token) ?>"><input type="hidden" name="delete_post_id" value="<?= (int)$p['PostID'] ?>"><button type="submit">Delete post</button></form><?php endif; ?></article><?php endforeach; ?></section><?php endif; ?></main></body></html>
