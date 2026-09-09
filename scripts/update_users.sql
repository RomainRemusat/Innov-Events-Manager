-- MySQL 8.0 : alignement de users depuis l'export du 09/09/2026.
-- Rejouable sur cette version ou sur schema.sql ; voir scripts/README.md.
-- Le mode strict refuse les NULL existants au lieu de les convertir en silence.
-- Les ALTER TABLE ne sont pas annulables par un ROLLBACK global.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_ALL_TABLES');

ALTER TABLE users
    MODIFY COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'CLIENT',
    MODIFY COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0;
