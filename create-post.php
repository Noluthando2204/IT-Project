<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_verified_organiser();
$pdo=eventnest_db();
$edit=[];
if(isset($_GET['edit_post'])) {
    $stmt=$pdo->prepare('SELECT * FROM POSTS WHERE PostID=? AND OrganiserID=?');
    $stmt->execute([eventnest_id($_GET['edit_post']),$user['UserID']]); $edit=$stmt->fetch();
    if(!$edit) { http_response_code(404); exit('Post not found for your account.'); }
}
$stmt=$pdo->prepare("SELECT EventID,EventName FROM EVENTS WHERE OrganiserID=? AND Status IN ('draft','published','completed') ORDER BY EventDate DESC");
$stmt->execute([$user['UserID']]);
$events=$stmt->fetchAll();
$stmt=$pdo->prepare('SELECT p.PostID,p.Content,p.PostDate,p.EditedAt,p.Media,p.Media_type,e.EventName FROM POSTS p LEFT JOIN EVENTS e ON e.EventID=p.EventID AND e.OrganiserID=p.OrganiserID WHERE p.OrganiserID=? ORDER BY p.PostDate DESC LIMIT 50');
$stmt->execute([$user['UserID']]);
$posts=$stmt->fetchAll();
$page_title='Event updates and media';
require __DIR__.'/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('organiser'); ?><main class="dashboard-main"><h1>Event updates and media</h1>
<form class="content-card" method="post" action="post.php" enctype="multipart/form-data"><?php eventnest_csrf_field(); ?><input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>"><?php if($edit): ?><input type="hidden" name="PostID" value="<?= (int)$edit['PostID'] ?>"><?php endif; ?><input type="hidden" name="form_context" value="post">
<div class="field"><label for="EventID">Linked event</label><select id="EventID" name="EventID"><option value="">General update (no linked event)</option><?php foreach($events as $event): ?><option value="<?= (int)$event['EventID'] ?>" <?= (int)($edit['EventID']??0)===(int)$event['EventID']?'selected':'' ?>><?= escape_html($event['EventName']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="Content">Event update</label><textarea id="Content" name="Content" maxlength="10000"><?= escape_html($edit['Content']??'') ?></textarea></div><div class="field"><label for="Media">Optional image or video</label><input id="Media" type="file" name="Media" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm" data-media-preview><small>Images up to 5 MB; videos up to 20 MB. Text-only updates need no file.</small><div data-upload-preview></div></div>
<div class="field"><label for="Hashtags">Hashtags</label><input id="Hashtags" name="Hashtags" maxlength="255" value="<?= escape_html($edit['Hashtags']??'') ?>"></div><p>General updates are public. Linked posts are public while their event is published or completed.</p><?php if($edit): ?><label><input type="checkbox" name="remove_media" value="1"> Remove existing media</label><a class="btn btn-outline" href="create-post.php">Cancel edit</a><?php endif; ?><button class="btn btn-orange"><?= $edit?'Save changes':'Publish post' ?></button></form>
<h2>Your recent posts</h2><?php foreach($posts as $post): ?><article class="post-card"><h3><?= escape_html($post['EventName'] ?? 'General update') ?></h3><p><?= escape_html(eventnest_excerpt($post['Content'])) ?></p><p><?= escape_html(format_datetime($post['PostDate'])) ?><?= !empty($post['EditedAt'])?' (edited)':'' ?></p><?php $preview=eventnest_safe_media_path($post['Media'],$post['Media_type']); if($preview): ?><div class="management-media"><?php if($post['Media_type']==='video'): ?><video controls playsinline preload="metadata" src="<?= escape_html(eventnest_url($preview)) ?>"></video><?php else: ?><img src="<?= escape_html(eventnest_url($preview)) ?>" alt="Post preview" loading="lazy"><?php endif; ?></div><?php endif; ?><div class="post-management-actions"><a class="btn btn-outline" href="create-post.php?edit_post=<?= (int)$post['PostID'] ?>">Edit post</a><a class="btn btn-blue" href="post-detail.php?post_id=<?= (int)$post['PostID'] ?>">View public post</a><form method="post" action="post.php" data-confirm="Delete this post and its reactions?"><?php eventnest_csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="PostID" value="<?= (int)$post['PostID'] ?>"><button class="btn btn-red">Delete post</button></form></div></article><?php endforeach; ?><?php if(!$posts): ?><p class="empty-state">No posts yet.</p><?php endif; ?></main></div>
<?php require __DIR__.'/shared-components/footer.php'; ?>
