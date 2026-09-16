-- Conserve le motif avec la décision client, indépendamment du journal MongoDB.
-- Migration MySQL 8.0 rejouable ; les motifs historiques ne sont pas reconstitués.
SET @change_reason_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'devis' AND COLUMN_NAME = 'change_reason'),
    'SELECT 1', 'ALTER TABLE devis ADD COLUMN change_reason TEXT NULL'
);
PREPARE change_reason_stmt FROM @change_reason_ddl;
EXECUTE change_reason_stmt;
DEALLOCATE PREPARE change_reason_stmt;
