<?php
/**
 * Modèle : User (Gestion des accès aux données Utilisateurs)
 *
 * Ce composant agit comme une couche d'accès aux données (Data Access Object - DAO)
 * pour l'entité Utilisateur. Il interagit directement avec la table `users`
 * de la base de données relationnelle MySQL via une instance PDO sécurisée.
 *
 * @package    InnovEventsManager
 * @subpackage Models\SQL
 * @author     Romain Remusat
 * @version    1.1.0
 */

require_once __DIR__ . '/../../config/Database.php';

class User
{
    /**
     * @var PDO Instance de connexion active à la base de données.
     */
    private $db;

    /**
     * Constructeur de la classe User.
     *
     * Initialise la connexion à la base de données en récupérant
     * l'instance unique (Pattern Singleton) garantie par la classe Database,
     * évitant ainsi la multiplication des connexions au serveur SQL.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recherche et récupère un utilisateur spécifique grâce à son adresse email.
     *
     * Cette méthode utilise systématiquement une requête préparée PDO pour prévenir
     * de manière stricte toute tentative d'injection SQL. La clause `LIMIT 1` est
     * ajoutée pour optimiser les performances de lecture du moteur de base de données.
     *
     * @param string $email L'adresse email de l'utilisateur à rechercher.
     * @return array|false Retourne un tableau associatif contenant les données de l'utilisateur,
     * ou false (booléen) si aucune correspondance n'est trouvée.
     */
    public function findByEmail(string $email)
    {
        // Préparation de la requête SQL sécurisée avec un marqueur nommé (:email)
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);

        // Exécution de la requête en liant dynamiquement la variable nettoyée au paramètre
        $stmt->execute([':email' => $email]);

        // Récupération et retour du résultat formaté en tableau associatif pur
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}