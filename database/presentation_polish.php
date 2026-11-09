<?php
declare(strict_types=1);

// Presentation data polish. Runs on every deploy (see Dockerfile), safe to repeat, never stops the deploy.
//  1. Posts: removes images that were attached twice (the same photo on two posts) and fixes the
//     matching captions, so each organiser photo appears once.
//  2. Reactions: replaces the inflated synthetic counts (thousands on a few posts, 1-3 on the rest)
//     with modest, believable counts on every seeded post. Reactions from real accounts are kept and
//     counted; only the login-disabled synthetic reactor accounts are adjusted.
//  3. Removes the unused synthetic reactor accounts (5,500 were created; 60 are enough).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/database-config/connection.php';

const POLISH_POOL = 60;
const POLISH_CUTOFF = '2026-11-11 00:00:00'; // posts created live after this are left alone

function polish_q(PDO $pdo, string $sql, array $args = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt;
}

function polish_fixture(PDO $pdo, string $key): int
{
    try {
        return (int) polish_q($pdo, 'SELECT EntityID FROM DEMO_FIXTURES WHERE FixtureKey=?', [$key])->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

// Believable, stable counts per post: 9-46 reactions in total, older posts a little higher.
function polish_targets(int $post_id, string $post_date): array
{
    $h = crc32('eventnest-post-' . $post_id);
    $days_old = max(0, (int) floor((strtotime(POLISH_CUTOFF) - strtotime($post_date)) / 86400));
    $total = 9 + ($h % 24) + min(12, $days_old);
    $thumbs = (int) round($total * (0.42 + (($h >> 8) % 15) / 100));
    $claps = (int) round(($total - $thumbs) * (0.50 + (($h >> 16) % 25) / 100));
    $bulbs = max(1, $total - $thumbs - $claps);
    return ['thumbs_up' => $thumbs, 'clap' => max(1, $claps), 'lightbulb' => $bulbs];
}

$report = ['posts_fixed' => 0, 'posts_balanced' => 0, 'reactors_removed' => 0, 'reactors_renamed' => 0];

// ---------------------------------------------------------------- 1. Posts
try {
    $pdo->beginTransaction();
    $duplicates = [
        // fixture key => [photo that already belongs to an earlier post, old caption, new caption]
        'post10' => [
            'uploaded-content/posts/cloud-nithen-naidoo-speaker.jpeg',
            'Our Cloud Skills Workshop speaker spotlight: practise deployment fundamentals, ask about entry-level cloud roles and bring your learning goals. #CloudSkills',
            'Two weeks to Cloud Skills Workshop: practise deployment fundamentals, ask our speakers about entry-level cloud roles and bring your learning goals. Seats are free but limited. #CloudSkills',
        ],
        'post16' => [
            'uploaded-content/posts/creative-design-lab-prep.jpg',
            null,
            null,
        ],
    ];
    foreach ($duplicates as $key => [$photo, $old, $new]) {
        $id = polish_fixture($pdo, $key);
        if (!$id) { continue; }
        $owner = (int) polish_q($pdo, 'SELECT COUNT(*) FROM POSTS WHERE Media=? AND PostID<>?', [$photo, $id])->fetchColumn();
        if ($owner > 0) {
            $report['posts_fixed'] += polish_q($pdo, "UPDATE POSTS SET Media=NULL, Media_type=NULL WHERE PostID=? AND Media=?", [$id, $photo])->rowCount();
        }
        if ($old !== null) {
            $report['posts_fixed'] += polish_q($pdo, 'UPDATE POSTS SET Content=? WHERE PostID=? AND Content=?', [$new, $id, $old])->rowCount();
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo 'Post polish skipped: ' . $e->getMessage() . "\n";
}

// ---------------------------------------------------------------- 2/3. Reactions and reactor pool
try {
    $hasFixtures = (bool) $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='DEMO_FIXTURES'")->fetchColumn();
    if (!$hasFixtures) { throw new RuntimeException('DEMO_FIXTURES table missing; run migration_presentation.sql first.'); }

    // Make sure exactly POLISH_POOL synthetic reactors exist (login disabled: NULL password, unverified).
    $pdo->beginTransaction();
    for ($i = 1; $i <= POLISH_POOL; $i++) {
        $key = 'reactor' . sprintf('%06d', $i);
        if (polish_fixture($pdo, $key)) { continue; }
        $email = 'demo.' . $key . '@example.com';
        $existing = (int) polish_q($pdo, 'SELECT UserID FROM USERS WHERE Email=?', [$email])->fetchColumn();
        if (!$existing) {
            polish_q($pdo, "INSERT INTO USERS (FirstName,LastName,Email,PasswordHash,Role,UserStatus,Is_email_verified,CreatedAt)
                            VALUES ('Community','Member',?,NULL,'attendee','active',0,'2026-10-15 08:00:00')", [$email]);
            $existing = (int) $pdo->lastInsertId();
        }
        polish_q($pdo, "INSERT INTO DEMO_FIXTURES (FixtureKey,EntityType,EntityID) VALUES (?,'reactor',?)", [$key, $existing]);
    }
    $pool = polish_q($pdo, "SELECT f.EntityID FROM DEMO_FIXTURES f JOIN USERS u ON u.UserID=f.EntityID
                            WHERE f.EntityType='reactor' AND f.FixtureKey<=? AND u.PasswordHash IS NULL
                            ORDER BY f.FixtureKey", ['reactor' . sprintf('%06d', POLISH_POOL)])->fetchAll(PDO::FETCH_COLUMN);
    $pool = array_map('intval', $pool);

    // Give the kept reactors ordinary names (they only ever appear in admin user lists).
    $first = ['Ayanda','Bongani','Chloe','Dineo','Ethan','Fatima','Gugu','Hlengiwe','Imran','Jabu','Kamogelo','Lebo','Musa','Nandi','Omphile','Precious','Quinton','Refilwe','Siya','Tumi'];
    $last = ['Mabaso','Pillay','Van Wyk','Khumalo','Ndlovu','Botha','Mahlangu','Govender','Sithole','Petersen','Zulu','Moloi'];
    foreach ($pool as $n => $uid) {
        $report['reactors_renamed'] += polish_q($pdo, "UPDATE USERS SET FirstName=?, LastName=? WHERE UserID=? AND PasswordHash IS NULL AND FirstName IN ('Demo','Community')",
            [$first[$n % count($first)], $last[($n * 7) % count($last)], $uid])->rowCount();
    }
    $pdo->commit();

    // Rebalance every seeded post (anything posted before the cut-off date).
    $posts = $pdo->query("SELECT PostID, PostDate FROM POSTS WHERE PostDate < '" . POLISH_CUTOFF . "' ORDER BY PostID")->fetchAll();
    $marks = implode(',', array_fill(0, count($pool), '?'));
    foreach ($posts as $post) {
        $pid = (int) $post['PostID'];
        $pdo->beginTransaction();
        // Clear every synthetic reaction on this post (pool and surplus reactors alike).
        polish_q($pdo, "DELETE r FROM REACTIONS r JOIN DEMO_FIXTURES f ON f.EntityID=r.UserID AND f.EntityType='reactor' WHERE r.PostID=?", [$pid]);
        $real = polish_q($pdo, 'SELECT ReactionType, COUNT(*) FROM REACTIONS WHERE PostID=? GROUP BY ReactionType', [$pid])->fetchAll(PDO::FETCH_KEY_PAIR);
        $offset = ($pid * 13) % max(1, count($pool)); // different faces per post
        $rotated = array_merge(array_slice($pool, $offset), array_slice($pool, 0, $offset));
        $cursor = 0;
        foreach (polish_targets($pid, (string) $post['PostDate']) as $type => $target) {
            $need = max(0, $target - (int) ($real[$type] ?? 0));
            $users = array_slice($rotated, $cursor, $need);
            $cursor += count($users);
            if (!$users) { continue; }
            $rows = []; $args = [];
            foreach ($users as $k => $uid) {
                $rows[] = '(?,?,?,?)';
                // Spread reaction times over the days after the post.
                array_push($args, $pid, $uid, $type, date('Y-m-d H:i:s', strtotime((string) $post['PostDate']) + 1800 + (($k * 7919 + $pid * 104729) % 172800)));
            }
            polish_q($pdo, 'INSERT IGNORE INTO REACTIONS (PostID,UserID,ReactionType,ReactionDate) VALUES ' . implode(',', $rows), $args);
        }
        $pdo->commit();
        $report['posts_balanced']++;
    }

    // Remove surplus synthetic reactors (they no longer hold any reactions).
    $surplus = polish_q($pdo, "SELECT f.EntityID FROM DEMO_FIXTURES f WHERE f.EntityType='reactor' AND f.FixtureKey>?",
        ['reactor' . sprintf('%06d', POLISH_POOL)])->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_chunk(array_map('intval', $surplus), 500) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $pdo->beginTransaction();
        try {
            polish_q($pdo, "DELETE FROM REACTIONS WHERE UserID IN ($in)", $chunk);
            $removed = polish_q($pdo, "DELETE FROM USERS WHERE UserID IN ($in) AND PasswordHash IS NULL AND Email LIKE 'demo.reactor%@example.com'", $chunk)->rowCount();
            polish_q($pdo, "DELETE FROM DEMO_FIXTURES WHERE EntityType='reactor' AND EntityID IN ($in)", $chunk);
            $pdo->commit();
            $report['reactors_removed'] += $removed;
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo 'Reactor cleanup batch skipped: ' . $e->getMessage() . "\n";
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo 'Reaction polish skipped: ' . $e->getMessage() . "\n";
}

echo 'Presentation polish: ' . json_encode($report) . "\n";
exit(0);
