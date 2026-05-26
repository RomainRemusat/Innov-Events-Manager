SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS prospects;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Table Utilisateurs (Authentification et Rôles)
CREATE TABLE users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       email VARCHAR(255) NOT NULL UNIQUE,
                       password VARCHAR(255) NOT NULL,
                       firstname VARCHAR(100) NOT NULL,
                       lastname VARCHAR(100) NOT NULL,
                       role VARCHAR(50) DEFAULT 'CLIENT',
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Injection Jeux d'essais Utilisateurs (Mot de passe haché : 'password')
INSERT INTO users (email, password, firstname, lastname, role) VALUES
                                                                   ('chloe@innovevents.fr', 'password', 'Chloé', 'Admin', 'ADMIN'),
                                                                   ('client@luxe.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice', 'Vancort', 'CLIENT');

-- 2. Table Prospects (Flux du Formulaire de Devis)
CREATE TABLE prospects (
                           id INT AUTO_INCREMENT PRIMARY KEY,
                           company_name VARCHAR(255) NOT NULL,
                           contact_name VARCHAR(255) NOT NULL,
                           email VARCHAR(255) NOT NULL,
                           phone VARCHAR(50) NOT NULL,
                           event_type VARCHAR(100) NOT NULL,
                           status VARCHAR(50) DEFAULT 'en attente',
                           created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Injection Jeux d'essais Prospects
INSERT INTO prospects (company_name, contact_name, email, phone, event_type, status) VALUES
                                                                                         ('TechCorp', 'Jean Dupont', 'j.dupont@techcorp.fr', '0123456789', 'Séminaire', 'en attente'),
                                                                                         ('Luxury Hotel', 'Marc Petit', 'm.petit@hotel-luxe.fr', '0612345678', 'Soirée de Gala', 'en attente');

-- 3. Table Événements (Suivi Opérationnel)
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

-- Injection Jeux d'essais Événements
INSERT INTO events (client_id, title, description, event_date, location, status) VALUES
    (2, 'Gala de Charité', 'Événement annuel de levée de fonds', '2026-06-15 20:00:00', 'Palais Brongniart', 'confirmé');