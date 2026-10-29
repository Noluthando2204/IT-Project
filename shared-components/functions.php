<?php

declare(strict_types=1);

// Event dates and booking cutoffs use South African time throughout the application.
date_default_timezone_set('Africa/Johannesburg');

// Keep shared layout names connected to the existing project helpers.
function e(mixed $value): string { return escape_html($value); }
function app_url(string $path = ''): string { return eventnest_url($path); }

function eventnest_id(mixed $value): int
{
    if (!is_string($value) && !is_int($value)) { return 0; }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : (int) $id;
}

function eventnest_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST'); http_response_code(405); exit('Method not allowed.');
    }
    if (!eventnest_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403); exit('The request could not be verified. Refresh the form.');
    }
}

function eventnest_csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . escape_html(eventnest_csrf_token()) . '">';
}

function eventnest_status_badge(string $status): string
{
    return '<span class="' . eventnest_status_class($status) . '">' . escape_html(eventnest_status_label($status)) . '</span>';
}

// Sidebars use fixed project routes; endpoints still check their own permissions.
function eventnest_sidebar(string $role): void
{
    $links = $role === 'admin' ? [
        'admin-panel/dashboard.php' => 'Overview', 'admin-panel/manage-verifications.php' => 'Verifications',
        'admin-panel/manage-events.php' => 'Events', 'admin-panel/manage-bookings.php' => 'Bookings',
        'admin-panel/manage-businesses.php' => 'Organisers', 'admin-panel/manage-users.php' => 'Users',
        'admin-panel/moderation.php' => 'Moderation', 'admin-panel/categories.php' => 'Categories',
        'admin-panel/escrow.php' => 'Payments and escrow',
    ] : ($role === 'organiser' ? [
        'business-dashboard.php' => 'Overview', 'create-event.php' => 'Create event', 'manage-events.php' => 'Manage events',
        'manage-bookings.php' => 'Manage bookings', 'create-post.php' => 'Posts', 'manage-reviews.php' => 'Private reviews',
        'organiser-account.php' => 'Organiser account', 'organiser-verification.php' => 'Verification',
    ] : ['user-dashboard.php' => 'Overview', 'my-bookings.php' => 'My bookings', 'profile.php' => 'Profile', 'settings.php' => 'Settings']);
    $links['logout.php'] = 'Log out';
    echo '<aside class="dashboard-sidebar" aria-label="Account navigation"><strong class="sidebar-role">' . escape_html(ucfirst($role)) . '</strong>';
    foreach ($links as $path => $label) {
        $active = basename($_SERVER['SCRIPT_NAME'] ?? '') === basename($path);
        echo '<a class="sidebar-link' . ($active ? ' active' : '') . '"' . ($active ? ' aria-current="page"' : '') . ' href="' . escape_html(eventnest_url($path)) . '">' . escape_html($label) . '</a>';
    }
    echo '</aside>';
}

function eventnest_password_valid(string $password): bool
{
    return strlen($password) >= 8 && strlen($password) <= 72;
}

function eventnest_organiser_display_name(array $organiser): string
{
    // Public pages show the organisation name while the personal name remains the representative.
    $organisation_name = trim((string) ($organiser['OrganisationName'] ?? ''));
    if ($organisation_name !== '') {
        return $organisation_name;
    }

    $representative_name = trim(
        (string) ($organiser['FirstName'] ?? '') . ' ' . (string) ($organiser['LastName'] ?? '')
    );

    return $representative_name !== '' ? $representative_name : 'EventNest organiser';
}

function eventnest_organisation_initials(string $name): string
{
    $words = preg_split('/\s+/u', trim($name)) ?: [];
    $initials = '';

    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }

        $initials .= function_exists('mb_substr')
            ? mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8')
            : strtoupper(substr($word, 0, 1));

        if (mb_strlen($initials, 'UTF-8') >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'EN';
}

function eventnest_provinces(): array
{
    return ['Eastern Cape', 'Free State', 'Gauteng', 'KwaZulu-Natal', 'Limpopo',
        'Mpumalanga', 'Northern Cape', 'North West', 'Western Cape'];
}

