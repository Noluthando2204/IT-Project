<?php

/*
|--------------------------------------------------------------------------
| EventNest - shared-components/functions.php
|--------------------------------------------------------------------------
| Shared reusable helper functions for EventNest.
|
| Responsibilities:
| - Input trimming and HTML escaping.
| - CSRF protection.
| - Date/time and money formatting.
| - Unique EN- booking references.
| - Safe upload filenames and filesystem paths.
| - Hashtag normalisation.
| - Event-image resolution.
| - Consistent status labels.
|
| IMPORTANT:
| This file does not create another database connection.
| Functions requiring database access receive the existing PDO connection.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| CLEAN INPUT
|--------------------------------------------------------------------------
| Trims scalar input before validation/processing.
|
| This does not replace validation or prepared statements.
|--------------------------------------------------------------------------
*/

function clean_input($value): string
{
    if ($value === null) {
        return '';
    }

    if (!is_scalar($value)) {
        return '';
    }

    return trim((string) $value);
}


/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
| Use immediately before displaying untrusted or database-provided text in
| HTML.
|--------------------------------------------------------------------------
*/

function escape_html($value): string
{
    if ($value === null) {
        return '';
    }

    if (!is_scalar($value)) {
        return '';
    }

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| ENSURE SESSION FOR CSRF
|--------------------------------------------------------------------------
*/

function ensure_csrf_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}


/*
|--------------------------------------------------------------------------
| GET CSRF TOKEN
|--------------------------------------------------------------------------
*/

function get_csrf_token(): string
{
    ensure_csrf_session();

    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


/*
|--------------------------------------------------------------------------
| VERIFY CSRF TOKEN
|--------------------------------------------------------------------------
*/

function verify_csrf_token($submitted_token): bool
{
    ensure_csrf_session();

    if (
        !is_string($submitted_token) ||
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $submitted_token
    );
}


/*
|--------------------------------------------------------------------------
| FORMAT DATE
|--------------------------------------------------------------------------
*/

function format_date($date): string
{
    if (!is_string($date) || trim($date) === '') {
        return '';
    }

    try {
        $date_object = new DateTimeImmutable($date);

        return $date_object->format('d M Y');

    } catch (Exception $exception) {
        return '';
    }
}


/*
|--------------------------------------------------------------------------
| FORMAT DATE AND TIME
|--------------------------------------------------------------------------
*/

function format_datetime($date): string
{
    if (!is_string($date) || trim($date) === '') {
        return '';
    }

    try {
        $date_object = new DateTimeImmutable($date);

        return $date_object->format(
            'd M Y, H:i'
        );

    } catch (Exception $exception) {
        return '';
    }
}


/*
|--------------------------------------------------------------------------
| FORMAT MONEY
|--------------------------------------------------------------------------
*/

function format_money($amount): string
{
    if (!is_numeric($amount)) {
        return '';
    }

    $amount = (float) $amount;

    if ($amount < 0) {
        return '';
    }

    return 'R' . number_format(
        $amount,
        2,
        '.',
        ','
    );
}


/*
|--------------------------------------------------------------------------
| GENERATE UNIQUE BOOKING REFERENCE
|--------------------------------------------------------------------------
| Generates an unpredictable EventNest reference using the required EN-
| prefix and confirms that it does not already exist in BOOKINGS.
|--------------------------------------------------------------------------
*/

function generate_booking_reference(
    PDO $pdo
): string {
    $maximum_attempts = 10;

    for (
        $attempt = 1;
        $attempt <= $maximum_attempts;
        $attempt++
    ) {
        $reference =
            'EN-' .
            strtoupper(
                bin2hex(random_bytes(6))
            );

        $statement = $pdo->prepare(
            'SELECT BookingID
             FROM BOOKINGS
             WHERE BookingReference = :booking_reference
             LIMIT 1'
        );

        $statement->execute([
            ':booking_reference' => $reference
        ]);

        if ($statement->fetch() === false) {
            return $reference;
        }
    }

    throw new RuntimeException(
        'Unable to generate a unique booking reference.'
    );
}


/*
|--------------------------------------------------------------------------
| SAFE FILE EXTENSION
|--------------------------------------------------------------------------
| Validates the extension against an allow-list supplied by the calling
| upload handler.
|--------------------------------------------------------------------------
*/

function get_safe_file_extension(
    string $original_name,
    array $allowed_extensions
): ?string {
    $extension = strtolower(
        pathinfo(
            $original_name,
            PATHINFO_EXTENSION
        )
    );

    if ($extension === '') {
        return null;
    }

    $normalised_extensions = [];

    foreach ($allowed_extensions as $allowed_extension) {
        if (!is_string($allowed_extension)) {
            continue;
        }

        $allowed_extension = strtolower(
            ltrim(
                trim($allowed_extension),
                '.'
            )
        );

        if ($allowed_extension !== '') {
            $normalised_extensions[] =
                $allowed_extension;
        }
    }

    if (
        !in_array(
            $extension,
            $normalised_extensions,
            true
        )
    ) {
        return null;
    }

    return $extension;
}


/*
|--------------------------------------------------------------------------
| GENERATE SAFE UPLOAD FILENAME
|--------------------------------------------------------------------------
| User-supplied original filenames are not used as stored filenames.
|--------------------------------------------------------------------------
*/

function generate_safe_upload_filename(
    string $original_name,
    array $allowed_extensions
): ?string {
    $extension = get_safe_file_extension(
        $original_name,
        $allowed_extensions
    );

    if ($extension === null) {
        return null;
    }

    return bin2hex(random_bytes(16))
        . '.'
        . $extension;
}


/*
|--------------------------------------------------------------------------
| BUILD SAFE UPLOAD PATH
|--------------------------------------------------------------------------
| Prevents directory traversal and accepts only plain generated filenames.
|--------------------------------------------------------------------------
*/

function build_safe_upload_path(
    string $upload_directory,
    string $filename
): ?string {
    if (
        $upload_directory === '' ||
        $filename === ''
    ) {
        return null;
    }

    if (basename($filename) !== $filename) {
        return null;
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9._-]+$/',
            $filename
        )
    ) {
        return null;
    }

    $real_directory =
        realpath($upload_directory);

    if (
        $real_directory === false ||
        !is_dir($real_directory)
    ) {
        return null;
    }

    return rtrim(
        $real_directory,
        DIRECTORY_SEPARATOR
    )
        . DIRECTORY_SEPARATOR
        . $filename;
}


