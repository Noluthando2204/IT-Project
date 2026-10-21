<?php
declare(strict_types=1);
require_once __DIR__ . '/../shared-components/auth.php';
$admin = auth_require_admin();
$pdo = auth_db();

function edash_h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function edash_count(PDO $pdo, string $sql): int { return (int)$pdo->query($sql)->fetchColumn(); }

try {
    $stats = [
        'users' => edash_count($pdo, "SELECT COUNT(*) FROM USERS WHERE Role = 'attendee'"),
        'organisers' => edash_count($pdo, "SELECT COUNT(*) FROM USERS WHERE Role = 'organiser'"),
        'pending' => edash_count($pdo, "SELECT COUNT(*) FROM USERS WHERE Role = 'organiser' AND VerificationStatus = 'pending'"),
        'published_events' => edash_count($pdo, "SELECT COUNT(*) FROM EVENTS WHERE Status = 'published'"),
        'active_bookings' => edash_count($pdo, "SELECT COUNT(*) FROM BOOKINGS WHERE Status IN ('pending','paid')"),
        'held_escrow' => (float)$pdo->query("SELECT COALESCE(SUM(p.Amount), 0) FROM PAYMENTS p WHERE p.PaymentStatus = 'success' AND p.Escrow_status = 'held'")->fetchColumn(),
    ];
    $stmt = $pdo->query("SELECT b.BlockID, b.BlockedEmail, b.Reason, b.BlockDate, u.FirstName, u.LastName FROM BLOCKS b JOIN USERS u ON u.UserID = b.OrganiserID ORDER BY b.BlockDate DESC LIMIT 10");
    $recentBlocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('EventNest admin dashboard query failed: '.$e->getMessage());
    http_response_code(500);
    exit('The dashboard could not be loaded. Please try again later.');
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin dashboard | EventNest</title><link rel="stylesheet" href="../assets/css/style.css"></head><body>
<main class="container"><h1>Admin dashboard</h1><p>Welcome, <?= edash_h(trim(($admin['FirstName'] ?? '').' '.($admin['LastName'] ?? ''))) ?>.</p>
<section class="dashboard-cards" aria-label="Platform statistics">
<?php foreach ([['Attendees',$stats['users']],['Organisers',$stats['organisers']],['Pending organiser verifications',$stats['pending']],['Published events',$stats['published_events']],['Active bookings',$stats['active_bookings']],['Escrow held',number_format($stats['held_escrow'],2)]] as [$label,$value]): ?>
<article class="card"><h2><?= edash_h($label) ?></h2><p><?= edash_h($value) ?></p></article>
<?php endforeach; ?></section>
<p><a href="moderation.php">Manage moderation</a> | <a href="../logout.php">Log out</a></p>
<h2>Recent blocks</h2><?php if (!$recentBlocks): ?><p>No blocks have been recorded.</p><?php else: ?><div class="table-responsive"><table><thead><tr><th>Organiser</th><th>Blocked email</th><th>Reason</th><th>Date</th></tr></thead><tbody>
<?php foreach ($recentBlocks as $row): ?><tr><td><?= edash_h(trim($row['FirstName'].' '.$row['LastName'])) ?></td><td><?= edash_h($row['BlockedEmail']) ?></td><td><?= edash_h($row['Reason'] ?: '—') ?></td><td><?= edash_h($row['BlockDate']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></main></body></html>



               

        
