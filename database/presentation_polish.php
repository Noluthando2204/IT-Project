<?php
declare(strict_types=1);

// Explicit development/presentation-only fixture polish; never run automatically on production.
// Only registered fixture posts and login-disabled synthetic reactors are adjusted.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/database-config/connection.php';

const POLISH_POOL = 5500;
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

// Stable hundreds/thousands for explicitly designated presentation posts.
function polish_targets(int $post_id, string $post_date): array
{
    $n=$post_id%3;
    return ['thumbs_up'=>847+$n*353,'clap'=>1200+$n*200,'lightbulb'=>1600+$n*200];
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
    $registered=$pdo->query("SELECT FixtureKey FROM DEMO_FIXTURES WHERE EntityType='reactor'")->fetchAll(PDO::FETCH_COLUMN);
    $registered=array_fill_keys($registered,true);
    $existing=$pdo->query("SELECT Email FROM USERS WHERE Email LIKE 'demo.reactor%@example.com'")->fetchAll(PDO::FETCH_COLUMN);
    $existing=array_fill_keys($existing,true);
    $pending=[];
    for($i=1;$i<=POLISH_POOL;$i++) {
        $key='reactor'.sprintf('%06d',$i);
        if(isset($registered[$key])) continue;
        $email='demo.'.$key.'@example.com';
        if(isset($existing[$email])) throw new RuntimeException('Unmanaged demo reactor collision; no account adopted.');
        $pending[$key]=$email;
    }
    foreach(array_chunk($pending,250,true) as $chunk) {
        $marks=implode(',',array_fill(0,count($chunk),"('Demo','Reactor',?,NULL,'attendee','active',0,'2026-10-15 08:00:00')"));
        polish_q($pdo,'INSERT INTO USERS(FirstName,LastName,Email,PasswordHash,Role,UserStatus,Is_email_verified,CreatedAt) VALUES '.$marks,array_values($chunk));
        $marks=implode(',',array_fill(0,count($chunk),'?'));
        $users=polish_q($pdo,"SELECT UserID,Email FROM USERS WHERE Email IN ($marks)",array_values($chunk))->fetchAll();
        $args=[];$rows=[];
        foreach($users as $u){$rows[]="(?,'reactor',?)";array_push($args,substr(explode('@',$u['Email'])[0],5),$u['UserID']);}
        polish_q($pdo,'INSERT INTO DEMO_FIXTURES(FixtureKey,EntityType,EntityID) VALUES '.implode(',',$rows),$args);
    }
    $pool = polish_q($pdo, "SELECT f.EntityID FROM DEMO_FIXTURES f JOIN USERS u ON u.UserID=f.EntityID
                            WHERE f.EntityType='reactor' AND f.FixtureKey<=? AND u.PasswordHash IS NULL
                            ORDER BY f.FixtureKey", ['reactor' . sprintf('%06d', POLISH_POOL)])->fetchAll(PDO::FETCH_COLUMN);
    $pool = array_map('intval', $pool);

    $pdo->commit();

    // Adjust only explicitly registered presentation posts.
    $posts = $pdo->query("SELECT DISTINCT p.PostID,p.PostDate FROM POSTS p JOIN DEMO_FIXTURES f ON f.EntityID=p.PostID AND f.EntityType='post' WHERE f.FixtureKey REGEXP '^post[0-9]+$' ORDER BY p.PostID")->fetchAll();
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
