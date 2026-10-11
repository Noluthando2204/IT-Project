

<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| EventNest - register.php
|--------------------------------------------------------------------------
| Creates attendee accounts using the approved USERS table.
| Requires database-config/connection.php to provide a PDO
| connection named $pdo.
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/database-config/connection.php';

$message = '';
$message_type = 'error';

$fullName = '';
$email = '';

/*
|--------------------------------------------------------------------------
| Redirect already authenticated users
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['user_id'])) {
    header('Location: my-bookings.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF protection
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['register_csrf_token'])) {
    $_SESSION['register_csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

/*
|--------------------------------------------------------------------------
| Process registration
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals(
            $_SESSION['register_csrf_token'],
            $submittedToken
        )
    ) {
        $message = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $fullName = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        /*
         * Validate submitted data types.
         */

        if (
            !is_string($fullName) ||
            !is_string($email) ||
            !is_string($password)
        ) {
            $message = 'Please enter valid registration details.';
        } elseif (
            $fullName === '' ||
            !preg_match('//u', $fullName) ||
            preg_match('/[\x00-\x1F\x7F]/', $fullName)
        ) {
            $message = 'Please enter a valid full name.';
        } elseif (
            preg_match('/\s/u', $fullName) === 0
        ) {
            $message = 'Please enter both your first and last name.';
        } elseif (strlen($fullName) > 101) {
            $message = 'Your full name is too long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
        } elseif (strlen($email) > 100) {
            $message = 'Your email address must not exceed 100 characters.';
        } elseif (
            strlen($password) < 8 ||
            strlen($password) > 72
        ) {
            $message = 'Your password must be between 8 and 72 bytes.';
        } else {
            /*
             * Split the full name into the approved database fields.
             * The first word becomes FirstName; remaining words
             * become LastName.
             */

            $nameParts = preg_split(
                '/\s+/u',
                $fullName,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            if ($nameParts === false || count($nameParts) < 2) {
                $message = 'Please enter your first and last name.';
            } else {
                $firstName = array_shift($nameParts);
                $lastName = implode(' ', $nameParts);

                if (
                    mb_strlen($firstName, 'UTF-8') > 50 ||
                    mb_strlen($lastName, 'UTF-8') > 50
                ) {
                    $message = 'Your first or last name exceeds the allowed length.';
                } else {
                    try {
                        /*
                         * Check for an existing email.
                         * The database UNIQUE constraint remains
                         * the final protection against duplicates.
                         */

                        $checkStatement = $pdo->prepare(
                            'SELECT UserID
                             FROM USERS
                             WHERE Email = :email
                             LIMIT 1'
                        );

                        $checkStatement->execute([
                            ':email' => $email
                        ]);

                        if ($checkStatement->fetchColumn() !== false) {
                            $message = 'An account with this email address already exists.';
                        } else {
                            /*
                             * Never store the original password.
                             */

                            $passwordHash = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            if ($passwordHash === false) {
                                throw new RuntimeException(
                                    'Password hashing failed.'
                                );
                            }

                            /*
                             * Use schema-approved defaults:
                             * Role = attendee
                             * UserStatus = active
                             * VerificationStatus = unverified
                             * Is_email_verified = FALSE
                             *
                             * These values are set explicitly so
                             * registration cannot assign privileged
                             * roles through submitted form data.
                             */

                            $insertStatement = $pdo->prepare(
                                'INSERT INTO USERS (
                                    FirstName,
                                    LastName,
                                    Email,
                                    PasswordHash,
                                    Role,
                                    UserStatus,
                                    VerificationStatus,
                                    Is_email_verified
                                ) VALUES (
                                    :first_name,
                                    :last_name,
                                    :email,
                                    :password_hash,
                                    :role,
                                    :user_status,
                                    :verification_status,
                                    :email_verified
                                )'
                            );

                            $insertStatement->execute([
                                ':first_name' => $firstName,
                                ':last_name' => $lastName,
                                ':email' => $email,
                                ':password_hash' => $passwordHash,
                                ':role' => 'attendee',
                                ':user_status' => 'active',
                                ':verification_status' => 'unverified',
                                ':email_verified' => 0
                            ]);

                            /*
                             * Rotate the CSRF token after success.
                             */

                            $_SESSION['register_csrf_token'] =
                                bin2hex(random_bytes(32));

                            /*
                             * Do not automatically authenticate the
                             * new account or mark its email verified.
                             */

                            header('Location: login.php?registered=1');
                            exit;
                        }
                    } catch (PDOException $exception) {
                        /*
                         * Handle a duplicate email that may have
                         * been inserted concurrently by another request.
                         * MySQL duplicate-key error: SQLSTATE 23000,
                         * driver error 1062.
                         */

                        $errorInfo = $exception->errorInfo ?? [];

                        if (
                            $exception->getCode() === '23000' &&
                            isset($errorInfo[1]) &&
                            (int) $errorInfo[1] === 1062
                        ) {
                            $message = 'An account with this email address already exists.';
                        } else {
                            error_log(
                                'EventNest registration database error: ' .
                                $exception->getMessage()
                            );

                            $message = 'Registration could not be completed. Please try again later.';
                        }
                    } catch (Throwable $exception) {
                        error_log(
                            'EventNest registration error: ' .
                            $exception->getMessage()
                        );

                        $message = 'Registration could not be completed. Please try again later.';
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Shared EventNest layout
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/shared-components/header.php';
require_once __DIR__ . '/shared-components/navbar.php';
?>

<main class="auth-container">
    <h1>Create your EventNest account</h1>

    <p>
        Register to discover and book career-related events.
    </p>

    <?php if ($message !== ''): ?>
        <p
            class="form-message form-message--<?= htmlspecialchars(
                $message_type,
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            role="alert"
        >
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $_SESSION['register_csrf_token'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
        >

        <div class="form-group">
            <label for="name">Full name</label>

            <input
                type="text"
                id="name"
                name="name"
                autocomplete="name"
                maxlength="101"
                value="<?= htmlspecialchars(
                    $fullName,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="email">Email address</label>

            <input
                type="email"
                id="email"
                name="email"
                autocomplete="email"
                maxlength="100"
                value="<?= htmlspecialchars(
                    $email,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="new-password"
                minlength="8"
                maxlength="72"
                required
            >

            <small>
                Enter a password of at least 8 characters.
            </small>
        </div>

        <button type="submit">
            Create account
        </button>
    </form>

    <p>
        Already have an account?
        <a href="login.php">Log in</a>
    </p>
</main>

<?php require_once __DIR__ . '/shared-components/footer.php'; ?>