// Public venues may include any town or suburb; never disclose legacy joining URLs.
function eventnest_event_location(array $event): string
{
    $venue = trim((string) ($event['Venue'] ?? ''));
    $province = trim((string) ($event['Province'] ?? ''));
    if (filter_var($venue, FILTER_VALIDATE_URL)) { $venue = ''; }
    if ($venue === '') {
        $venue = ($event['Format'] ?? '') === 'online' ? 'Online event' : '';
    }
    if ($province !== '' && stripos($venue, $province) === false) {
        $venue = $venue === '' ? $province : $venue . ', ' . $province;
    }
    return $venue !== '' ? $venue : 'Location to be confirmed';
}

function event_booking_eligibility(PDO $pdo, array $event, array $user): array
{
    $stmt = $pdo->prepare('SELECT 1 FROM BLOCKS WHERE OrganiserID = ? AND LOWER(BlockedEmail) = LOWER(?)');
    $stmt->execute([$event['OrganiserID'], $user['Email']]);
    if ($stmt->fetchColumn()) {
        return [false, 'Organiser block'];
    }

    if (($event['CareerGoalType'] ?? '') === 'field') {
        $stmt = $pdo->prepare('SELECT CategoryName FROM CATEGORIES WHERE CategoryID = ?');
        $stmt->execute([$event['AllowedCareerField']]);
        $allowed = trim((string) $stmt->fetchColumn());

        if ($allowed === '' || strcasecmp($allowed, trim((string) ($user['CareerField'] ?? ''))) !== 0) {
            return [false, 'Career field restriction'];
        }
    }

    if (($event['CareerGoalType'] ?? '') === 'email_domain') {
        $domain = strtolower(substr(strrchr((string) $user['Email'], '@') ?: '', 1));
        $allowed = strtolower(ltrim((string) ($event['AllowedEmailDomain'] ?? ''), '@'));

        if (empty($user['Is_email_verified']) || $allowed === '' || !($domain === $allowed || str_ends_with($domain, '.' . $allowed))) {
            return [false, 'Email restriction'];
        }
    }

    return [true, ''];
}

// Only a configured support address can receive contact and report messages.
function eventnest_send_mail(string $to, string $subject, string $body, ?string $reply_to = null): bool
{
    $autoload = eventnest_project_root() . '/vendor/autoload.php';
    if (!is_file($autoload) || !filter_var($to, FILTER_VALIDATE_EMAIL)) { return false; }
    require_once $autoload;
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer') || !getenv('EVENTNEST_SMTP_HOST')) { return false; }
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP(); $mail->Host = (string) getenv('EVENTNEST_SMTP_HOST');
        $mail->Port = (int) (getenv('EVENTNEST_SMTP_PORT') ?: 587);
        $mail->SMTPAuth = true; $mail->Username = (string) getenv('EVENTNEST_SMTP_USER');
        $mail->Password = (string) getenv('EVENTNEST_SMTP_PASS');
        $mail->SMTPSecure = $mail->Port === 465 ? 'ssl' : 'tls';
        $mail->Timeout = 8; $mail->CharSet = 'UTF-8';
        $mail->setFrom((string) getenv('EVENTNEST_FROM_EMAIL'), (string) (getenv('EVENTNEST_FROM_NAME') ?: 'EventNest'));
        $mail->addAddress($to);
        if ($reply_to && filter_var($reply_to, FILTER_VALIDATE_EMAIL)) { $mail->addReplyTo($reply_to); }
        $mail->Subject = $subject; $mail->Body = $body;
        return $mail->send();
    } catch (Throwable $exception) {
        error_log('EventNest mail delivery failed: ' . get_class($exception)); return false;
    }
}

