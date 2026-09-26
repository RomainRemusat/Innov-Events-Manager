-- MySQL 8.0 : conservation du motif de non-faisabilité, sans modifier les dossiers existants.
-- À exécuter avant le déploiement de la qualification ; script rejouable.
SET @prospect_rejection_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prospects' AND COLUMN_NAME = 'rejection_reason'),
    'SELECT 1',
    'ALTER TABLE prospects ADD COLUMN rejection_reason TEXT NULL AFTER description'
);
PREPARE prospect_rejection_stmt FROM @prospect_rejection_ddl;
EXECUTE prospect_rejection_stmt;
DEALLOCATE PREPARE prospect_rejection_stmt;
