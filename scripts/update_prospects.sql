-- MySQL 8.0 : alignement de prospects depuis l'export du 09/09/2026.
-- Les champs de qualification et user_id existent déjà. user_id désigne
-- le client propriétaire : ne pas créer un second lien id_user « commercial ».
-- Les statuts des dossiers existants restent inchangés.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_ALL_TABLES');

ALTER TABLE prospects
    MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'à contacter';
