-- =====================================================================
-- SCRIPT D'ÉVOLUTION DU SCHÉMA (Sprint 2)
-- Objet : Mise à jour de la table 'prospects' pour le tunnel de devis
-- =====================================================================

ALTER TABLE prospects
    ADD COLUMN event_date DATE NULL,
ADD COLUMN estimated_participants INT NULL,
ADD COLUMN budget DECIMAL(10, 2) NULL,
ADD COLUMN description TEXT NULL;