<?php

declare(strict_types=1);

require_once __DIR__ . '/shared-components/functions.php';
require_once __DIR__ . '/database-config/connection.php';

/*
PayFast notifications arrive without an organiser session.
The signature, merchant and server-to-server check authenticate this branch.
Browser return URLs never extend fee coverage.
*/
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['m_payment_id'], $_POST['signature'])) {
    header('Content-Type: text/plain; charset=utf-8');
    try {
        eventnest_record_verification_fee(eventnest_db(), $_POST);
        echo 'OK';
    } catch (Throwable $exception) {
        error_log('EventNest fee notification rejected: ' . get_class($exception));
        http_response_code(400); echo 'INVALID';
    }
    exit;
}
require_once __DIR__ . '/shared-components/auth.php';

//ORGANISER AUTHENTICATION

$user = auth_require_organiser();

$user_id = (int) $user['UserID'];

//POST ONLY
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    http_response_code(405);
    exit('Method not allowed.');
}

//CSRF VALIDATION
$csrf_token = $_POST['csrf_token'] ?? null;

if (!eventnest_verify_csrf_token($csrf_token)) {
    auth_set_flash(
        'Your verification request could not be confirmed. Please try again.'
    );

    auth_redirect('organiser-verification.php');
}

// Fee checkout reuses this approved verification handler and existing USERS fee fields.
if (trim_input($_POST['action'] ?? '') === 'pay_fee') {
    try {
        $form = eventnest_prepare_verification_fee(auth_db(), $user);
        $page_title = 'Annual organiser verification fee';
        require __DIR__ . '/shared-components/header.php';
        ?>
        <main class="result-card"><h1>Annual organiser verification</h1>
        <p>R99.00 for 12 months from successfully verified payment. This checkout uses PayFast sandbox.</p>
        <p>Returning to EventNest does not confirm payment. Document approval remains a separate administrator decision.</p>
        <form method="post" action="https://sandbox.payfast.co.za/eng/process">
        <?php foreach ($form as $name => $value): ?><input type="hidden" name="<?= escape_html($name) ?>" value="<?= escape_html($value) ?>"><?php endforeach; ?>
        <button class="btn btn-orange">Continue to secure sandbox payment</button>
        </form><p><a href="organiser-verification.php">Back to verification</a></p></main>
        <?php require __DIR__ . '/shared-components/footer.php';
        exit;
    } catch (Throwable $exception) {
        auth_set_flash($exception instanceof DomainException ? $exception->getMessage() : 'Fee checkout is unavailable. Please try again later.');
        auth_redirect('organiser-verification.php');
    }
}

//CHECK CURRENT VERIFICATION STATE
$current_status = (string) $user['VerificationStatus'];

$upload_eligible_statuses = [
    'unverified',
    'pending',
    'rejected',
];

if (!in_array(
    $current_status,
    $upload_eligible_statuses,
    true
)) {
    auth_set_flash(
        'Your organiser verification is already complete.',
        'info'
    );

    auth_redirect('organiser-verification.php');
}

//FILE EXISTS
if (
    !isset($_FILES['verification_doc'])
    || !is_array($_FILES['verification_doc'])
) {
    auth_set_flash(
        'Please select a verification document.'
    );

    auth_redirect('organiser-verification.php');
}

$file = $_FILES['verification_doc'];
if (!is_int($file['error'] ?? null) || !is_string($file['tmp_name'] ?? null)
    || !is_string($file['name'] ?? null) || !is_int($file['size'] ?? null)) {
    auth_set_flash('The uploaded verification document is invalid.');
    auth_redirect('organiser-verification.php');
}

//PHP UPLOAD ERROR
$upload_error = isset($file['error'])
    ? (int) $file['error']
    : UPLOAD_ERR_NO_FILE;

if ($upload_error !== UPLOAD_ERR_OK) {
    $message = 'The verification document could not be uploaded.';

    switch ($upload_error) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $message = 'The verification document is too large.';
            break;

        case UPLOAD_ERR_NO_FILE:
            $message = 'Please select a verification document.';
            break;

        case UPLOAD_ERR_PARTIAL:
            $message =
                'The verification document was only partially uploaded. Please try again.';
            break;
    }

    auth_set_flash($message);

    auth_redirect('organiser-verification.php');
}

//VERIFY TEMPORARY UPLOAD
$tmp_name = isset($file['tmp_name'])
    ? (string) $file['tmp_name']
    : '';

if (
    $tmp_name === ''
    || !is_uploaded_file($tmp_name)
) {
    auth_set_flash(
        'The uploaded verification document is invalid.'
    );

    auth_redirect('organiser-verification.php');
}

//FILE SIZE
function verification_ini_bytes(string $value): int
{
    $value = trim($value);

    if ($value === '') {
        return 0;
    }

    if ($value === '-1') {
        return 0;
    }

    $last = strtolower(substr($value, -1));

    $number = (float) $value;

    switch ($last) {
        case 'g':
            $number *= 1024;
            // fall through

        case 'm':
            $number *= 1024;
            // fall through

        case 'k':
            $number *= 1024;
            break;
    }

    return (int) $number;
}


$file_size = isset($file['size'])
    ? (int) $file['size']
    : 0;

if ($file_size <= 0) {
    auth_set_flash(
        'The verification document is empty.'
    );

    auth_redirect('organiser-verification.php');
}

//MAXIMUM FILE SIZE - 5 MB. This applies to PDF, JPG, JPEG and PNG files.
$max_file_size = 5 * 1024 * 1024; // 5 MB

if ($file_size > $max_file_size) {
    auth_set_flash(
        'The verification document must not exceed 5 MB.'
    );

    auth_redirect('organiser-verification.php');
}

