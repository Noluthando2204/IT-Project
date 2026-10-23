<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {
    http_response_code(403);
    exit('Command-line access only.');
}
require_once dirname(__DIR__).'/database-config/connection.php';
require_once dirname(__DIR__).'/shared-components/functions.php';
try {
    echo eventnest_expire_pending(eventnest_db())." pending booking(s) expired.\n";
}
catch(Throwable $exception) {
    error_log('EventNest pending cleanup failed: '.get_class($exception));
    exit(1);
}
