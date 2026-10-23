<?php

declare(strict_types=1);

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
    function trim_input($value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }
}

if (!function_exists('escape_html')) {
    function escape_html($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/*
 * Legacy-compatible display helper. Prepared statements must still be used
 * for database values; this helper is not SQL sanitisation.
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($value): string
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
    function formatDate($value): string
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
    function format_money($amount): string
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

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

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

        $path = trim(str_replace('\\', '/', $database_path));
        $path = ltrim($path, '/');

        if (
            $path === '' ||
            strpos($path, "\0") !== false ||
            strpos($path, 'uploaded-content/') !== 0 ||
            preg_match('#(^|/)\.\.(/|$)#', $path) === 1
        ) {
            return null;
        }

        $uploads_root = realpath(eventnest_project_root() . '/uploaded-content');
        $full_path = realpath(eventnest_project_root() . '/' . $path);

        if ($uploads_root === false || $full_path === false || !is_file($full_path)) {
            return null;
        }

        $uploads_normalised = rtrim(str_replace('\\', '/', $uploads_root), '/') . '/';
        $full_normalised = str_replace('\\', '/', $full_path);

        if (strpos($full_normalised, $uploads_normalised) !== 0) {
            return null;
        }

        if (!class_exists('finfo')) {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($full_path);

        if (!is_string($mime_type)) {
            return null;
        }

        $image_types = ['image/jpeg', 'image/png', 'image/webp'];
        $video_types = ['video/mp4', 'video/webm'];

        if ($expected_type === 'image' && !in_array($mime_type, $image_types, true)) {
            return null;
        }

        if ($expected_type === 'video' && !in_array($mime_type, $video_types, true)) {
            return null;
        }

        if (
            $expected_type === null &&
            !in_array($mime_type, array_merge($image_types, $video_types), true)
        ) {
            return null;
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


/** Private verification files live outside the web document root. */
function eventnest_verification_directory(): string
{
    $configured_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $document_root = $configured_root !== '' ? realpath($configured_root) : false;
    $document_root = $document_root ?: dirname(eventnest_project_root());
    return dirname($document_root) . '/eventnest-private/organiser-documents';
}
