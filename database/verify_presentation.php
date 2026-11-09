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
verify('Synthetic login disabled',(int)$pdo->query("SELECT COUNT(*) FROM USERS u JOIN DEMO_FIXTURES f ON f.EntityID=u.UserID AND f.EntityType='reactor' WHERE PasswordHash IS NULL AND Role='attendee' AND Is_email_verified=0")->fetchColumn()===(int)$pdo->query("SELECT COUNT(*) FROM DEMO_FIXTURES WHERE EntityType='reactor'")->fetchColumn());
verify('Synthetic reactor pool trimmed',(int)$pdo->query("SELECT COUNT(*) FROM DEMO_FIXTURES WHERE EntityType='reactor'")->fetchColumn()<=60);
$totals=$pdo->query("SELECT p.PostID,COUNT(r.UserID) AS Total FROM POSTS p LEFT JOIN REACTIONS r ON r.PostID=p.PostID WHERE p.PostDate<'2026-11-11' GROUP BY p.PostID")->fetchAll(PDO::FETCH_KEY_PAIR);
verify('Believable reaction totals on seeded posts',$totals!==[] && max($totals)<=80 && min($totals)>=5);
verify('No duplicate reactions',(int)$pdo->query('SELECT COUNT(*) FROM (SELECT UserID,PostID FROM REACTIONS GROUP BY UserID,PostID HAVING COUNT(*)>1) x')->fetchColumn()===0);
$neo=$pdo->query("SELECT * FROM USERS WHERE Email='neo.lowbalance@example.com'")->fetch();
verify('Neo R10 sandbox guard',eventnest_demo_insufficient_funds($pdo,$neo,40));
putenv('PAYFAST_MODE=live');verify('Production bypasses demo guard',!eventnest_demo_insufficient_funds($pdo,$neo,40));

// Additional readiness assertions retain all historical checks above.
$copy=require __DIR__.'/presentation_copy.php';
$categories=$pdo->query('SELECT CategoryName,Description FROM CATEGORIES')->fetchAll(PDO::FETCH_KEY_PAIR);
foreach (['Career Development','Cloud Computing','Dermatology','Leadership','Networking','Security','Technology'] as $name) {
    verify('Distinct career description: '.$name,trim((string)($categories[$name]??''))!=='');
}
verify('Seven distinct descriptions',count(array_unique(array_intersect_key($categories,array_flip(['Career Development','Cloud Computing','Dermatology','Leadership','Networking','Security','Technology']))))===7);
foreach ($events as $event) {
    verify('Relevant fixture tags: '.$event['EventName'],($copy['event_tags'][$event['EventName']]??null)===$event['Hashtags']);
}
verify('All designated posts have three reaction types',(int)$pdo->query("SELECT COUNT(*) FROM (SELECT p.PostID FROM POSTS p JOIN DEMO_FIXTURES f ON f.EntityID=p.PostID AND f.EntityType='post' JOIN REACTIONS r ON r.PostID=p.PostID GROUP BY p.PostID HAVING COUNT(DISTINCT r.ReactionType)=3) x")->fetchColumn()===18);
verify('Company logos cannot render as event/category artwork',str_ends_with(eventnest_image_url('uploaded-content/profile-pictures/cloudstep_careers_b.png'),'images/event-placeholder.svg') && str_ends_with(eventnest_image_url('uploaded-content/profile-pictures/cloudstep_careers_b.png','category'),'images/category-placeholder.svg'));
foreach (['graduate_summit_b.png', 'futuretech_b.png', 'creative_lab_b.png'] as $file) {
    verify('Profile-directory artwork rejected: '.$file, str_ends_with(eventnest_image_url('uploaded-content/profile-pictures/'.$file), 'images/event-placeholder.svg'));
}
$event=$pdo->query("SELECT e.*,u.VerificationStatus FROM EVENTS e JOIN DEMO_FIXTURES f ON f.EntityID=e.EventID AND f.FixtureKey='review-event-20261105' JOIN USERS u ON u.UserID=e.OrganiserID")->fetch();
verify('5 November completed review event',$event && $event['EventDate']==='2026-11-05 14:00:00' && $event['Status']==='completed' && $event['VerificationStatus']==='verified' && strtotime($event['EventDate'])<time());
$stmt=$pdo->prepare("SELECT b.*,u.Role FROM BOOKINGS b JOIN USERS u ON u.UserID=b.UserID JOIN TICKETS t ON t.TicketID=b.TicketID AND t.EventID=b.EventID WHERE b.EventID=?");
$stmt->execute([$event['EventID']]);$bookings=$stmt->fetchAll();
verify('Exactly two valid attendee bookings',count($bookings)===2 && count(array_unique(array_column($bookings,'UserID')))===2 && count(array_filter($bookings,fn($b)=>$b['Status']==='paid' && $b['Role']==='attendee' && (int)$b['Quantity']===1 && (float)$b['TotalAmount']===0.0))===2);
$stmt=$pdo->prepare('SELECT BookingID FROM REVIEWS WHERE EventID=?');$stmt->execute([$event['EventID']]);
$reviewed=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
// Readiness is opt-in; normal deployments must also accept a legitimately completed demo.
if (in_array('--require-unreviewed',$argv,true)) {
    verify('Both demo bookings initially unreviewed',$reviewed===[]);
}
require_once dirname(__DIR__).'/shared-components/notifications.php';
foreach($bookings as $booking) {
    $notices=eventnest_collect_notices($pdo,(int)$booking['UserID']);
    $expected=in_array((int)$booking['BookingID'],$reviewed,true)?0:1;
    verify('Own reminder matches review state '.$booking['BookingReference'],count(array_filter($notices,fn($n)=>$n['url']==='leave-review.php?booking_id='.$booking['BookingID'] && ($n['action']??'')==='Rate your experience'))===$expected);
}
