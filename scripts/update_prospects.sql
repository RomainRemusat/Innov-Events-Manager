-- =====================================================================
-- SCRIPT D'ÉVOLUTION DU SCHÉMA (Sprint 2)
-- Objet : Alignement de la table 'prospects' et création de la table 'devis'
-- =====================================================================

-- 1. Ajout des colonnes de qualification de devis (avec positionnement propre)
ALTER TABLE prospects
    ADD COLUMN event_date DATE NULL AFTER event_type,
    ADD COLUMN estimated_participants INT NULL AFTER event_date,
    ADD COLUMN budget DECIMAL(10, 2) NULL AFTER estimated_participants,
    ADD COLUMN description TEXT NULL AFTER budget;

-- 2. Ajout de la clé étrangère pour le commercial gérant le prospect (MCD)
ALTER TABLE prospects
    ADD COLUMN id_user INT NULL AFTER id,
    ADD CONSTRAINT fk_prospect_user
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL;

-- 3. Création de la table devis manquante
CREATE TABLE IF NOT EXISTS devis (
    id_devis INT AUTO_INCREMENT PRIMARY KEY,
    id_prospect INT NOT NULL,
    reference_pdf VARCHAR(255) NOT NULL,
    montant_ht DECIMAL(10, 2) NOT NULL,
    tva DECIMAL(10, 2) NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_devis_prospect FOREIGN KEY (id_prospect) REFERENCES prospects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;