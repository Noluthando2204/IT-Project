<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_verified_organiser();
$pdo=eventnest_db();
$stmt=$pdo->prepare("SELECT EventID,EventName FROM EVENTS WHERE OrganiserID=? AND Status IN ('draft','published','completed') ORDER BY EventDate DESC");
$stmt->execute([$user['UserID']]);
$events=$stmt->fetchAll();
$stmt=$pdo->prepare('SELECT p.PostID,p.Content,p.PostDate,e.EventName FROM POSTS p JOIN EVENTS e ON e.EventID=p.EventID AND e.OrganiserID=p.OrganiserID WHERE p.OrganiserID=? ORDER BY p.PostDate DESC LIMIT 50');
$stmt->execute([$user['UserID']]);
$posts=$stmt->fetchAll();
$page_title='Event updates and media';
require __DIR__.'/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('organiser'); ?><main class="dashboard-main"><h1>Event updates and media</h1>
<form class="content-card" method="post" action="post.php" enctype="multipart/form-data"><?php eventnest_csrf_field(); ?><input type="hidden" name="action" value="create">
<div class="field"><label for="EventID">Linked event</label><select id="EventID" name="EventID" required><option value="">Select your event</option><?php foreach($events as $event): ?><option value="<?= (int)$event['EventID'] ?>"><?= escape_html($event['EventName']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="Content">Event update</label><textarea id="Content" name="Content" maxlength="10000"></textarea></div><div class="field"><label for="Media">Optional image or video</label><input id="Media" type="file" name="Media" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm" data-media-preview><small>Images up to 5 MB; videos up to 20 MB. Text-only updates need no file.</small><div data-upload-preview></div></div>
<div class="field"><label for="Hashtags">Hashtags</label><input id="Hashtags" name="Hashtags" maxlength="255"></div><p>Posts are public only while the linked event is published or completed.</p><button class="btn btn-orange">Publish post</button></form>
<h2>Your recent posts</h2><?php foreach($posts as $post): ?><article class="post-card"><h3><?= escape_html($post['EventName']) ?></h3><p><?= escape_html(eventnest_excerpt($post['Content'])) ?></p><p><?= escape_html(format_datetime($post['PostDate'])) ?></p><a href="post-detail.php?post_id=<?= (int)$post['PostID'] ?>">View public post</a><form method="post" action="post.php" data-confirm="Delete this post and its reactions?"><?php eventnest_csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="PostID" value="<?= (int)$post['PostID'] ?>"><button class="btn btn-red">Delete post</button></form></article><?php endforeach; ?><?php if(!$posts): ?><p class="empty-state">No posts yet.</p><?php endif; ?></main></div>
<?php require __DIR__.'/shared-components/footer.php'; ?>
