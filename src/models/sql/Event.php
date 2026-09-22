<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/Database.php';

/**
 * Modèle de données : Event (Data Access Layer - SQL)
 *
 * Encapsule l'ensemble des opérations relationnelles sur la table `events`.
 *
 * Alignement ECF Studi (Titre CDA) :
 * - AT1 : Requêtes préparées PDO systématiques contre les injections SQL (CWE-89).
 * - AT2 : Respect de la 3NF, masquage strict des données financières en vitrine publique,
 *         et capture étanche des exceptions PDO sans fuite d'informations.
 *
 * @package    InnovEventsManager
 * @subpackage Models\SQL
 * @author     Romain Remusat
 * @version    2.3.0
 */
class Event
{
    /**
     * Référentiel opérationnel partagé par les formulaires et les écritures SQL.
     * Les états ECF (p. 7 et 12) sont complétés par « planifié », déjà utilisé
     * à la conversion : planifier ne vaut pas acceptation commerciale du devis.
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'brouillon' => 'Brouillon',
        'planifié' => 'Planifié',
        'accepté' => 'Accepté',
        'en cours' => 'En cours',
        'terminé' => 'Terminé',
        'annulé' => 'Annulé',
    ];

    /**
     * Assure la compatibilité avec l'ancien libellé d'annulation de l'énoncé.
     * Cette normalisation ne valide pas le statut et ne modifie pas les données stockées.
     *
     * @param string $status Valeur issue du formulaire ou d'un enregistrement historique.
     * @return string « annulé » pour l'ancien « annuler », sinon la valeur sans espaces périphériques.
     */
    public static function normalizeStatus(string $status): string
    {
        $status = trim($status);
        return $status === 'annuler' ? 'annulé' : $status;
    }

    /**
     * Instance de connexion PDO partagée.
     */
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recherche les événements publics publiés selon les filtres multicritères.
     *
     * Spécifications CDC (Page 7) : publication demandée et accord client confirmé,
     * statut != 'brouillon', et STRICTEMENT AUCUNE DONNÉE FINANCIÈRE extraite.
     *
     * @param string|null $dateStart Date minimale (format Y-m-d).
     * @param string|null $dateEnd   Date maximale (format Y-m-d).
     * @param string|null $type      Type d'événement (ex: Conférence, Gala).
     * @param string|null $theme     Thématique (ex: Luxe, IA).
     * @return array<int, array<string, mixed>>
     */
    public function findPublishedEvents(
        ?string $dateStart = null,
        ?string $dateEnd = null,
        ?string $type = null,
        ?string $theme = null
    ): array {
        try {
            $sql = "
                SELECT e.id,
                       e.title,
                       e.description,
                       e.start_date,
                       e.end_date,
                       e.location,
                       e.estimated_participants,
                       e.image_path,
                       e.event_type,
                       e.theme,
                       c.name AS company_name
                FROM events e
                LEFT JOIN companies c ON e.company_id = c.id
                WHERE e.is_published = 1 
                  AND e.publication_consent_at IS NOT NULL
                  AND e.status != 'brouillon'
            ";

            $params = [];

            if (!empty($dateStart)) {
                $sql .= " AND e.start_date >= :date_start";
                $params[':date_start'] = $dateStart . ' 00:00:00';
            }

            if (!empty($dateEnd)) {
                $sql .= " AND e.start_date <= :date_end";
                $params[':date_end'] = $dateEnd . ' 23:59:59';
            }

            if (!empty($type)) {
                $sql .= " AND e.event_type = :event_type";
                $params[':event_type'] = $type;
            }

            if (!empty($theme)) {
                $sql .= " AND e.theme = :theme";
                $params[':theme'] = $theme;
            }

            $sql .= " ORDER BY e.start_date ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("[Event::findPublishedEvents] Défaillance SQL : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Extrait la fiche publique détaillée d'un événement publié.
     *
     * @param int $id Clé primaire de l'événement.
     * @return array<string, mixed>|null Détails ou null si non accessible.
     */
    public function findPublishedById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT e.id,
                       e.title,
                       e.description,
                       e.start_date,
                       e.end_date,
                       e.location,
                       e.estimated_participants,
                       e.image_path,
                       e.event_type,
                       e.theme,
                       c.name AS company_name
                FROM events e
                LEFT JOIN companies c ON e.company_id = c.id
                WHERE e.id = :id 
                  AND e.is_published = 1 
                  AND e.publication_consent_at IS NOT NULL
                  AND e.status != 'brouillon'
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result !== false ? $result : null;
        } catch (\PDOException $e) {
            error_log(sprintf("[Event::findPublishedById] Erreur SQL event #%d : %s", $id, $e->getMessage()));
            return null;
        }
    }

