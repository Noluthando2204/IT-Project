<?php

declare(strict_types=1);

require_once __DIR__ . '/../shared-components/auth.php';
require_once __DIR__ . '/../shared-components/functions.php';

$admin_user = auth_require_admin();
$pdo = eventnest_db();

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function admin_verification_positive_id(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $id === false ? 0 : (int) $id;
}

function admin_verification_load_organiser(PDO $pdo, int $user_id): ?array
{
    $statement = $pdo->prepare(
        "SELECT
            UserID,
            FirstName,
            LastName,
            Email,
            Role,
            VerificationStatus,
            Verification_docs_path,
            VerificationFeePaidAt,
            VerificationFeePaidUntil,
            VerificationFeePaymentStatus,
            VerificationFeeGatewayReference,
            CreatedAt
        FROM USERS
        WHERE UserID = :user_id
          AND Role = 'organiser'
        LIMIT 1"
    );

    $statement->execute([':user_id' => $user_id]);
    $organiser = $statement->fetch();

    return $organiser ?: null;
}

function admin_verification_document_path(?string $database_path): ?array
{
    if ($database_path === null) {
        return null;
    }

    if ($database_path === '' || strpos($database_path, "\0") !== false) {
        return null;
    }
    $uploads_root = realpath(eventnest_verification_directory());
    $absolute_path = realpath($database_path);
    // Read previously stored documents without changing database records.
    if (strpos(str_replace('\\', '/', $database_path), 'uploaded-content/') === 0) {
        $uploads_root = realpath(eventnest_project_root() . '/uploaded-content');
        $absolute_path = realpath(eventnest_project_root() . '/' . $database_path);
    }
    if ($uploads_root === false || $absolute_path === false || !is_file($absolute_path)) {
        return null;
    }
    $uploads_prefix = rtrim(str_replace('\\', '/', $uploads_root), '/') . '/';
    $normalised_file = str_replace('\\', '/', $absolute_path);
    if (strpos($normalised_file, $uploads_prefix) !== 0) {
        return null;
    }

    if (!class_exists('finfo')) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($absolute_path);

    $allowed_mime_types = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    if (!is_string($mime_type) || !isset($allowed_mime_types[$mime_type])) {
        return null;
    }

    return [
        'absolute' => $absolute_path,
        'mime_type' => $mime_type,
        'extension' => $allowed_mime_types[$mime_type],
    ];
}

