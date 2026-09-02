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
 *         et capture étanche des exceptions PDO sans fuite d'informations[cite: 19].
 *
 * @package    InnovEventsManager
 * @subpackage Models\SQL
 * @author     Romain Remusat
 * @version    2.1.0
 */
class Event
{
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
     * Spécifications CDC (Page 7) : Accord client obligatoire (is_published = 1),
     * statut != 'brouillon', et STRICTEMENT AUCUNE DONNÉE FINANCIÈRE extraite[cite: 19].
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
                WHERE is_published = 1 AND status != 'brouillon' AND event_type IS NOT NULL
                ORDER BY event_type ASC
            ")->fetchAll(\PDO::FETCH_COLUMN);

            $themes = $this->db->query("
                SELECT DISTINCT theme 
                FROM events 
                WHERE is_published = 1 AND status != 'brouillon' AND theme IS NOT NULL AND theme != ''
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
     * Exigence CDC (Page 11) : Affiche les événements dont la date de début est la plus proche
     * avec le nom de l'événement et le client associé[cite: 19].
     *
     * @param int $limit Nombre maximal d'enregistrements.
     * @return array<int, array<string, mixed>>
     */
    public function findUpcomingEvents(int $limit = 3): array
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
                WHERE e.start_date >= NOW()
                ORDER BY e.start_date ASC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
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
}