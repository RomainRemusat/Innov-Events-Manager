-- MySQL 8.0 : alignement de companies depuis l'export du 09/09/2026.
-- Les company_id et leurs clés étrangères existent déjà dans users,
-- prospects et events. Leurs noms et règles de cascade sont conservés.
-- La largeur du nom est identique à celle de schema.sql (255 caractères).
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_ALL_TABLES');

ALTER TABLE companies
    MODIFY COLUMN name VARCHAR(255) NOT NULL;
