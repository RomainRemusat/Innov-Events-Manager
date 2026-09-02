-- 1. Table des Entreprises clientes (B2B)
CREATE TABLE IF NOT EXISTS companies (
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(150) NOT NULL,
siren VARCHAR(9) NULL,
address VARCHAR(255) NULL,
postal_code VARCHAR(10) NULL,
city VARCHAR(100) NULL,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Mise à jour de la table USERS pour rattachement
ALTER TABLE users
ADD COLUMN company_id INT NULL AFTER role,
ADD CONSTRAINT fk_users_companies
FOREIGN KEY (company_id) REFERENCES companies(id)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- 3. Mise à jour de la table PROSPECTS pour lier la société identifiée
ALTER TABLE prospects
ADD COLUMN company_id INT NULL AFTER user_id,
ADD CONSTRAINT fk_prospects_companies
FOREIGN KEY (company_id) REFERENCES companies(id)
ON DELETE SET NULL
ON UPDATE CASCADE;