// Booking locks are shared with callbacks. Only the first pending transition restores stock.
function eventnest_expire_pending(PDO $pdo, ?int $user_id = null): int
{
    $sql = "SELECT BookingID FROM BOOKINGS WHERE Status = 'pending' AND BookingDate <= DATE_SUB(NOW(), INTERVAL 4 MINUTE)";
    $params = [];
    if ($user_id !== null) { $sql .= ' AND UserID = ?'; $params[] = $user_id; }
    $stmt = $pdo->prepare($sql . ' ORDER BY BookingID LIMIT 200'); $stmt->execute($params);
    $expired = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT BookingID, TicketID, Quantity, Status FROM BOOKINGS WHERE BookingID = ? FOR UPDATE');
            $lock->execute([$id]); $booking = $lock->fetch();
            if ($booking && $booking['Status'] === 'pending') {
                $paid = $pdo->prepare("SELECT PaymentID FROM PAYMENTS WHERE BookingID = ? AND PaymentStatus IN ('success','refunded') FOR UPDATE");
                $paid->execute([$id]);
                if (!$paid->fetchColumn()) {
                    $change = $pdo->prepare("UPDATE BOOKINGS SET Status = 'canceled' WHERE BookingID = ? AND Status = 'pending' AND BookingDate <= DATE_SUB(NOW(), INTERVAL 4 MINUTE)");
                    $change->execute([$id]);
                    if ($change->rowCount() === 1) {
                        $restore = $pdo->prepare('UPDATE TICKETS SET QuantityAvailable = LEAST(Capacity, QuantityAvailable + ?) WHERE TicketID = ?');
                        $restore->execute([$booking['Quantity'], $booking['TicketID']]); $expired++;
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }
    return $expired;
}

if (!function_exists('eventnest_db')) {
    function eventnest_db(): PDO
    {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $GLOBALS['pdo'];
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            return $GLOBALS['conn'];
        }

        throw new RuntimeException('EventNest database connection is unavailable.');
    }
}

if (!function_exists('trim_input')) {
    function trim_input(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }
}

if (!function_exists('escape_html')) {
    function escape_html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/*
Legacy-compatible display helper. Prepared statements must still be used
for database values; this helper is not SQL sanitisation.
*/
if (!function_exists('sanitizeInput')) {
    function sanitizeInput(mixed $value): string
    {
        return escape_html(trim_input($value));
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? '' : date('d M Y', $timestamp);
    }
}

if (!function_exists('formatDate')) {
    function formatDate(mixed $value): string
    {
        return format_date($value === null ? null : (string) $value);
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? '' : date('d M Y, H:i', $timestamp);
    }
}

if (!function_exists('format_money')) {
    function format_money(mixed $amount): string
    {
        if (!is_numeric($amount)) {
            return 'R0.00';
        }

        return 'R' . number_format((float) $amount, 2, '.', ',');
    }
}

if (!function_exists('eventnest_project_root')) {
    function eventnest_project_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('eventnest_base_url')) {
    function eventnest_base_url(): string
    {
        $project_root = realpath(eventnest_project_root());
        $document_root = isset($_SERVER['DOCUMENT_ROOT'])
            ? realpath((string) $_SERVER['DOCUMENT_ROOT'])
            : false;

        if ($project_root !== false && $document_root !== false) {
            $project_normalised = str_replace('\\', '/', $project_root);
            $document_normalised = rtrim(str_replace('\\', '/', $document_root), '/');
            $document_prefix = $document_normalised . '/';

            if (
                strcasecmp($project_normalised, $document_normalised) === 0 ||
                stripos($project_normalised . '/', $document_prefix) === 0
            ) {
                $relative = substr($project_normalised, strlen($document_normalised));
                $relative = '/' . trim((string) $relative, '/');

                return $relative === '/' ? '' : $relative;
            }
        }

        $script_name = isset($_SERVER['SCRIPT_NAME'])
            ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME'])
            : '';

        $project_name = basename(eventnest_project_root());
        $needle = '/' . $project_name . '/';
        $position = strpos($script_name, $needle);

        if ($position !== false) {
            return substr($script_name, 0, $position + strlen($needle) - 1);
        }

        $directory = str_replace('\\', '/', dirname($script_name));

        return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
    }
}

if (!function_exists('eventnest_url')) {
    function eventnest_url(string $path = ''): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if (
            strpos($path, "\0") !== false ||
            preg_match('#(^|/)\.\.(/|$)#', $path) === 1 ||
            preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1 ||
            strpos($path, '//') === 0
        ) {
            $base = eventnest_base_url();
            return $base === '' ? '/' : $base . '/';
        }

        $base = eventnest_base_url();
        $path = ltrim($path, '/');

        if ($path === '') {
            return $base === '' ? '/' : $base . '/';
        }

        return $base . '/' . $path;
    }
}

if (!function_exists('eventnest_ensure_session')) {
    function eventnest_ensure_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || parse_url((string)getenv('EVENTNEST_BASE_URL'), PHP_URL_SCHEME) === 'https';

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_start();
    }
}

