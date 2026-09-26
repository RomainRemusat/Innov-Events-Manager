-- Ajoute le pseudo des comptes sans en inventer pour les utilisateurs existants.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @username_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'username'),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN username VARCHAR(20) NULL AFTER lastname'
);
PREPARE username_stmt FROM @username_ddl;
EXECUTE username_stmt;
DEALLOCATE PREPARE username_stmt;

SET @username_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'username' AND NON_UNIQUE = 0),
    'SELECT 1',
    'ALTER TABLE users ADD UNIQUE INDEX uq_users_username (username)'
);
PREPARE username_stmt FROM @username_ddl;
EXECUTE username_stmt;
DEALLOCATE PREPARE username_stmt;
