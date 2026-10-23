<?php
/*
Server-side optional EventNest recommendations.
Uses OpenAI Embeddings only when OPENAI_API_KEY exists; otherwise returns a labelled SQL fallback.
*/

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function cosine_similarity(array $a, array $b): ?float
{
    if (!$a || count($a) !== count($b)) return null;
    $dot = 0.0; $norm_a = 0.0; $norm_b = 0.0;
    $length = count($a);
    for ($i = 0; $i < $length; $i++) {
        if (!is_numeric($a[$i]) || !is_numeric($b[$i])) return null;
        $x = (float)$a[$i]; $y = (float)$b[$i];
        if (!is_finite($x) || !is_finite($y)) return null;
        $dot += $x * $y; $norm_a += $x * $x; $norm_b += $y * $y;
    }
    if ($norm_a <= 0 || $norm_b <= 0) return null;
    $score = $dot / (sqrt($norm_a) * sqrt($norm_b));
    return is_finite($score) ? $score : null;
}

function recommendation_fallback(PDO $pdo, array $user, int $limit, string $state = 'fallback'): array
{
    $career = trim((string)($user['CareerField'] ?? ''));
    $sql = "SELECT E.*, C.CategoryName
            FROM EVENTS E
            JOIN CATEGORIES C ON C.CategoryID = E.CategoryID
            JOIN USERS O ON O.UserID = E.OrganiserID
            WHERE E.Status = 'published' AND E.EventDate > NOW()
              AND O.Role = 'organiser' AND O.UserStatus = 'active' AND O.Role = 'organiser' AND O.VerificationStatus = 'verified'
              AND O.VerificationFeePaymentStatus = 'paid'
              AND O.VerificationFeePaidUntil > NOW()
            ORDER BY (LOWER(C.CategoryName) = LOWER(:career)) DESC, E.IsFeatured DESC, E.EventDate ASC
            LIMIT 30";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':career' => $state === 'opted-out' ? '' : $career]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $events = [];
    foreach ($rows as $event) {
        [$eligible] = event_booking_eligibility($pdo, $event, $user);
        if (!$eligible) continue;
        $event['_reason'] = $career !== '' && strcasecmp((string)$event['CategoryName'], $career) === 0
            ? 'Matches your ' . $career . ' career field'
            : 'Approved upcoming event';
        $events[] = $event;
        if (count($events) >= $limit) break;
    }
    return ['state' => $state, 'personalised' => false, 'events' => $events];
}

function eventnest_openai_api_key(): string
{
    /*
    The real OpenAI key is not stored in source code.
    Configure OPENAI_API_KEY in the server/Render environment
    (and locally for AI testing) so recommendations can use it securely.
    */
    $key = getenv('OPENAI_API_KEY');
    return is_string($key) ? trim($key) : '';
}

function openai_embeddings_request(array $inputs): ?array
{
    $key = eventnest_openai_api_key();
    if (!$key || !function_exists('curl_init') || !$inputs) return null;

    $payload = json_encode([
        'model' => 'text-embedding-3-small',
        'input' => array_values($inputs),
        'encoding_format' => 'float',
    ]);
    if ($payload === false) return null;

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
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $status < 200 || $status >= 300) return null;

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || empty($decoded['data']) || !is_array($decoded['data'])) return null;
    $vectors = [];
    foreach ($decoded['data'] as $item) {
        if (!isset($item['index'], $item['embedding']) || !is_array($item['embedding'])) return null;
        $vectors[(int)$item['index']] = $item['embedding'];
    }
    ksort($vectors);
    return array_values($vectors);
}

