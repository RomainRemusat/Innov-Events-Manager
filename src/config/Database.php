<?php
/**
 * Configuration : Database (Gestionnaire de connexion SQL via le pattern Singleton)
 *
 * Ce composant encapsule l'instance de connexion PDO à la base de données MySQL.
 * Le pattern Singleton garantit qu'une seule et unique connexion au serveur de données
 * est ouverte durant le cycle de vie de la requête HTTP, optimisant ainsi l'usage de la mémoire.
 *
 * @package    InnovEventsManager
 * @subpackage Config
 * @author     Romain Remusat
 * @version    1.1.0
 */

class Database
{
    /**
     * @var Database|null Instance unique du gestionnaire de base de données.
     */
    private static $instance = null;

    /**
     * @var PDO Instance active de l'objet de connexion PHP Data Objects.
     */
    private $pdo;

    /**
     * Constructeur privé de la classe Database.
     *
     * Le constructeur est rendu inaccessible depuis l'extérieur de la classe (private)
     * pour empêcher l'instanciation directe via l'opérateur `new`, forçant le passage
     * par la méthode getInstance().
     */
    private function __construct()
    {
        // Paramètres de connexion calqués sur les variables d'environnement Docker Compose
        $host    = 'db';
        $db      = 'innovevents_db';
        $user    = 'root';
        $pass    = 'root_password';
        $charset = 'utf8mb4';

        // Construction du Data Source Name (DSN)
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

        try {
            // Tentative d'initialisation de la connexion PDO avec options de sécurité avancées
            $this->pdo = new PDO($dsn, $user, $pass, [
                // Lève des exceptions en cas d'erreur SQL pour être interceptées par l'application
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Force le retour des données sous forme de tableaux associatifs purs
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (\PDOException $e) {
            // Masquage de l'erreur brute en production pour des raisons de sécurité (prévention de l'information disclosure)
            die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
        }
    }

    /**
     * Récupère l'instance unique de l'objet PDO.
     *
     * Si l'instance n'existe pas encore, elle est initialisée à la volée (Lazy Loading).
     * Dans le cas contraire, l'instance existante est immédiatement retournée.
     *
     * @return PDO Instance de connexion active.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
}