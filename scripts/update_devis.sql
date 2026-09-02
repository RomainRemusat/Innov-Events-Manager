-- =====================================================================
-- PROJET : INNOV'EVENTS MANAGER
-- FICHIER : update.sql (Script de mise à jour ALTER TABLE)
-- OBJECTIF : AT2 - Mise en conformité du cycle de vie métier (Devis)
-- =====================================================================

-- Ajout de la colonne status à la table devis pour respecter le cahier des charges
ALTER TABLE devis
    ADD COLUMN status VARCHAR(50) DEFAULT 'brouillon' AFTER tva;

-- Optionnel : si on souhaite directement passer les anciens devis existants
-- dans l'état "étude côté client" pour la rétrocompatibilité des données :
-- UPDATE devis SET status = 'étude côté client' WHERE status = 'brouillon';
