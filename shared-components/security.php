<?php
declare(strict_types=1);

function eventnest_audit(string $event, ?int $actor=null, string $outcome='observed'): void {
    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) return;
    try {
        $pdo->prepare('INSERT INTO SECURITY_AUDIT (ActorID,EventName,Outcome) VALUES (?,?,?)')->execute([$actor,substr($event,0,80),substr($outcome,0,40)]);
    } catch (Throwable $e) { error_log('EventNest audit storage unavailable.'); }
}

function eventnest_rate_limit(string $bucket, int $limit, int $seconds): void {
    if (PHP_SAPI==='cli') return;
    // Shared across sessions; do not trust forwarded headers from arbitrary clients.
    $ip=(string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $dir=dirname(__DIR__).'/database-config/runtime/limits';
    if (!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir)) {
        http_response_code(503); exit('Request protection unavailable. Please try later.');
    }
    // A bounded set of per-action files; expired windows are pruned under an exclusive lock.
    $file=@fopen($dir.'/'.hash('sha256',$bucket).'.json','c+');
    if (!$file || !flock($file,LOCK_EX)) { http_response_code(503); exit('Please try later.'); }
    $data=json_decode(stream_get_contents($file),true); $data=is_array($data)?$data:[]; $now=time();
    foreach($data as $key=>$row) if (($row['until']??0)<=$now) unset($data[$key]);
    $key=hash('sha256',$ip); $row=$data[$key]??['until'=>$now+$seconds,'count'=>0];
    $blocked=$row['count'] >= $limit; $row['count']++; $data[$key]=$row;
    rewind($file); ftruncate($file,0); fwrite($file,json_encode($data)); fflush($file); flock($file,LOCK_UN); fclose($file);
    if ($blocked) {
        http_response_code(429); header('Retry-After: '.max(1,$row['until']-$now));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error'=>'Too many requests. Please try again shortly.','suggestions'=>[]]); exit;
    }
}

function eventnest_require_email(array $user): void {
    if (empty($user['Is_email_verified'])) {
        eventnest_audit('email_verification_required',(int)$user['UserID'],'denied');
        http_response_code(403);
        $message='Verify your email address before using this sensitive action.';
        if(basename($_SERVER['SCRIPT_NAME']??'')==='reaction.php') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'message'=>$message]); exit;
        }
        exit($message);
    }
}

if (PHP_SAPI!=='cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Frame-Options: DENY');
    // Existing inline styles/handlers are retained; no third-party executable scripts.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self' https://www.payfast.co.za https://sandbox.payfast.co.za");
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || parse_url((string)getenv('EVENTNEST_BASE_URL'),PHP_URL_SCHEME)==='https') header('Strict-Transport-Security: max-age=31536000');
    $route=basename($_SERVER['SCRIPT_NAME']??'');
    $suggest=$route==='search-results.php' && in_array($_GET['mode']??null,['suggest','suggestions'],true);
    if ($suggest) eventnest_rate_limit('suggestions',120,60);
    if ($route==='payment-status.php') eventnest_rate_limit('payment-poll',120,60);
    if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
        $limits=['login.php'=>[15,300],'register.php'=>[8,600],'forgot-password.php'=>[6,600],'reset-password.php'=>[10,600],'profile.php'=>[10,300],'settings.php'=>[15,300],'reaction.php'=>[60,60],'review.php'=>[10,300],'booking.php'=>[20,300],'contact.php'=>[5,300],'report-event.php'=>[5,300]];
        [$cap,$window]=$limits[$route]??[40,300];
        eventnest_rate_limit('post:'.$route,$cap,$window);
        register_shutdown_function(static function() use($route) {
            eventnest_audit('request:'.$route,isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null,'http_'.http_response_code());
        });
    }
}
