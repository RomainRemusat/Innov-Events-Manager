<?php

// docker compose exec -T app php tests/conversion.php
// Bases SQL/MongoDB temporaires : aucune donnée de travail ni aucun mail modifié.
require_once __DIR__ . '/../src/services/ConversionService.php';
require_once __DIR__ . '/../src/models/sql/Devis.php';

function verify(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$name = 'innovevents_test_conversion_' . bin2hex(random_bytes(6));
$pdo = new PDO('mysql:host=db;charset=utf8mb4', 'root', 'root_password', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$mongo = new MongoDB\Driver\Manager('mongodb://mongodb:27017');
$_ENV['MONGO_URI'] = 'mongodb://mongodb:27017';
$_ENV['MONGO_DATABASE'] = $name;
$pdo->exec("CREATE DATABASE `$name`");
try {
    $pdo->exec("USE `$name`");
    $pdo->exec(file_get_contents(__DIR__ . '/../scripts/schema.sql'));
    $pdo->exec(file_get_contents(__DIR__ . '/../scripts/initialise.sql'));

    // Oriente aussi les modèles Company et User vers la base de test.
    $databaseClass = new ReflectionClass(Database::class);
    $database = $databaseClass->newInstanceWithoutConstructor();
    $databaseClass->getProperty('pdo')->setValue($database, $pdo);
    $databaseClass->getProperty('instance')->setValue(null, $database);
    verify(Database::getInstance()->query('SELECT DATABASE()')->fetchColumn() === $name,
        'Le service doit exclusivement utiliser la base temporaire');

    $pdo->exec("INSERT INTO prospects (company_name, contact_name, email, phone, event_type)
        VALUES ('NextGen Software', 'Amandine Legrand', 'a.legrand@nextgen.io', '0102030405', 'Séminaire')");
    $prospectId = (int)$pdo->lastInsertId();
    $data = [
        'prospect_id' => $prospectId,
        'company_name' => 'NextGen Software',
        'contact_name' => 'Amandine Legrand',
        'email' => 'a.legrand@nextgen.io',
        'event_title' => 'Conversion de test',
        'start_date' => '2026-10-12T14:30',
        'location' => 'Paris',
        'estimated_participants' => 25,
        'event_type' => 'Conférence',
        'description' => 'Projet ajusté pendant la conversion',
    ];
    $service = new ConversionService();
    $devisId = $service->convertProspectToClient($data, null, 1);
    $event = $pdo->query('SELECT * FROM events ORDER BY id DESC LIMIT 1')->fetch();
    verify($event['start_date'] === '2026-10-12 14:30:00', 'Date de début incorrecte');
    verify($event['title'] === $data['event_title'] && (int)$event['client_id'] === 4
        && (int)$event['company_id'] === 3 && $event['status'] === 'brouillon', 'Événement incorrect');
    $prospect = $pdo->query("SELECT * FROM prospects WHERE id = $prospectId")->fetch();
    verify($prospect['status'] === 'converti' && (int)$prospect['user_id'] === 4
        && (int)$prospect['company_id'] === 3, 'Prospect non converti');
    $devis = $pdo->query("SELECT * FROM devis WHERE id_devis = $devisId")->fetch();
    verify((int)$devis['id_prospect'] === $prospectId && $devis['status'] === 'brouillon', 'Devis incorrect');
    verify((int)$devis['event_id'] === (int)$event['id'], 'Lien événement/devis absent');
    $devisModel = new Devis();
    $pdfData = $devisModel->findWithProspect($devisId);
    verify($pdfData['event_date'] === '2026-10-12' && $pdfData['event_type'] === 'Conférence'
        && (int)$pdfData['estimated_participants'] === 25
        && $pdfData['description'] === $data['description'] && $pdfData['location'] === 'Paris',
        'Les données du PDF ne correspondent pas au projet converti');
    $pdo->exec("INSERT INTO prestations (devis_id, libelle, montant_ht) VALUES ($devisId, 'Prestation du premier projet', 100)");
    $logs = $mongo->executeQuery($name . '.logs', new MongoDB\Driver\Query([
        'type_action' => 'CONVERSION_PROSPECT', 'details.devis_id' => $devisId,
    ]))->toArray();
    verify(count($logs) === 1 && $logs[0]->id_utilisateur === 1
        && $logs[0]->details->event_id === (int)$event['id'], 'Journal de conversion incorrect');
    echo "OK : conversion réelle, start_date, liens client/entreprise, devis et journal MongoDB.\n";

    $pdo->exec("INSERT INTO prospects (company_name, contact_name, email, phone, event_type)
        VALUES ('NextGen Software', 'Amandine Legrand', 'a.legrand@nextgen.io', '0102030405', 'Autre')");
    $data['prospect_id'] = (int)$pdo->lastInsertId();
    $data['event_title'] = 'Deuxième projet du même client';
    $data['event_status'] = 'planifié';
    $secondQuote = $service->convertProspectToClient($data, null, 1);
    $secondEvent = (int)$pdo->query("SELECT event_id FROM devis WHERE id_devis = $secondQuote")->fetchColumn();
    verify($pdo->query("SELECT status FROM events WHERE id = $secondEvent")->fetchColumn() === 'planifié',
        'Le statut initial planifié doit être conservé');
    $first = $devisModel->findByEventIdWithPrestations((int)$event['id']);
    $second = $devisModel->findByEventIdWithPrestations($secondEvent);
    verify((int)$first['id_devis'] === $devisId && count($first['prestations']) === 1
        && (int)$second['id_devis'] === $secondQuote && $second['prestations'] === [],
        'Les devis/prestations de deux événements du même client sont mélangés');
    verify($first['reference_pdf'] !== $second['reference_pdf'], 'Collision des noms de PDF');
    $pdo->exec("UPDATE devis SET event_id = NULL WHERE id_devis = $secondQuote");
    verify($devisModel->findByEventIdWithPrestations($secondEvent) === null, 'Un devis historique ne doit pas être deviné');
    $pdo->exec("UPDATE devis SET event_id = $secondEvent WHERE id_devis = $secondQuote");
    $pdo->exec("DELETE FROM events WHERE id = $secondEvent");
    verify($pdo->query("SELECT event_id FROM devis WHERE id_devis = $secondQuote")->fetchColumn() === null,
        'La suppression de l’événement doit conserver le devis sans lien');
    echo "OK : isolation de deux projets, données PDF synchronisées, absence de lien et suppression événement.\n";

    $eventModel = new Event();
    $eventId = (int)$event['id'];
    verify(!$eventModel->updateStatus($eventId, 'inconnu', 'brouillon'), 'Statut inconnu accepté');
    verify(!$eventModel->updateStatus($eventId, 'en cours', 'brouillon'), 'Démarrage sans devis accepté');
    $pdo->exec("UPDATE devis SET status = 'accepté' WHERE id_devis = $devisId");
    $oldStatus = 'brouillon';
    foreach (array_keys(Event::STATUS_LABELS) as $status) {
        if ($status === $oldStatus) continue;
        verify($eventModel->updateStatus($eventId, $status, $oldStatus), "Statut refusé : $status");
        verify($pdo->query("SELECT status FROM events WHERE id = $eventId")->fetchColumn() === $status, 'Statut SQL incorrect');
        $oldStatus = $status;
    }
    verify(!$eventModel->updateStatus($eventId, 'brouillon', 'planifié'), 'État concurrent écrasé');
    verify(!$eventModel->updateStatus(2147483647, 'brouillon', 'planifié'), 'Événement inexistant accepté');
    $pdo->exec("UPDATE events SET status = 'annuler' WHERE id = $eventId");
    verify($eventModel->updateStatus($eventId, 'annuler', 'annuler'), 'Ancien libellé non normalisé');
    verify($pdo->query("SELECT status FROM events WHERE id = $eventId")->fetchColumn() === 'annulé', 'Annulation non normalisée');
    $pdo->exec("INSERT INTO devis (id_prospect, event_id, reference_pdf, status) VALUES ($prospectId, $eventId, 'revision.pdf', 'refusé')");
    verify(!$eventModel->updateStatus($eventId, 'en cours', 'annulé'), 'Ancienne version acceptée utilisée malgré la dernière version refusée');
    foreach (['inconnu', 'en cours'] as $invalidStatus) {
        try {
            $service->convertProspectToClient(array_replace($data, ['event_status' => $invalidStatus]), null, 1);
            throw new RuntimeException('Statut initial invalide accepté');
        } catch (InvalidArgumentException $error) {
            verify(str_contains($error->getMessage(), 'Statut initial'), 'Mauvaise validation du statut initial');
        }
    }
    echo "OK : référentiel des statuts, devis accepté obligatoire, concurrence et compatibilité annuler.\n";

    $pdo->exec("INSERT INTO prospects (company_name, contact_name, email, phone, event_type)
        VALUES ('NextGen Software', 'Amandine Legrand', 'a.legrand@nextgen.io', '0102030405', 'Autre')");
    $data['prospect_id'] = (int)$pdo->lastInsertId();
    // Échec réel en fin de transaction, sans modifier le service ni toucher la base de travail.
    verify($pdo->query('SELECT DATABASE()')->fetchColumn() === $name, 'Base temporaire attendue');
    $pdo->exec("CREATE TRIGGER `$name`.fail_quote BEFORE INSERT ON `$name`.devis FOR EACH ROW
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Échec devis simulé'");

    $snapshot = static function () use ($pdo): array {
        $rows = [];
        foreach (['companies', 'users', 'prospects', 'events', 'devis'] as $table) {
            $rows[$table] = $pdo->query("SELECT * FROM $table ORDER BY 1")->fetchAll();
        }
        return $rows;
    };
    $before = $snapshot();
    $data['company_name'] = 'Entreprise à annuler';
    try {
        $service->convertProspectToClient($data, null, 1);
        throw new RuntimeException('Une erreur SQL était attendue');
    } catch (PDOException $error) {
        verify($error->getCode() === '45000', 'Erreur SQL inattendue');
    }
    verify(!$pdo->inTransaction() && $snapshot() === $before, 'Rollback incomplet');
    echo "OK : rollback SQL complet si la création du devis échoue.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec("DROP DATABASE `$name`");
    $mongo->executeCommand($name, new MongoDB\Driver\Command(['dropDatabase' => 1]));
}