if (!function_exists('eventnest_csrf_token')) {
    function eventnest_csrf_token(): string
    {
        eventnest_ensure_session();

        if (
            !isset($_SESSION['csrf_token']) ||
            !is_string($_SESSION['csrf_token']) ||
            strlen($_SESSION['csrf_token']) < 64
        ) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('eventnest_verify_csrf_token')) {
    function eventnest_verify_csrf_token(mixed $token): bool
    {
        eventnest_ensure_session();

        return is_string($token) && $token !== ''
            && isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('generate_booking_reference')) {
    function generate_booking_reference(PDO $pdo): string
    {
        $statement = $pdo->prepare(
            'SELECT 1 FROM BOOKINGS WHERE BookingReference = :reference LIMIT 1'
        );

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $reference = 'EN-' . strtoupper(bin2hex(random_bytes(8)));

            $statement->execute([
                ':reference' => $reference,
            ]);

            if ($statement->fetchColumn() === false) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique booking reference.');
    }
}

if (!function_exists('generateBookingReference')) {
    function generateBookingReference(PDO $pdo): string
    {
        return generate_booking_reference($pdo);
    }
}

if (!function_exists('eventnest_normalise_hashtag')) {
    function eventnest_normalise_hashtag(string $tag): ?string
    {
        $tag = trim($tag);

        if (strpos($tag, '#') === 0) {
            $tag = substr($tag, 1);
        }

        $tag = trim($tag);

        if ($tag === '' || strlen($tag) > 50) {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/D', $tag)) {
            return null;
        }

        return $tag;
    }
}

if (!function_exists('eventnest_normalise_hashtags')) {
    function eventnest_normalise_hashtags(?string $hashtags): ?string
    {
        if ($hashtags === null || trim($hashtags) === '') {
            return null;
        }

        $parts = preg_split('/[\s,]+/', trim($hashtags));

        if ($parts === false) {
            return null;
        }

        $normalised = [];
        $seen = [];

        foreach ($parts as $part) {
            $tag = eventnest_normalise_hashtag((string) $part);

            if ($tag === null) {
                continue;
            }

            $key = strtolower($tag);

            if (isset($seen[$key])) {
                continue;
            }

            $candidate = '#' . $tag;
            $candidate_result = empty($normalised)
                ? $candidate
                : implode(' ', $normalised) . ' ' . $candidate;

            if (strlen($candidate_result) > 255) {
                break;
            }

            $seen[$key] = true;
            $normalised[] = $candidate;
        }

        return empty($normalised) ? null : implode(' ', $normalised);
    }
}

if (!function_exists('eventnest_extract_hashtags')) {
    function eventnest_extract_hashtags(?string $hashtags): array
    {
        if ($hashtags === null || trim($hashtags) === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', trim($hashtags));

        if ($parts === false) {
            return [];
        }

        $result = [];
        $seen = [];

        foreach ($parts as $part) {
            $tag = eventnest_normalise_hashtag((string) $part);

            if ($tag === null) {
                continue;
            }

            $key = strtolower($tag);

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $tag;
            }
        }

        return $result;
    }
}

if (!function_exists('eventnest_hashtag_regex')) {
    function eventnest_hashtag_regex(string $tag): string
    {
        $normalised = eventnest_normalise_hashtag($tag);

        if ($normalised === null) {
            return 'a^';
        }

        return '(^|[[:space:],])#?' . $normalised . '([[:space:],]|$)';
    }
}

if (!function_exists('eventnest_generate_upload_filename')) {
    function eventnest_generate_upload_filename(string $prefix, string $mime_type): string
    {
        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
        ];

        if (!isset($extensions[$mime_type])) {
            throw new InvalidArgumentException('Unsupported upload MIME type.');
        }

        $safe_prefix = preg_replace('/[^A-Za-z0-9_-]/', '-', trim($prefix));
        $safe_prefix = trim((string) $safe_prefix, '-_');

        if ($safe_prefix === '') {
            $safe_prefix = 'upload';
        }

        return strtolower($safe_prefix)
            . '_'
            . bin2hex(random_bytes(16))
            . '.'
            . $extensions[$mime_type];
    }
}

if (!function_exists('eventnest_build_upload_path')) {
    function eventnest_build_upload_path(string $subdirectory, string $filename): array
    {
        $subdirectory = trim(str_replace('\\', '/', $subdirectory), '/');

        if (
            $subdirectory !== '' &&
            !preg_match('/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*$/D', $subdirectory)
        ) {
            throw new InvalidArgumentException('Invalid upload subdirectory.');
        }

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $filename)) {
            throw new InvalidArgumentException('Invalid upload filename.');
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm'];

        if (!in_array($extension, $allowed_extensions, true)) {
            throw new InvalidArgumentException('Unsupported upload filename extension.');
        }

        $relative = 'uploaded-content/'
            . ($subdirectory === '' ? '' : $subdirectory . '/')
            . $filename;

        return [
            'relative' => $relative,
            'absolute' => eventnest_project_root()
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relative),
        ];
    }
}

