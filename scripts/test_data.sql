-- =====================================================================
-- PROJET : INNOV'EVENTS MANAGER
-- FICHIER : init.sql (Script d'initialisation et jeu d'essai complet)
-- ACTIVITÉ TYPE : AT2 - Concevoir et mettre en place une base de données relationnelle
-- NORME : 3NF (Troisième Forme Normale) - Intégrité B2B & Conformité RGPD
-- AUTEUR : Romain Rémusat
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
-- 1. TABLE : COMPANIES (Sociétés clientes B2B - 3NF)
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

INSERT INTO companies (id, name, siren, address, postal_code, city)
VALUES
    (1, 'TechCorp', '123456789', '12 avenue des Champs-Élysées', '75008', 'Paris'),
    (2, 'Luxury Hotel Group', '987654321', '45 boulevard de la Croisette', '06400', 'Cannes'),
    (3, 'NextGen Software', '456789123', '8 rue de la Paix', '75002', 'Paris'),
    (4, 'Alterboutique', '888777666', '10 rue du Commerce', '54200', 'Toul'),
    (5, 'BioBoutique', '111222333', '24 rue Verte', '54000', 'Nancy'),
    (6, 'eventHorizon', '999000111', '100 rue de l Espace', '75011', 'Paris');


-- ---------------------------------------------------------------------
-- 2. TABLE : USERS (Authentification, Rôles RBAC et Rattachement B2B)
-- ---------------------------------------------------------------------
CREATE TABLE users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       company_id INT NULL,
                       email VARCHAR(255) NOT NULL UNIQUE,
                       password VARCHAR(255) NOT NULL,
                       must_change_password TINYINT(1) DEFAULT 0,
                       firstname VARCHAR(100) NOT NULL,
                       lastname VARCHAR(100) NOT NULL,
                       role VARCHAR(50) DEFAULT 'CLIENT', -- ADMIN, EMPLOYEE, CLIENT
                       is_deleted TINYINT(1) DEFAULT 0,
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jeu d'essai des utilisateurs (Mots de passe : 'password' hachés en Bcrypt)
INSERT INTO users (id, company_id, email, password, firstname, lastname, role, must_change_password)
VALUES
    (1, NULL, 'chloe@innovevents.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Chloé', 'Admin', 'ADMIN', 0),
    (2, NULL, 'jose@innovevents.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'José', 'Employé', 'EMPLOYEE', 0),
    (3, 2, 'client@luxe.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice', 'Vancort', 'CLIENT', 0),
    (4, 3, 'a.legrand@nextgen.io', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Amandine', 'Legrand', 'CLIENT', 0),
    (7, 5, 'rremusat@hotmail.fr', '$2y$10$5sE9Whc099RauJtqxOoFPu3HXzGoa/7KOF6ZNQUHENi42XmeUBkQa', 'Romain', 'Rémusat', 'CLIENT', 1),
    (8, 4, 'rremusat@gmail.com', '$2y$10$6Ey6.z5pyWWJD8RCzvFrsuYkQD/ySaM7EflLUl0nfsajGqV0EURgq', 'Romain', 'Rémusat', 'CLIENT', 0);


-- ---------------------------------------------------------------------
-- 3. TABLE : PROSPECTS (Tunnels d'acquisition de Leads)
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
                           status VARCHAR(50) DEFAULT 'en attente',
                           created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                           FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
                           FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO prospects (id, user_id, company_id, company_name, contact_name, email, phone, event_type, event_date, location, estimated_participants, budget, description, status)
VALUES
    (
        1,
        NULL,
        1,
        'TechCorp',
        'Jean Dupont',
        'j.dupont@techcorp.fr',
        '0123456789',
        'Séminaire',
        '2026-09-15',
        'Éco-Lodge, Fontainebleau',
        150,
        15000.00,
        'Séminaire de rentrée annuel. Besoins requis :\n- Salle de conférence principale équipée (vidéoprojecteur 4K, micros sans fil)\n- Service traiteur buffet froid (options végétariennes obligatoires)\n- Hébergement à proximité pour 50 collaborateurs.',
        'en attente'
    ),
    (
        2,
        3,
        2,
        'Luxury Hotel Group',
        'Marc Petit',
        'm.petit@hotel-luxe.fr',
        '0612345678',
        'Soirée de Gala',
        '2026-12-10',
        'Palais Brongniart, Paris',
        200,
        45000.00,
        'Gala de fin d année haut de gamme. Besoins requis :\n- Scénographie lumineuse LED complète et sonorisation pour orchestre de jazz en direct\n- Menu gastronomique 5 services pour 200 personnes\n- Location de mobilier de réception chic.',
        'devis envoyé'
    ),
    (
        3,
        4,
        3,
        'NextGen Software',
        'Amandine Legrand',
        'a.legrand@nextgen.io',
        '0789456123',
        'Lancement de produit',
        '2026-10-05',
        'Le Cargo, Espace Innovation Paris',
        80,
        8500.00,
        'Keynote de lancement de notre nouvelle solution IA. Besoins requis :\n- Espace moderne style loft industriel\n- Cocktail dinatoire debout avec animations culinaires (bars à sushis, pièces haut de gamme)\n- Régie streaming live pour diffusion en ligne.',
        'accepté'
    ),
    (
        4,
        8,
        4,
        'Alterboutique',
        'Rémusat Bernole',
        'rremusat@gmail.com',
        '0698291585',
        'Team Building',
        '2026-10-18',
        'Domaine de l Abbaye, Nancy',
        24,
        50000.00,
        'Soirée cohésion d équipe. Jeux, animations de plateau, repas gastronomique et concert jazz.',
        'étude côté client'
    ),
    (
        5,
        7,
        5,
        'BioBoutique',
        'Rémusat Bernole',
        'rremusat@hotmail.fr',
        '0698291585',
        'Séminaire',
        '2026-12-24',
        'Centre de Congrès, Strasbourg',
        10,
        100000.00,
        'Séminaire stratégique de direction et rétrospective annuelle.',
        'en attente'
    ),
    (
        6,
        8,
        6,
        'eventHorizon',
        'Rémusat Bernole',
        'rremusat@gmail.com',
        '0698291585',
        'Soirée de Gala',
        '2027-12-24',
        'Hôtel Martinez, Cannes',
        450,
        50000.00,
        'Soirée de gala institutionnelle annuelle.',
        'étude côté client'
    );


-- ---------------------------------------------------------------------
-- 4. TABLE : EVENTS (Gestion opérationnelle des projets convertis)
-- ---------------------------------------------------------------------
CREATE TABLE events (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        client_id INT NOT NULL,
                        company_id INT NULL,
                        title VARCHAR(255) NOT NULL,
                        description TEXT NULL,
                        event_date DATETIME NOT NULL,
                        location VARCHAR(255) NOT NULL,
                        estimated_participants INT NULL,
                        image_path VARCHAR(255) NULL,
                        status VARCHAR(50) DEFAULT 'en attente',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO events (id, client_id, company_id, title, description, event_date, location, estimated_participants, image_path, status)
VALUES
    (
        1,
        3,
        2,
        'Gala de Charité Luxury Group',
        'Événement annuel de levée de fonds corporate. Traiteur 3 étoiles, spectacle vivant, vente aux enchères caritative.',
        '2026-06-15 20:00:00',
        'Palais Brongniart, Paris',
        200,
        'uploads/events/gala_luxury.webp',
        'confirmé'
    ),
    (
        2,
        4,
        3,
        'Keynote NextGen IA v2',
        'Conférence de presse interactive et démonstrations en réalité virtuelle pour le lancement du progiciel NextGen.',
        '2026-10-05 14:00:00',
        'Le Cargo, Espace Innovation Paris',
        80,
        'uploads/events/keynote_nextgen.webp',
        'en cours'
    );


-- ---------------------------------------------------------------------
-- 5. TABLE : DEVIS (Traces financières et administratives)
-- ---------------------------------------------------------------------
CREATE TABLE devis (
                       id_devis INT AUTO_INCREMENT PRIMARY KEY,
                       id_prospect INT NOT NULL,
                       reference_pdf VARCHAR(255) NOT NULL,
                       montant_ht DECIMAL(10, 2) NOT NULL,
                       tva DECIMAL(10, 2) NOT NULL,
                       date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
                       FOREIGN KEY (id_prospect) REFERENCES prospects(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO devis (id_devis, id_prospect, reference_pdf, montant_ht, tva)
VALUES
    (1, 2, 'Devis_Luxury_Hotel_Group_DEC2026.pdf', 45000.00, 9000.00),
    (2, 3, 'Devis_NextGen_Software_OCT2026.pdf', 8500.00, 1700.00);


-- ---------------------------------------------------------------------
-- 6. TABLE : PRESTATIONS (Détail commercial des devis)
-- ---------------------------------------------------------------------
CREATE TABLE prestations (
                             id INT AUTO_INCREMENT PRIMARY KEY,
                             devis_id INT NOT NULL,
                             libelle VARCHAR(255) NOT NULL,
                             montant_ht DECIMAL(10, 2) NOT NULL,
                             FOREIGN KEY (devis_id) REFERENCES devis(id_devis) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO prestations (id, devis_id, libelle, montant_ht)
VALUES
    (1, 1, 'Scénographie lumineuse LED complète', 15000.00),
    (2, 1, 'Menu gastronomique 5 services (200 pax)', 25000.00),
    (3, 1, 'Location de mobilier de réception', 5000.00),
    (4, 2, 'Espace moderne style loft industriel', 3500.00),
    (5, 2, 'Cocktail dinatoire debout', 5000.00);


-- ---------------------------------------------------------------------
-- 7. TABLE : NOTES (Système collaboratif pour les événements)
-- ---------------------------------------------------------------------
CREATE TABLE notes (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       event_id INT NOT NULL,
                       user_id INT NOT NULL,
                       content TEXT NOT NULL,
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE ON UPDATE CASCADE,
                       FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO notes (id, event_id, user_id, content)
VALUES
    (1, 1, 2, 'Attention, le client a demandé des options végétariennes supplémentaires pour le traiteur.'),
    (2, 2, 1, 'Le prestataire technique pour le streaming doit arriver à 10h pour les tests.');