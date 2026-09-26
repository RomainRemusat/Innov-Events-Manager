<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/Database.php';

/**
 * Modèle de données : Note (DAL SQL)
 *
 * Gère les notes collaboratives rattachées aux projets ainsi que les informations
 * globales destinées à l'équipe, représentées par un event_id nul.
 *
 * @package    InnovEventsManager
 * @subpackage Models\SQL
 * @author     Romain Remusat
 * @version    2.0.0
 */
class Note
{
    private \PDO $db;

    /** Initialise l'accès aux notes collaboratives. */
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
     * Extrait les dernières notes destinées au tableau de bord de l'équipe.
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
        if ($content === '' || mb_strlen($content) > 10000) {
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

    /** Modifie une note si l'acteur en est l'auteur ou possède le rôle administrateur. */
    public function update(int $id, int $actorId, bool $isAdmin, string $content): bool
    {
        $content = trim($content);
        if ($content === '' || mb_strlen($content) > 10000) return false;
        try {
            $stmt = $this->db->prepare('UPDATE notes SET content=:content WHERE id=:id'
                . ($isAdmin ? '' : ' AND user_id=:actor_id'));
            $parameters = [':content' => $content, ':id' => $id];
            if (!$isAdmin) $parameters[':actor_id'] = $actorId;
            $stmt->execute($parameters);
            return $stmt->rowCount() === 1;
        } catch (\PDOException $e) {
            error_log(sprintf('[Note::update] Erreur SQL note #%d : %s', $id, $e->getMessage()));
            return false;
        }
    }

    /**
     * Supprime une note appartenant à l'auteur connecté, ou toute note pour un administrateur.
     * @return bool Vrai uniquement si une note autorisée a été supprimée.
     */
    public function delete(int $id, int $actorId, bool $isAdmin): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM notes WHERE id=:id'
                . ($isAdmin ? '' : ' AND user_id=:actor_id'));
            $parameters = [':id' => $id];
            if (!$isAdmin) $parameters[':actor_id'] = $actorId;
            $stmt->execute($parameters);
            return $stmt->rowCount() === 1;
        } catch (\PDOException $e) {
            error_log(sprintf('[Note::delete] Erreur SQL suppression note #%d : %s', $id, $e->getMessage()));
            return false;
        }
    }

    /** @return array<string, mixed>|null Note utilisée pour contrôler le retour après une action. */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id,event_id,user_id,content FROM notes WHERE id=?');
        $stmt->execute([$id]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        return $note ?: null;
    }
}
