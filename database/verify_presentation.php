<?php
declare(strict_types=1);
// Read-only acceptance checks. Run after the opt-in presentation seed.
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once dirname(__DIR__).'/database-config/connection.php';
require_once dirname(__DIR__).'/shared-components/functions.php';
function verify(string $name,bool $ok): void {echo ($ok?'PASS ':'FAIL ').$name."\n";if(!$ok) exit(1);}
verify('Compact counts',array_map('eventnest_compact_count',[847,1200,1600,2000,5300])===['847','1.2k','1.6k','2k','5.3k']);
$posts=$pdo->query("SELECT p.* FROM POSTS p JOIN DEMO_FIXTURES f ON f.EntityID=p.PostID AND f.EntityType='post'")->fetchAll();
verify('18 designated posts',count($posts)===18);
verify('Six general posts',count(array_filter($posts,fn($p)=>$p['EventID']===null))===6);
verify('Post dates November 2–10',count(array_filter($posts,fn($p)=>$p['PostDate']<'2026-11-02' || $p['PostDate']>='2026-11-11'))===0);
$events=$pdo->query("SELECT e.* FROM EVENTS e JOIN DEMO_FIXTURES f ON f.EntityID=e.EventID WHERE f.FixtureKey REGEXP '^event[0-9]+$'")->fetchAll();
verify('12 future published events',count($events)===12 && count(array_filter($events,fn($e)=>$e['Status']!=='published' || $e['EventDate']<='2026-11-11 23:59:59'))===0);
verify('Summit rescheduled',(string)$pdo->query("SELECT EventDate FROM EVENTS WHERE EventID=1")->fetchColumn()==='2026-11-19 10:45:00');
verify('Canceled booking fixture preserved',(string)$pdo->query("SELECT Status FROM BOOKINGS WHERE BookingReference='EN-BN-0001'")->fetchColumn()==='canceled');
verify('Completed and canceled events preserved',(int)$pdo->query("SELECT COUNT(*) FROM EVENTS WHERE (EventID=4 AND Status='completed' AND EventDate='2026-09-20 18:00:00') OR (EventID=5 AND Status='canceled' AND EventDate='2026-10-28 09:00:00')")->fetchColumn()===2);
verify('Synthetic login disabled',(int)$pdo->query("SELECT COUNT(*) FROM USERS u JOIN DEMO_FIXTURES f ON f.EntityID=u.UserID AND f.EntityType='reactor' WHERE PasswordHash IS NULL AND Role='attendee' AND Is_email_verified=0")->fetchColumn()===5500);
$targets=[0=>[1200,1600,847],3=>[934,1400,2000],6=>[1700,1100,1300],9=>[2000,1800,1500],12=>[1300,1900,1100],15=>[1600,1200,1800]];
foreach($targets as $post=>$expected) {
    $stmt=$pdo->prepare("SELECT r.ReactionType,COUNT(*) AS Total FROM REACTIONS r JOIN DEMO_FIXTURES f ON f.EntityID=r.PostID AND f.EntityType='post' WHERE f.FixtureKey=? GROUP BY r.ReactionType");$stmt->execute(['post'.$post]);$actual=$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    verify('Featured reaction counts post'.$post,array_map(fn($t)=>(int)($actual[$t]??0),['thumbs_up','clap','lightbulb'])===$expected);
}
verify('No duplicate reactions',(int)$pdo->query('SELECT COUNT(*) FROM (SELECT UserID,PostID FROM REACTIONS GROUP BY UserID,PostID HAVING COUNT(*)>1) x')->fetchColumn()===0);
$neo=$pdo->query("SELECT * FROM USERS WHERE Email='neo.lowbalance@example.com'")->fetch();
verify('Neo R10 sandbox guard',eventnest_demo_insufficient_funds($pdo,$neo,40));
putenv('PAYFAST_MODE=live');verify('Production bypasses demo guard',!eventnest_demo_insufficient_funds($pdo,$neo,40));
