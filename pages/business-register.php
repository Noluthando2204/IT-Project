

<?php
session_start();

require_once 'database-config/connection.php';

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $career_field = trim($_POST['career_field'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $document = $_FILES['verification_document'] ?? null;
    $document_path = null;
    $saved_file = null;

    try {
        // Validate required fields.
        if (
            $first_name === '' ||
            $last_name === '' ||
            $email === '' ||
            $phone === '' ||
            $career_field === '' ||
            $password === '' ||
            $confirm_password === ''
        ) {
            throw new RuntimeException('Please complete all required fields.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('Your password must contain at least 8 characters.');
        }

        if ($password !== $confirm_password) {
            throw new RuntimeException('The passwords do not match.');
        }

        // A CIPC or identity document is required for organiser verification.
        if (
            !$document ||
            $document['error'] !== UPLOAD_ERR_OK
        ) {
            throw new RuntimeException('Please upload your CIPC or identity verification document.');
        }

        if ($document['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('The document must not exceed 5 MB.');
        }

        // Check the actual file type, not just its extension.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($document['tmp_name']);

        $allowed_types = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png'
        ];

        if (!isset($allowed_types[$mime_type])) {
            throw new RuntimeException('Upload a PDF, JPG or PNG document.');
        }

        // Check for an existing account with this email.
        $check = $conn->prepare(
            'SELECT UserID FROM USERS WHERE Email = ?'
        );
        $check->execute([$email]);

        if ($check->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'An account with this email address already exists.'
            );
        }

        // Store verification documents in a non-public directory
        // where possible. Configure this path for your server.
        $upload_directory = dirname(__DIR__) . '/eventnest-private/organiser-documents';

        if (
            !is_dir($upload_directory) &&
            !mkdir($upload_directory, 0700, true)
        ) {
            throw new RuntimeException('Unable to prepare secure document storage.');
        }

        if (!is_writable($upload_directory)) {
            throw new RuntimeException('Document storage is not writable.');
        }

        $file_name = bin2hex(random_bytes(24))
            . '.'
            . $allowed_types[$mime_type];

        $saved_file = $upload_directory . DIRECTORY_SEPARATOR . $file_name;

        if (!move_uploaded_file($document['tmp_name'], $saved_file)) {
            throw new RuntimeException('Unable to save the verification document.');
        }

        $document_path = $saved_file;

        // Generate an email verification token.
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $token_expiry = date('Y-m-d H:i:s', time() + 3600);

        // Hash the password before storing it.
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        if ($password_hash === false) {
            throw new RuntimeException('Unable to secure your password.');
        }

        // Create an organiser account. Verification and payment
        // remain incomplete until the appropriate workflows finish.
        $sql = "
            INSERT INTO USERS (
                FirstName,
                LastName,
                Email,
                PasswordHash,
                Phone,
                CareerField,
                UserStatus,
                Role,
                VerificationStatus,
                Verification_docs_path,
                RecommendationOptIn,
                Blocks_count,
                Suspension_count,
                Is_email_verified,
                EmailVerificationToken,
                EmailVerificationExpiresAt,
                VerificationFeePaymentStatus
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                'active',
                'organiser',
                'pending',
                ?,
                0,
                0,
                0,
                0,
                ?,
                ?,
                'unpaid'
            )
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $first_name,
            $last_name,
            $email,
            $password_hash,
            $phone,
            $career_field,
            $document_path,
            $token_hash,
            $token_expiry
        ]);

        // Set this to the actual URL of your EventNest project.
        $base_url = 'http://localhost/IT-Project';
        $verification_url = $base_url
            . '/verify-email.php?token='
            . urlencode($token);

        $subject = 'Verify your EventNest organiser account';

        $body = "Hello {$first_name},\n\n"
            . "Thank you for registering as an EventNest organiser.\n\n"
            . "Verify your email address using this link:\n"
            . $verification_url . "\n\n"
            . "This link expires in one hour.\n\n"
            . "Your organiser verification and document review are still pending.\n"
            . "Your verification fee has not been marked as paid.\n";

        $headers = "From: no-reply@your-domain.example\r\n"
            . "Content-Type: text/plain; charset=UTF-8";

        if (!mail($email, $subject, $body, $headers)) {
            throw new RuntimeException(
                'Your registration could not complete email delivery. Please contact support.'
            );
        }

        $message = 'Registration submitted. Please check your email to verify your address. Your organiser verification is pending.';
        $message_type = 'success';

    } catch (RuntimeException $e) {
        $message = $e->getMessage();

        if ($saved_file && is_file($saved_file)) {
            unlink($saved_file);
        }

    } catch (PDOException $e) {
        error_log('EventNest business registration error: ' . $e->getMessage());

        $message = 'Registration could not be completed. Please try again.';

        if ($saved_file && is_file($saved_file)) {
            unlink($saved_file);
        }
    }
}

function escapeBusinessRegistration($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<?php include 'shared-components/header.php'; ?>

<main class="container">
    <h2>EventNest Business / Organiser Registration</h2>

    <p>
        Register as an organiser to apply to manage events on EventNest.
        Your verification documents must be reviewed before organiser
        privileges can be granted.
    </p>

    <?php if ($message !== ''): ?>
        <p
            role="status"
            style="color: <?= $message_type === 'success' ? 'green' : 'red'; ?>;"
        >
            <?= escapeBusinessRegistration($message); ?>
        </p>
    <?php endif; ?>

    <form
        action="business-registration.php"
        method="POST"
        enctype="multipart/form-data"
    >
        <label for="first_name">First name</label><br>
        <input
            type="text"
            id="first_name"
            name="first_name"
            maxlength="100"
            autocomplete="given-name"
            required
        ><br><br>

        <label for="last_name">Last name</label><br>
        <input
            type="text"
            id="last_name"
            name="last_name"
            maxlength="100"
            autocomplete="family-name"
            required
        ><br><br>

        <label for="email">Business / account email</label><br>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="254"
            autocomplete="email"
            required
        ><br><br>

        <label for="phone">Contact number</label><br>
        <input
            type="tel"
            id="phone"
            name="phone"
            maxlength="30"
            autocomplete="tel"
            required
        ><br><br>

        <label for="career_field">Career industry / sector</label><br>
        <input
            type="text"
            id="career_field"
            name="career_field"
            maxlength="150"
            required
        ><br><br>

        <label for="password">Password (at least 8 characters)</label><br>
        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            autocomplete="new-password"
            required
        ><br><br>

        <label for="confirm_password">Confirm password</label><br>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            minlength="8"
            autocomplete="new-password"
            required
        ><br><br>

        <label for="verification_document">
            Upload CIPC or identity verification document (PDF, JPG or PNG; maximum 5 MB)
        </label><br>
        <input
            type="file"
            id="verification_document"
            name="verification_document"
            accept=".pdf,.jpg,.jpeg,.png"
            required
        ><br><br>

        <button type="submit">Register as Organiser</button>
    </form>

    <p>
        Already registered?
        <a href="login.php">Log in</a>
    </p>
</main>

<?php include 'shared-components/footer.php'; ?>
