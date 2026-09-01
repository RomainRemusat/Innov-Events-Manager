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
     * @param array $data Données assainies issues du QuoteController
     * @return bool True en cas de succès, False sinon
     */
    public function create(array $data): bool
    {
        try {
            // A. Gestion B2B : Recherche ou création de l'entreprise
            $companyId = null;
            $companyName = trim($data['company_name'] ?? '');

            if (!empty($companyName)) {
                $stmtCompany = $this->db->prepare("SELECT id FROM companies WHERE name = ? LIMIT 1");
                $stmtCompany->execute([$companyName]);
                $existing = $stmtCompany->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $companyId = (int)$existing['id'];
                } else {
                    $stmtNew = $this->db->prepare("INSERT INTO companies (name) VALUES (?)");
                    $stmtNew->execute([$companyName]);
                    $companyId = (int)$this->db->lastInsertId();
                }
            }

            // B. Insertion dans la table prospects
            $sql = "INSERT INTO prospects (
                        user_id,
                        company_id,
                        company_name, 
                        contact_name, 
                        email, 
                        phone, 
                        location,
                        event_type, 
                        event_date, 
                        estimated_participants, 
                        budget, 
                        description,
                        status
                    ) VALUES (
                        :user_id,
                        :company_id,
                        :company_name, 
                        :contact_name, 
                        :email, 
                        :phone, 
                        :location,
                        :event_type, 
                        :event_date, 
                        :estimated_participants, 
                        :budget, 
                        :description,
                        'en attente'
                    )";

            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                ':user_id'                => $data['user_id'] ?? null,
                ':company_id'             => $companyId,
                ':company_name'           => $companyName,
                ':contact_name'           => $data['contact_name'],
                ':email'                  => $data['email'],
                ':phone'                  => $data['phone'],
                ':location'               => $data['location'],
                ':event_type'             => $data['event_type'],
                ':event_date'             => $data['event_date'],
                ':estimated_participants' => $data['estimated_participants'],
                ':budget'                 => $data['budget'],
                ':description'            => $data['description']
            ]);

        } catch (\PDOException $e) {
            error_log("CRASH SQL Prospect::create : " . $e->getMessage());
            return false;
        }
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
            $query = "SELECT * FROM prospects ORDER BY created_at DESC";
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

    public function findAllActive(): array
    {
        try {
            // Préparation de la requête SQL (Tri par ID décroissant pour avoir les plus récents)
            $query = "SELECT * FROM prospects WHERE status NOT IN ('accepté', 'refusé')  ORDER BY created_at DESC";
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


    public function NbActive(): int
    {
        try {

            // Préparation de la requête SQL (Tri par ID décroissant pour avoir les plus récents)
            $query = "SELECT count(id) FROM prospects WHERE status NOT IN ('accepté', 'refusé')  ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            // fetchColumn() renvoie directement la valeur scalaire (le chiffre)
            return (int) $stmt->fetchColumn();

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

    public function findClientRequests(int $clientId): array
    {
        try {

            $stmt = $this->db->prepare("
                SELECT p.*, d.* 
                FROM prospects p
                LEFT JOIN devis d ON p.id = d.id_prospect
                WHERE p.user_id = :user_id
                ORDER BY p.created_at DESC
            ");

            $stmt->execute([':user_id' => $clientId]);

            // FETCH_ASSOC garantit un tableau propre
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            // AT2 : Gestion de l'exception (On logue l'erreur silencieusement)
            error_log("Erreur SQL (findClientRequests) pour le client $clientId : " . $e->getMessage());

            // On retourne un tableau vide pour protéger la vue (le foreach ne plantera pas)
            return [];
        }
    }
}