if (!function_exists('eventnest_safe_media_path')) {
    function eventnest_safe_media_path(?string $database_path, ?string $expected_type = null): ?string
    {
        if ($database_path === null) {
            return null;
        }

        if ($expected_type !== null && !in_array($expected_type, ['image', 'video'], true)) {
            return null;
        }

        $path = ltrim(trim(str_replace('\\', '/', $database_path)), '/');

        if (
            $path === '' ||
            strpos($path, "\0") !== false ||
            strpos($path, 'uploaded-content/') !== 0 ||
            preg_match('#(^|/)\.\.(/|$)#', $path) === 1 ||
            preg_match('#^[A-Za-z0-9_./-]+$#D', $path) !== 1
        ) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $image_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        $video_extensions = ['mp4', 'webm'];

        if ($expected_type === 'image' && !in_array($extension, $image_extensions, true)) {
            return null;
        }

        if ($expected_type === 'video' && !in_array($extension, $video_extensions, true)) {
            return null;
        }

        if ($expected_type === null && !in_array($extension, array_merge($image_extensions, $video_extensions), true)) {
            return null;
        }

        /*
        The database stores a web-relative media path.
        Render and local XAMPP can resolve the physical file differently, so the safe path is still returned when the path format is valid.

        */
        $full_path = eventnest_project_root() . '/' . $path;
        if (is_file($full_path) && class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($full_path);
            $image_types = ['image/jpeg', 'image/png', 'image/webp'];
            $video_types = ['video/mp4', 'video/webm'];

            if ($expected_type === 'image' && !in_array($mime_type, $image_types, true)) {
                return null;
            }

            if ($expected_type === 'video' && !in_array($mime_type, $video_types, true)) {
                return null;
            }

            if ($expected_type === null && !in_array($mime_type, array_merge($image_types, $video_types), true)) {
                return null;
            }
        }

        return $path;
    }
}

