<?php
/*
Server-side optional EventNest recommendations.
OpenAI Embeddings are used only when OPENAI_API_KEY exists, otherwise EventNest uses a normal SQL fallback.

*/

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function cosine_similarity(array $a, array $b): ?float
{
    if (!$a || count($a) !== count($b)) {
        return null;
    }

    $dot = 0.0;
    $norm_a = 0.0;
    $norm_b = 0.0;

    foreach ($a as $index => $value) {
        if (!isset($b[$index]) || !is_numeric($value) || !is_numeric($b[$index])) {
            return null;
        }

        $x = (float) $value;
        $y = (float) $b[$index];
        $dot += $x * $y;
        $norm_a += $x * $x;
        $norm_b += $y * $y;
    }

    if ($norm_a <= 0 || $norm_b <= 0) {
        return null;
    }

    $score = $dot / (sqrt($norm_a) * sqrt($norm_b));
    return is_finite($score) ? $score : null;
}

function recommendation_reason(array $event, array $user, int $position = 0): string
{
    $career = trim((string) ($user['CareerField'] ?? ''));
    $category = trim((string) ($event['CategoryName'] ?? 'career'));
    $format = strtolower(trim((string) ($event['Format'] ?? '')));
    $same_field = $career !== '' && strcasecmp($category, $career) === 0;

    if ($same_field && $format === 'online') {
        return 'An online ' . $category . ' event that matches your selected career field.';
    }

    if ($same_field && $format === 'physical') {
        return 'An in-person ' . $category . ' event that matches your selected career field.';
    }

    if ($same_field && $format === 'hybrid') {
        return 'A hybrid ' . $category . ' event that matches your selected career field.';
    }

    if ($same_field) {
        return 'A strong match for your selected ' . $category . ' career field.';
    }

    if ((int) ($event['IsFeatured'] ?? 0) === 1) {
        return 'A featured ' . $category . ' event that can broaden your career exploration.';
    }

    if ($format === 'online') {
        return 'An online ' . $category . ' event outside your main field that is easy to explore.';
    }

    if ($format === 'hybrid') {
        return 'A hybrid ' . $category . ' event that gives you another career area to explore.';
    }

    $options = [
        'An upcoming ' . $category . ' event that can broaden your career options.',
        'A different career area you may want to explore alongside your main field.',
        'An approved upcoming ' . $category . ' event selected to add variety to your recommendations.',
    ];

    return $options[$position % count($options)];
}

function recommendation_fallback(PDO $pdo, array $user, int $limit, string $state = 'fallback'): array
{
    $career = $state === 'opted-out' ? '' : trim((string) ($user['CareerField'] ?? ''));
    $sql = "SELECT E.*, C.CategoryName
            FROM EVENTS E
            JOIN CATEGORIES C ON C.CategoryID = E.CategoryID
            JOIN USERS O ON O.UserID = E.OrganiserID
            WHERE E.Status = 'published'
              AND E.EventDate > NOW()
              AND O.Role = 'organiser'
              AND O.UserStatus = 'active'
              AND O.VerificationStatus = 'verified'
              AND O.VerificationFeePaymentStatus = 'paid'
              AND O.VerificationFeePaidUntil > NOW()
            ORDER BY (LOWER(C.CategoryName) = LOWER(:career)) DESC,
                     E.IsFeatured DESC,
                     E.EventDate ASC
            LIMIT 30";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':career' => $career]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $events = [];

    foreach ($rows as $event) {
        [$eligible] = event_booking_eligibility($pdo, $event, $user);
        if (!$eligible) {
            continue;
        }

        $reason_user = $user;
        if ($state === 'opted-out') {
            $reason_user['CareerField'] = '';
        }

        $event['_reason'] = recommendation_reason($event, $reason_user, count($events));
        $events[] = $event;

        if (count($events) >= $limit) {
            break;
        }
    }

    return ['state' => $state, 'personalised' => false, 'events' => $events];
}

function eventnest_openai_api_key(): string
{
    /*
    The OpenAI key stays in the server environment and is never written into the PHP file.

    */
    $key = getenv('OPENAI_API_KEY');
    return is_string($key) ? trim($key) : '';
}

function openai_embeddings_request(array $inputs): ?array
{
    $key = eventnest_openai_api_key();
    if ($key === '' || !function_exists('curl_init') || !$inputs) {
        return null;
    }

    $payload = json_encode([
        'model' => 'text-embedding-3-small',
        'input' => array_values($inputs),
        'encoding_format' => 'float',
    ]);

    if ($payload === false) {
        return null;
    }

    $ch = curl_init('https://api.openai.com/v1/embeddings');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $status < 200 || $status >= 300) {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || empty($decoded['data']) || !is_array($decoded['data'])) {
        return null;
    }

    $vectors = [];
    foreach ($decoded['data'] as $item) {
        if (!isset($item['index'], $item['embedding']) || !is_array($item['embedding'])) {
            return null;
        }
        $vectors[(int) $item['index']] = $item['embedding'];
    }

    ksort($vectors);
    return array_values($vectors);
}

