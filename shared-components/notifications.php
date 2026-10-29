<?php
declare(strict_types=1);

/*
Notification helpers for EventNest.

The approved ERD has no NOTIFICATIONS table, so notices are generated live from
existing records (bookings, events, posts, account status). "Read" state is kept
per user in a small cookie holding the time the notifications page was last
opened, so no schema change is needed.
*/

require_once __DIR__ . '/functions.php';

if (!function_exists('eventnest_notice_cookie_name')) {
    function eventnest_notice_cookie_name(int $user_id): string
    {
        return 'eventnest_notices_seen_' . $user_id;
    }
}

if (!function_exists('eventnest_notices_last_seen')) {
    /** Unix time the user last opened the notifications page (0 = never). */
    function eventnest_notices_last_seen(int $user_id): int
    {
        $value = $_COOKIE[eventnest_notice_cookie_name($user_id)] ?? '';
        return is_string($value) && ctype_digit($value) ? (int) $value : 0;
    }
}

if (!function_exists('eventnest_notices_mark_seen')) {
    function eventnest_notices_mark_seen(int $user_id): void
    {
        if (headers_sent()) {
            return;
        }
        $now = time();
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(eventnest_notice_cookie_name($user_id), (string) $now, [
            'expires' => $now + 31536000,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[eventnest_notice_cookie_name($user_id)] = (string) $now;
    }
}

if (!function_exists('eventnest_notice_time')) {
    function eventnest_notice_time(mixed $value): int
    {
        $time = is_string($value) && $value !== '' ? strtotime($value) : false;
        return $time === false ? 0 : $time;
    }
}

if (!function_exists('eventnest_time_ago')) {
    function eventnest_time_ago(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }
        $diff = time() - $timestamp;
        if ($diff < 0) {
            return 'Upcoming';
        }
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 604800) {
            $d = (int) floor($diff / 86400);
            return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
        }
        return date('j M Y', $timestamp);
    }
}