if (!function_exists('resolve_event_image')) {
    function resolve_event_image(PDO $pdo, int $event_id): string
    {
        $fallback = 'images/eventnest-logo.png';

        if ($event_id <= 0) {
            return $fallback;
        }

        try {
            $statement = $pdo->prepare(
                "SELECT p.Media
                 FROM POSTS p
                 INNER JOIN EVENTS e
                    ON e.EventID = p.EventID
                   AND e.OrganiserID = p.OrganiserID
                 WHERE p.EventID = :event_id
                   AND p.Media_type = 'image'
                   AND p.Media IS NOT NULL
                   AND TRIM(p.Media) <> ''
                 ORDER BY p.PostDate DESC, p.PostID DESC"
            );

            $statement->execute([
                ':event_id' => $event_id,
            ]);

            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $safe_path = eventnest_safe_media_path((string) $row['Media'], 'image');

                if ($safe_path !== null) {
                    return $safe_path;
                }
            }
        } catch (PDOException $exception) {
            error_log('EventNest event image resolver failed: ' . $exception->getMessage());
        }

        return $fallback;
    }
}

if (!function_exists('eventnest_status_label')) {
    function eventnest_status_label(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        $labels = [
            'active' => 'Active',
            'suspended' => 'Suspended',
            'deleted' => 'Deleted',
            'unverified' => 'Unverified',
            'pending' => 'Pending',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'draft' => 'Draft',
            'published' => 'Published',
            'completed' => 'Completed',
            'canceled' => 'Canceled',
            'paid' => 'Paid',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            'success' => 'Success',
            'held' => 'Held',
            'released' => 'Released',
            'unpaid' => 'Unpaid',
        ];

        return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('eventnest_status_class')) {
    function eventnest_status_class(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        if (in_array($status, ['active', 'verified', 'published', 'paid', 'success', 'released', 'completed'], true)) {
            return 'status status--success';
        }

        if (in_array($status, ['pending', 'held', 'unverified', 'unpaid', 'draft'], true)) {
            return 'status status--warning';
        }

        if (in_array($status, ['failed', 'rejected', 'canceled', 'deleted', 'suspended'], true)) {
            return 'status status--danger';
        }

        if ($status === 'refunded') {
            return 'status status--info';
        }

        return 'status';
    }
}

if (!function_exists('eventnest_post_type_label')) {
    function eventnest_post_type_label(?string $media_type): string
    {
        if ($media_type === 'image') {
            return 'Image';
        }

        if ($media_type === 'video') {
            return 'Video';
        }

        return 'Event update';
    }
}

if (!function_exists('eventnest_excerpt')) {
    function eventnest_excerpt(?string $text, int $length = 180): string
    {
        $text = trim((string) $text);

        if ($text === '' || $length < 10) {
            return $text;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $length) {
                return $text;
            }

            return rtrim(mb_substr($text, 0, $length - 1, 'UTF-8')) . '…';
        }

        if (strlen($text) <= $length) {
            return $text;
        }

        return rtrim(substr($text, 0, $length - 1)) . '…';
    }
}


/*
Private verification files live outside the web document root.
*/
function eventnest_verification_directory(): string
{
    $configured_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $document_root = $configured_root !== '' ? realpath($configured_root) : false;
    $document_root = $document_root ?: dirname(eventnest_project_root());
    return dirname($document_root) . '/eventnest-private/organiser-documents';
}

// Bookings and organiser fees use the same server-side PayFast verification.
function payfast_signature(array $data, string $passphrase = ''): string
{
    $parts = [];
    foreach ($data as $key => $value) {
        if ($key === 'signature' || $value === '' || $value === null || is_array($value)) {
            continue;
        }
        $parts[] = $key . '=' . urlencode(trim((string) $value));
    }
    $query = implode('&', $parts);
    if ($passphrase !== '') {
        $query .= '&passphrase=' . urlencode(trim($passphrase));
    }
    return md5($query);
}

function payfast_validate_with_provider(array $data): bool
{
    if (!function_exists('curl_init')) {
        error_log('EventNest PayFast validation unavailable: cURL extension missing.');
        return false;
    }

    $validation_data = $data;
    unset($validation_data['signature']);
    $body = http_build_query($validation_data, '', '&', PHP_QUERY_RFC3986);

    $curl = curl_init('https://sandbox.payfast.co.za/eng/query/validate');
    if ($curl === false) {
        return false;
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'EventNest-PayFast-Sandbox/1.0',
    ]);

    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return $status === 200 && is_string($response) && trim($response) === 'VALID';
}


