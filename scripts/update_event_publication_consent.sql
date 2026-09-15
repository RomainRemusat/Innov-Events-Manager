-- Confirmation explicite de l'accord client, distincte de la visibilité demandée.
-- Aucun accord n'est déduit des anciennes valeurs de is_published.
-- Appliquer avant le code : les événements historiques devront être reconfirmés.
SET @publication_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'publication_consent_at'),
    'SELECT 1', 'ALTER TABLE events ADD COLUMN publication_consent_at DATETIME NULL AFTER is_published'
);
PREPARE publication_stmt FROM @publication_ddl;
EXECUTE publication_stmt;
DEALLOCATE PREPARE publication_stmt;

SET @publication_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'publication_consent_by'),
    'SELECT 1', 'ALTER TABLE events ADD COLUMN publication_consent_by INT NULL COMMENT ''Identifiant historique de l administrateur attestant l accord client'' AFTER publication_consent_at'
);
PREPARE publication_stmt FROM @publication_ddl;
EXECUTE publication_stmt;
DEALLOCATE PREPARE publication_stmt;
