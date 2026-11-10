<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/database-config/connection.php';

if (!in_array('--apply', $argv, true)) {
    echo "Use --apply after backing up the target database. Schema changes only.\n";
    exit;
}

try {
    $column = static function (string $table, string $name, string $definition) use ($pdo): void {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $statement->execute([$table, $name]);
        if (!$statement->fetchColumn()) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $name $definition");
        }
    };
    $column('EVENTS', 'City', 'VARCHAR(100) NULL');
    $column('POSTS', 'EditedAt', 'DATETIME NULL');
    $column('CATEGORIES', 'IsVisible', 'TINYINT NOT NULL DEFAULT 1');
    $pdo->exec("CREATE TABLE IF NOT EXISTS SECURITY_AUDIT (AuditID BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ActorID INT UNSIGNED NULL,EventName VARCHAR(80) NOT NULL,Outcome VARCHAR(40) NOT NULL,CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $column('SECURITY_AUDIT', 'Summary', 'TEXT NULL');
    $pdo->exec("CREATE TABLE IF NOT EXISTS ACCOUNT_TOKENS (TokenHash CHAR(64) PRIMARY KEY,UserID INT UNSIGNED NOT NULL,Purpose VARCHAR(30) NOT NULL,Payload VARCHAR(255) NULL,PasswordMarker CHAR(64) NOT NULL,ExpiresAt DATETIME NOT NULL,CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY(UserID,Purpose),FOREIGN KEY(UserID) REFERENCES USERS(UserID) ON DELETE CASCADE) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS PUBLIC_COPY (CopyKey VARCHAR(80) PRIMARY KEY,Content TEXT NOT NULL) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS LOGIN_FAILURES (AccountKey CHAR(64) PRIMARY KEY,Attempts INT NOT NULL DEFAULT 0,WindowStart DATETIME NOT NULL,AlertAt DATETIME NULL) ENGINE=InnoDB");
    echo "Schema migration complete. No existing records updated or deleted.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Schema migration failed; verify database availability and ALTER/CREATE permissions before retrying.\n");
    exit(1);
}
