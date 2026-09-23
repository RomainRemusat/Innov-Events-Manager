<?php
/**
 * Modèle relationnel : Devis (Gestion du cycle de vie des propositions commerciales)
 *
 * Implémente la persistance et la logique transactionnelle des propositions,
 * de leurs prestations et de leurs changements de statut.
 *
 * @package    InnovEventsManager
 * @subpackage Models/SQL
 * @author     Romain Remusat
 * @version    1.3.0
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/Prestation.php';

/** Fournit les opérations SQL du cycle de vie des devis. */
class Devis
{
    /**
     * Crée le premier devis d'un événement et conserve ses coordonnées commerciales.
     * Le verrou de l'événement empêche deux soumissions de créer des doublons.
     *
     * @param int $eventId Projet existant appartenant à un client actif.
     * @param string $phone Téléphone du contact, absent du compte utilisateur.
     * @return int Identifiant du brouillon créé, sans envoi ni génération de PDF.
     * @throws Throwable Si le projet est indisponible, déjà chiffré ou si une écriture échoue.
     */
    public function createForEvent(int $eventId, string $phone): int
    {
        $phone = trim($phone);
        if (strlen($phone) > 50 || !preg_match('/^\+?[0-9 ().-]+$/D', $phone)
            || strlen(preg_replace('/\D/', '', $phone)) < 6) {
            throw new InvalidArgumentException('Renseignez un numéro de téléphone valide pour le contact.');
        }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT e.*, u.firstname, u.lastname, u.email, c.name AS company_name
                FROM events e INNER JOIN users u ON u.id=e.client_id
                LEFT JOIN companies c ON c.id=e.company_id
                WHERE e.id=? AND u.role='CLIENT' AND u.is_deleted=0 FOR UPDATE");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$event) throw new InvalidArgumentException('Événement introuvable ou compte client suspendu.');
            $stmt = $this->db->prepare('SELECT id_devis FROM devis WHERE event_id=? LIMIT 1');
            $stmt->execute([$eventId]);
            if ($stmt->fetchColumn()) throw new InvalidArgumentException('Un devis est déjà associé à cet événement. Rechargez sa fiche pour le consulter.');

            // Un dossier converti fournit au devis son identité commerciale propre,
            // sans modifier une demande existante ni créer un second événement.
            $contact = $event['firstname'] . ' ' . $event['lastname'];
            $stmt = $this->db->prepare("INSERT INTO prospects
                (user_id,company_id,company_name,contact_name,email,phone,event_type,event_date,
                 location,estimated_participants,description,status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,'converti')");
            $stmt->execute([$event['client_id'], $event['company_id'], $event['company_name'] ?? $contact,
                $contact, $event['email'], $phone, $event['event_type'], substr($event['start_date'], 0, 10),
                $event['location'], $event['estimated_participants'], $event['description']]);
            $prospectId = (int)$this->db->lastInsertId();
            $reference = 'Devis_Event_' . $eventId . '_' . bin2hex(random_bytes(8)) . '.pdf';
            $stmt = $this->db->prepare("INSERT INTO devis (id_prospect,event_id,reference_pdf,status)
                VALUES (?,?,?,'brouillon')");
            $stmt->execute([$prospectId, $eventId, $reference]);
            $id = (int)$this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

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
                       d.change_reason,
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
     * @param string|null $reason Motif obligatoire pour une demande de modification.
     * @return bool Vrai en cas de succès.
     */
    public function updateStatus(int $devisId, string $newStatus, int $userId, int $revision, ?string $reason = null): bool
    {
        if (!in_array($newStatus, ['accepté', 'refusé', 'modification'], true) || $revision < 1) {
            return false;
        }
        $reason = trim($reason ?? '');
        if ($newStatus === 'modification' && (mb_strlen($reason) < 5 || strlen($reason) > 65535)) {
            return false;
        }
        try {
            $stmt = $this->db->prepare("
                UPDATE devis
                SET status = :status, change_reason = :reason
                WHERE id_devis = :devis_id AND revision = :revision
                  AND status IN ('étude côté client', 'devis envoyé')
                  AND EXISTS (SELECT 1 FROM prospects p WHERE p.id = devis.id_prospect AND p.user_id = :user_id)
            ");

            $stmt->execute([
                ':status'   => $newStatus,
                ':reason' => $newStatus === 'modification' ? $reason : null,
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
