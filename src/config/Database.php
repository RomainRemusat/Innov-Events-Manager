<?php
// Fichier : src/config/Database.php

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $host = 'db'; // Le nom du service MySQL dans ton docker-compose.yml
        $db = 'innovevents_db';
        $user = 'root';
        $pass = 'root_password';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (\PDOException $e) {
            die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
}