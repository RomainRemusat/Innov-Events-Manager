-- Suppression des tables si elles existent (pour pouvoir relancer le script)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS quotes, events, prospects, users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Table des Utilisateurs
CREATE TABLE users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       email VARCHAR(255) UNIQUE NOT NULL,
                       password VARCHAR(255) NOT NULL,
                       firstname VARCHAR(100),
                       lastname VARCHAR(100),
                       role ENUM('ADMIN', 'EMPLOYEE', 'CLIENT') NOT NULL DEFAULT 'CLIENT',
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Table des Prospects (Leads du site web)
CREATE TABLE prospects (
                           id INT AUTO_INCREMENT PRIMARY KEY,
                           company_name VARCHAR(255) NOT NULL,
                           contact_name VARCHAR(255) NOT NULL,
                           email VARCHAR(255) NOT NULL,
                           phone VARCHAR(20),
                           event_type VARCHAR(100),
                           estimated_participants INT,
                           status ENUM('en attente', 'contacté', 'converti', 'annulé') DEFAULT 'en attente',
                           created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Table des Événements
CREATE TABLE events (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        client_id INT NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        description TEXT,
                        event_date DATETIME NOT NULL,
                        location VARCHAR(255),
                        status ENUM('brouillon', 'devis envoyé', 'confirmé', 'terminé', 'annulé') DEFAULT 'brouillon',
                        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Table des Devis (Quotes)
CREATE TABLE quotes (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        event_id INT NOT NULL,
                        amount_ht DECIMAL(10, 2) NOT NULL,
                        tva_rate DECIMAL(5, 2) DEFAULT 20.00,
                        amount_ttc DECIMAL(10, 2) AS (amount_ht * (1 + tva_rate / 100)) STORED,
                        is_accepted BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;