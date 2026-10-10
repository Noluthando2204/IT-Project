<?php
// admin-panel/moderation.php
// Shows recent blocks and the suspension state for each blocked email.
// Only admins can open this page.

require_once __DIR__ . '/../shared-components/auth.php';
require_once __DIR__ . '/../database-config/connection.php';
require_once __DIR__ . '/../shared-components/functions.php';

requireRole('admin');

$page_title = 'Moderation';

// Count how many different organisers blocked each email.
// Three different organisers = suspension is triggered.
$emailCounts = $pdo->query(
    "SELECT BlockedEmail, COUNT(DISTINCT OrganiserID) AS distinct_orgs
     FROM BLOCKS
     GROUP BY BlockedEmail
     ORDER BY distinct_orgs DESC, BlockedEmail ASC"
)->fetchAll();

// Any user whose email matches a block.
$affectedUsers = $pdo->query(
    "SELECT u.UserID, u.FirstName, u.LastName, u.Email,
            u.UserStatus, u.Blocks_count, u.Suspension_count,
            u.Suspension_start_date
     FROM USERS u
     WHERE u.Email IN (SELECT DISTINCT BlockedEmail FROM BLOCKS)
     ORDER BY u.Suspension_count DESC, u.Blocks_count DESC"
)->fetchAll();

// Recent blocks with the organiser's name.
$recentBlocks = $pdo->query(
    "SELECT b.BlockDate, b.BlockedEmail, b.Reason,
            u.FirstName AS OrgFirstName, u.LastName AS OrgLastName
     FROM BLOCKS b
     JOIN USERS u ON u.UserID = b.OrganiserID
     ORDER BY b.BlockDate DESC
     LIMIT 100"
)->fetchAll();

$today = new DateTimeImmutable('now');

require_once __DIR__ . '/../shared-components/header.php';
?>

<div class="dashboard-shell">
    <?php en_render_sidebar(); ?>

    <main id="main-content" class="dashboard-main">
        <h1>Moderation</h1>

        <section class="dashboard-panel">
            <div class="panel-header">
                <h2>Distinct organiser counts</h2>
            </div>
            <p class="text-muted">
                Three different organisers blocking the same email triggers a suspension.
                Repeated blocks from the same organiser only count once.
            </p>

            <?php if (empty($emailCounts)): ?>
                <p class="empty-state">No blocks have been recorded yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Blocked email</th>
                                <th scope="col">Distinct organisers</th>
                                <th scope="col">State</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emailCounts as $row): ?>
                                <?php
                                $distinct = (int)$row['distinct_orgs'];

                                if ($distinct >= 3) {
                                    $stateText  = 'Suspension triggered';
                                    $stateClass = 'status-badge status-suspended';
                                } elseif ($distinct === 2) {
                                    $stateText  = 'One more triggers suspension';
                                    $stateClass = 'status-badge status-pending';
                                } else {
                                    $stateText  = 'Below threshold';
                                    $stateClass = 'status-badge status-draft';
                                }
                                ?>
                                <tr>
                                    <td><?php echo en_e($row['BlockedEmail']); ?></td>
                                    <td><?php echo $distinct; ?></td>
                                    <td><span class="<?php echo $stateClass; ?>"><?php echo en_e($stateText); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel">
            <div class="panel-header">
                <h2>Affected users</h2>
            </div>

            <?php if (empty($affectedUsers)): ?>
                <p class="empty-state">No users currently match a blocked email.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Account state</th>
                                <th scope="col">Blocks</th>
                                <th scope="col">Suspensions</th>
                                <th scope="col">Suspension end</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($affectedUsers as $u): ?>
                                <?php
                                // Work out when a 30-day suspension ends.
                                $endText = '—';
                                if (!empty($u['Suspension_start_date'])) {
                                    $start = new DateTimeImmutable($u['Suspension_start_date']);
                                    $end   = $start->modify('+30 days');
                                    $endText = $end > $today
                                        ? $end->format('d M Y')
                                        : 'Expired';
                                }

                                $susCount = (int)$u['Suspension_count'];

                                if ($susCount >= 2) {
                                    $accountState = 'Permanent ban';
                                    $accountClass = 'status-badge status-suspended';
                                } elseif ($u['UserStatus'] === 'suspended') {
                                    $accountState = 'Suspended';
                                    $accountClass = 'status-badge status-suspended';
                                } else {
                                    $accountState = 'Active';
                                    $accountClass = 'status-badge status-active';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <?php
                                        echo en_e(trim(
                                            ($u['FirstName'] ?? '') . ' ' . ($u['LastName'] ?? '')
                                        ));
                                        ?>
                                    </td>
                                    <td><?php echo en_e($u['Email']); ?></td>
                                    <td><span class="<?php echo $accountClass; ?>"><?php echo en_e($accountState); ?></span></td>
                                    <td><?php echo (int)$u['Blocks_count']; ?></td>
                                    <td><?php echo $susCount; ?></td>
                                    <td><?php echo en_e($endText); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel">
            <div class="panel-header">
                <h2>Recent blocks</h2>
            </div>

            <?php if (empty($recentBlocks)): ?>
                <p class="empty-state">No blocks have been recorded yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Organiser</th>
                                <th scope="col">Blocked email</th>
                                <th scope="col">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBlocks as $b): ?>
                                <tr>
                                    <td><?php echo en_e(formatDate($b['BlockDate'])); ?></td>
                                    <td>
                                        <?php
                                        echo en_e(trim(
                                            ($b['OrgFirstName'] ?? '') . ' ' . ($b['OrgLastName'] ?? '')
                                        ));
                                        ?>
                                    </td>
                                    <td><?php echo en_e($b['BlockedEmail']); ?></td>
                                    <td><?php echo en_e($b['Reason'] ?? '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/../shared-components/footer.php'; ?>