/*
Protected document view
The real upload path is never exposed to the browser.
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && trim_input($_GET['action'] ?? '') === 'view_document'
) {
    $user_id = admin_verification_positive_id($_GET['user_id'] ?? null);

    if ($user_id <= 0) {
        http_response_code(400);
        exit('Invalid organiser.');
    }

    $organiser = admin_verification_load_organiser($pdo, $user_id);

    if ($organiser === null) {
        http_response_code(404);
        exit('Organiser not found.');
    }

    $document = admin_verification_document_path(
        $organiser['Verification_docs_path'] ?? null
    );

    if ($document === null) {
        http_response_code(404);
        exit('Verification document is unavailable.');
    }

    $safe_filename = 'eventnest-verification-' . $user_id . '.' . $document['extension'];

    header('Content-Type: ' . $document['mime_type']);
    header('Content-Length: ' . filesize($document['absolute']));
    header('Content-Disposition: inline; filename="' . $safe_filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');

    readfile($document['absolute']);
    exit;
}

/*
Approve / reject
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : null;

    if (!eventnest_verify_csrf_token($csrf_token)) {
        http_response_code(403);
        exit('The request could not be verified.');
    }

    $user_id = admin_verification_positive_id($_POST['user_id'] ?? null);
    $action = trim_input($_POST['action'] ?? '');
    $rejection_reason = trim_input($_POST['rejection_reason'] ?? '');

    if ($user_id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        http_response_code(400);
        exit('Invalid verification request.');
    }

    $organiser = admin_verification_load_organiser($pdo, $user_id);

    if ($organiser === null) {
        http_response_code(404);
        exit('Organiser not found.');
    }

    if ((string) $organiser['VerificationStatus'] !== 'pending') {
        header('Location: manage-verifications.php?result=not_pending&user_id=' . $user_id);
        exit;
    }

    $document = admin_verification_document_path(
        $organiser['Verification_docs_path'] ?? null
    );

    if ($document === null) {
        header('Location: manage-verifications.php?result=document_unavailable&user_id=' . $user_id);
        exit;
    }

    if ($action === 'reject') {
        $reason_length = function_exists('mb_strlen')
            ? mb_strlen($rejection_reason, 'UTF-8')
            : strlen($rejection_reason);

        if ($reason_length < 10 || $reason_length > 500) {
            header('Location: manage-verifications.php?result=invalid_rejection_reason&user_id=' . $user_id);
            exit;
        }
    }

    $new_status = $action === 'approve' ? 'verified' : 'rejected';

    try {
        $pdo->beginTransaction();

        $lock_statement = $pdo->prepare(
            "SELECT UserID, VerificationStatus, Verification_docs_path
            FROM USERS
            WHERE UserID = :user_id
              AND Role = 'organiser'
            LIMIT 1
            FOR UPDATE"
        );
        $lock_statement->execute([':user_id' => $user_id]);
        $locked_organiser = $lock_statement->fetch();

        if (
            !$locked_organiser
            || (string) $locked_organiser['VerificationStatus'] !== 'pending'
            || $locked_organiser['Verification_docs_path'] !== $organiser['Verification_docs_path']
        ) {
            throw new DomainException(
                'The verification state changed before the decision was saved.'
            );
        }

        $update_statement = $pdo->prepare(
            "UPDATE USERS
            SET VerificationStatus = :verification_status
            WHERE UserID = :user_id
              AND Role = 'organiser'
              AND VerificationStatus = 'pending'"
        );

        $update_statement->execute([
            ':verification_status' => $new_status,
            ':user_id' => $user_id,
        ]);

        if ($update_statement->rowCount() !== 1) {
            throw new DomainException(
                'The organiser verification could not be updated safely.'
            );
        }

        $pdo->commit();

        if ($action === 'reject') {
            /*
The current approved schema has no rejection-reason column.
Do not invent one. The reason is validated for the admin decision,
and can later be passed to an approved notification mechanism.
*/
            error_log(
                'EventNest organiser verification rejected for UserID '
                . $user_id
                . '. Rejection guidance was supplied by the administrator.'
            );
        }

        $notification_sent = eventnest_send_mail(
            (string) $organiser['Email'],
            'EventNest organiser verification',
            $action === 'reject' ? 'Your organiser verification was rejected. ' . $rejection_reason : 'Your organiser document verification was approved. Annual fee coverage is checked separately.'
        );
        if (!$notification_sent) {
            auth_set_flash('Decision saved. The notification email could not be delivered; contact the organiser through the support workflow.', 'info');
        }

        header(
            'Location: manage-verifications.php?result=' .
            rawurlencode($action === 'approve' ? 'approved' : 'rejected') .
            '&user_id=' . $user_id
        );
        exit;
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('EventNest verification decision denied: ' . $exception->getMessage());
        header('Location: manage-verifications.php?result=state_changed&user_id=' . $user_id);
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'EventNest verification decision error: ' .
            get_class($exception) . ' - ' . $exception->getMessage()
        );

        header('Location: manage-verifications.php?result=error');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

$selected_user_id = admin_verification_positive_id($_GET['user_id'] ?? null);
$result = trim_input($_GET['result'] ?? '');

$result_messages = [
    'approved' => 'The organiser verification was approved.',
    'rejected' => 'The organiser verification was rejected.',
    'not_pending' => 'Only a pending verification can be approved or rejected.',
    'document_unavailable' => 'The private verification document is unavailable or failed validation.',
    'invalid_rejection_reason' => 'Enter a rejection reason between 10 and 500 characters.',
    'state_changed' => 'The verification record changed before the decision was saved. Reload and review it again.',
    'error' => 'The verification decision could not be completed. Please try again.',
];

$result_message = $result_messages[$result] ?? '';
$csrf_token = eventnest_csrf_token();
$organisers = [];
$error_message = '';

try {
    $statement = $pdo->query(
        "SELECT
            UserID,
            FirstName,
            LastName,
            Email,
            VerificationStatus,
            Verification_docs_path,
            VerificationFeePaidAt,
            VerificationFeePaidUntil,
            VerificationFeePaymentStatus,
            CreatedAt
        FROM USERS
        WHERE Role = 'organiser'
        ORDER BY
            CASE VerificationStatus
                WHEN 'pending' THEN 0
                WHEN 'unverified' THEN 1
                WHEN 'rejected' THEN 2
                WHEN 'verified' THEN 3
                ELSE 4
            END,
            CreatedAt DESC,
            UserID DESC"
    );

    $organisers = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log('EventNest manage-verifications load error: ' . $exception->getMessage());
    $error_message = 'Organiser verification records could not be loaded.';
}

$page_title = 'Manage organiser verifications';
require_once __DIR__ . '/../shared-components/header.php';
?>

