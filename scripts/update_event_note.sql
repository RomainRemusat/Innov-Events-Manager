-- MySQL 8.0 : alignement des événements et notes depuis l'export du 09/09/2026.
-- start_date/end_date, les filtres et is_published existent déjà.
-- Ne pas recréer event_date, inventer une date de fin, modifier un accord
-- de publication ou insérer des notes de démonstration pendant une migration.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_ALL_TABLES');

ALTER TABLE events
    MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'brouillon';

-- La clé étrangère existante est conservée, quel que soit son nom.
ALTER TABLE notes
    MODIFY COLUMN event_id INT NULL;
