-- Forward-only, repeatable MariaDB migration. Never reset a populated database.
ALTER TABLE USERS ADD COLUMN IF NOT EXISTS ProfileImagePath VARCHAR(255) NULL;
ALTER TABLE USERS ADD COLUMN IF NOT EXISTS SandboxBalance DECIMAL(10,2) UNSIGNED NULL;
ALTER TABLE POSTS MODIFY COLUMN EventID INT UNSIGNED NULL;
-- Tracks ownership of presentation fixtures without repurposing business fields.
CREATE TABLE IF NOT EXISTS DEMO_FIXTURES (
    FixtureKey VARCHAR(100) NOT NULL PRIMARY KEY,
    EntityType VARCHAR(20) NOT NULL,
    EntityID INT UNSIGNED NOT NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
