<?php
/**
 * Modèle : Prospect (Accès aux données relationnelles)
 *
 * Cette classe gère le cycle de vie et la persistance des données relatives
 * aux prospects (leads capturés depuis le site web public) au sein de la base
 * de données relationnelle MySQL.
 *
 * @package    InnovEventsManager
 * @subpackage Models\SQL
 * @author     Romain Remusat
 * @version    1.0.0
 */

require_once __DIR__ . '/../../config/Database.php';

class Prospect
{
    /**
     * @var PDO Instance de connexion à la base de données relationnelle.
     */
    private $db;

    /**
     * Constructeur du modèle.
     * Initialise la connexion unique à la base de données via le patron de conception Singleton.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Enregistre une nouvelle demande de devis dans la table 'prospects'.
     *
     * @param array $data Tableau associatif contenant les informations brutes issues de $_POST.
     * @return bool Renvoie true en cas de succès de l'insertion, false sinon.
     * * @throws PDOException En cas d'anomalie de structure ou de contrainte d'intégrité en BDD.
     */
    public function create(array $data): bool
    {
        // Préparation de la requête SQL d'insertion.
        // Le statut est forcé par défaut selon la spécification fonctionnelle.
        $sql = "INSERT INTO prospects (company_name, contact_name, email, phone, event_type, status) 
                VALUES (:company_name, :contact_name, :email, :phone, :event_type, 'en attente')";

        // Sécurisation contre les injections SQL grâce à l'utilisation d'une requête préparée
        $stmt = $this->db->prepare($sql);

        // Exécution de la requête avec nettoyage et sanitisation des entrées (Protection failles XSS)
        return $stmt->execute([
            ':company_name' => htmlspecialchars($data['company_name'], ENT_QUOTES, 'UTF-8'),
            ':contact_name' => htmlspecialchars($data['contact_name'], ENT_QUOTES, 'UTF-8'),
            ':email'        => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
            ':phone'        => htmlspecialchars($data['phone'], ENT_QUOTES, 'UTF-8'),
            ':event_type'   => htmlspecialchars($data['event_type'], ENT_QUOTES, 'UTF-8')
        ]);
    }
}