function getPersonalisedRecommendations(PDO $pdo, int $user_id, int $limit = 5): array
{
    $limit = max(3, min(5, $limit));
    $stmt = $pdo->prepare("SELECT UserID, Email, CareerField, UserStatus, Is_email_verified, RecommendationOptIn
                           FROM USERS WHERE UserID = :user_id AND Role = 'attendee' AND UserStatus = 'active' LIMIT 1");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return ['state' => 'controlled-error', 'personalised' => false, 'events' => []];
    if (!(bool)$user['RecommendationOptIn']) return recommendation_fallback($pdo, $user, $limit, 'opted-out');
    if (trim((string)$user['CareerField']) === '') return recommendation_fallback($pdo, $user, $limit, 'no-profile');
    if (eventnest_openai_api_key() === '') return recommendation_fallback($pdo, $user, $limit, 'fallback');

    $cache = $pdo->prepare("SELECT E.*, C.CategoryName, R.SimilarityScore
                            FROM USER_EVENT_RECOMMENDATIONS R
                            JOIN EVENTS E ON E.EventID = R.EventID
                            JOIN CATEGORIES C ON C.CategoryID = E.CategoryID
                            JOIN USERS O ON O.UserID = E.OrganiserID
                            WHERE R.UserID = :user_id AND R.ExpiresAt > NOW()
                              AND E.Status = 'published' AND E.EventDate > NOW()
                              AND O.UserStatus = 'active' AND O.Role = 'organiser' AND O.VerificationStatus = 'verified'
                              AND O.VerificationFeePaymentStatus = 'paid'
                              AND O.VerificationFeePaidUntil > NOW()
                            ORDER BY R.SimilarityScore DESC LIMIT " . (int)$limit);
    $cache->execute(['user_id' => $user_id]);
    $cached_rows = $cache->fetchAll(PDO::FETCH_ASSOC);
    $cached = [];
    foreach ($cached_rows as $event) {
        [$eligible] = event_booking_eligibility($pdo, $event, $user);
        if (!$eligible) continue;
        $event['_reason'] = strcasecmp((string)$event['CategoryName'], (string)$user['CareerField']) === 0
            ? 'Matches your ' . $user['CareerField'] . ' career field'
            : 'Matches your career-event profile';
        $cached[] = $event;
    }
    if (count($cached) >= 3) {
        return ['state' => 'personalised-cache', 'personalised' => true, 'events' => array_slice($cached, 0, $limit)];
    }

    $candidate_stmt = $pdo->query("SELECT E.*, C.CategoryName
                                    FROM EVENTS E
                                    JOIN CATEGORIES C ON C.CategoryID = E.CategoryID
                                    JOIN USERS O ON O.UserID = E.OrganiserID
                                    WHERE E.Status = 'published' AND E.EventDate > NOW()
                                      AND O.UserStatus = 'active' AND O.Role = 'organiser' AND O.VerificationStatus = 'verified'
                                      AND O.VerificationFeePaymentStatus = 'paid'
                                      AND O.VerificationFeePaidUntil > NOW()
                                    ORDER BY E.IsFeatured DESC, E.EventDate ASC LIMIT 30");
    $candidate_rows = $candidate_stmt->fetchAll(PDO::FETCH_ASSOC);
    $candidates = [];
    foreach ($candidate_rows as $event) {
        [$eligible] = event_booking_eligibility($pdo, $event, $user);
        if ($eligible) $candidates[] = $event;
        if (count($candidates) >= 25) break;
    }
    if (!$candidates) return ['state' => 'no-results', 'personalised' => false, 'events' => []];

    // Use only an approved category label, rather than free-text personal information.
    $career_label = $pdo->prepare('SELECT CategoryName FROM CATEGORIES WHERE CategoryName = ? LIMIT 1');
    $career_label->execute([$user['CareerField']]);
    $safe_career = $career_label->fetchColumn();
    if (!$safe_career) return recommendation_fallback($pdo, $user, $limit, 'no-profile');
    $user_text = 'Career field: ' . $safe_career;
    $inputs = [$user_text];
    foreach ($candidates as $event) {
        $inputs[] = 'Category: ' . $event['CategoryName'] . '. Format: ' . $event['Format'];
    }

    $vectors = openai_embeddings_request($inputs);
    if (!$vectors || count($vectors) !== count($inputs)) return recommendation_fallback($pdo, $user, $limit, 'fallback');
    $user_vector = array_shift($vectors);
    $ranked = [];
    foreach ($candidates as $i => $event) {
        $score = cosine_similarity($user_vector, $vectors[$i] ?? []);
        if ($score === null) continue;
        $event['_score'] = max(0.0, min(1.0, $score));
        $event['_reason'] = strcasecmp((string)$event['CategoryName'], (string)$user['CareerField']) === 0
            ? 'Matches your ' . $user['CareerField'] . ' career field'
            : 'Related to your career-event profile';
        $ranked[] = $event;
    }
    usort($ranked, function ($a, $b) { return $a['_score'] < $b['_score'] ? 1 : ($a['_score'] > $b['_score'] ? -1 : 0); });
    $ranked = array_slice($ranked, 0, $limit);
    if (!$ranked) return recommendation_fallback($pdo, $user, $limit, 'fallback');

    try {
        $pdo->beginTransaction();
        $delete = $pdo->prepare('DELETE FROM USER_EVENT_RECOMMENDATIONS WHERE UserID = :user_id');
        $delete->execute(['user_id' => $user_id]);
        $insert = $pdo->prepare("INSERT INTO USER_EVENT_RECOMMENDATIONS (UserID, EventID, SimilarityScore, ModelName, GeneratedAt, ExpiresAt)
                                 VALUES (:user_id, :event_id, :score, 'text-embedding-3-small', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
        foreach ($ranked as $event) {
            $insert->execute(['user_id' => $user_id, 'event_id' => (int)$event['EventID'], 'score' => round((float)$event['_score'], 4)]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

    return ['state' => 'personalised', 'personalised' => true, 'events' => $ranked];
}
