-- MySQL 8.0 : lien explicite devis/événement, sans rapprochement historique deviné.
-- À exécuter avant de déployer le code qui renseigne event_id. Rejouable.
SET @devis_event_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'devis' AND COLUMN_NAME = 'event_id'),
    'SELECT 1',
    'ALTER TABLE devis ADD COLUMN event_id INT NULL AFTER id_prospect'
);
PREPARE devis_event_stmt FROM @devis_event_ddl;
EXECUTE devis_event_stmt;
DEALLOCATE PREPARE devis_event_stmt;

SET @devis_event_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'devis' AND COLUMN_NAME = 'event_id'
          AND REFERENCED_TABLE_NAME = 'events' AND REFERENCED_COLUMN_NAME = 'id'),
    'SELECT 1',
    'ALTER TABLE devis ADD CONSTRAINT fk_devis_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE devis_event_stmt FROM @devis_event_ddl;
EXECUTE devis_event_stmt;
DEALLOCATE PREPARE devis_event_stmt;