function eventnest_absolute_url(string $path): string
{
    $base = rtrim((string) getenv('EVENTNEST_BASE_URL'), '/');
    if (!filter_var($base, FILTER_VALIDATE_URL) || parse_url($base, PHP_URL_SCHEME) !== 'https'
        || parse_url($base, PHP_URL_USER) !== null || parse_url($base, PHP_URL_QUERY) !== null) {
        throw new DomainException('Configure EVENTNEST_BASE_URL with the trusted HTTPS site URL.');
    }
    return $base . '/' . ltrim($path, '/');
}

function eventnest_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function eventnest_base64url_decode(string $value): ?string
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
        return null;
    }

    $padding = (4 - strlen($value) % 4) % 4;
    $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);

    return is_string($decoded) ? $decoded : null;
}

function eventnest_password_reset_secret(): string
{
    $configured = trim((string) getenv('EVENTNEST_PASSWORD_RESET_SECRET'));
    if ($configured !== '') {
        return $configured;
    }

    $app_key = trim((string) getenv('APP_KEY'));
    if ($app_key !== '') {
        return $app_key;
    }

    /*
    SIT already needs SMTP to send the reset email.
    If a separate reset secret was not added yet, derive a server-only signing key from existing private environment values.

    */
    $smtp_password = trim((string) getenv('EVENTNEST_SMTP_PASS'));
    $site_url = trim((string) getenv('EVENTNEST_BASE_URL'));

    return $smtp_password !== '' && $site_url !== ''
        ? hash('sha256', 'EventNest password reset|' . $smtp_password . '|' . $site_url)
        : '';
}

function eventnest_create_password_reset_token(array $user, int $minutes = 30): ?string
{
    $secret = eventnest_password_reset_secret();
    $user_id = (int) ($user['UserID'] ?? 0);
    $email = strtolower(trim((string) ($user['Email'] ?? '')));
    $password_hash = (string) ($user['PasswordHash'] ?? '');

    if ($secret === '' || $user_id <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password_hash === '') {
        return null;
    }

    $payload = [
        'v' => 1,
        'uid' => $user_id,
        'email' => $email,
        'exp' => time() + max(5, min(60, $minutes)) * 60,
        'pwd' => hash('sha256', $password_hash),
    ];

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return null;
    }

    $encoded = eventnest_base64url_encode($json);
    $signature = hash_hmac('sha256', $encoded, $secret, true);

    return $encoded . '.' . eventnest_base64url_encode($signature);
}

function eventnest_read_password_reset_token(string $token): ?array
{
    $secret = eventnest_password_reset_secret();
    if ($secret === '' || substr_count($token, '.') !== 1) {
        return null;
    }

    [$encoded, $signature_text] = explode('.', $token, 2);
    $signature = eventnest_base64url_decode($signature_text);
    $json = eventnest_base64url_decode($encoded);

    if ($signature === null || $json === null) {
        return null;
    }

    $expected = hash_hmac('sha256', $encoded, $secret, true);
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        return null;
    }

    $user_id = (int) ($payload['uid'] ?? 0);
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $expires = (int) ($payload['exp'] ?? 0);
    $password_marker = (string) ($payload['pwd'] ?? '');

    if ($user_id <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $expires < time() || strlen($password_marker) !== 64) {
        return null;
    }

    return [
        'user_id' => $user_id,
        'email' => $email,
        'expires' => $expires,
        'password_marker' => $password_marker,
    ];
}

function eventnest_verify_payfast_notification(array $post): void
{
    foreach ($post as $value) {
        if (!is_scalar($value)) { throw new RuntimeException('Invalid notification field.'); }
    }
    $merchant = trim((string) getenv('PAYFAST_MERCHANT_ID'));
    if ($merchant === '' || !hash_equals($merchant, trim((string) ($post['merchant_id'] ?? '')))
        || !hash_equals(payfast_signature($post, (string) getenv('PAYFAST_PASSPHRASE')), strtolower(trim((string) ($post['signature'] ?? ''))))
        || !payfast_validate_with_provider($post)) {
        throw new RuntimeException('Payment verification failed.');
    }
}

