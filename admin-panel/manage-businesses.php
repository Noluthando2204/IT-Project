<?php
declare(strict_types=1);
require_once __DIR__.'/../shared-components/auth.php';
auth_require_admin();
$pdo=eventnest_db();
$q=substr(trim_input($_GET['q'] ?? ''),0,100);
// Admin search includes the public organisation name and the account representative.
$stmt=$pdo->prepare("SELECT u.UserID,u.FirstName,u.LastName,u.OrganisationName,u.Email,u.UserStatus,u.VerificationStatus,u.VerificationFeePaidUntil,u.VerificationFeePaymentStatus,u.CreatedAt,(SELECT COUNT(*) FROM EVENTS e WHERE e.OrganiserID=u.UserID) AS Events FROM USERS u WHERE u.Role='organiser' AND (u.Email LIKE ? OR u.FirstName LIKE ? OR u.LastName LIKE ? OR u.OrganisationName LIKE ?) ORDER BY u.CreatedAt DESC LIMIT 200");
$stmt->execute(['%'.$q.'%','%'.$q.'%','%'.$q.'%','%'.$q.'%']);
$users=$stmt->fetchAll();
$page_title='Organiser accounts';
require __DIR__.'/../shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('admin'); ?><main class="dashboard-main"><h1>Organiser accounts</h1><form><label>Search organiser<input name="q" value="<?= escape_html($q) ?>" maxlength="100"></label><button class="btn btn-orange">Search</button></form><div class="table-wrap"><table><thead><tr><th>Organiser</th><th>Verification</th><th>Fee coverage</th><th>Joined / events</th><th>Actions</th></tr></thead><tbody><?php foreach($users as $u): ?><?php $business_name=eventnest_organiser_display_name($u); $representative=trim($u['FirstName'].' '.$u['LastName']); ?><tr><td><strong><?= escape_html($business_name) ?></strong><br><?php if($representative!==''): ?><small>Representative: <?= escape_html($representative) ?></small><br><?php endif; ?><?= escape_html($u['Email']) ?> <?= eventnest_status_badge($u['UserStatus']) ?></td><td><?= eventnest_status_badge($u['VerificationStatus']) ?></td><td><?= escape_html($u['VerificationFeePaymentStatus']) ?><br><?= escape_html(format_datetime($u['VerificationFeePaidUntil'])) ?></td><td><?= escape_html(format_datetime($u['CreatedAt'])) ?><br><?= (int)$u['Events'] ?> events</td><td><a class="btn btn-outline" href="manage-verifications.php?user_id=<?= (int)$u['UserID'] ?>">Review verification</a><a href="manage-events.php?organiser_id=<?= (int)$u['UserID'] ?>">Events</a></td></tr><?php endforeach; ?><?php if(!$users): ?><tr><td colspan="5">No matching organisers.</td></tr><?php endif; ?></tbody></table></div></main></div>
<?php require __DIR__.'/../shared-components/footer.php'; ?>
