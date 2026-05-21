USE innovevents_db;

-- 1. Utilisateurs de test (Admin et Client)
-- Le mot de passe est 'password' (haché pour tes futurs tests PHP)
INSERT INTO users (email, password, firstname, lastname, role)
            VALUES
               ('chloe@innovevents.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Chloé', 'Admin', 'ADMIN'),
               ('client@luxe.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice', 'Vancort', 'CLIENT');

-- 2. Prospects (Demandes de devis via le site)
INSERT INTO prospects (company_name, contact_name, email, phone, event_type, status)
            VALUES
                 ('TechCorp', 'Jean Dupont', 'j.dupont@techcorp.fr', '0123456789', 'Séminaire', 'en attente'),
                 ('Luxury Hotel', 'Marc Petit', 'm.petit@hotel-luxe.fr', '0612345678', 'Soirée de Gala', 'en attente');

-- 3. Un événement existant pour tester l'affichage
INSERT INTO events (client_id, title, description, event_date, location, status)
            VALUES
                (2, 'Gala de Charité', 'Événement annuel de levée de fonds', '2026-06-15 20:00:00', 'Palais Brongniart', 'confirmé');