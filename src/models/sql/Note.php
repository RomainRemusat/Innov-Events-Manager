<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/Database.php';

/**
 * Modèle de données : Note (DAL SQL)
 *
 * Gère le cycle de vie complet des notes collaboratives de projets (Chloé & José)
 * ainsi que les notes globales d'équipe décorrélées (event_id NULL).
 *
 * Exigences respectées (ECF Titre CDA) :
 * - AT1 : Requêtes préparées PDO contre les injections SQL (CWE-89).
 * - AT2 : Clé étrangère event_id nullable conforme au CDC p. 11.
 *
 * @package    InnovEventsManager
 * @subpackage Models\SQL
 * @author     Romain Remusat
 * @version    2.0.0
 */
class Note
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère toutes les notes d'un événement donné pour la fiche projet.
     *
     * @param int $eventId
     * @return array<int, array<string, mixed>>
     */
    public function findByEventId(int $eventId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT n.id,
                       n.event_id,
                       n.user_id,
                       n.content,
                       n.created_at,
                       u.firstname,
                       u.lastname,
                       u.role AS user_role
                FROM notes n
                INNER JOIN users u ON n.user_id = u.id
                WHERE n.event_id = :event_id
                ORDER BY n.created_at DESC
            ");
            $stmt->execute([':event_id' => $eventId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log(sprintf("[Note::findByEventId] Erreur SQL event #%d : %s", $eventId, $e->getMessage()));
            return [];
        }
    }

    /**
     * Extrait les 5 dernières notes pour le widget Dashboard de Chloé (CDC p. 11).
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function findLatestNotes(int $limit = 5): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT n.id,
                       n.content,
                       n.created_at,
                       n.event_id,
                       u.firstname,
                       u.lastname,
                       COALESCE(e.title, 'Note d\'équipe globale') AS event_title
                FROM notes n
                INNER JOIN users u ON n.user_id = u.id
                LEFT JOIN events e ON n.event_id = e.id
                ORDER BY n.created_at DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("[Note::findLatestNotes] Erreur SQL : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Crée une nouvelle note (liée à un projet ou globale).
     *
     * @param int|null $eventId ID de l'événement ou null pour une note globale.
     * @param int      $userId  Auteur de la note.
     * @param string   $content Contenu textuel.
     * @return bool
     */
    public function create(?int $eventId, int $userId, string $content): bool
    {
        $content = trim($content);
        if ($content === '') {
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO notes (event_id, user_id, content, created_at)
                VALUES (:event_id, :user_id, :content, NOW())
            ");
            return $stmt->execute([
                ':event_id' => $eventId,
                ':user_id'  => $userId,
                ':content'  => $content
            ]);
        } catch (\PDOException $e) {
            error_log("[Note::create] Erreur insertion note : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime une note par son identifiant.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM notes WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (\PDOException $e) {
            error_log(sprintf("[Note::delete] Erreur SQL suppression note #%d : %s", $id, $e->getMessage()));
            return false;
        }
    }
}