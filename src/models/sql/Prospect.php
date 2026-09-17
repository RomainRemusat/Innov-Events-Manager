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
 * @version    1.3.0
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

            // B. Insertion dans la table prospects (Statut initial réglementaire : 'à contacter')
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
                        'à contacter'
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
            $query = "SELECT * FROM prospects ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            error_log("Erreur lors de la récupération des prospects : " . $e->getMessage());
            return [];
        }
    }

    public function findAllActive(): array
    {
        try {
            $query = "SELECT * FROM prospects WHERE status NOT IN ('accepté', 'refusé', 'échoué', 'converti') ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            error_log("Erreur lors de la récupération des prospects : " . $e->getMessage());
            return [];
        }
    }

    public function findByStatus(string $status): array
    {
        try {
            $query = "SELECT * FROM prospects WHERE status = :status ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':status' => $status]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur findByStatus Prospect : " . $e->getMessage());
            return [];
        }
    }

    public function NbActive(): int
    {
        try {
            $query = "SELECT count(id) FROM prospects WHERE status NOT IN ('accepté', 'refusé', 'échoué', 'converti') ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            return (int) $stmt->fetchColumn();

        } catch (\PDOException $e) {
            error_log("Erreur lors de la récupération des prospects : " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Recherche et récupère un prospect unique par son identifiant.
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
     * Enregistre une qualification et son motif sans rouvrir un dossier converti.
     * Le verrou SQL protège la vérification de l'état attendu jusqu'à l'écriture.
     * Une soumission identique reste valide pour réessayer une notification échouée.
     *
     * @param int    $id     L'identifiant unique du prospect.
     * @param string $status Le nouveau statut à appliquer.
     * @param string $reason Motif obligatoire pour l'état « échoué ».
     * @param string $expectedStatus État lu par le contrôleur avant la modification.
     * @return bool True en cas de succès, false sinon.
     */
    public function updateStatus(int $id, string $status, string $reason, string $expectedStatus): bool
    {
        $reason = trim($reason);
        if (!in_array($status, ['à contacter', 'en attente', 'échoué'], true)
            || ($status === 'échoué' && ($reason === '' || strlen($reason) > 10000))) {
            return false;
        }
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT status FROM prospects WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $currentStatus = $stmt->fetchColumn();
            $stmt = $this->db->prepare('SELECT id_devis FROM devis WHERE id_prospect = ? LIMIT 1');
            $stmt->execute([$id]);
            if ($currentStatus === false || $currentStatus === 'converti'
                || $currentStatus !== $expectedStatus || $stmt->fetchColumn() !== false) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare("UPDATE prospects SET status = ?,
                rejection_reason = CASE WHEN ? = 'échoué' THEN ? ELSE rejection_reason END WHERE id = ?");
            $stmt->execute([$status, $status, $reason, $id]);
            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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

    /**
     * Liste les demandes affectées au client, avec leurs devis lorsqu'ils existent.
     * L'appartenance repose sur user_id, jamais sur une simple correspondance d'email.
     *
     * @param int $clientId Identifiant du propriétaire des demandes.
     * @return array<int, array<string, mixed>> Une ligne par devis, ou par demande sans devis.
     */
    public function findClientRequests(int $clientId): array
    {
        try {
            $stmt = $this->db->prepare("
            SELECT 
                d.id_devis,
                d.revision,
                COALESCE(d.status, p.status) AS status,
                p.status AS prospect_status,
                p.rejection_reason,
                d.reference_pdf,
                d.montant_ht,
                d.tva,
                p.id AS prospect_id,
                p.company_name,
                p.contact_name,
                p.email,
                p.event_type,
                p.event_date,
                p.created_at
            FROM prospects p
            LEFT JOIN devis d ON d.id_prospect = p.id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC, p.id DESC, d.id_devis DESC
        ");
            $stmt->execute([$clientId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur SQL findClientRequests : " . $e->getMessage());
            return [];
        }
    }
}