<div class="dashboard-layout"><?php eventnest_sidebar('admin'); ?><main class="dashboard-main">
    <header class="page-header">
        <p>Administrator</p>
        <h1>Manage organiser verifications</h1>
        <p>Review private organiser documents, verification status and annual fee coverage as separate controls.</p>
        <div class="button-group">
            <a class="btn btn--outline" href="manage-bookings.php">Manage bookings</a>
            <a class="btn btn--outline" href="manage-businesses.php">Manage businesses</a>
        </div>
    </header>

    <?php if ($result_message !== ''): ?>
        <div class="message <?= $result === 'approved' ? 'message--success' : 'message--info' ?>" role="status">
            <?= escape_html($result_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message !== ''): ?>
        <div class="message message--error" role="alert"><?= escape_html($error_message) ?></div>
    <?php endif; ?>

    <section class="panel" aria-labelledby="verification-list-heading">
        <h2 id="verification-list-heading">Organiser records</h2>

        <?php if ($organisers === []): ?>
            <div class="empty-state">
                <h3>No organiser records found</h3>
                <p>There are currently no organiser accounts to review.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Organiser</th>
                            <th scope="col">Email</th>
                            <th scope="col">Verification</th>
                            <th scope="col">Document</th>
                            <th scope="col">Fee coverage</th>
                            <th scope="col">Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($organisers as $organiser): ?>
                        <?php
                        $user_id = (int) $organiser['UserID'];
                        $verification_status = (string) $organiser['VerificationStatus'];
                        $document = admin_verification_document_path(
                            $organiser['Verification_docs_path'] ?? null
                        );

                        $paid_until = $organiser['VerificationFeePaidUntil'] ?? null;
                        $paid_until_timestamp = $paid_until ? strtotime((string) $paid_until) : false;
                        $coverage_active =
                            (string) $organiser['VerificationFeePaymentStatus'] === 'paid'
                            && $paid_until_timestamp !== false
                            && $paid_until_timestamp > time();

                        $organiser_name = trim(
                            (string) ($organiser['FirstName'] ?? '') . ' ' .
                            (string) ($organiser['LastName'] ?? '')
                        );
                        $is_selected = $selected_user_id === $user_id;
                        ?>
                        <tr <?= $is_selected ? 'aria-current="true"' : '' ?>>
                            <td>
                                <strong><?= escape_html($organiser_name !== '' ? $organiser_name : 'Unnamed organiser') ?></strong><br>
                                <small>User #<?= $user_id ?></small>
                            </td>
                            <td><?= escape_html((string) $organiser['Email']) ?></td>
                            <td>
                                <span class="<?= escape_html(eventnest_status_class($verification_status)) ?>">
                                    <?= escape_html(eventnest_status_label($verification_status)) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($document !== null): ?>
                                    <a class="btn btn--small btn--outline" href="<?= escape_html('manage-verifications.php?action=view_document&user_id=' . $user_id) ?>" target="_blank" rel="noopener">View document</a>
                                <?php else: ?>
                                    Unavailable
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($coverage_active): ?>
                                    <span class="<?= escape_html(eventnest_status_class('paid')) ?>">Active</span><br>
                                    <small>Until <?= escape_html(format_datetime((string) $paid_until)) ?></small>
                                <?php elseif ($paid_until): ?>
                                    <span class="<?= escape_html(eventnest_status_class('unpaid')) ?>">Expired / review</span><br>
                                    <small>Ended <?= escape_html(format_datetime((string) $paid_until)) ?></small>
                                <?php else: ?>
                                    No paid coverage recorded
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($verification_status === 'pending' && $document !== null): ?>
                                    <form method="post" action="manage-verifications.php" onsubmit="return confirm('Approve this organiser verification?');">
                                        <input type="hidden" name="csrf_token" value="<?= escape_html($csrf_token) ?>">
                                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn--confirm" type="submit">Approve</button>
                                    </form>

                                    <form method="post" action="manage-verifications.php" onsubmit="return confirm('Reject this organiser verification?');">
                                        <input type="hidden" name="csrf_token" value="<?= escape_html($csrf_token) ?>">
                                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                        <input type="hidden" name="action" value="reject">

                                        <label for="rejection_reason_<?= $user_id ?>">Rejection reason</label>
                                        <textarea id="rejection_reason_<?= $user_id ?>" name="rejection_reason" maxlength="500" rows="2" required></textarea>

                                        <button class="btn btn--danger" type="submit">Reject</button>
                                    </form>
                                <?php elseif ($verification_status === 'pending' && $document === null): ?>
                                    <p>A valid private document is required before a decision.</p>
                                <?php else: ?>
                                    <p>No pending decision.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>


</main></div>

<?php
require_once __DIR__ . '/../shared-components/footer.php';
