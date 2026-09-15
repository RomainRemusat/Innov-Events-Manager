-- Version de la proposition affichée au client ; prévient les décisions sur un ancien écran.
-- Migration MySQL 8.0 rejouable, à appliquer avant le code du cycle commercial.
SET @quote_revision_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'devis' AND COLUMN_NAME = 'revision'),
    'SELECT 1', 'ALTER TABLE devis ADD COLUMN revision INT NOT NULL DEFAULT 1 AFTER id_devis'
);
PREPARE quote_revision_stmt FROM @quote_revision_ddl;
EXECUTE quote_revision_stmt;
DEALLOCATE PREPARE quote_revision_stmt;
