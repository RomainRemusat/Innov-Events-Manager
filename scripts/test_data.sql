/**
 * Script de Peuplement / Jeu d'essai (Seed Data)
 *
 * Ce script initialise la base de données relationnelle avec un jeu de données
 * de test cohérent (utilisateurs, prospects, événements) permettant de valider
 * les fonctionnalités de l'application en environnement de développement.
 *
 * @package    InnovEventsManager
 * @subpackage Database\Scripts
 * @author     Romain Remusat
 * @version    1.2.0
 */

-- Spécification stricte de l'encodage pour éviter la corruption des caractères accentués (ex: Chlo??, confirm??)
SET NAMES 'utf8mb4';

USE innovevents_db;

-- Désactivation temporaire des contraintes de clés étrangères pour permettre un nettoyage propre
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE events;
TRUNCATE TABLE prospects;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================================
-- 1. UTILISATEURS (Administrateurs, Employés, Clients)
-- =========================================================================
-- Note technique : Pour le MVP actuel, le mot de passe de Chloé est stocké en clair
-- pour correspondre au système de vérification temporaire du contrôleur.
-- Le mot de passe haché (via BCRYPT) est fourni pour le client à des fins de démonstration technique.
INSERT INTO users (id, email, password, firstname, lastname, role)
VALUES
    (1, 'chloe@innovevents.fr', 'password', 'Chloé', 'Admin', 'ADMIN'),
    (2, 'client@luxe.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice', 'Vancort', 'CLIENT');

-- =========================================================================
-- 2. PROSPECTS (Demandes de devis issues du formulaire public)
-- =========================================================================
INSERT INTO prospects (id, company_name, contact_name, email, phone, event_type, status)
VALUES
    (1, 'TechCorp', 'Jean Dupont', 'j.dupont@techcorp.fr', '0123456789', 'Séminaire', 'en attente'),
    (2, 'Luxury Hotel', 'Marc Petit', 'm.petit@hotel-luxe.fr', '0612345678', 'Soirée de Gala', 'en attente');

-- =========================================================================
-- 3. ÉVÉNEMENTS (Dossiers événementiels liés à un client existant)
-- =========================================================================
-- Le client_id fait référence à l'utilisateur ID 2 (Alice Vancort) créé ci-dessus.
INSERT INTO events (id, client_id, title, description, event_date, location, status)
VALUES
    (1, 2, 'Gala de Charité', 'Événement annuel de levée de fonds', '2026-06-15 20:00:00', 'Palais Brongniart', 'confirmé');