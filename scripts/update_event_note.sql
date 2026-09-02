-- =====================================================================
-- PROJET : INNOV'EVENTS MANAGER
-- FICHIER : scripts/migrations/alter_events_notes_v2.sql
-- OBJECTIF : Alignement du schéma SQL avec les exigences métier ECF :
--   1. Support des filtres et publication de la vitrine publique (events)
--   2. Prise en charge des notes globales d'équipe décorrélées (notes)
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. ÉVOLUTION TABLE : EVENTS
-- Cahier des charges :
-- - Filtrage obligatoire par dates (début/fin), type et thème.
-- - Exposition publique conditionnée à l'accord client (is_published)
--   et statut != 'brouillon', strictement sans mention de prix.
-- ---------------------------------------------------------------------

-- Ajout des colonnes de gestion temporelle, catégorisation et visibilité
ALTER TABLE events
    ADD COLUMN start_date DATETIME NULL AFTER description,
  ADD COLUMN end_date DATETIME NULL AFTER start_date,
  ADD COLUMN event_type VARCHAR(100) NOT NULL DEFAULT 'Autre' AFTER location,
  ADD COLUMN theme VARCHAR(100) NULL AFTER event_type,
  ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

-- Migration des données existantes (contournement du mode strict NO_ZERO_DATE MySQL 8.0)
UPDATE events
SET start_date = event_date,
    end_date = DATE_ADD(event_date, INTERVAL 4 HOUR);

-- Application de la contrainte d'intégrité NOT NULL sur start_date
ALTER TABLE events
    MODIFY COLUMN start_date DATETIME NOT NULL;

-- Suppression de l'ancienne colonne redondante
ALTER TABLE events
DROP COLUMN event_date;

-- Mise à jour du jeu d'essai pour la vitrine publique (accords clients)
UPDATE events
SET event_type = 'Gala',
    theme = 'Luxe & Prestige',
    is_published = 1
WHERE id = 1;

UPDATE events
SET event_type = 'Conférence',
    theme = 'Intelligence Artificielle',
    is_published = 1
WHERE id = 2;


-- ---------------------------------------------------------------------
-- 2. ÉVOLUTION TABLE : NOTES
-- Cahier des charges :
-- - José et Chloé créent des notes rattachées aux projets opérationnels.
-- - Chloé doit également pouvoir publier des « notes globales » d'équipe.
-- - event_id doit donc être nullable pour accepter les notes transverses.
-- ---------------------------------------------------------------------

-- Suppression temporaire de la contrainte de clé étrangère existante
ALTER TABLE notes
DROP FOREIGN KEY notes_ibfk_1;

-- Modification de la colonne pour autoriser la valeur NULL
ALTER TABLE notes
    MODIFY COLUMN event_id INT NULL;

-- Réapplication de la contrainte avec cascade référentielle
ALTER TABLE notes
    ADD CONSTRAINT fk_notes_event
        FOREIGN KEY (event_id) REFERENCES events(id)
            ON DELETE CASCADE ON UPDATE CASCADE;

-- Insertion d'une note globale d'équipe (preuve de concept pour le jury)
INSERT INTO notes (event_id, user_id, content)
VALUES (NULL, 1, 'Rappel équipe : clôture impérative des fiches techniques prestataires avant vendredi 17h.');