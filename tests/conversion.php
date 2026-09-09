<?php

// docker compose exec -T app php tests/conversion.php
// Bases SQL/MongoDB temporaires : aucune donnée de travail ni aucun mail modifié.
require_once __DIR__ . '/../src/services/ConversionService.php';

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
    $logs = $mongo->executeQuery($name . '.logs', new MongoDB\Driver\Query([
        'type_action' => 'CONVERSION_PROSPECT', 'details.devis_id' => $devisId,
    ]))->toArray();
    verify(count($logs) === 1 && $logs[0]->id_utilisateur === 1
        && $logs[0]->details->event_id === (int)$event['id'], 'Journal de conversion incorrect');
    echo "OK : conversion réelle, start_date, liens client/entreprise, devis et journal MongoDB.\n";

    $snapshot = static function () use ($pdo): array {
        $rows = [];
        foreach (['companies', 'users', 'prospects', 'events', 'devis'] as $table) {
            $rows[$table] = $pdo->query("SELECT * FROM $table ORDER BY 1")->fetchAll();
        }
        return $rows;
    };
    $before = $snapshot();
    $data['prospect_id'] = 2147483647; // Échec FK du devis après création de l'événement.
    $data['company_name'] = 'Entreprise à annuler';
    try {
        $service->convertProspectToClient($data, null, 1);
        throw new RuntimeException('Une erreur SQL était attendue');
    } catch (PDOException $error) {
        verify($error->getCode() === '23000', 'Erreur SQL inattendue');
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