function eventnest_prepare_verification_fee(PDO $pdo, array $user): array
{
    $merchant = trim((string) getenv('PAYFAST_MERCHANT_ID'));
    $merchant_key = trim((string) getenv('PAYFAST_MERCHANT_KEY'));
    if ($merchant === '' || $merchant_key === '') { throw new DomainException('Sandbox fee payment is not configured on this server.'); }
    $return_url = eventnest_absolute_url('organiser-verification.php');
    $notify_url = eventnest_absolute_url('verification.php');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM USERS WHERE UserID = ? AND Role = 'organiser' AND UserStatus = 'active' FOR UPDATE");
        $stmt->execute([$user['UserID']]); $current = $stmt->fetch();
        if (!$current) { throw new DomainException('Organiser account is unavailable.'); }
        if (auth_has_current_verification_fee($current)) { throw new DomainException('Your annual fee coverage is already active.'); }
        $reference = $current['VerificationFeeGatewayReference'];
        if ($current['VerificationFeePaymentStatus'] !== 'pending' || !$reference || !str_starts_with($reference, 'EN-VERIFY-')) {
            $reference = 'EN-VERIFY-' . strtoupper(bin2hex(random_bytes(16)));
            $stmt = $pdo->prepare("UPDATE USERS SET VerificationFeePaymentStatus = 'pending', VerificationFeeGatewayReference = ? WHERE UserID = ?");
            $stmt->execute([$reference, $current['UserID']]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
    $form = [
        'merchant_id' => $merchant, 'merchant_key' => $merchant_key,
        'return_url' => $return_url, 'cancel_url' => $return_url . '?fee=not-completed', 'notify_url' => $notify_url,
        'name_first' => (string) $current['FirstName'], 'name_last' => (string) $current['LastName'],
        'email_address' => (string) $current['Email'], 'm_payment_id' => $reference,
        'amount' => '99.00', 'item_name' => 'EventNest annual organiser verification',
    ];
    // PayFast requires its checkout merchant fields; the signing passphrase stays on the server.
    $form['signature'] = payfast_signature($form, (string) getenv('PAYFAST_PASSPHRASE'));
    return $form;
}

function eventnest_record_verification_fee(PDO $pdo, array $post): void
{
    eventnest_verify_payfast_notification($post);
    $reference = trim((string) ($post['m_payment_id'] ?? ''));
    $status = strtoupper(trim((string) ($post['payment_status'] ?? '')));
    if (!str_starts_with($reference, 'EN-VERIFY-') || strlen($reference) > 100
        || trim((string) ($post['pf_payment_id'] ?? '')) === ''
        || !is_numeric($post['amount_gross'] ?? null) || abs((float) $post['amount_gross'] - 99.0) > 0.009
        || !in_array($status, ['COMPLETE', 'FAILED'], true)) {
        throw new RuntimeException('Invalid annual fee notification.');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT UserID, VerificationFeePaymentStatus FROM USERS WHERE Role = 'organiser' AND VerificationFeeGatewayReference = ? FOR UPDATE");
        $stmt->execute([$reference]); $user = $stmt->fetch();
        if (!$user) { throw new RuntimeException('Unknown annual fee reference.'); }
        // Replayed success must not extend coverage again, and later failure cannot downgrade it.
        if ($user['VerificationFeePaymentStatus'] !== 'paid') {
            if ($status === 'COMPLETE') {
                $stmt = $pdo->prepare("UPDATE USERS SET VerificationFeePaidAt = NOW(), VerificationFeePaidUntil = DATE_ADD(NOW(), INTERVAL 1 YEAR), VerificationFeePaymentStatus = 'paid' WHERE UserID = ?");
                $stmt->execute([$user['UserID']]);
            } else {
                $stmt = $pdo->prepare("UPDATE USERS SET VerificationFeePaymentStatus = 'failed' WHERE UserID = ? AND VerificationFeePaymentStatus = 'pending'");
                $stmt->execute([$user['UserID']]);
            }
        }
        // This fee belongs to USERS; PAYMENTS remains exclusively linked to ticket bookings.
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}

