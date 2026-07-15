-- =====================================================================
-- SCRIPT DE CRÉATION ET D'INITIALISATION DE LA BASE DE DONNÉES (SQL)
-- PROJET : INNOV'EVENTS MANAGER - ARCHITECTURE DOUBLE PERSISTANCE
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS devis;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS prospects;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. TABLE : UTILISATEURS (Authentification et Autorisations)
-- ---------------------------------------------------------------------
CREATE TABLE users (
   id INT AUTO_INCREMENT PRIMARY KEY,
   email VARCHAR(255) NOT NULL UNIQUE,
   password VARCHAR(255) NOT NULL,
   firstname VARCHAR(100) NOT NULL,
   lastname VARCHAR(100) NOT NULL,
   role VARCHAR(50) DEFAULT 'CLIENT',
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Injection des utilisateurs (Tous les mots de passe sont 'password' hachés en Bcrypt)
INSERT INTO users (email, password, firstname, lastname, role)
VALUES
   ('chloe@innovevents.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Chloé', 'Admin', 'ADMIN'),
   ('jose@innovevents.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'José', 'Employé', 'EMPLOYEE'),
   ('client@luxe.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice', 'Vancort', 'CLIENT');


-- ---------------------------------------------------------------------
-- 2. TABLE : PROSPECTS (Tunnels d'acquisition de Leads)
-- ---------------------------------------------------------------------
CREATE TABLE prospects (
   id INT AUTO_INCREMENT PRIMARY KEY,
   company_name VARCHAR(255) NOT NULL,
   contact_name VARCHAR(255) NOT NULL,
   email VARCHAR(255) NOT NULL,
   phone VARCHAR(50) NOT NULL,
   event_type VARCHAR(100) NOT NULL,
   event_date DATE NULL,
   estimated_participants INT NULL,
   budget DECIMAL(10, 2) NULL,
   description TEXT NULL,
   status VARCHAR(50) DEFAULT 'en attente',
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Injection de prospects avec des textes descriptifs et des budgets détaillés
INSERT INTO prospects (company_name, contact_name, email, phone, event_type, event_date, estimated_participants, budget, description, status)
VALUES
      (
          'TechCorp',
          'Jean Dupont',
          'j.dupont@techcorp.fr',
          '0123456789',
          'Séminaire',
          '2026-09-15',
          150,
          15000.00,
          'Séminaire de rentrée annuel. Besoins requis : \n- Salle de conférence principale équipée (vidéoprojecteur 4K, micros sans fil)\n- Service traiteur buffet froid (options végétariennes obligatoires)\n- Hébergement à proximité pour 50 collaborateurs.',
          'en attente'
      ),
      (
          'Luxury Hotel Group',
          'Marc Petit',
          'm.petit@hotel-luxe.fr',
          '0612345678',
          'Soirée de Gala',
          '2026-12-10',
          200,
          45000.00,
          'Gala de fin d année haut de gamme. Besoins requis : \n- Scénographie lumineuse LED complète et sonorisation pour orchestre de jazz en direct\n- Menu gastronomique 5 services pour 200 personnes\n- Location de mobilier de réception chic.',
          'devis envoyé'
      ),
      (
          'NextGen Software',
          'Amandine Legrand',
          'a.legrand@nextgen.io',
          '0789456123',
          'Lancement de produit',
          '2026-10-05',
          80,
          8500.00,
          'Keynote de lancement de notre nouvelle solution IA. Besoins requis : \n- Espace moderne style loft industriel\n- Cocktail dinatoire debout avec animations culinaires (bars à sushis, pièces haut de gamme)\n- Régie streaming live pour diffusion en ligne.',
          'accepté'
      );


-- ---------------------------------------------------------------------
-- 3. TABLE : EVENTS (Gestion opérationnelle des projets convertis)
-- ---------------------------------------------------------------------
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'en attente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Injection d'événements opérationnels d'envergure
INSERT INTO events (client_id, title, description, event_date, location, status)
VALUES
        (
        3,
        'Gala de Charité Luxury Group',
        'Événement annuel de levée de fonds corporate. Traiteur 3 étoiles, spectacle vivant, vente aux enchères caritative.',
        '2026-06-15 20:00:00',
        'Palais Brongniart, Paris',
        'confirmé'
        ),
        (
        3,
        'Keynote NextGen IA v2',
        'Conférence de presse interactive et démonstrations en réalité virtuelle pour le lancement du progiciel NextGen.',
        '2026-10-05 14:00:00',
        'Le Cargo, Espace Innovation Paris',
        'en cours'
        );


-- ---------------------------------------------------------------------
-- 4. TABLE : DEVIS (Traces financières et administratives)
-- ---------------------------------------------------------------------
CREATE TABLE devis (
   id_devis INT AUTO_INCREMENT PRIMARY KEY,
   id_prospect INT NOT NULL,
   reference_pdf VARCHAR(255) NOT NULL,
   montant_ht DECIMAL(10, 2) NOT NULL,
   tva DECIMAL(10, 2) NOT NULL,
   date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
   FOREIGN KEY (id_prospect) REFERENCES prospects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des devis avec calcul automatique de la TVA (20%)
INSERT INTO devis (id_prospect, reference_pdf, montant_ht, tva)
VALUES
(2, 'Devis_Luxury_Hotel_Group_DEC2026.pdf', 45000.00, 9000.00),
(3, 'Devis_NextGen_Software_OCT2026.pdf', 8500.00, 1700.00);