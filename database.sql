-- =====================================================================
-- SCRIPT D'INITIALISATION ALIGNÉ SUR LE DOSSIER TECHNIQUE ECF
-- Version : 2.1.0
-- Auteur : Romain Rémusat
-- Description : Alignement strict des identifiants d'administration.
-- =====================================================================

-- 1. Nettoyage des anciennes structures pour éviter les conflits d'index
DROP TABLE IF EXISTS prospects;
DROP TABLE IF EXISTS users;

-- 2. Structure de la table 'users' (Gestion des profils d'accès)
CREATE TABLE users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       firstname VARCHAR(50) NOT NULL,
                       email VARCHAR(100) NOT NULL UNIQUE,
                       password VARCHAR(255) NOT NULL, -- Stockage temporaire (MVP) avant implémentation du hachage v2
                       role ENUM('ADMIN', 'EMPLOYEE', 'CLIENT') DEFAULT 'ADMIN',
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Structure de la table 'prospects' (Leads et besoins métiers)
CREATE TABLE prospects (
                           id INT AUTO_INCREMENT PRIMARY KEY,
                           company_name VARCHAR(100) NOT NULL,
                           contact_name VARCHAR(100) NOT NULL,
                           email VARCHAR(100) NOT NULL,
                           phone VARCHAR(20) NOT NULL,
                           event_type VARCHAR(50) NOT NULL,
                           event_date DATE NOT NULL,
                           estimated_participants INT NOT NULL,
                           budget DECIMAL(10, 2) NOT NULL,
                           description TEXT NOT NULL,
                           status ENUM('en attente', 'devis envoyé', 'accepté', 'refusé', 'terminé') DEFAULT 'en attente',
                           created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Injection des données du document de rendu (Partie 1 - Compte Administrateur)
INSERT INTO users (firstname, email, password, role)
VALUES ('Chloé', 'chloe@innovevents.fr', 'password', 'ADMIN');

-- 5. Injection du jeu d'essai métier (Indispensable pour la démonstration du Dashboard)
INSERT INTO prospects (company_name, contact_name, email, phone, event_type, event_date, estimated_participants, budget, description, status)
VALUES
    ('Test NoSQL Corp', 'Elon Mongo', 'elon@nosql.com', '0601020304', 'Séminaire', '2026-09-15', 120, 15000.00, 'Séminaire de rentrée avec animation Team Building et cocktail dînatoire.', 'en attente'),
    ('AeroSpace SA', 'Thomas Pesquet', 'thomas@aero.fr', '0789456123', 'Gala', '2026-12-24', 350, 45000.00, 'Soirée de gala annuelle pour la remise des prix de l''innovation.', 'accepté');