<?php
declare(strict_types=1);
// Copy-only forward update: no identities, passwords, dates or financial fields.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/database-config/connection.php';
$copy = require __DIR__ . '/presentation_copy.php';
$changed = ['categories'=>0, 'events'=>0, 'posts'=>0];
$pdo->beginTransaction();
try {
    $category = $pdo->prepare('UPDATE CATEGORIES SET Description=? WHERE CategoryName=? AND Description=?');
    foreach ($copy['categories'] as $name=>$description) {
        $category->execute([$description,$name,'Presentation career family: '.$name.'. Fictional demo events; this is not a statistical ranking.']);
        $changed['categories'] += $category->rowCount();
    }
    $events = $pdo->query("SELECT DISTINCT e.EventID,e.EventName,e.Description,e.Hashtags FROM EVENTS e JOIN DEMO_FIXTURES f ON f.EntityID=e.EventID AND f.EntityType='event' WHERE f.FixtureKey REGEXP '^event[0-9]+$' FOR UPDATE")->fetchAll();
    $updateEvent=$pdo->prepare('UPDATE EVENTS SET Description=?,Hashtags=? WHERE EventID=?');
    foreach ($events as $event) {
        $description=$event['Description'];
        if (str_starts_with((string)$description,'Fictional presentation event. Explore ') && isset($copy['events'][$event['EventName']])) {
            $description=$copy['events'][$event['EventName']];
        }
        $tags=preg_replace('/(?<!\S)#EventNestDemo\b/', '', (string)$event['Hashtags']);
        $tags=trim(preg_replace('/\s+/', ' ', $tags));
        if ($description!==$event['Description'] || $tags!==(string)$event['Hashtags']) {
            $updateEvent->execute([$description,$tags ?: null,$event['EventID']]);
            $changed['events'] += $updateEvent->rowCount();
        }
    }
    $posts=$pdo->query("SELECT DISTINCT p.PostID,p.Content,p.Hashtags FROM POSTS p JOIN DEMO_FIXTURES f ON f.EntityID=p.PostID AND f.EntityType='post' FOR UPDATE")->fetchAll();
    $updatePost=$pdo->prepare('UPDATE POSTS SET Content=?,Hashtags=? WHERE PostID=?');
    foreach ($posts as $post) {
        $content=strtr((string)$post['Content'],$copy['phrases']);
        $tags=preg_replace('/(?<!\S)#EventNestDemo\b/','#CareerGrowth',(string)$post['Hashtags']);
        if ($content!==(string)$post['Content'] || $tags!==(string)$post['Hashtags']) {
            $updatePost->execute([$content,$tags,$post['PostID']]);
            $changed['posts'] += $updatePost->rowCount();
        }
    }
    $pdo->commit();
    echo json_encode($changed,JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR,$error->getMessage().PHP_EOL); exit(1);
}
