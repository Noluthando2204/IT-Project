<?php
declare(strict_types=1);
require_once __DIR__.'/../shared-components/auth.php';
auth_require_admin();
$pdo=eventnest_db();
$q=substr(trim_input($_GET['q'] ?? ''),0,100);
$payment=trim_input($_GET['payment_status'] ?? '');
$escrow=trim_input($_GET['escrow_status'] ?? '');
$event_id=eventnest_id($_GET['event_id'] ?? null);
$payment_id=eventnest_id($_GET['payment_id'] ?? null);
$sql='SELECT p.*,b.BookingReference,b.Status AS BookingStatus,e.EventID,e.EventName,e.Status AS EventStatus,e.EventDate,e.UpdatedAt FROM PAYMENTS p JOIN BOOKINGS b ON b.BookingID=p.BookingID JOIN EVENTS e ON e.EventID=b.EventID WHERE (b.BookingReference LIKE ? OR e.EventName LIKE ?)';
$params=['%'.$q.'%','%'.$q.'%'];
if(in_array($payment,['success','failed','refunded'],true)) {
    $sql.=' AND p.PaymentStatus=?';
    $params[]=$payment;
}
if(in_array($escrow,['held','released','refunded'],true)) {
    $sql.=' AND p.Escrow_status=?';
    $params[]=$escrow;
}
if($event_id) {
    $sql.=' AND e.EventID=?';
    $params[]=$event_id;
}
if($payment_id) {
    $sql.=' AND p.PaymentID=?';
    $params[]=$payment_id;
}
$stmt=$pdo->prepare($sql.' ORDER BY p.PaymentID DESC LIMIT 200');
$stmt->execute($params);
$payments=$stmt->fetchAll();
$totals=$pdo->query("SELECT COALESCE(SUM(CASE WHEN Escrow_status='held' THEN Amount ELSE 0 END),0) AS Held,COALESCE(SUM(CASE WHEN Escrow_status='released' THEN Amount ELSE 0 END),0) AS Released,COALESCE(SUM(CASE WHEN Escrow_status='refunded' THEN Amount ELSE 0 END),0) AS Refunded FROM PAYMENTS")->fetch();
$page_title='Payments and escrow';
require __DIR__.'/../shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('admin'); ?><main class="dashboard-main"><h1>Payments and escrow</h1><div class="stat-grid"><?php foreach (['Held','Released','Refunded'] as $label): ?><div class="stat-card"><span><?= $label ?></span><strong><?= escape_html(format_money($totals[$label])) ?></strong></div><?php endforeach; ?></div><p class="callout">Authorise release only after the 24-hour waiting period and after checking for unresolved emailed concerns. Release records the local escrow decision; it does not transfer provider funds. Refunds require payment-provider verification.</p>
<form method="get"><label>Booking or event<input name="q" value="<?= escape_html($q) ?>" maxlength="100"></label><label>Payment status<select name="payment_status"><option value="">All</option><?php foreach(['success','failed','refunded'] as $v): ?><option <?= $v===$payment?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><label>Escrow status<select name="escrow_status"><option value="">All</option><?php foreach(['held','released','refunded'] as $v): ?><option <?= $v===$escrow?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><button class="btn btn-orange">Filter</button></form>
<div class="table-wrap"><table><thead><tr><th>Payment / date</th><th>Booking / event</th><th>Amount</th><th>Payment / escrow</th><th>Actions</th></tr></thead><tbody><?php foreach($payments as $p): ?>
<tr><td>#<?= (int)$p['PaymentID'] ?><br><?= escape_html(format_datetime($p['PaymentDate'])) ?><br><?= escape_html($p['TransactionID']) ?></td><td><?= escape_html($p['BookingReference']) ?><br><?= escape_html($p['EventName']) ?></td><td><?= escape_html(format_money($p['Amount'])) ?><br><small>Platform commission (5%): <?= escape_html(format_money(round((float)$p['Amount']*0.05,2))) ?><br>Organiser share estimate: <?= escape_html(format_money((float)$p['Amount']-round((float)$p['Amount']*0.05,2))) ?></small></td><td><?= eventnest_status_badge($p['PaymentStatus']) ?><br><?= $p['Escrow_status']===null?'Not applicable':eventnest_status_badge($p['Escrow_status']) ?></td><td><a href="manage-bookings.php?action=view&amp;booking_id=<?= (int)$p['BookingID'] ?>">Booking</a> <a href="manage-events.php?event_id=<?= (int)$p['EventID'] ?>">Event</a>
<?php $ready=$p['PaymentStatus']==='success' && $p['Escrow_status']==='held' && $p['BookingStatus']==='paid' && $p['EventStatus']==='completed' && max(strtotime($p['EventDate']),strtotime($p['UpdatedAt']))<=time()-86400; ?>
<?php if($ready): ?><form method="post" action="../scheduled-tasks/escrow.php" data-confirm="Confirm all emailed concerns are resolved and authorise local escrow release?"><?php eventnest_csrf_field(); ?><input type="hidden" name="payment_id" value="<?= (int)$p['PaymentID'] ?>"><button class="btn btn-green" name="action" value="release">Authorise release</button></form><?php elseif($p['PaymentStatus']==='success' && ($p['EventStatus']==='canceled' || $p['BookingStatus']==='canceled')): ?><p class="text-danger">Provider refund review required. Keep financial records unchanged until verified.</p><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$payments): ?><tr><td colspan="5">No matching payments.</td></tr><?php endif; ?></tbody></table></div></main></div>
<?php require __DIR__.'/../shared-components/footer.php'; ?>
