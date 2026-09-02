-- =====================================================================
-- PROJET : INNOV'EVENTS MANAGER (ECF Titre CDA - Studi)
-- FICHIER : scripts/schema.sql
-- OBJECTIF : DDL - Schéma relationnel en 3NF (Création manuelle des tables)
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notes;
DROP TABLE IF EXISTS prestations;
DROP TABLE IF EXISTS devis;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS prospects;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS companies;
SET FOREIGN_KEY_CHECKS = 1;

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
id_prospect INT NOT NULL,
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
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_events_client
FOREIGN KEY (client_id) REFERENCES users(id)
ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT fk_events_company
FOREIGN KEY (company_id) REFERENCES companies(id)
ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
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