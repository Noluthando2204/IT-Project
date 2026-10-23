<?php
declare(strict_types=1);
require_once __DIR__.'/../shared-components/auth.php';
auth_require_admin();
$pdo=eventnest_db();
$q=substr(trim_input($_GET['q'] ?? ''),0,100);
$user_id=eventnest_id($_GET['user_id'] ?? null);
$sql='SELECT UserID,FirstName,LastName,Email,UserStatus,Role,Blocks_count,Suspension_count,Suspension_start_date,Is_email_verified,CreatedAt FROM USERS WHERE (Email LIKE ? OR FirstName LIKE ? OR LastName LIKE ?)';
$params=['%'.$q.'%','%'.$q.'%','%'.$q.'%'];
if($user_id) {
    $sql.=' AND UserID=?';
    $params[]=$user_id;
}
$stmt=$pdo->prepare($sql.' ORDER BY CreatedAt DESC LIMIT 200');
$stmt->execute($params);
$users=$stmt->fetchAll();
$page_title='User management';
require __DIR__.'/../shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('admin'); ?><main class="dashboard-main"><h1>User management</h1><form><label>Search name or email<input name="q" value="<?= escape_html($q) ?>" maxlength="100"></label><button class="btn btn-orange">Search</button></form><div class="table-wrap"><table><thead><tr><th>User</th><th>Role / status</th><th>Blocks / suspensions</th><th>Suspension state</th><th>Email / joined</th><th>Context</th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td><?= escape_html($u['FirstName'].' '.$u['LastName']) ?><br><?= escape_html($u['Email']) ?></td><td><?= escape_html($u['Role']) ?> <?= eventnest_status_badge($u['UserStatus']) ?></td><td><?= (int)$u['Blocks_count'] ?> / <?= (int)$u['Suspension_count'] ?></td><td><?php if((int)$u['Suspension_count']>=2): ?>Permanent ban<?php elseif($u['Suspension_start_date'] && strtotime($u['Suspension_start_date'].' +30 days')>time()): ?>Suspended until <?= escape_html(format_datetime(date('Y-m-d H:i:s',strtotime($u['Suspension_start_date'].' +30 days')))) ?><?php else: ?>No active timed suspension<?php endif; ?></td><td><?= $u['Is_email_verified']?'Email verified':'Email unverified' ?><br><?= escape_html(format_datetime($u['CreatedAt'])) ?></td><td><a href="manage-bookings.php?q=<?= rawurlencode($u['Email']) ?>">Bookings</a> <a href="moderation.php">Moderation</a></td></tr><?php endforeach; ?><?php if(!$users): ?><tr><td colspan="6">No matching users.</td></tr><?php endif; ?></tbody></table></div><p>Account anonymisation uses the password-confirmed account workflow. Moderation history and emailed issues require review; this page cannot hard-delete users.</p></main></div>
<?php require __DIR__.'/../shared-components/footer.php'; ?>
