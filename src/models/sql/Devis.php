<?php
/**
 * Modèle relationnel : Devis (Gestion du cycle de vie des propositions commerciales)
 *
 * Implémente la persistance et la logique de synchronisation transactionnelle
 * pour l'Activité Type 2 (AT2). Assure l'intégrité financière et la traçabilité
 * des états conformément au référentiel RNCP ECF.
 *
 * @package    InnovEventsManager
 * @subpackage Models/SQL
 * @author     Romain Remusat
 * @version    1.3.0
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/Prestation.php';

class Devis
{
    /**
     * Instance de connexion PDO partagée (Singleton)
     * @var PDO
     */
    private PDO $db;

    /**
     * Initialise la couche d'accès aux données.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recherche un devis par sa clé primaire avec les attributs du prospect associé.
     *
     * Emploie des alias SQL explicites (`status`, `prospect_status`) afin d'empêcher
     * l'écrasement de la colonne d'état du devis par celle du prospect lors du mapping PDO::FETCH_ASSOC.
     *
     * @param int $devisId Identifiant unique du devis (`id_devis`).
     * @return array<string, mixed>|null Enregistrement associatif complet ou null si introuvable.
     */
    public function findWithProspect(int $devisId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT d.id_devis,
                       d.revision,
                       d.id_prospect,
                       d.reference_pdf,
                       d.montant_ht,
                       d.tva,
                       d.status AS status,
                       d.date_creation,
                       p.user_id,
                       p.company_name,
                       p.contact_name,
                       p.email,
                       p.phone,
                       p.event_type,
                       p.event_date,
                       p.location,
                       p.estimated_participants,
                       p.budget,
                       p.description,
                       p.status AS prospect_status
                FROM devis d
                INNER JOIN prospects p ON d.id_prospect = p.id
                WHERE d.id_devis = :devis_id
                LIMIT 1
            ");
            $stmt->execute([':devis_id' => $devisId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Défaut SQL findWithProspect sur devis #{$devisId} : " . $e->getMessage());
            return null;
        }
    }

    /**
     * Compte le nombre de devis nécessitant une modification suite au retour client.
     *
     * @return int
     */
    public function countPendingModifications(): int
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM devis WHERE status = 'modification'");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Défaut SQL countPendingModifications : " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Recherche un ensemble de devis filtrés par leur statut métier.
     *
     * @param string $status État commercial recherché (ex: 'accepté', 'en attente', 'refusé').
     * @return array<int, array<string, mixed>> Collection des devis correspondants.
     */
    public function findByStatus(string $status): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id_devis,
                       id_prospect,
                       reference_pdf,
                       montant_ht,
                       tva,
                       status,
                       date_creation
                FROM devis
                WHERE status = :status
                ORDER BY date_creation DESC
            ");
            $stmt->execute([':status' => $status]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Défaut SQL findByStatus ({$status}) : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Extrait l'intégralité des propositions commerciales consolidées avec les coordonnées client.
     *
     * @return array<int, array<string, mixed>> Liste ordonnée antéchronologiquement.
     */
    public function findAllWithProspects(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT d.id_devis,
                       d.id_prospect,
                       d.reference_pdf,
                       d.montant_ht,
                       d.tva,
                       d.status AS status,
                       d.date_creation,
                       p.company_name,
                       p.contact_name,
                       p.email
                FROM devis d
                LEFT JOIN prospects p ON d.id_prospect = p.id
                ORDER BY d.date_creation DESC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Défaut SQL findAllWithProspects : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Enregistre atomiquement une décision sur la version de l'offre consultée.
     * Le filtre SQL empêche une réponse sur un brouillon, une autre version ou
     * un devis déjà accepté, même si deux requêtes arrivent simultanément.
     *
     * @param int    $devisId   Identifiant unique du devis.
     * @param string $newStatus Décision client : 'accepté', 'refusé' ou 'modification'.
     * @param int $userId Propriétaire authentifié de la demande.
     * @param int $revision Version transmise par le formulaire client.
     * @return bool Vrai en cas de succès.
     */
    public function updateStatus(int $devisId, string $newStatus, int $userId, int $revision): bool
    {
        if (!in_array($newStatus, ['accepté', 'refusé', 'modification'], true) || $revision < 1) {
            return false;
        }
        try {
            $stmt = $this->db->prepare("
                UPDATE devis
                SET status = :status
                WHERE id_devis = :devis_id AND revision = :revision
                  AND status IN ('étude côté client', 'devis envoyé')
                  AND EXISTS (SELECT 1 FROM prospects p WHERE p.id = devis.id_prospect AND p.user_id = :user_id)
            ");

            $stmt->execute([
                ':status'   => $newStatus,
                ':devis_id' => $devisId,
                ':user_id' => $userId,
                ':revision' => $revision,
            ]);
            return $stmt->rowCount() === 1;
        } catch (PDOException $e) {
            error_log("Défaut SQL updateStatus sur devis #{$devisId} : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère la liste de tous les devis avec calcul dynamique des totaux.
     *
     * @return array
     */
    public function findAllWithTotals(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT d.id_devis,
                       d.id_prospect,
                       d.reference_pdf,
                       d.status,
                       d.date_creation,
                       p.company_name,
                       p.contact_name,
                       COALESCE(SUM(pr.montant_ht), d.montant_ht, 0.00) AS total_ht,
                       ROUND(COALESCE(SUM(pr.montant_ht), d.montant_ht, 0.00) * 0.20, 2) AS total_tva,
                       ROUND(COALESCE(SUM(pr.montant_ht), d.montant_ht, 0.00) * 1.20, 2) AS total_ttc
                FROM devis d
                LEFT JOIN prospects p ON d.id_prospect = p.id
                LEFT JOIN prestations pr ON d.id_devis = pr.devis_id
                GROUP BY d.id_devis
                ORDER BY d.date_creation DESC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Défaut SQL findAllWithTotals : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère le dernier devis explicitement rattaché à cet événement et ses prestations.
     *
     * @param int $eventId Identifiant unique de l'événement (events.id)
     * @return array|null
     */
    public function findByEventIdWithPrestations(int $eventId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT d.id_devis,
                       d.id_prospect,
                       d.reference_pdf,
                       d.status,
                       d.date_creation,
                       p.company_name,
                       p.contact_name,
                       p.event_type,
                       p.event_date,
                       COALESCE(SUM(pr.montant_ht), d.montant_ht, 0.00) AS total_ht,
                       ROUND(COALESCE(SUM(pr.montant_ht), d.montant_ht, 0.00) * 0.20, 2) AS total_tva,
                       ROUND(COALESCE(SUM(pr.montant_ht), d.montant_ht, 0.00) * 1.20, 2) AS total_ttc
                FROM devis d
                INNER JOIN prospects p ON d.id_prospect = p.id
                LEFT JOIN prestations pr ON d.id_devis = pr.devis_id
                INNER JOIN events e ON e.id = d.event_id AND e.client_id = p.user_id
                WHERE d.event_id = :event_id
                GROUP BY d.id_devis
                ORDER BY d.id_devis DESC
                LIMIT 1
            ");
            $stmt->execute([':event_id' => $eventId]);
            $devis = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($devis) {
                $prestationModel = new Prestation();
                $devis['prestations'] = $prestationModel->findByDevisId((int)$devis['id_devis']);
                return $devis;
            }

            return null;
        } catch (PDOException $e) {
            error_log("Défaut SQL findByEventIdWithPrestations : " . $e->getMessage());
            return null;
        }
    }
}
