<?php
declare(strict_types=1);
require_once __DIR__ . '/../shared-components/auth.php';
auth_require_admin();
$pdo = auth_db();
function emod_h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
try {
    $summary = $pdo->query("SELECT COUNT(*) AS total_blocks, COUNT(DISTINCT BlockedEmail) AS unique_emails, COUNT(DISTINCT OrganiserID) AS organisers FROM BLOCKS")->fetch(PDO::FETCH_ASSOC) ?: [];
    $stmt = $pdo->query("SELECT b.BlockID,b.BlockedEmail,b.Reason,b.BlockDate, o.FirstName AS OrganiserFirstName,o.LastName AS OrganiserLastName, (SELECT COUNT(DISTINCT b2.OrganiserID) FROM BLOCKS b2 WHERE LOWER(b2.BlockedEmail)=LOWER(b.BlockedEmail)) AS organiser_count, u.UserID AS TargetUserID,u.UserStatus,u.Blocks_count,u.Suspension_count FROM BLOCKS b JOIN USERS o ON o.UserID=b.OrganiserID LEFT JOIN USERS u ON LOWER(u.Email)=LOWER(b.BlockedEmail) ORDER BY b.BlockDate DESC LIMIT 100");
    $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('EventNest moderation query failed: '.$e->getMessage()); http_response_code(500); exit('Moderation data could not be loaded.'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Moderation | EventNest</title><link rel="stylesheet" href="../assets/css/style.css"></head><body><main class="container"><h1>Moderation overview</h1><p><a href="dashboard.php">Back to dashboard</a></p>
<section class="dashboard-cards"><article class="card"><h2>Total block records</h2><p><?= (int)($summary['total_blocks'] ?? 0) ?></p></article><article class="card"><h2>Unique blocked emails</h2><p><?= (int)($summary['unique_emails'] ?? 0) ?></p></article><article class="card"><h2>Organisers who have blocked</h2><p><?= (int)($summary['organisers'] ?? 0) ?></p></article></section>
<h2>Latest block records</h2><?php if (!$blocks): ?><p>No moderation records found.</p><?php else: ?><div class="table-responsive"><table><thead><tr><th>Date</th><th>Organiser</th><th>Blocked email</th><th>Reason</th><th>Distinct organisers</th><th>Account status</th><th>Block count</th><th>Suspensions</th></tr></thead><tbody><?php foreach ($blocks as $b): ?><tr><td><?= emod_h($b['BlockDate']) ?></td><td><?= emod_h(trim(($b['OrganiserFirstName'] ?? '').' '.($b['OrganiserLastName'] ?? ''))) ?></td><td><?= emod_h($b['BlockedEmail']) ?></td><td><?= emod_h($b['Reason'] ?: '—') ?></td><td><?= (int)$b['organiser_count'] ?></td><td><?= emod_h($b['UserStatus'] ?? 'No matching account') ?></td><td><?= isset($b['Blocks_count']) ? (int)$b['Blocks_count'] : '—' ?></td><td><?= isset($b['Suspension_count']) ? (int)$b['Suspension_count'] : '—' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></main></body></html>
     
        
                                
        
                        
                                   
                   
                                
                       
