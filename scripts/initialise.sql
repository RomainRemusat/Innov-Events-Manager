-- =====================================================================
-- PROJET : INNOV'EVENTS MANAGER (ECF Titre CDA - Studi)
-- FICHIER : scripts/initialise.sql
-- OBJECTIF : DML - Jeu de démonstration (4 comptes et 2 entreprises clientes)
-- =====================================================================

-- 1. Entreprises des deux clientes de démonstration (B2B)
INSERT INTO companies (id, name, siren, address, postal_code, city) VALUES
    (2, 'Luxury Hotel Group', '987654321', '45 boulevard de la Croisette', '06400', 'Cannes'),
    (3, 'NextGen Software', '456789123', '8 rue de la Paix', '75002', 'Paris');

-- 2. Quatre comptes : un ADMIN, un EMPLOYEE, deux CLIENT (mot de passe : 'Password123!').
-- Les deux clientes ont des dossiers distincts pour tester l'isolation des accès.
INSERT INTO users (id, company_id, email, password, must_change_password, firstname, lastname, role, is_deleted) VALUES
    (1, NULL, 'chloe@innovevents.fr', '$2y$10$cfwWHSuakfndahoUJEffxuTativi27EWkeaE4Ufo00LOn0N4Hh2rK', 0, 'Chloé', 'Admin', 'ADMIN', 0),
    (2, NULL, 'jose@innovevents.fr', '$2y$10$J1yf7klsCmcK5UO72lWye.ubC9DYJOwX9zK5EwOxhE5iCVDEUtijm', 0, 'José', 'Employé', 'EMPLOYEE', 0),
    (3, 2, 'client@luxe.com', '$2y$10$T3.JkYp.QnOvuV2riuPAjebz8g0GWHwOqnKsS/2BFlKVtMmz0YNpu', 0, 'Alice', 'Vancort', 'CLIENT', 0),
    (4, 3, 'a.legrand@nextgen.io', '$2y$10$Angr1fW.BGoEUcvC78NJXexrV/8ZO3HXGM7zWq2vR02jk5ygQyHFK', 0, 'Amandine', 'Legrand', 'CLIENT', 0);

-- 3. Insertion des Demandes de prospects
INSERT INTO prospects (id, user_id, company_id, company_name, contact_name, email, phone, event_type, event_date, location, estimated_participants, budget, description, status) VALUES
    (1, 4, 3, 'NextGen Software', 'Amandine Legrand', 'a.legrand@nextgen.io', '0123456789', 'Séminaire', '2026-09-15', 'Éco-Lodge, Fontainebleau', 150, 15000.00, 'Séminaire de rentrée annuel.', 'converti'),
    (2, 3, 2, 'Luxury Hotel Group', 'Alice Vancort', 'client@luxe.com', '0612345678', 'Soirée de Gala', '2026-12-10', 'Palais Brongniart, Paris', 200, 45000.00, 'Gala de fin d année haut de gamme.', 'converti'),
    (3, 4, 3, 'NextGen Software', 'Amandine Legrand', 'a.legrand@nextgen.io', '0789456123', 'Lancement de produit', '2026-10-05', 'Le Cargo, Espace Innovation Paris', 80, 8500.00, 'Keynote de lancement IA.', 'converti'),
    (4, 3, 2, 'Luxury Hotel Group', 'Alice Vancort', 'client@luxe.com', '0612345678', 'Team Building', '2026-10-18', 'Domaine de l Abbaye, Nancy', 24, 50000.00, 'Soirée cohésion d équipe.', 'converti');

