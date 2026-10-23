<?php
declare(strict_types=1);
require_once __DIR__.'/shared-components/auth.php';
$user=auth_require_verified_organiser();
$pdo=eventnest_db();
$event_id=eventnest_id($_GET['event_id'] ?? null);
$rating=eventnest_id($_GET['rating'] ?? null);
$contact=($_GET['contact'] ?? '')==='1';
$sql='SELECT r.*,e.EventName FROM REVIEWS r JOIN EVENTS e ON e.EventID=r.EventID WHERE e.OrganiserID=?';
$params=[$user['UserID']];
if($event_id) {
    $sql.=' AND r.EventID=?';
    $params[]=$event_id;
}
if($rating>=1 && $rating<=5) {
    $sql.=' AND r.Rating=?';
    $params[]=$rating;
}
if($contact) {
    $sql.=' AND r.WantsContact=1';
}
$stmt=$pdo->prepare($sql.' ORDER BY r.ReviewDate DESC LIMIT 200');
$stmt->execute($params);
$reviews=$stmt->fetchAll();
$stmt=$pdo->prepare('SELECT EventID,EventName FROM EVENTS WHERE OrganiserID=?');
$stmt->execute([$user['UserID']]);
$events=$stmt->fetchAll();
$page_title='Private reviews';
require __DIR__.'/shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('organiser'); ?><main class="dashboard-main"><h1>Private reviews</h1><div class="stat-grid"><div class="stat-card"><span>Reviews shown</span><strong><?= count($reviews) ?></strong></div><div class="stat-card"><span>Average rating shown</span><strong><?= $reviews?number_format(array_sum(array_column($reviews,'Rating'))/count($reviews),1):'?' ?></strong></div><div class="stat-card"><span>Contact requests shown</span><strong><?= array_sum(array_column($reviews,'WantsContact')) ?></strong></div></div><p>Private attendee feedback. No public replies or comments.</p>
<form method="get"><label>Event<select name="event_id"><option value="">All your events</option><?php foreach($events as $event): ?><option value="<?= (int)$event['EventID'] ?>" <?= $event_id===$event['EventID']?'selected':'' ?>><?= escape_html($event['EventName']) ?></option><?php endforeach; ?></select></label><label>Rating<select name="rating"><option value="">All ratings</option><?php foreach(range(1,5) as $value): ?><option <?= $rating===$value?'selected':'' ?>><?= $value ?></option><?php endforeach; ?></select></label><label><input type="checkbox" name="contact" value="1" <?= $contact?'checked':'' ?>> Contact requested</label><button class="btn btn-orange">Filter</button></form>
<?php foreach($reviews as $review): ?><article class="content-card"><h2><?= escape_html($review['EventName']) ?></h2><p><?= (int)$review['Rating'] ?>/5 ? <?= escape_html(format_datetime($review['ReviewDate'])) ?></p><p><?= nl2br(escape_html($review['Tags'] ?: $review['Comment'])) ?></p><?php if($review['WantsContact']): ?><p>Contact permitted: <?= escape_html($review['ContactEmail']) ?></p><?php endif; ?><a href="manage-bookings.php?event_id=<?= (int)$review['EventID'] ?>">View booking context</a></article><?php endforeach; ?><?php if(!$reviews): ?><p class="empty-state">No matching private reviews.</p><?php endif; ?></main></div>
<?php require __DIR__.'/shared-components/footer.php'; ?>
