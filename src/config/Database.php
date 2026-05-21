<?php
/**
 * Gestionnaire de Connexion à la Base de Données Relationnelle
 *
 * Ce fichier configure et centralise l'accès à l'instance PDO en utilisant
 * le Design Pattern Singleton. Il garantit qu'une seule et unique connexion
 * réseau est ouverte vers le conteneur de données durant le cycle de vie de la requête.
 *
 * @package    InnovEventsManager
 * @subpackage Config
 * @author     Romain Remusat
 * @version    1.1.0
 */

class Database {
    /**
     * Instance unique de la classe Database (Pattern Singleton)
     * @var Database|null
     */
    private static $instance = null;

    /**
     * Instance de connexion PHP Data Objects (PDO)
     * @var PDO
     */
    private $pdo;

    /**
     * Constructeur privé
     * * Empêche l'instanciation directe de la classe depuis l'extérieur
     * (interdiction d'utiliser 'new Database()') pour forcer le passage par le Singleton.
     * Initialise la connexion PDO avec l'infrastructure Docker.
     */
    private function __construct() {
        // Configuration de la connexion réseau (Mise à niveau UTF-8 stricte)
        $host    = 'db'; // Correspond au nom exact du service MySQL dans docker-compose.yml
        $db      = 'innovevents_db';
        $user    = 'root';
        $pass    = 'root_password';
        $charset = 'utf8mb4'; // Encodage global préservant l'intégrité des accents français et des émojis

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                // Mode de gestion des erreurs : lève des exceptions attrapables pour la sécurité
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Mode de récupération par défaut : transforme les lignes SQL en tableaux associatifs purs
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Désactivation des émulations pour utiliser les vraies requêtes préparées de MySQL
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (\PDOException $e) {
            // Sécurité en production : masquage des détails de l'infrastructure (identifiants, ports) en cas de crash
            error_log("Database connection failure: " . $e->getMessage());
            die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
        }
    }

    /**
     * Point d'accès unique du Singleton
     *
     * Crée l'instance unique de la classe lors du premier appel, puis renvoie
     * systématiquement l'objet de connexion PDO encapsulé.
     *
     * @example $db = Database::getInstance(); $stmt = $db->query("SELECT...");
     * @return PDO L'instance active et configurée de la connexion MySQL
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
}