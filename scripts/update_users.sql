-- =====================================================================
-- SCRIPT DE MIGRATION SQL : INNOV'EVENTS MANAGER
-- Objectif : Mise à jour de la table 'users'
-- Contexte : Implémentation de la politique de sécurité des mots de passe
-- (Forcer le changement du mot de passe temporaire à la première connexion)
-- =====================================================================

-- Démarrage d'une transaction pour garantir l'intégrité de la base
-- (Si une erreur survient, aucune modification ne sera appliquée)
START TRANSACTION;

-- Ajout de la colonne 'must_change_password' (type booléen simulé par TINYINT)
-- La valeur par défaut est '0' (Faux) pour ne pas bloquer les comptes déjà existants (Chloé, José, Alice)
ALTER TABLE `users`
    ADD `must_change_password` TINYINT(1) NOT NULL DEFAULT '0' AFTER `password`;

-- Validation des modifications
COMMIT;

-- Note de versioning : Migration à exécuter après le déploiement du AuthController v1.4