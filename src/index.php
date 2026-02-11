<?php

$host = 'db'; // Nom du service dans ton docker-compose.yml
$db = 'innovevents_db';
$user = 'root';
$pass = 'root_password';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);
    echo "<h1>✅ Succès !</h1>";
    echo "<p>L'application Innov'Events est connectée à la base de données SQL.</p>";
} catch (\PDOException $e) {
    echo "<h1>❌ Erreur de connexion</h1>";
    echo "<p>Message : " . $e->getMessage() . "</p>";
}