-- 4. Insertion des Devis générés
INSERT INTO devis (id_devis, id_prospect, reference_pdf, montant_ht, tva, status, date_creation) VALUES
    (1, 2, 'Devis_Luxury_Hotel_Group_DEC2026.pdf', 45000.00, 9000.00, 'étude côté client', '2026-09-01 11:38:42'),
    (2, 3, 'Devis_NextGen_Software_OCT2026.pdf', 8500.00, 1700.00, 'accepté', '2026-09-01 11:38:42'),
    (3, 4, 'Devis_LUXURY_TeamBuilding_20260901_122234.pdf', 0.00, 0.00, 'brouillon', '2026-09-01 12:22:34'),
    (4, 1, 'Devis_NEXTGEN_Seminaire_20260901_130730.pdf', 0.00, 0.00, 'brouillon', '2026-09-01 13:07:30'),
    (5, 1, 'Devis_NEXTGEN_Seminaire_20260901_131300.pdf', 7049.00, 1409.80, 'modification', '2026-09-01 13:13:00');

-- 5. Événements des deux clientes (images du dépôt et cas sans image)
INSERT INTO events (id, client_id, company_id, title, description, start_date, end_date, location, event_type, theme, estimated_participants, image_path, status, is_published) VALUES
    (1, 3, 2, 'Gala de Charité Luxury Group', 'Événement annuel de levée de fonds corporate réunissant donateurs et partenaires.', '2026-06-15 20:00:00', '2026-06-16 01:00:00', 'Palais Brongniart, Paris', 'Gala', 'Luxe & Prestige', 200, 'uploads/events/event_5c6c4886f81713243caf.png', 'terminé', 1),
    (2, 4, 3, 'Keynote NextGen IA v2', 'Conférence de presse interactive présentant la nouvelle suite logicielle.', '2026-10-05 14:00:00', '2026-10-05 18:00:00', 'Le Cargo, Espace Innovation Paris', 'Conférence', 'Intelligence Artificielle', 80, 'uploads/events/event_9ca27f2ed5a2d0a0267f.png', 'en cours', 1),
    (3, 3, 2, 'Team Building - Luxury Hotel Group', 'Journée de cohésion d équipe en pleine nature avec ateliers collaboratifs.', '2026-10-18 08:00:00', '2026-10-18 19:00:00', 'Domaine de l Abbaye, Nancy', 'Team Building', 'Nature & Cohésion', 24, NULL, 'brouillon', 0),
    (4, 4, 3, 'Séminaire - NextGen Software', 'Séminaire de rentrée annuel.', '2026-09-15 08:00:00', '2026-09-15 12:00:00', 'Éco-Lodge, Fontainebleau', 'Séminaire', NULL, 150, NULL, 'brouillon', 0);

-- 6. Insertion des Prestations détaillées chiffrées
INSERT INTO prestations (id, devis_id, libelle, montant_ht) VALUES
    (1, 1, 'Scénographie lumineuse LED complète', 15000.00),
    (2, 1, 'Menu gastronomique 5 services (200 pax)', 25000.00),
    (3, 1, 'Location de mobilier de réception', 5000.00),
    (4, 2, 'Espace moderne style loft industriel', 3500.00),
    (5, 2, 'Cocktail dinatoire debout', 5000.00),
    (6, 5, 'Lieu éco-lodge privatisé', 1500.00),
    (7, 5, 'Matériel audiovisuel et vidéo-projection', 2500.00),
    (8, 5, 'Service traiteur déjeunatoire', 3049.00);

-- 7. Insertion des Notes de projet et notes globales d'équipe
INSERT INTO notes (id, event_id, user_id, content, created_at) VALUES
    (1, 1, 2, 'Attention, le client a demandé des options végétariennes supplémentaires pour le traiteur.', '2026-09-01 11:38:43'),
    (2, 2, 1, 'Le prestataire technique pour le streaming doit arriver à 10h pour les tests.', '2026-09-01 11:38:43'),
    (3, 3, 1, 'Consigne logistique : prévoir des barnums en cas d intempéries pour les ateliers extérieurs.', '2026-09-02 10:42:01'),
    (4, NULL, 1, 'Note globale équipe : réunion hebdomadaire avancée à lundi 9h30 exceptionnellement.', '2026-09-02 11:00:00');
