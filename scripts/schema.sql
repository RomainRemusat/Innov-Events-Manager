-- =====================================================================
-- PROJET : INNOV'EVENTS MANAGER
-- FICHIER : scripts/schema.sql
-- OBJECTIF : DDL - Création manuelle du schéma relationnel courant
-- USAGE : uniquement sur une base vide (voir scripts/README.md).
-- Une table déjà présente provoque une erreur : aucune table n'est supprimée.
-- Pour la base exportée le 09/09/2026, utiliser les update_*.sql.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 1. TABLE : COMPANIES (Entités morales B2B)
-- ---------------------------------------------------------------------
CREATE TABLE companies (
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(255) NOT NULL,
siren VARCHAR(9) NULL,
address VARCHAR(255) NULL,
postal_code VARCHAR(10) NULL,
city VARCHAR(100) NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. TABLE : USERS (Authentification et RBAC)
-- ---------------------------------------------------------------------
CREATE TABLE users (
id INT AUTO_INCREMENT PRIMARY KEY,
company_id INT NULL,
email VARCHAR(255) NOT NULL UNIQUE,
password VARCHAR(255) NOT NULL,
must_change_password TINYINT(1) NOT NULL DEFAULT 0,
firstname VARCHAR(100) NOT NULL,
lastname VARCHAR(100) NOT NULL,
role VARCHAR(50) NOT NULL DEFAULT 'CLIENT',
is_deleted TINYINT(1) NOT NULL DEFAULT 0,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_users_company
FOREIGN KEY (company_id) REFERENCES companies(id)
ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. TABLE : PROSPECTS (Qualification commerciale amont)
-- ---------------------------------------------------------------------
CREATE TABLE prospects (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NULL,
company_id INT NULL,
company_name VARCHAR(255) NOT NULL,
contact_name VARCHAR(255) NOT NULL,
email VARCHAR(255) NOT NULL,
phone VARCHAR(50) NOT NULL,
event_type VARCHAR(100) NOT NULL,
event_date DATE NULL,
location VARCHAR(255) NULL,
estimated_participants INT NULL,
budget DECIMAL(10, 2) NULL,
description TEXT NULL,
rejection_reason TEXT NULL,
status VARCHAR(50) NOT NULL DEFAULT 'à contacter',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_prospects_user
FOREIGN KEY (user_id) REFERENCES users(id)
ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT fk_prospects_company
FOREIGN KEY (company_id) REFERENCES companies(id)
ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. TABLE : DEVIS (Propositions commerciales et statuts)
-- ---------------------------------------------------------------------
CREATE TABLE devis (
id_devis INT AUTO_INCREMENT PRIMARY KEY,
revision INT NOT NULL DEFAULT 1,
change_reason TEXT NULL,
id_prospect INT NOT NULL,
event_id INT NULL,
reference_pdf VARCHAR(255) NOT NULL,
montant_ht DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
tva DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
status VARCHAR(50) NOT NULL DEFAULT 'brouillon',
date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_devis_prospect
FOREIGN KEY (id_prospect) REFERENCES prospects(id)
ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. TABLE : EVENTS (Projets logistiques et catalogue vitrine)
-- ---------------------------------------------------------------------
CREATE TABLE events (
id INT AUTO_INCREMENT PRIMARY KEY,
client_id INT NOT NULL,
company_id INT NULL,
title VARCHAR(255) NOT NULL,
description TEXT NULL,
start_date DATETIME NOT NULL,
end_date DATETIME NULL,
location VARCHAR(255) NOT NULL,
event_type VARCHAR(100) NOT NULL DEFAULT 'Autre',
theme VARCHAR(100) NULL,
estimated_participants INT NULL,
image_path VARCHAR(255) NULL,
status VARCHAR(50) NOT NULL DEFAULT 'brouillon',
is_published TINYINT(1) NOT NULL DEFAULT 0,
publication_consent_at DATETIME NULL,
publication_consent_by INT NULL COMMENT 'Identifiant historique de l administrateur attestant l accord client',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_events_client
FOREIGN KEY (client_id) REFERENCES users(id)
ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT fk_events_company
FOREIGN KEY (company_id) REFERENCES companies(id)
ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Le devis reste consultable si son événement est supprimé.
ALTER TABLE devis ADD CONSTRAINT fk_devis_event
FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- 6. TABLE : PRESTATIONS (Postes budgétaires rattachés au devis)
-- ---------------------------------------------------------------------
CREATE TABLE prestations (
id INT AUTO_INCREMENT PRIMARY KEY,
devis_id INT NOT NULL,
libelle VARCHAR(255) NOT NULL,
montant_ht DECIMAL(10, 2) NOT NULL,
CONSTRAINT fk_prestations_devis
FOREIGN KEY (devis_id) REFERENCES devis(id_devis)
ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. TABLE : NOTES (Notes collaboratives de projet & globales d'équipe)
-- ---------------------------------------------------------------------
CREATE TABLE notes (
id INT AUTO_INCREMENT PRIMARY KEY,
event_id INT NULL,
user_id INT NOT NULL,
content TEXT NOT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_notes_event
FOREIGN KEY (event_id) REFERENCES events(id)
ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT fk_notes_user
FOREIGN KEY (user_id) REFERENCES users(id)
ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. TABLE : TASKS (Tâches opérationnelles assignées aux employés)
-- ---------------------------------------------------------------------
CREATE TABLE tasks (
id INT AUTO_INCREMENT PRIMARY KEY,
event_id INT NOT NULL,
assigned_user_id INT NOT NULL,
created_by INT NOT NULL,
title VARCHAR(255) NOT NULL,
status VARCHAR(50) NOT NULL DEFAULT 'à faire',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_tasks_event
FOREIGN KEY (event_id) REFERENCES events(id)
ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT fk_tasks_assigned_user
FOREIGN KEY (assigned_user_id) REFERENCES users(id)
ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT fk_tasks_creator
FOREIGN KEY (created_by) REFERENCES users(id)
ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. TABLE : REVIEWS (Avis clients modérés avant publication)
-- ---------------------------------------------------------------------
CREATE TABLE reviews (
id INT AUTO_INCREMENT PRIMARY KEY,
event_id INT NOT NULL UNIQUE,
rating TINYINT UNSIGNED NOT NULL,
comment TEXT NOT NULL,
status VARCHAR(50) NOT NULL DEFAULT 'en attente',
rejection_reason VARCHAR(500) NULL,
moderated_by INT NULL,
moderated_at DATETIME NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
CONSTRAINT chk_reviews_status CHECK (status IN ('en attente', 'validé', 'refusé')),
CONSTRAINT fk_reviews_event
FOREIGN KEY (event_id) REFERENCES events(id)
ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT fk_reviews_moderator
FOREIGN KEY (moderated_by) REFERENCES users(id)
ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. TABLE : SITE_SETTINGS (Contenus publics administrables)
-- ---------------------------------------------------------------------
CREATE TABLE site_settings (
setting_key VARCHAR(100) PRIMARY KEY,
setting_value TEXT NOT NULL,
updated_by INT NULL,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_site_settings_user
FOREIGN KEY (updated_by) REFERENCES users(id)
ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key, setting_value) VALUES
('quote_thank_you_message', 'Merci pour votre confiance. Votre demande est enregistrée et consultable par notre équipe, qui vous recontactera pour discuter de votre projet.');
