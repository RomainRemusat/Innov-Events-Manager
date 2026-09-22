-- Réduit les données de démonstration tout en conservant les principaux parcours.
START TRANSACTION;

-- La tâche créée pour cet événement est conservée avec le brouillon correspondant.
UPDATE tasks
SET event_id = 141
WHERE id = 1
  AND event_id = 61
  AND title = 'Le test d''event';

-- Un seul événement et un seul devis suffisent pour le scénario TechCorp.
UPDATE devis
SET event_id = 4
WHERE id_devis = 5
  AND id_prospect = 1
  AND event_id IS NULL;

DELETE FROM devis
WHERE id_devis = 4
  AND id_prospect = 1;

DELETE FROM events
WHERE id = 5
  AND client_id = 9;

-- Les comptes générés par les tests sont supprimés avec leurs données dépendantes.
DELETE FROM users
WHERE role = 'CLIENT'
  AND (
      email REGEXP '^upload_test_.*@test\\.com$'
      OR email REGEXP '^client_.*@example\\.com$'
  );

-- Trois demandes isolées illustrent les statuts à contacter, échoué et en attente.
DELETE FROM prospects
WHERE user_id IS NULL
  AND id NOT IN (33, 54, 62);

-- Les sociétés devenues inutilisées sont retirées après le nettoyage des comptes.
DELETE FROM companies
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE users.company_id = companies.id
)
AND NOT EXISTS (
    SELECT 1 FROM prospects WHERE prospects.company_id = companies.id
)
AND NOT EXISTS (
    SELECT 1 FROM events WHERE events.company_id = companies.id
);

COMMIT;