$upload_max = verification_ini_bytes(
    (string) ini_get('upload_max_filesize')
);

$post_max = verification_ini_bytes(
    (string) ini_get('post_max_size')
);

$server_limits = [];

if ($upload_max > 0) {
    $server_limits[] = $upload_max;
}

if ($post_max > 0) {
    $server_limits[] = $post_max;
}

if (!empty($server_limits)) {
    $maximum_size = min($server_limits);

    if ($file_size > $maximum_size) {
        auth_set_flash(
            'The verification document is too large.'
        );

        auth_redirect('organiser-verification.php');
    }
}

//EXTENSION VALIDATION
if (!class_exists('finfo')) {
    error_log(
        'EventNest verification: PHP Fileinfo extension is unavailable.'
    );

    auth_set_flash(
        'Document verification is temporarily unavailable.'
    );

    auth_redirect('organiser-verification.php');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);

$mime_type = $finfo->file($tmp_name);

$allowed_types = [
    'application/pdf' => ['pdf'],
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png' => ['png'],
];

if (
    !is_string($mime_type)
    || !isset($allowed_types[$mime_type])
) {
    auth_set_flash(
        'Please upload a PDF, JPG, JPEG or PNG verification document.'
    );

    auth_redirect('organiser-verification.php');
}


$original_name = isset($file['name'])
    ? (string) $file['name']
    : '';

$extension = strtolower(
    pathinfo($original_name, PATHINFO_EXTENSION)
);

if (
    $extension === ''
    || !in_array(
        $extension,
        $allowed_types[$mime_type],
        true
    )
) {
    auth_set_flash(
        'The verification document type does not match its file extension.'
    );

    auth_redirect('organiser-verification.php');
}

//ADDITIONAL IMAGE VALIDATION
if (
    $mime_type === 'image/jpeg'
    || $mime_type === 'image/png'
) {
    if (@getimagesize($tmp_name) === false) {
        auth_set_flash(
            'The uploaded image is not valid.'
        );

        auth_redirect('organiser-verification.php');
    }
}

//PROTECTED UPLOAD LOCATION
$upload_directory = eventnest_verification_directory();
if (!is_dir($upload_directory)) {
    @mkdir($upload_directory, 0700, true);
}

if (
    !is_dir($upload_directory)
    || !is_writable($upload_directory)
) {
    error_log(
        'EventNest verification: protected upload directory missing or not writable.'
    );

    auth_set_flash(
        'Verification document storage is temporarily unavailable.'
    );

    auth_redirect('organiser-verification.php');
}

//Do not save original client filename for permanent storage.
try {
    $random_name = bin2hex(random_bytes(16));
} catch (Exception $e) {
    error_log(
        'EventNest verification filename generation failed: '
        . $e->getMessage()
    );

    auth_set_flash(
        'The verification document could not be saved.'
    );

    auth_redirect('organiser-verification.php');
}

$stored_extension = $allowed_types[$mime_type][0];

$safe_filename =
    'verification_'
    . $user_id
    . '_'
    . $random_name
    . '.'
    . $stored_extension;

$absolute_path =
    $upload_directory
    . DIRECTORY_SEPARATOR
    . $safe_filename;

$database_path = $absolute_path;


//MOVE UPLOAD
if (!move_uploaded_file($tmp_name, $absolute_path)) {
    error_log(
        'EventNest verification: move_uploaded_file failed for UserID '
        . $user_id
    );

    auth_set_flash(
        'The verification document could not be saved.'
    );

    auth_redirect('organiser-verification.php');
}

/*
Best-effort restrictive permission.
Windows/XAMPP may handle permissions differently.
*/
@chmod($absolute_path, 0640);

//UPDATE DATABASE
$db = auth_db();

$old_database_path = isset($user['Verification_docs_path'])
    ? (string) $user['Verification_docs_path']
    : '';

try {
    $db->beginTransaction();

    $stmt = $db->prepare(
        "
        UPDATE USERS
        SET
            Verification_docs_path = :document_path,
            VerificationStatus = 'pending'
        WHERE
            UserID = :user_id
            AND Role = 'organiser'
            AND VerificationStatus IN (
                'unverified',
                'pending',
                'rejected'
            )
        "
    );

    $stmt->execute([
        ':document_path' => $database_path,
        ':user_id' => $user_id,
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Verification record was not updated.'
        );
    }

    $db->commit();

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    /*
Do not leave an orphaned uploaded document if the DB update fails.
*/
    if (is_file($absolute_path)) {
        @unlink($absolute_path);
    }

    error_log(
        'EventNest verification database update failed: '
        . $e->getMessage()
    );

    auth_set_flash(
        'Your verification document could not be submitted. Please try again.'
    );

    auth_redirect('organiser-verification.php');
}

// REMOVE PREVIOUS DOCUMENT AFTER SUCCESSFUL UPDATE
if (
    $old_database_path !== ''
    && $old_database_path !== $database_path
) {
    $upload_real = realpath($upload_directory);

    $old_absolute = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $old_database_path
        );

    $old_real = realpath($old_absolute);

    if (
        $upload_real !== false
        && $old_real !== false
        && strpos(
            $old_real,
            $upload_real . DIRECTORY_SEPARATOR
        ) === 0
        && is_file($old_real)
    ) {
        @unlink($old_real);
    }
}

//REFRESH COMPATIBILITY SESSION STATE
$_SESSION['verification_status'] = 'pending';
auth_set_flash(
    'Your verification document was submitted successfully and is awaiting administrator review.',
    'success'
);

auth_redirect('organiser-verification.php');

