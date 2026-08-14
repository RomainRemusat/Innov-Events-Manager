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
 * @version    1.1.0
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
        $sql = "INSERT INTO prospects (company_name, contact_name, email, phone, event_type, event_date, estimated_participants, budget, description) 
            VALUES (:company_name, :contact_name, :email, :phone, :event_type, :event_date, :estimated_participants, :budget, :description)";

        // Sécurisation contre les injections SQL grâce à l'utilisation d'une requête préparée
        $stmt = $this->db->prepare($sql);

        // Exécution de la requête avec nettoyage et sanitisation des entrées (Protection failles XSS)
        return $stmt->execute([
            ':company_name'           => $data['company_name'],
            ':contact_name'           => $data['contact_name'],
            ':email'                  => $data['email'],
            ':phone'                  => $data['phone'],
            ':event_type'             => $data['event_type'],
            ':event_date'             => $data['event_date'] ?? null,
            ':estimated_participants' => $data['estimated_participants'] ?? null,
            ':budget'                 => $data['budget'] ?? null,
            ':description'            => htmlspecialchars($data['description'] ?? '', ENT_QUOTES, 'UTF-8')
        ]);
    }

    /**
     * Récupère l'ensemble des demandes de devis (prospects).
     * Les résultats sont triés par date de création décroissante (les plus récents en premier).
     *
     * @return array Tableau contenant tous les enregistrements de la table prospects.
     */
    public function findAll(): array
    {
        try {
            // Préparation de la requête SQL (Tri par ID décroissant pour avoir les plus récents)
            $query = "SELECT * FROM prospects WHERE status NOT IN ('accepté', 'refusé') ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            // Récupération de tous les résultats sous forme de tableau associatif
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            // Enregistrement de l'erreur dans les logs du serveur sans bloquer l'application
            error_log("Erreur lors de la récupération des prospects : " . $e->getMessage());
            return []; // Retourne un tableau vide en cas d'échec pour éviter un crash de la vue
        }
    }

    /**
     * Recherche et récupère un prospect unique par son identifiant.
     * Utilise une requête préparée pour faire barrage aux injections SQL.
     *
     * @param int $id L'identifiant unique du prospect.
     * @return array|false Tableau associatif des données du prospect ou false si non trouvé.
     */
    public function find(int $id)
    {
        try {
            $query = "SELECT * FROM prospects WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $id]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur lors de la récupération du prospect $id : " . $e->getMessage());
            return false;
        }
    }


    /**
     * Met à jour le statut d'un prospect spécifique.
     *
     * @param int    $id     L'identifiant unique du prospect.
     * @param string $status Le nouveau statut à appliquer.
     * @return bool True en cas de succès, false sinon.
     */
    public function updateStatus(int $id, string $status): bool
    {
        try {
            $query = "UPDATE prospects SET status = :status WHERE id = :id";
            $stmt = $this->db->prepare($query);

            return $stmt->execute([
                ':status' => $status,
                ':id'     => $id
            ]);
        } catch (\PDOException $e) {
            error_log("Erreur lors de la mise à jour du statut pour le prospect $id : " . $e->getMessage());
            return false;
        }
    }


    /**
     * Met à jour le statut d'un devis/prospect côté client.
     * Intègre une vérification stricte de propriété (user_id) pour prévenir
     * les failles de type IDOR (Insecure Direct Object Reference).
     *
     * @param int $prospectId L'identifiant de la demande.
     * @param int $userId L'identifiant du client connecté (propriétaire exigé).
     * @param string $newStatus Le nouveau statut ('accepté' ou 'refusé').
     * @return bool Vrai si la mise à jour a réussi.
     */
    public function updateStatusByClient(int $prospectId, int $userId, string $newStatus): bool
    {
        try {
            $sql = "UPDATE prospects SET status = :status WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                ':status'  => $newStatus,
                ':id'      => $prospectId,
                ':user_id' => $userId
            ]);
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la mise à jour du devis client : " . $e->getMessage());
            return false;
        }
    }

    public function findClientRequests(int $userId): array
    {
        $sql = "
            SELECT 
                p.id, p.event_type, p.company_name, p.status, p.created_at,
                d.reference_pdf, d.montant_ht
            FROM prospects p
            LEFT JOIN devis d ON p.id = d.id_prospect
            WHERE p.user_id = :user_id
            ORDER BY p.created_at DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}