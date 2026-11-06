<?php
declare(strict_types=1);

// Narrow, repeatable migration for existing MariaDB / MySQL databases. No row edits.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__).'/database-config/connection.php';
try {
    $stmt=$pdo->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_reviews_content_by_rating'");
    $clause=$stmt->fetchColumn();
    if(!is_string($clause)) {
        throw new RuntimeException('Expected review content constraint was not found; no changes applied.');
    }
    $normal=strtolower(preg_replace('/[\\s`]+/', '', $clause));
    if(!str_contains($normal,'tagsisnotnull')) {
        echo "Review content constraint already permits optional compliments; no changes needed.\n";
        exit(0);
    }
    if(!in_array('--apply',$argv,true)) {
        echo "Required: permit NULL Tags for 4–5 stars, retaining required low-rating comments. Run this script with --apply. No data will be changed.\n";
        exit(0);
    }
    $maria=stripos((string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION),'MariaDB')!==false;
    $drop=$maria?'DROP CONSTRAINT':'DROP CHECK';
    // One ALTER replaces the check; rating, contact, foreign and unique keys remain.
    $pdo->exec("ALTER TABLE REVIEWS $drop chk_reviews_content_by_rating,
        ADD CONSTRAINT chk_reviews_content_by_rating CHECK (
            (Rating >= 4 AND Comment IS NULL)
            OR (Rating < 4 AND Comment IS NOT NULL AND CHAR_LENGTH(TRIM(Comment)) > 0 AND Tags IS NULL)
        )");
    echo "Updated review content constraint. Existing reviews and bookings preserved.\n";
} catch(Throwable $error) {
    fwrite(STDERR,"Review constraint migration failed: ".$error->getMessage()."\n");
    exit(1);
}
