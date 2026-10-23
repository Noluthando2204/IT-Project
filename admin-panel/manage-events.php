<?php
declare(strict_types=1);
require_once __DIR__.'/../shared-components/auth.php';
$user=auth_require_admin();
$pdo=eventnest_db();
if($_SERVER['REQUEST_METHOD']==='POST') {
    eventnest_require_post();
    $event_id=eventnest_id($_POST['event_id'] ?? null);
    $action=trim_input($_POST['action'] ?? '');
    try {
        $pdo->beginTransaction();
        $stmt=$pdo->prepare('SELECT e.*,u.VerificationStatus,u.UserStatus FROM EVENTS e JOIN USERS u ON u.UserID=e.OrganiserID WHERE e.EventID=? FOR UPDATE');
        $stmt->execute([$event_id]);
        $event=$stmt->fetch();
        if(!$event) {
            throw new DomainException('Event not found.');
        }
        if($action==='publish') {
            if($event['Status']!=='draft' || $event['VerificationStatus']!=='verified' || $event['UserStatus']!=='active' || strtotime($event['EventDate'])<=time()) {
                throw new DomainException('Only a future draft from an active verified organiser may be published.');
            }
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM TICKETS WHERE EventID=?');
            $stmt->execute([$event_id]);
            if(!$stmt->fetchColumn()) {
                throw new DomainException('The event needs valid ticket tiers first.');
            }
            $stmt=$pdo->prepare("UPDATE EVENTS SET Status='published' WHERE EventID=? AND Status='draft'");
            $stmt->execute([$event_id]);
        }
        elseif($action==='feature') {
            if($event['Status']!=='published') {
                throw new DomainException('Only published events may be featured.');
            }
            $stmt=$pdo->prepare('UPDATE EVENTS SET IsFeatured=NOT IsFeatured WHERE EventID=?');
            $stmt->execute([$event_id]);
        }
        else {
            throw new DomainException('Invalid event action.');
        }
        $pdo->commit();
        auth_set_flash('Event updated.','success');
    }
    catch(Throwable $exception) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        auth_set_flash($exception instanceof DomainException?$exception->getMessage():'Unable to update event.');
    }
    auth_redirect('admin-panel/manage-events.php');
}
$q=substr(trim_input($_GET['q'] ?? ''),0,100);
$status=trim_input($_GET['status'] ?? '');
$category=eventnest_id($_GET['category_id'] ?? null);
$organiser=eventnest_id($_GET['organiser_id'] ?? null);
$event_id=eventnest_id($_GET['event_id'] ?? null);
$sql='SELECT e.*,c.CategoryName,u.FirstName,u.LastName FROM EVENTS e JOIN CATEGORIES c ON c.CategoryID=e.CategoryID JOIN USERS u ON u.UserID=e.OrganiserID WHERE (e.EventName LIKE ? OR u.FirstName LIKE ? OR u.LastName LIKE ?)';
$params=['%'.$q.'%','%'.$q.'%','%'.$q.'%'];
if(in_array($status,['draft','published','completed','canceled'],true)) {
    $sql.=' AND e.Status=?';
    $params[]=$status;
}
foreach(['CategoryID'=>$category,'OrganiserID'=>$organiser,'EventID'=>$event_id] as $column=>$id) {
    if($id) {
        $sql.=' AND e.'.$column.'=?';
        $params[]=$id;
    }
}
$stmt=$pdo->prepare($sql.' ORDER BY e.Created_at DESC LIMIT 200');
$stmt->execute($params);
$events=$stmt->fetchAll();
$categories=$pdo->query('SELECT CategoryID,CategoryName FROM CATEGORIES ORDER BY CategoryName')->fetchAll();
$page_title='Admin event management';
require __DIR__.'/../shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('admin'); ?><main class="dashboard-main"><h1>Event management</h1><form method="get" class="filter-panel"><label>Search<input name="q" value="<?= escape_html($q) ?>" maxlength="100"></label><label>Status<select name="status"><option value="">All</option><?php foreach(['draft','published','completed','canceled'] as $v): ?><option <?= $v===$status?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><label>Category<select name="category_id"><option value="">All categories</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['CategoryID'] ?>" <?= $category===(int)$c['CategoryID']?'selected':'' ?>><?= escape_html($c['CategoryName']) ?></option><?php endforeach; ?></select></label><label>Organiser ID<input name="organiser_id" type="number" min="1" value="<?= $organiser ?: '' ?>"></label><button class="btn btn-orange">Filter</button></form>
<div class="table-wrap"><table><thead><tr><th>Event</th><th>Organiser / category</th><th>Date</th><th>Status / featured</th><th>Actions</th></tr></thead><tbody><?php foreach($events as $event): ?><tr><td><?= escape_html($event['EventName']) ?></td><td><?= escape_html($event['FirstName'].' '.$event['LastName']) ?><br><?= escape_html($event['CategoryName']) ?></td><td><?= escape_html(format_datetime($event['EventDate'])) ?></td><td><?= eventnest_status_badge($event['Status']) ?><br><?= $event['IsFeatured']?'Featured':'Standard' ?></td><td><div class="inline-actions"><a class="btn btn-outline" href="../event-detail.php?event_id=<?= (int)$event['EventID'] ?>">View</a>
<?php foreach($event['Status']==='draft'?['publish'=>'Publish']:($event['Status']==='published'?['feature'=>'Toggle featured']:[]) as $action=>$label): ?><form method="post" data-confirm="Confirm this event change?"><?php eventnest_csrf_field(); ?><input type="hidden" name="event_id" value="<?= (int)$event['EventID'] ?>"><button class="btn btn-green" name="action" value="<?= $action ?>"><?= $label ?></button></form><?php endforeach; ?>
<?php if(in_array($event['Status'],['draft','published'],true)): ?><a class="btn btn-red" href="../cancel-event.php?event_id=<?= (int)$event['EventID'] ?>">Cancel event</a><?php endif; ?><a href="escrow.php?event_id=<?= (int)$event['EventID'] ?>">Payment context</a></div></td></tr><?php endforeach; ?><?php if(!$events): ?><tr><td colspan="5">No matching events.</td></tr><?php endif; ?></tbody></table></div></main></div>
<?php require __DIR__.'/../shared-components/footer.php'; ?>