    /**
     * Récupère les types et thèmes existants pour alimenter dynamiquement les menus <select>.
     *
     * @return array{types: string[], themes: string[]}
     */
    public function getFilterCriteria(): array
    {
        try {
            $types = $this->db->query("
                SELECT DISTINCT event_type 
                FROM events 
                WHERE is_published = 1 AND publication_consent_at IS NOT NULL AND status != 'brouillon' AND event_type IS NOT NULL
                ORDER BY event_type ASC
            ")->fetchAll(\PDO::FETCH_COLUMN);

            $themes = $this->db->query("
                SELECT DISTINCT theme 
                FROM events 
                WHERE is_published = 1 AND publication_consent_at IS NOT NULL AND status != 'brouillon' AND theme IS NOT NULL AND theme != ''
                ORDER BY theme ASC
            ")->fetchAll(\PDO::FETCH_COLUMN);

            return [
                'types'  => $types ?: [],
                'themes' => $themes ?: []
            ];
        } catch (\PDOException $e) {
            error_log("[Event::getFilterCriteria] Erreur SQL : " . $e->getMessage());
            return ['types' => [], 'themes' => []];
        }
    }

    /**
     * Extrait les prochains événements à venir (Widget Dashboard Admin Chloé & Espace Client).
     *
     * @param int $limit Nombre maximal d'enregistrements.
     * @param int|null $clientId Si renseigné, limite la liste aux projets à venir de ce client.
     * @return array<int, array<string, mixed>>
     */
    public function findUpcomingEvents(int $limit = 3, ?int $clientId = null): array
    {
        try {
            $clientFilter = $clientId !== null
                ? " AND e.client_id = :client_id AND e.status NOT IN ('annulé', 'annuler', 'terminé')"
                : '';
            $stmt = $this->db->prepare("
                SELECT e.id,
                       e.title,
                       e.start_date,
                       e.end_date,
                       e.location,
                       e.status,
                       u.firstname,
                       u.lastname,
                       c.name AS company_name
                FROM events e
                INNER JOIN users u ON e.client_id = u.id
                LEFT JOIN companies c ON e.company_id = c.id
                WHERE e.start_date >= NOW()
                $clientFilter
                ORDER BY e.start_date ASC, e.id ASC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            if ($clientId !== null) {
                $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
            }
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("[Event::findUpcomingEvents] Erreur SQL : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Extrait l'historique des événements passés.
     *
     * @param int $limit Nombre maximal d'enregistrements.
     * @return array<int, array<string, mixed>>
     */
    public function findPastEvents(int $limit = 3): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT e.id,
                       e.title,
                       e.start_date,
                       e.end_date,
                       e.location,
                       e.status,
                       u.firstname,
                       u.lastname,
                       c.name AS company_name
                FROM events e
                INNER JOIN users u ON e.client_id = u.id
                LEFT JOIN companies c ON e.company_id = c.id
                WHERE e.start_date < NOW()
                ORDER BY e.start_date DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("[Event::findPastEvents] Erreur SQL : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Extrait l'intégralité des événements pour le Back-Office d'administration.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllAdmin(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT e.id,
                       e.title,
                       e.start_date,
                       e.end_date,
                       e.location,
                       e.status,
                       e.is_published,
                       e.publication_consent_at,
                       e.event_type,
                       e.theme,
                       e.estimated_participants,
                       u.firstname,
                       u.lastname,
                       c.name AS company_name
                FROM events e
                INNER JOIN users u ON e.client_id = u.id
                LEFT JOIN companies c ON e.company_id = c.id
                ORDER BY e.start_date DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("[Event::findAllAdmin] Erreur SQL : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Alias de compatibilité vers findAllAdmin.
     */
    public function findAllWithClient(): array
    {
        return $this->findAllAdmin();
    }

    /**
     * Renvoie l'historique complet, y compris les événements privés ou annulés.
     * @param int $clientId Propriétaire des événements.
     * @return array<int, array<string, mixed>> Événements du plus récent au plus ancien.
     */
    public function findByClientId(int $clientId): array
    {
        $stmt = $this->db->prepare('SELECT id, title, start_date, end_date, location, status
            FROM events WHERE client_id = ? ORDER BY start_date DESC, id DESC');
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return int Nombre de projets encore au statut brouillon. */
    public function countDrafts(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM events WHERE status = 'brouillon'")->fetchColumn();
    }

    /**
     * Recherche un événement par son identifiant unique (Accès Back-Office).
     *
     * @param int $id Identifiant unique de l'événement.
     * @return array<string, mixed>|null
     */
    public function findByIdAdmin(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT e.*,
                       u.firstname,
                       u.lastname,
                       u.email AS client_email,
                       c.name AS company_name
                FROM events e
                INNER JOIN users u ON e.client_id = u.id
                LEFT JOIN companies c ON e.company_id = c.id
                WHERE e.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result !== false ? $result : null;
        } catch (\PDOException $e) {
            error_log(sprintf("[Event::findByIdAdmin] Erreur SQL #%d : %s", $id, $e->getMessage()));
            return null;
        }
    }

    /**
     * Alias de compatibilité vers findByIdAdmin.
     */
    public function findByIdWithClient(int $id): ?array
    {
        return $this->findByIdAdmin($id);
    }

    /**
     * Met à jour le statut opérationnel d'un événement.
     * L'administrateur peut corriger les états sans ordre imposé ; le démarrage
     * exige toutefois l'acceptation du dernier devis explicitement associé (ECF p. 12).
     * La comparaison avec l'état lu évite de journaliser un ancien état devenu obsolète.
     *
     * @param int $id Identifiant de l'événement.
     * @param string $newStatus État cible issu du référentiel STATUS_LABELS.
     * @param string $expectedStatus État SQL lu avant la modification, non normalisé.
     * @return bool Une ligne effectivement modifiée ; false en cas de refus ou d'erreur SQL.
     */
    public function updateStatus(int $id, string $newStatus, string $expectedStatus): bool
    {
        $newStatus = self::normalizeStatus($newStatus);
        if (!isset(self::STATUS_LABELS[$newStatus])) {
            return false;
        }
        try {
            $stmt = $this->db->prepare("
                UPDATE events 
                SET status = :status
                WHERE id = :id AND status = :expected_status
                  AND (:target_status <> 'en cours' OR (
                    SELECT d.status FROM devis d
                    JOIN prospects p ON p.id = d.id_prospect AND p.user_id = events.client_id
                    WHERE d.event_id = events.id ORDER BY d.id_devis DESC LIMIT 1
                  ) = 'accepté')
            ");
            $stmt->execute([
                ':status' => $newStatus,
                ':id' => $id,
                ':expected_status' => $expectedStatus,
                ':target_status' => $newStatus,
            ]);
            return $stmt->rowCount() === 1;
        } catch (\PDOException $e) {
            error_log(sprintf("[Event::updateStatus] Erreur SQL event #%d : %s", $id, $e->getMessage()));
            return false;
        }
    }

    /**
     * Enregistre une intention explicite de publication et l'attestation de l'administrateur.
     * L'accord est recueilli hors application ; cette trace ne vaut pas signature du client.
     * Le retrait efface l'accord actif : toute republication exige une nouvelle confirmation.
     * Un brouillon peut être préparé, mais reste exclu des lectures publiques.
     *
     * @param int $id Identifiant de l'événement.
     * @param bool $publish Visibilité demandée, jamais une bascule implicite.
     * @param bool $consentConfirmed Confirmation volontaire de l'accord client.
     * @param int $actorUserId Administrateur authentifié ayant recueilli l'accord.
     * @return bool Une ligne a effectivement été modifiée.
     */
    public function setPublication(int $id, bool $publish, bool $consentConfirmed, int $actorUserId): bool
    {
        if ($publish && !$consentConfirmed) {
            return false;
        }
        try {
            $sql = $publish
                ? 'UPDATE events SET is_published = 1, publication_consent_at = NOW(), publication_consent_by = :actor
                   WHERE id = :id AND (is_published = 0 OR publication_consent_at IS NULL)'
                : 'UPDATE events SET is_published = 0, publication_consent_at = NULL, publication_consent_by = NULL
                   WHERE id = :id AND (is_published = 1 OR publication_consent_at IS NOT NULL)';
            $sql .= " AND EXISTS (SELECT 1 FROM users WHERE id = :admin AND role = 'ADMIN' AND is_deleted = 0 AND must_change_password = 0)";
            $params = [':id' => $id, ':admin' => $actorUserId];
            if ($publish) {
                $params[':actor'] = $actorUserId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() === 1;
        } catch (\PDOException $e) {
            error_log(sprintf("[Event::setPublication] Erreur SQL event #%d : %s", $id, $e->getMessage()));
            return false;
        }
    }

    /**
     * Met à jour le chemin d'accès de l'image de couverture.
     */
    public function updateImage(int $id, string $imagePath): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE events 
                SET image_path = :image_path
                WHERE id = :id
            ");
            $stmt->execute([
                ':image_path' => $imagePath,
                ':id'         => $id
            ]);
            return $stmt->rowCount() === 1;
        } catch (\PDOException $e) {
            error_log(sprintf("[Event::updateImage] Erreur SQL event #%d : %s", $id, $e->getMessage()));
            return false;
        }
    }
}