function getPersonalisedRecommendations(PDO $pdo, int $user_id, int $limit = 5): array
{
    $limit = max(3, min(5, $limit));
    $stmt = $pdo->prepare(
        "SELECT UserID, Email, CareerField, UserStatus, Is_email_verified, RecommendationOptIn
         FROM USERS
         WHERE UserID = :user_id AND Role = 'attendee' AND UserStatus = 'active'
         LIMIT 1"
    );
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ['state' => 'controlled-error', 'personalised' => false, 'events' => []];
    }
    if (!(bool) $user['RecommendationOptIn']) {
        return recommendation_fallback($pdo, $user, $limit, 'opted-out');
    }
    if (trim((string) $user['CareerField']) === '') {
        return recommendation_fallback($pdo, $user, $limit, 'no-profile');
    }
    if (eventnest_openai_api_key() === '') {
        return recommendation_fallback($pdo, $user, $limit, 'fallback');
    }

    $cache = $pdo->prepare(
        "SELECT E.*, C.CategoryName, R.SimilarityScore
         FROM USER_EVENT_RECOMMENDATIONS R
         JOIN EVENTS E ON E.EventID = R.EventID
         JOIN CATEGORIES C ON C.CategoryID = E.CategoryID
         JOIN USERS O ON O.UserID = E.OrganiserID
         WHERE R.UserID = :user_id
           AND R.ExpiresAt > NOW()
           AND E.Status = 'published'
           AND E.EventDate > NOW()
           AND O.UserStatus = 'active'
           AND O.Role = 'organiser'
           AND O.VerificationStatus = 'verified'
           AND O.VerificationFeePaymentStatus = 'paid'
           AND O.VerificationFeePaidUntil > NOW()
         ORDER BY R.SimilarityScore DESC
         LIMIT " . (int) $limit
    );
    $cache->execute(['user_id' => $user_id]);
    $cached_rows = $cache->fetchAll(PDO::FETCH_ASSOC);
    $cached = [];

    foreach ($cached_rows as $event) {
        [$eligible] = event_booking_eligibility($pdo, $event, $user);
        if (!$eligible) {
            continue;
        }

        $event['_reason'] = recommendation_reason($event, $user, count($cached));
        $cached[] = $event;
    }

    if (count($cached) >= 3) {
        return ['state' => 'personalised-cache', 'personalised' => true, 'events' => array_slice($cached, 0, $limit)];
    }

    $candidate_stmt = $pdo->query(
        "SELECT E.*, C.CategoryName
         FROM EVENTS E
         JOIN CATEGORIES C ON C.CategoryID = E.CategoryID
         JOIN USERS O ON O.UserID = E.OrganiserID
         WHERE E.Status = 'published'
           AND E.EventDate > NOW()
           AND O.UserStatus = 'active'
           AND O.Role = 'organiser'
           AND O.VerificationStatus = 'verified'
           AND O.VerificationFeePaymentStatus = 'paid'
           AND O.VerificationFeePaidUntil > NOW()
         ORDER BY E.IsFeatured DESC, E.EventDate ASC
         LIMIT 30"
    );

    $candidate_rows = $candidate_stmt->fetchAll(PDO::FETCH_ASSOC);
    $candidates = [];

    foreach ($candidate_rows as $event) {
        [$eligible] = event_booking_eligibility($pdo, $event, $user);
        if ($eligible) {
            $candidates[] = $event;
        }
        if (count($candidates) >= 25) {
            break;
        }
    }

    if (!$candidates) {
        return ['state' => 'no-results', 'personalised' => false, 'events' => []];
    }

    $career_label = $pdo->prepare('SELECT CategoryName FROM CATEGORIES WHERE CategoryName = ? LIMIT 1');
    $career_label->execute([$user['CareerField']]);
    $safe_career = $career_label->fetchColumn();

    if (!$safe_career) {
        return recommendation_fallback($pdo, $user, $limit, 'no-profile');
    }

    $inputs = ['Career field: ' . $safe_career];
    foreach ($candidates as $event) {
        $inputs[] = 'Category: ' . $event['CategoryName'] . '. Format: ' . $event['Format'];
    }

    $vectors = openai_embeddings_request($inputs);
    if (!$vectors || count($vectors) !== count($inputs)) {
        return recommendation_fallback($pdo, $user, $limit, 'fallback');
    }

    $user_vector = array_shift($vectors);
    $ranked = [];

    foreach ($candidates as $index => $event) {
        $score = cosine_similarity($user_vector, $vectors[$index] ?? []);
        if ($score === null) {
            continue;
        }

        $event['_score'] = max(0.0, min(1.0, $score));
        $ranked[] = $event;
    }

    usort($ranked, static function (array $a, array $b): int {
        return ($b['_score'] <=> $a['_score']);
    });
    $ranked = array_slice($ranked, 0, $limit);

    foreach ($ranked as $index => &$event) {
        $event['_reason'] = recommendation_reason($event, $user, $index);
    }
    unset($event);

    if (!$ranked) {
        return recommendation_fallback($pdo, $user, $limit, 'fallback');
    }

    try {
        $pdo->beginTransaction();
        $delete = $pdo->prepare('DELETE FROM USER_EVENT_RECOMMENDATIONS WHERE UserID = :user_id');
        $delete->execute(['user_id' => $user_id]);
        $insert = $pdo->prepare(
            "INSERT INTO USER_EVENT_RECOMMENDATIONS
                (UserID, EventID, SimilarityScore, ModelName, GeneratedAt, ExpiresAt)
             VALUES
                (:user_id, :event_id, :score, 'text-embedding-3-small', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))"
        );

        foreach ($ranked as $event) {
            $insert->execute([
                'user_id' => $user_id,
                'event_id' => (int) $event['EventID'],
                'score' => round((float) $event['_score'], 4),
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    return ['state' => 'personalised', 'personalised' => true, 'events' => $ranked];
}