/*
|--------------------------------------------------------------------------
| NORMALISE ONE HASHTAG
|--------------------------------------------------------------------------
*/

function normalise_hashtag(
    $hashtag
): ?string {
    if (!is_string($hashtag)) {
        return null;
    }

    $hashtag = trim($hashtag);

    if ($hashtag === '') {
        return null;
    }

    if (substr($hashtag, 0, 1) === '#') {
        $hashtag = substr($hashtag, 1);
    }

    $hashtag = trim($hashtag);

    if ($hashtag === '') {
        return null;
    }

    if (
        !preg_match(
            '/^[\p{L}\p{N}_]+$/u',
            $hashtag
        )
    ) {
        return null;
    }

    if (strlen($hashtag) > 255) {
        return null;
    }

    return '#' . $hashtag;
}


/*
|--------------------------------------------------------------------------
| NORMALISE MULTIPLE HASHTAGS
|--------------------------------------------------------------------------
| Accepts whitespace/comma-separated hashtags, removes invalid duplicates
| and ensures the final stored string fits the VARCHAR(255) field.
|--------------------------------------------------------------------------
*/

function normalise_hashtags(
    $hashtags
): string {
    if (!is_string($hashtags)) {
        return '';
    }

    $hashtags = trim($hashtags);

    if ($hashtags === '') {
        return '';
    }

    $parts = preg_split(
        '/[\s,]+/u',
        $hashtags,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    if ($parts === false) {
        return '';
    }

    $normalised = [];
    $seen = [];

    foreach ($parts as $part) {
        $hashtag =
            normalise_hashtag($part);

        if ($hashtag === null) {
            continue;
        }

        $comparison_key =
            strtolower($hashtag);

        if (isset($seen[$comparison_key])) {
            continue;
        }

        $candidate = implode(
            ' ',
            array_merge(
                $normalised,
                [$hashtag]
            )
        );

        if (strlen($candidate) > 255) {
            break;
        }

        $seen[$comparison_key] = true;
        $normalised[] = $hashtag;
    }

    return implode(
        ' ',
        $normalised
    );
}


/*
|--------------------------------------------------------------------------
| SAFE PUBLIC MEDIA PATH
|--------------------------------------------------------------------------
| Public organiser media must remain under uploaded-content/.
|--------------------------------------------------------------------------
*/

function get_safe_public_media_path(
    $media_path
): ?string {
    if (!is_string($media_path)) {
        return null;
    }

    $media_path = trim(
        str_replace(
            '\\',
            '/',
            $media_path
        )
    );

    if ($media_path === '') {
        return null;
    }

    if (
        filter_var(
            $media_path,
            FILTER_VALIDATE_URL
        ) !== false
    ) {
        return null;
    }

    if (
        strpos($media_path, '../') !== false ||
        strpos($media_path, './') === 0 ||
        strpos($media_path, "\0") !== false
    ) {
        return null;
    }

    if (
        strpos(
            $media_path,
            'uploaded-content/'
        ) !== 0
    ) {
        return null;
    }

    return $media_path;
}


/*
|--------------------------------------------------------------------------
| RESOLVE EVENT IMAGE
|--------------------------------------------------------------------------
| EVENTS does not contain an EventImage field.
|
| Event image rule:
| 1. newest linked image POST
| 2. otherwise EventNest fallback logo
|--------------------------------------------------------------------------
*/

function resolve_event_image(
    PDO $pdo,
    $event_id
): string {
    $fallback_image =
        'images/eventnest-logo.png';

    if (
        !is_numeric($event_id) ||
        (int) $event_id <= 0
    ) {
        return $fallback_image;
    }

    try {
        $statement = $pdo->prepare(
            'SELECT Media
             FROM POSTS
             WHERE EventID = :event_id
               AND Media_type = :media_type
               AND Media IS NOT NULL
               AND Media <> \'\'
             ORDER BY PostDate DESC, PostID DESC
             LIMIT 1'
        );

        $statement->execute([
            ':event_id' => (int) $event_id,
            ':media_type' => 'image'
        ]);

        $media = $statement->fetchColumn();

        if ($media === false) {
            return $fallback_image;
        }

        $safe_media_path =
            get_safe_public_media_path($media);

        if ($safe_media_path === null) {
            return $fallback_image;
        }

        return $safe_media_path;

    } catch (PDOException $exception) {
        error_log(
            'EventNest event-image resolver error: ' .
            $exception->getMessage()
        );

        return $fallback_image;
    }
}


/*
|--------------------------------------------------------------------------
| EVENT STATUS DISPLAY
|--------------------------------------------------------------------------
*/

function get_event_status_display(
    string $status
): ?array {
    $statuses = [
        'draft' => [
            'label' => 'Draft',
            'tone' => 'neutral'
        ],
        'published' => [
            'label' => 'Published',
            'tone' => 'success'
        ],
        'completed' => [
            'label' => 'Completed',
            'tone' => 'info'
        ],
        'canceled' => [
            'label' => 'Canceled',
            'tone' => 'danger'
        ]
    ];

    return $statuses[$status] ?? null;
}


/*
|--------------------------------------------------------------------------
| BOOKING STATUS DISPLAY
|--------------------------------------------------------------------------
*/

function get_booking_status_display(
    string $status
): ?array {
    $statuses = [
        'pending' => [
            'label' => 'Payment pending',
            'tone' => 'warning'
        ],
        'paid' => [
            'label' => 'Paid',
            'tone' => 'success'
        ],
        'failed' => [
            'label' => 'Failed',
            'tone' => 'danger'
        ],
        'refunded' => [
            'label' => 'Refunded',
            'tone' => 'info'
        ],
        'canceled' => [
            'label' => 'Canceled',
            'tone' => 'danger'
        ]
    ];

    return $statuses[$status] ?? null;
}


/*
|--------------------------------------------------------------------------
| PAYMENT STATUS DISPLAY
|--------------------------------------------------------------------------
*/

function get_payment_status_display(
    string $status
): ?array {
    $statuses = [
        'success' => [
            'label' => 'Successful',
            'tone' => 'success'
        ],
        'failed' => [
            'label' => 'Failed',
            'tone' => 'danger'
        ],
        'refunded' => [
            'label' => 'Refunded',
            'tone' => 'info'
        ]
    ];

    return $statuses[$status] ?? null;
}


/*
|--------------------------------------------------------------------------
| USER STATUS DISPLAY
|--------------------------------------------------------------------------
*/

function get_user_status_display(
    string $status
): ?array {
    $statuses = [
        'active' => [
            'label' => 'Active',
            'tone' => 'success'
        ],
        'suspended' => [
            'label' => 'Suspended',
            'tone' => 'warning'
        ],
        'deleted' => [
            'label' => 'Deleted',
            'tone' => 'danger'
        ]
    ];

    return $statuses[$status] ?? null;
}


/*
|--------------------------------------------------------------------------
| VERIFICATION STATUS DISPLAY
|--------------------------------------------------------------------------
*/

function get_verification_status_display(
    string $status
): ?array {
    $statuses = [
        'unverified' => [
            'label' => 'Unverified',
            'tone' => 'neutral'
        ],
        'pending' => [
            'label' => 'Pending',
            'tone' => 'warning'
        ],
        'verified' => [
            'label' => 'Verified',
            'tone' => 'success'
        ],
        'rejected' => [
            'label' => 'Rejected',
            'tone' => 'danger'
        ]
    ];

    return $statuses[$status] ?? null;
}


/*
|--------------------------------------------------------------------------
| ORGANISER VERIFICATION-FEE STATUS DISPLAY
|--------------------------------------------------------------------------
*/

function get_verification_fee_status_display(
    string $status
): ?array {
    $statuses = [
        'unpaid' => [
            'label' => 'Unpaid',
            'tone' => 'neutral'
        ],
        'pending' => [
            'label' => 'Pending',
            'tone' => 'warning'
        ],
        'paid' => [
            'label' => 'Paid',
            'tone' => 'success'
        ],
        'failed' => [
            'label' => 'Failed',
            'tone' => 'danger'
        ]
    ];

    return $statuses[$status] ?? null;
}


/*
|--------------------------------------------------------------------------
| ESCROW STATUS DISPLAY
|--------------------------------------------------------------------------
*/

function get_escrow_status_display(
    string $status
): ?array {
    $statuses = [
        'held' => [
            'label' => 'Held',
            'tone' => 'warning'
        ],
        'released' => [
            'label' => 'Released',
            'tone' => 'success'
        ],
        'refunded' => [
            'label' => 'Refunded',
            'tone' => 'info'
        ]
    ];

    return $statuses[$status] ?? null;
}


/*
|--------------------------------------------------------------------------
| GENERIC STATUS DISPLAY
|--------------------------------------------------------------------------
*/

function get_status_display(
    string $type,
    string $status
): ?array {
    switch ($type) {
        case 'event':
            return get_event_status_display(
                $status
            );

        case 'booking':
            return get_booking_status_display(
                $status
            );

        case 'payment':
            return get_payment_status_display(
                $status
            );

        case 'user':
            return get_user_status_display(
                $status
            );

        case 'verification':
            return get_verification_status_display(
                $status
            );

        case 'verification_fee':
            return get_verification_fee_status_display(
                $status
            );

        case 'escrow':
            return get_escrow_status_display(
                $status
            );

        default:
            return null;
    }
}


/*
|--------------------------------------------------------------------------
| BACKWARD-COMPATIBILITY WRAPPERS
|--------------------------------------------------------------------------
| These allow older EventNest pages to keep working while the project is
| migrated to the shared snake_case helpers above.
|--------------------------------------------------------------------------
*/

function sanitizeInput($data): string
{
    return escape_html(
        clean_input($data)
    );
}

function formatDate($date): string
{
    return format_date($date);
}

function generateBookingReference(
    ?PDO $pdo = null
): string {
    if ($pdo === null) {
        global $pdo;

        if (!($pdo instanceof PDO)) {
            throw new RuntimeException(
                'A valid PDO connection is required to generate a booking reference.'
            );
        }
    }

    return generate_booking_reference($pdo);
}