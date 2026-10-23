<?php
declare(strict_types=1);
require_once __DIR__.'/../shared-components/auth.php';
auth_require_admin();
$pdo=eventnest_db();
$blocks=$pdo->query('SELECT b.*,o.FirstName AS OrganiserFirstName,o.LastName AS OrganiserLastName,u.UserID,u.Suspension_count,u.Suspension_start_date,(SELECT COUNT(DISTINCT b2.OrganiserID) FROM BLOCKS b2 WHERE b2.BlockedEmail=b.BlockedEmail) AS DistinctOrganisers FROM BLOCKS b JOIN USERS o ON o.UserID=b.OrganiserID LEFT JOIN USERS u ON u.Email=b.BlockedEmail ORDER BY b.BlockDate DESC LIMIT 200')->fetchAll();
$page_title='Moderation';
require __DIR__.'/../shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('admin'); ?><main class="dashboard-main"><h1>Moderation and investigations</h1><p class="callout">Event reports arrive by email. Check the support inbox, investigate event and payment context, and keep escrow held until concerns are resolved.</p><p><a href="manage-events.php">Event management</a> ? <a href="escrow.php">Payments and escrow</a></p><div class="table-wrap"><table><thead><tr><th>Organiser</th><th>Blocked email</th><th>Reason / date</th><th>Distinct organisers</th><th>Suspension history</th></tr></thead><tbody><?php foreach($blocks as $block): ?><tr><td><?= escape_html($block['OrganiserFirstName'].' '.$block['OrganiserLastName']) ?></td><td><?= escape_html($block['BlockedEmail']) ?><?php if($block['UserID']): ?><br><a href="manage-users.php?user_id=<?= (int)$block['UserID'] ?>">User context</a><?php endif; ?></td><td><?= escape_html($block['Reason']) ?><br><?= escape_html(format_datetime($block['BlockDate'])) ?></td><td><?= (int)$block['DistinctOrganisers'] ?></td><td><?= (int)$block['Suspension_count'] ?> suspensions<?php if((int)$block['Suspension_count']>=2): ?> ? Permanent ban<?php elseif($block['Suspension_start_date']): ?><br>Until <?= escape_html(format_datetime(date('Y-m-d H:i:s',strtotime($block['Suspension_start_date'].' +30 days')))) ?><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$blocks): ?><tr><td colspan="5">No block records.</td></tr><?php endif; ?></tbody></table></div></main></div>
<?php require __DIR__.'/../shared-components/footer.php'; ?>
