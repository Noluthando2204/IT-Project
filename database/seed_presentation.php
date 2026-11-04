<?php
declare(strict_types=1);
// Explicitly opt-in, presentation-only fixtures. No registration/mail/payment APIs.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/database-config/connection.php';
require_once dirname(__DIR__) . '/shared-components/functions.php';
$copy = require __DIR__ . '/presentation_copy.php';
$mode = $argv[1] ?? '';
if (!in_array($mode, ['--apply', '--reactions', '--remove-reactions'], true)) {
    exit("Usage: php database/seed_presentation.php --apply|--reactions|--remove-reactions\nApply migration_presentation.sql first; back up your database.\n");
}
$start = microtime(true);
function query(string $sql, array $args = []): PDOStatement {
    global $pdo; $s=$pdo->prepare($sql); $s->execute($args); return $s;
}
function fixture(string $key): int { return (int) query('SELECT EntityID FROM DEMO_FIXTURES WHERE FixtureKey=?',[$key])->fetchColumn(); }
function remember(string $key, string $type, int $id): int {
    query('INSERT INTO DEMO_FIXTURES (FixtureKey,EntityType,EntityID) VALUES (?,?,?)',[$key,$type,$id]); return $id;
}
function asset(string $file): ?string {
    $path='uploaded-content/profile-pictures/'.$file;
    $info=@getimagesize(dirname(__DIR__).'/'.$path);
    if (!$info || $info['mime']!=='image/png') { fwrite(STDERR,"Missing/invalid PNG: $file\n"); return null; }
    return $path;
}
// New named fixtures only. Existing credentials, IDs and user details stay intact.
function person(string $key, string $first, string $last, string $email, string $field, ?string $company=null): int {
    global $pdo;
    if ($id=fixture($key)) return $id;
    $existing=query('SELECT UserID,Role,OrganisationName FROM USERS WHERE Email=?',[$email])->fetch();
    if ($existing) {
        if ($existing['Role']!==($company?'organiser':'attendee') || ($company && $existing['OrganisationName']!==$company)) throw new RuntimeException('Fixture identity collision: '.$email);
        return remember($key,'user',(int)$existing['UserID']);
    }
    query("INSERT INTO USERS (FirstName,LastName,Email,PasswordHash,CareerField,Role,Is_email_verified,OrganisationName,VerificationStatus,VerificationFeePaidAt,VerificationFeePaidUntil,VerificationFeePaymentStatus,CreatedAt)
        VALUES (?,?,?,?,?,?,1,?,?,?, ?,?, '2026-10-01 09:00:00')",
        [$first,$last,$email,password_hash('EventNestDemo!2026',PASSWORD_DEFAULT),$field,$company?'organiser':'attendee',$company,$company?'verified':'unverified',$company?'2026-11-01 09:00:00':null,$company?'2027-11-01 09:00:00':null,$company?'paid':'unpaid']);
    return remember($key,'user',(int)$pdo->lastInsertId());
}
$pdo->beginTransaction();
try {
if ($mode==='--apply') {
    $families=['Finance & Accounting','Healthcare & Medical','Engineering','ICT & Digital Technology','Skilled Trades & Technical Careers','Design & Creative Careers'];
    $categories=[];
    foreach($families as $name) {
        query('INSERT IGNORE INTO CATEGORIES (CategoryName,Description) VALUES (?,?)',[$name,$copy['categories'][$name]]);
        $categories[]= (int) query('SELECT CategoryID FROM CATEGORIES WHERE CategoryName=?',[$name])->fetchColumn();
    }
    $roster=[
        ['FinBridge Advisory Group','Lerato','Nkosi','lerato.finbridge@example.com','finbridge_advisory_group_b.png'],
        ['Ubuntu Health Careers Network','Thabiso','Molefe','thabiso.ubuntuhealth@example.com','ubuntu_health_careers_network_b.png'],
        ['Mzansi Engineering Works','Zinhle','Mthembu','zinhle.mzansieng@example.com','mzansi_engineering_works_b.png'],
        ['CloudStep Careers','Naledi','Mokoena','naledi.organiser01@example.com','cloudstep_careers_b.png'],
        ['ProTrade Skills Institute','Kabelo','Maseko','kabelo.protrade@example.com','protrade_skills_institute_b.png'],
        ['DesignForward Collective','Mpho','Dlamini','mpho.organiser02@example.com','designforward_collective_b.png'],
    ];
    $owners=[];
    foreach($roster as $i=>[$company,$first,$last,$email,$logo]) {
        // Preserve established company IDs/emails when the company already exists.
        $found=query("SELECT UserID FROM USERS WHERE OrganisationName=? AND Role='organiser'",[$company])->fetchAll(PDO::FETCH_COLUMN);
        if(count($found)>1) throw new RuntimeException('Ambiguous company identity: '.$company);
        $id=$found?(int)$found[0]:person('company'.$i,$first,$last,$email,$families[$i],$company);
        $owners[]=$id;
        if(!fixture('logo'.$i)) {
            if($path=asset($logo)) query('UPDATE USERS SET ProfileImagePath=? WHERE UserID=? AND ProfileImagePath IS NULL',[$path,$id]);
            remember('logo'.$i,'user',$id);
        }
        if(!fixture('coverage'.$i)) {
            // Only six expressly authorised fictional companies; never normal registrations.
            query("UPDATE USERS SET VerificationFeePaidAt='2026-11-01 09:00:00',VerificationFeePaidUntil='2027-11-01 09:00:00',VerificationFeePaymentStatus='paid' WHERE UserID=? AND (VerificationFeePaidUntil IS NULL OR VerificationFeePaidUntil<'2026-12-13')",[$id]);
            remember('coverage'.$i,'user',$id);
        }
    }
    $people=[['Amina','Patel','amina.finance@example.com',0],['Palesa','Molefe','palesa.health@example.com',1],['Jason','Naidoo','jason.engineering@example.com',2],['Zanele','Dube','zanele.design@example.com',5],['Sipho','Mthembu','sipho.trades@example.com',4],['Neo','Radebe','neo.lowbalance@example.com',3]];
    foreach($people as $i=>[$first,$last,$email,$field]) {
        $id=person('attendee'.$i,$first,$last,$email,$families[$field]);
        if($first==='Neo' && !fixture('neo-balance')) {
            query('UPDATE USERS SET SandboxBalance=10.00 WHERE UserID=? AND SandboxBalance IS NULL',[$id]);remember('neo-balance','user',$id);
        }
    }
    $events=[
        [0,'Chartered Accountancy Career Lab','2026-11-18 10:35:00','physical','Johannesburg',['Student'=>40,'General'=>90]],
        [0,'Financial Analyst Pathways Forum','2026-12-03 14:20:00','hybrid','Sandton',['General'=>120]],
        [1,'Allied Health Careers Expo','2026-11-20 09:40:00','physical','Pretoria',['General'=>0]],
        [1,'Healthcare Graduate Pathways','2026-12-05 12:15:00','online','Online',['Student'=>0,'General'=>50]],
        [2,'Engineering Graduate Launchpad','2026-11-23 11:10:00','physical','Midrand',['General'=>100]],
        [2,'Renewable Energy Engineering Workshop','2026-12-08 15:25:00','hybrid','Centurion',['Student'=>75,'General'=>150]],
        [3,'Cloud Skills Workshop','2026-11-26 10:20:00','online','Online',['General'=>0]],
        [3,'Cybersecurity Careers Bootcamp','2026-12-10 13:50:00','hybrid','Johannesburg',['Student'=>60,'General'=>120]],
        [4,'Artisan Careers Open Day','2026-11-28 08:45:00','physical','Pretoria',['General'=>0]],
        [4,'Electrical & Mechatronics Apprenticeship Fair','2026-12-12 11:35:00','physical','Johannesburg',['General'=>0]],
        [5,'Creative Design Lab','2026-11-21 11:15:00','physical','Braamfontein Creative Hub',['Student'=>50,'General'=>100]],
        [5,'UX & Digital Product Careers Evening','2026-12-04 17:40:00','hybrid','Johannesburg',['General'=>80]],
    ];
    $eventIDs=[];
    foreach($events as $i=>[$owner,$name,$date,$format,$venue,$tiers]) {
        $key='event'.$i;
        if($id=fixture($key)) { $eventIDs[]=$id; continue; }
        $existing=query('SELECT EventID FROM EVENTS WHERE OrganiserID=? AND EventName=?',[$owners[$owner],$name])->fetchAll(PDO::FETCH_COLUMN);
        if($existing) {
            // Only pre-existing named seed fixtures may be rescheduled, after dependency audit.
            $expected=['Cloud Skills Workshop'=>2,'Creative Design Lab'=>3];
            if(count($existing)!==1 || ($expected[$name] ?? 0)!==(int)$existing[0]) throw new RuntimeException('Unmanaged event collision: '.$name);
            $id=(int)$existing[0];
            query('UPDATE EVENTS SET EventDate=?,CategoryID=? WHERE EventID=?',[$date,$categories[$owner],$id]);
            // Preserve every existing tier/price/capacity/quantity and all booking history.
        } else {
            query("INSERT INTO EVENTS (OrganiserID,CategoryID,EventName,Description,Venue,EventDate,Status,Format,Province,Hashtags) VALUES (?,?,?,?,?,?,'published',?,'Gauteng',?)",[$owners[$owner],$categories[$owner],$name,$copy['events'][$name],$venue,$date,$format,'#CareerPathways']);
            $id=(int)$pdo->lastInsertId();
            foreach($tiers as $type=>$price) query('INSERT INTO TICKETS (EventID,TicketType,Price,QuantityAvailable,Capacity,Description) VALUES (?,?,?,80,80,?)',[$id,$type,$price,$type.' access to '.$name.'.']);
        }
        remember($key,'event',$id);$eventIDs[]=$id;
    }
    if(!fixture('summit-rescheduled')) {
        $id=(int)query("SELECT EventID FROM EVENTS WHERE EventID=1 AND EventName='Graduate Career Summit' AND OrganiserID=? AND Status='published'",[$owners[3]])->fetchColumn();
        if($id) { query("UPDATE EVENTS SET EventDate='2026-11-19 10:45:00' WHERE EventID=?",[$id]); remember('summit-rescheduled','event',$id); }
    }
    foreach([['Graduate Career Summit','graduate_summit_b.png'],['FutureTech Connect 2026','futuretech_b.png'],['Creative Design Lab','creative_lab_b.png']] as [$name,$file]) {
        if(!fixture('brand-'.$file)) {
            $id=(int)query('SELECT EventID FROM EVENTS WHERE EventName=? AND OrganiserID IN (?,?)',[$name,$owners[3],$owners[5]])->fetchColumn();
            if($id && ($path=asset($file))) {query('UPDATE EVENTS SET ImagePath=? WHERE EventID=?',[$path,$id]);remember('brand-'.$file,'event',$id);}
        }
    }
    // Only known local photos are reused; unavailable requested clips/photos remain text.
    $posts=[
      [0,0,'03 09:18','Bring your questions to our accounting career lab: practise an audit case, compare CA training routes and build an application checklist. #Accounting',null],
      [0,null,'05 16:42','Three graduate finance skills: explain a spreadsheet clearly, check assumptions critically and communicate an ethical recommendation. #FinanceSkills',null],
      [0,1,'09 11:37','Meet our analyst mentor, Keneilwe: learn how cash-flow models and clear storytelling turn numbers into decisions. Bring one question for the forum. #FinancialAnalysis',null],
      [1,null,'02 13:26','Healthcare extends beyond doctors and nurses. Explore occupational therapy, radiography, physiotherapy and health-service coordination. Which pathway interests you? #AlliedHealth',null],
      [1,2,'06 10:47','Our careers expo preview: compare allied-health study routes, explore workplace scenarios and meet career mentors. Bring your study questions. #HealthcareCareers',null],
      [1,3,'10 15:13','Healthcare Graduate Pathways welcomes speaker Dr Boitumelo Diale for a discussion of supervised practice, teamwork and graduate applications. #GraduatePathways',null],
      [2,4,'03 08:52','Prepare for Graduate Launchpad: bring a project sketch and a curious question. We will connect engineering fundamentals to real design constraints. #Engineering',null],
      [2,null,'05 14:38','A day in engineering means reviewing a design, testing assumptions, discussing safety and documenting what changed. Start your learning log today. #EngineeringLife',null],
      [2,5,'09 12:21','Preview our renewable-energy workshop: explore inverter sizing, solar-system diagrams and safe equipment planning in practical exercises. #RenewableEnergy',null],
      [3,null,'02 17:06','Cloud careers in 60 seconds: support keeps services running, engineers automate infrastructure and security specialists protect access. Pick a pathway and build one small project. #CloudCareers',null],
      [3,6,'05 09:33','Our Cloud Skills Workshop speaker spotlight: practise deployment fundamentals, ask about entry-level cloud roles and bring your learning goals. #CloudSkills','cloud-nithen-naidoo-speaker.jpeg'],
      [3,7,'10 13:44','Cybersecurity bootcamp preview: practise threat modelling, discuss responsible disclosure and map the first steps towards a security career. #Cybersecurity',null],
      [4,null,'03 10:11','Skilled artisans keep communities working. Electricians, fitters and mechatronics technicians combine careful practice with problem solving. Explore apprenticeships with us. #SkilledTrades',null],
      [4,8,'06 16:24','Workshop tour preview: follow an apprentice through tool checks, supervised practice and a daily learning review. Ask about entry routes at our open day. #ArtisanCareers',null],
      [4,9,'10 09:57','Our apprenticeship fair includes a guided mechatronics demonstration: trace a sensor signal, discuss electrical safety and compare training pathways. #Mechatronics',null],
      [5,null,'02 15:29','Portfolio tips: show the problem, explain your process and reflect on what you changed after feedback. Three thoughtful case studies can tell a strong story. #PortfolioTips',null],
      [5,10,'06 12:43','Preparing for Creative Design Lab? Bring a sketchbook, one portfolio question and a willingness to iterate with others. Let us explore design together. #CreativeCareers','creative-design-lab-prep.jpg'],
      [5,11,'10 17:17','Meet UX speaker Lindiwe: discover how interviews, accessible prototypes and product decisions connect at our careers evening. Bring a design question. #UXCareers',null],
    ];
    foreach($posts as $i=>[$owner,$event,$date,$content,$file]) {
        if(fixture('post'.$i)) continue;
        $media=$file && is_file(dirname(__DIR__).'/uploaded-content/posts/'.$file)?'uploaded-content/posts/'.$file:null;
        query('INSERT INTO POSTS (OrganiserID,EventID,Content,Media,Media_type,Hashtags,PostDate) VALUES (?,?,?,?,?,?,?)',[$owners[$owner],$event===null?null:$eventIDs[$event],strtr($content,$copy['phrases']),$media,$media?'image':null,'#CareerGrowth #'.str_replace([' ','&'], '',$families[$owner]),'2026-11-'.$date.':00']);
        remember('post'.$i,'post',(int)$pdo->lastInsertId());
    }
} elseif($mode==='--reactions') {
    // NULL password disables login; these users cannot receive real email and are not registered through HTTP.
    for($batch=0;$batch<5500;$batch+=250) {
        $rows=[];$args=[];
        for($i=$batch+1;$i<=min($batch+250,5500);$i++) {
            $key='reactor'.sprintf('%06d',$i); if(fixture($key)) continue;
            $email='demo.'.$key.'@example.com';
            if(query('SELECT 1 FROM USERS WHERE Email=?',[$email])->fetchColumn()) throw new RuntimeException('Unmanaged reactor email collision: '.$email);
            $rows[]="('Demo','Reactor',?,NULL,'attendee','active',0,'2026-11-01 08:00:00')";$args[]=$email;
        }
        if($rows) {
            query('INSERT INTO USERS (FirstName,LastName,Email,PasswordHash,Role,UserStatus,Is_email_verified,CreatedAt) VALUES '.implode(',',$rows),$args);
            $records=query("SELECT UserID,Email FROM USERS WHERE Email IN (".implode(',',array_fill(0,count($args),'?')).")",$args)->fetchAll();
            $marks=[];$values=[];
            foreach($records as $r) {$marks[]="(?,'reactor',?)";array_push($values,substr(explode('@',$r['Email'])[0],5),(int)$r['UserID']);}
            query('INSERT INTO DEMO_FIXTURES (FixtureKey,EntityType,EntityID) VALUES '.implode(',',$marks),$values);
        }
    }
    $ids=query("SELECT EntityID FROM DEMO_FIXTURES WHERE EntityType='reactor' ORDER BY FixtureKey")->fetchAll(PDO::FETCH_COLUMN);
    $targets=[0=>[1200,1600,847],3=>[934,1400,2000],6=>[1700,1100,1300],9=>[2000,1800,1500],12=>[1300,1900,1100],15=>[1600,1200,1800]];
    foreach($targets as $post=>$counts) {
        $postID=fixture('post'.$post);if(!$postID) throw new RuntimeException('Run --apply first.');
        $eligible=query("SELECT p.PostID FROM POSTS p JOIN USERS u ON u.UserID=p.OrganiserID
            LEFT JOIN EVENTS e ON e.EventID=p.EventID AND e.OrganiserID=p.OrganiserID
            WHERE p.PostID=? AND u.Role='organiser' AND u.UserStatus='active'
            AND u.VerificationStatus='verified' AND (p.EventID IS NULL OR e.Status='published')",[$postID])->fetchColumn();
        if(!$eligible) throw new RuntimeException('Featured fixture post is no longer public: '.$postID);
        $offset=0;
        foreach($counts as $kind=>$count) {
            $type=['thumbs_up','clap','lightbulb'][$kind];
            foreach(array_chunk(array_slice($ids,$offset,$count),250) as $chunk) {
                $marks=[];$values=[];foreach($chunk as $id) {$marks[]="(?,?,?,'2026-11-11 08:15:00')";array_push($values,$postID,$id,$type);}
                query('INSERT IGNORE INTO REACTIONS (PostID,UserID,ReactionType,ReactionDate) VALUES '.implode(',',$marks),$values);
            }
            $offset+=$count;
        }
    }
} else {
    // Rollback is restricted to registry-owned synthetic identities. Other FK references
    // abort the transaction rather than cascade into somebody's bookings or posts.
    query("DELETE r FROM REACTIONS r JOIN DEMO_FIXTURES f ON f.EntityID=r.UserID AND f.EntityType='reactor'");
    query("DELETE u FROM USERS u JOIN DEMO_FIXTURES f ON f.EntityID=u.UserID AND f.EntityType='reactor' WHERE u.PasswordHash IS NULL AND u.Email LIKE 'demo.reactor%@example.com'");
    query("DELETE FROM DEMO_FIXTURES WHERE EntityType='reactor'");
}
$pdo->commit();
echo $mode.' completed in '.number_format(microtime(true)-$start,3)." seconds\n";
} catch(Throwable $e) {if($pdo->inTransaction()) $pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
