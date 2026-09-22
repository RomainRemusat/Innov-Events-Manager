<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/Database.php';

/** Gère les tâches opérationnelles rattachées aux événements. */
class Task
{
    public const STATUS_LABELS = [
        'à faire' => 'À faire',
        'en cours' => 'En cours',
        'terminée' => 'Terminée',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array<int, array<string, mixed>> Tâches d'un événement avec leur employé assigné. */
    public function findByEventId(int $eventId): array
    {
        $stmt = $this->db->prepare("SELECT t.*, u.firstname, u.lastname
            FROM tasks t INNER JOIN users u ON u.id=t.assigned_user_id
            WHERE t.event_id=? ORDER BY FIELD(t.status,'en cours','à faire','terminée'), t.id");
        $stmt->execute([$eventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Renvoie les tâches d'un employé avec le contexte nécessaire à leur traitement.
     *
     * @return array<int, array<string, mixed>> Tâches triées par priorité puis par date d'événement.
     */
    public function findByAssignedUserId(int $employeeId): array
    {
        $stmt = $this->db->prepare("SELECT t.*, e.title AS event_title, e.start_date,
                e.location, c.firstname AS client_firstname, c.lastname AS client_lastname
            FROM tasks t
            INNER JOIN events e ON e.id=t.event_id
            INNER JOIN users c ON c.id=e.client_id
            WHERE t.assigned_user_id=?
            ORDER BY FIELD(t.status,'en cours','à faire','terminée'), e.start_date, t.id");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée une tâche uniquement pour un employé actif et un événement existant.
     * @return int|null Identifiant créé, ou null si les relations sont invalides.
     */
    public function create(int $eventId, int $employeeId, int $creatorId, string $title): ?int
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 255) return null;
        $stmt = $this->db->prepare("INSERT INTO tasks(event_id,assigned_user_id,created_by,title)
            SELECT e.id,u.id,a.id,? FROM events e, users u, users a
            WHERE e.id=? AND u.id=? AND u.role='EMPLOYEE' AND u.is_deleted=0
              AND a.id=? AND a.role='ADMIN' AND a.is_deleted=0");
        $stmt->execute([$title, $eventId, $employeeId, $creatorId]);
        return $stmt->rowCount() === 1 ? (int)$this->db->lastInsertId() : null;
    }

    /**
     * Change le statut. Un employé ne peut faire avancer que sa propre tâche, étape par étape.
     * @return int|null Identifiant de l'événement si la modification a réussi.
     */
    public function updateStatus(int $id, string $status, int $actorId, bool $isAdmin): ?int
    {
        if (!isset(self::STATUS_LABELS[$status])) return null;
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT event_id,assigned_user_id,status FROM tasks WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$task) throw new InvalidArgumentException('Tâche introuvable.');
            $next = ['à faire' => 'en cours', 'en cours' => 'terminée'];
            if (!$isAdmin && ((int)$task['assigned_user_id'] !== $actorId || ($next[$task['status']] ?? null) !== $status)) {
                throw new InvalidArgumentException('Cette tâche ne peut pas être modifiée par ce compte.');
            }
            if ($task['status'] === $status) throw new InvalidArgumentException('Le statut est déjà à jour.');
            $stmt = $this->db->prepare('UPDATE tasks SET status=? WHERE id=? AND status=?');
            $stmt->execute([$status, $id, $task['status']]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('La tâche a été modifiée entre-temps.');
            $this->db->commit();
            return (int)$task['event_id'];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    /** @return int|null Identifiant de l'événement si la suppression a réussi. */
    public function delete(int $id): ?int
    {
        $stmt = $this->db->prepare('SELECT event_id FROM tasks WHERE id=?');
        $stmt->execute([$id]);
        $eventId = $stmt->fetchColumn();
        if ($eventId === false) return null;
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1 ? (int)$eventId : null;
    }
}
