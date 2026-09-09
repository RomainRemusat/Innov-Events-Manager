-- MySQL 8.0 : alignement de devis depuis l'export du 09/09/2026.
-- Ne recrée ni la table ni status. Les montants et décisions déjà enregistrés
-- sont conservés ; les valeurs par défaut concernent les futures insertions.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_ALL_TABLES');

ALTER TABLE devis
    MODIFY COLUMN montant_ht DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN tva DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'brouillon';