if (!function_exists('eventnest_collect_notices')) {
    /**
     * Build the current notices for a user, newest first.
     * Each notice: type, title, message, url, time (unix).
     */
    function eventnest_collect_notices(PDO $pdo, int $user_id): array
    {
        $user_stmt = $pdo->prepare(
            'SELECT UserID, Email, Role, UserStatus, VerificationStatus, CareerField, RecommendationOptIn
             FROM USERS WHERE UserID = :user_id LIMIT 1'
        );
        $user_stmt->execute([':user_id' => $user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['UserStatus'] === 'deleted') {
            return [];
        }

        $role = (string) $user['Role'];
        $notices = [];

        if ($role === 'attendee') {
            /* Booking activity */
            $stmt = $pdo->prepare(
                "SELECT b.BookingID, b.Status, b.BookingDate, e.EventID, e.EventName, e.EventDate,
                        e.Status AS EventStatus, e.UpdatedAt
                 FROM BOOKINGS b
                 INNER JOIN EVENTS e ON e.EventID = b.EventID
                 WHERE b.UserID = :user_id
                 ORDER BY b.BookingDate DESC
                 LIMIT 30"
            );
            $stmt->execute([':user_id' => $user_id]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
                $event_ts = eventnest_notice_time($b['EventDate']);
                $booked_ts = eventnest_notice_time($b['BookingDate']);

                if ($b['Status'] === 'pending') {
                    $notices[] = [
                        'type' => 'Booking',
                        'title' => 'Payment still pending',
                        'message' => 'Your booking for ' . $b['EventName'] . ' is waiting for payment confirmation.',
                        'url' => 'my-bookings.php?booking_id=' . (int) $b['BookingID'],
                        'time' => $booked_ts,
                    ];
                } elseif ($b['Status'] === 'canceled' || $b['Status'] === 'refunded') {
                    $notices[] = [
                        'type' => 'Booking',
                        'title' => 'Booking update',
                        'message' => $b['EventName'] . ' has a booking status of ' . eventnest_status_label((string) $b['Status']) . '.',
                        'url' => 'my-bookings.php?booking_id=' . (int) $b['BookingID'],
                        'time' => max($booked_ts, eventnest_notice_time($b['UpdatedAt'])),
                    ];
                } elseif ($b['Status'] === 'paid') {
                    if ($b['EventStatus'] === 'canceled') {
                        $notices[] = [
                            'type' => 'Event',
                            'title' => 'Event canceled',
                            'message' => $b['EventName'] . ' has been canceled by the organiser. Check your booking for refund details.',
                            'url' => 'my-bookings.php?booking_id=' . (int) $b['BookingID'],
                            'time' => eventnest_notice_time($b['UpdatedAt']),
                        ];
                    } elseif ($event_ts > time() && $event_ts <= strtotime('+7 days')) {
                        $notices[] = [
                            'type' => 'Event',
                            'title' => 'Upcoming event',
                            'message' => $b['EventName'] . ' is coming up on ' . format_datetime((string) $b['EventDate']) . '.',
                            'url' => 'event-detail.php?event_id=' . (int) $b['EventID'],
                            'time' => $event_ts - 7 * 86400,
                        ];
                    } elseif ($booked_ts >= strtotime('-14 days')) {
                        $notices[] = [
                            'type' => 'Booking',
                            'title' => 'Booking confirmed',
                            'message' => 'Your booking for ' . $b['EventName'] . ' is confirmed.',
                            'url' => 'my-bookings.php?booking_id=' . (int) $b['BookingID'],
                            'time' => $booked_ts,
                        ];
                    }
                }
            }

            /* Organiser posts about booked events (and, if opted in, events in the attendee's career field) */
            $field_match = !empty($user['RecommendationOptIn']) && trim((string) $user['CareerField']) !== '';
            $post_params = [':user_id' => $user_id, ':email' => (string) $user['Email']];
            $relevance = "EXISTS (
                           SELECT 1 FROM BOOKINGS b
                           WHERE b.UserID = :user_id
                             AND b.EventID = p.EventID
                             AND b.Status IN ('paid','pending')
                       )";
            if ($field_match) {
                $relevance .= ' OR LOWER(c.CategoryName) = LOWER(:field)';
                $post_params[':field'] = (string) $user['CareerField'];
            }

            $stmt = $pdo->prepare(
                "SELECT p.PostID, p.Content, p.Media_type, p.PostDate,
                        e.EventName, u.OrganisationName, u.FirstName, u.LastName
                 FROM POSTS p
                 INNER JOIN EVENTS e ON e.EventID = p.EventID
                 INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
                 INNER JOIN USERS u ON u.UserID = p.OrganiserID
                 WHERE p.PostDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   AND p.PostDate <= NOW()
                   AND e.Status IN ('published','completed')
                   AND u.UserStatus = 'active'
                   AND (" . $relevance . ")
                   AND NOT EXISTS (
                       SELECT 1 FROM BLOCKS bl
                       WHERE bl.OrganiserID = p.OrganiserID
                         AND LOWER(bl.BlockedEmail) = LOWER(:email)
                   )
                 ORDER BY p.PostDate DESC
                 LIMIT 15"
            );
            $stmt->execute($post_params);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $organiser = trim((string) ($p['OrganisationName'] ?? ''));
                if ($organiser === '') {
                    $organiser = trim((string) $p['FirstName'] . ' ' . (string) $p['LastName']);
                }
                $snippet = eventnest_excerpt((string) ($p['Content'] ?? ''), 110);
                if ($snippet === '') {
                    $snippet = 'Shared a new ' . ($p['Media_type'] ?: 'media') . ' post.';
                }
                $notices[] = [
                    'type' => 'Post',
                    'title' => 'New post: ' . $p['EventName'],
                    'message' => $organiser . ' posted: ' . $snippet,
                    'url' => 'post-detail.php?post_id=' . (int) $p['PostID'],
                    'time' => eventnest_notice_time($p['PostDate']),
                ];
            }

            /* New events in the attendee's career field (only if they opted in) */
            if (!empty($user['RecommendationOptIn']) && trim((string) $user['CareerField']) !== '') {
                $stmt = $pdo->prepare(
                    "SELECT e.EventID, e.EventName, e.Created_at
                     FROM EVENTS e
                     INNER JOIN CATEGORIES c ON c.CategoryID = e.CategoryID
                     INNER JOIN USERS u ON u.UserID = e.OrganiserID
                     WHERE e.Status = 'published'
                       AND e.EventDate > NOW()
                       AND e.Created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                       AND LOWER(c.CategoryName) = LOWER(:field)
                       AND u.Role = 'organiser'
                       AND u.VerificationStatus = 'verified'
                       AND u.UserStatus = 'active'
                       AND NOT EXISTS (
                           SELECT 1 FROM BLOCKS bl
                           WHERE bl.OrganiserID = e.OrganiserID
                             AND LOWER(bl.BlockedEmail) = LOWER(:email)
                       )
                     ORDER BY e.Created_at DESC
                     LIMIT 5"
                );
                $stmt->execute([':field' => (string) $user['CareerField'], ':email' => (string) $user['Email']]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
                    $notices[] = [
                        'type' => 'Recommended',
                        'title' => 'New event in your career field',
                        'message' => $e['EventName'] . ' was just published in ' . $user['CareerField'] . '.',
                        'url' => 'event-detail.php?event_id=' . (int) $e['EventID'],
                        'time' => eventnest_notice_time($e['Created_at']),
                    ];
                }
            }
        } elseif ($role === 'organiser') {
            if (($user['VerificationStatus'] ?? '') !== 'verified') {
                $notices[] = [
                    'type' => 'Account',
                    'title' => 'Organiser verification',
                    'message' => 'Your organiser account is ' . eventnest_status_label((string) ($user['VerificationStatus'] ?? 'pending')) . '. Complete verification before managing public events.',
                    'url' => 'organiser-verification.php',
                    'time' => 0,
                ];
            }

            $stmt = $pdo->prepare(
                "SELECT e.EventID, e.EventName, e.Status, e.EventDate, e.UpdatedAt,
                        (SELECT COUNT(*) FROM BOOKINGS b WHERE b.EventID = e.EventID AND b.Status IN ('paid','pending')) AS BookingCount,
                        (SELECT MAX(b.BookingDate) FROM BOOKINGS b WHERE b.EventID = e.EventID AND b.Status IN ('paid','pending')) AS LastBooking
                 FROM EVENTS e
                 WHERE e.OrganiserID = :user_id
                 ORDER BY e.EventDate ASC
                 LIMIT 20"
            );
            $stmt->execute([':user_id' => $user_id]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $event) {
                if ($event['Status'] === 'draft') {
                    $notices[] = [
                        'type' => 'Event',
                        'title' => 'Draft event',
                        'message' => $event['EventName'] . ' is still a draft and is not visible to attendees.',
                        'url' => 'manage-events.php',
                        'time' => eventnest_notice_time($event['UpdatedAt']),
                    ];
                } elseif ($event['Status'] === 'published' && eventnest_notice_time($event['EventDate']) > time()) {
                    $notices[] = [
                        'type' => 'Booking',
                        'title' => 'Published event activity',
                        'message' => $event['EventName'] . ' currently has ' . (int) $event['BookingCount'] . ' active or pending booking(s).',
                        'url' => 'manage-bookings.php',
                        'time' => eventnest_notice_time($event['LastBooking']),
                    ];
                }
            }
        } elseif ($role === 'admin') {
            $queries = [
                ['Pending organiser verifications', ' organiser account(s) need verification review.',
                 "SELECT COUNT(*) AS c, MAX(CreatedAt) AS t FROM USERS WHERE Role = 'organiser' AND VerificationStatus = 'pending' AND UserStatus = 'active'",
                 'admin-panel/manage-verifications.php'],
                ['Events awaiting review', ' draft event(s) are currently in the system.',
                 "SELECT COUNT(*) AS c, MAX(UpdatedAt) AS t FROM EVENTS WHERE Status = 'draft'",
                 'admin-panel/manage-events.php'],
                ['Suspended users', ' user account(s) are currently suspended.',
                 "SELECT COUNT(*) AS c, MAX(Suspension_start_date) AS t FROM USERS WHERE UserStatus = 'suspended'",
                 'admin-panel/moderation.php'],
            ];
            foreach ($queries as [$title, $suffix, $sql, $url]) {
                $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
                if ($row && (int) $row['c'] > 0) {
                    $notices[] = [
                        'type' => 'Admin',
                        'title' => $title,
                        'message' => (int) $row['c'] . $suffix,
                        'url' => $url,
                        'time' => eventnest_notice_time($row['t'] ?? null),
                    ];
                }
            }
        }

        usort($notices, static fn(array $a, array $b): int => $b['time'] <=> $a['time']);
        return $notices;
    }
}

if (!function_exists('eventnest_unread_notice_count')) {
    /** Number of notices newer than the last time the user opened the page. Never throws. */
    function eventnest_unread_notice_count(int $user_id): int
    {
        try {
            $seen = eventnest_notices_last_seen($user_id);
            $count = 0;
            foreach (eventnest_collect_notices(eventnest_db(), $user_id) as $notice) {
                if ($notice['time'] > $seen) {
                    $count++;
                }
            }
            return $count;
        } catch (Throwable $exception) {
            error_log('EventNest notification count error: ' . get_class($exception));
            return 0;
        }
    }
}
