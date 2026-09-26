<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/Database.php';

/** Accès SQL aux avis clients et à leur modération. */
class Review
{
    public const STATUS_PENDING = 'en attente';
    public const STATUS_APPROVED = 'validé';
    public const STATUS_REJECTED = 'refusé';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Retourne les événements terminés du client et l'éventuel avis associé. */
    public function findReviewableEvents(int $clientId): array
    {
        $stmt = $this->db->prepare("
            SELECT e.id AS event_id, e.title, e.start_date,
                   r.id AS review_id, r.rating, r.comment, r.status,
                   r.rejection_reason, r.created_at, r.updated_at
            FROM events e
            LEFT JOIN reviews r ON r.event_id = e.id
            WHERE e.client_id = ? AND e.status = 'terminé'
            ORDER BY e.start_date DESC, e.id DESC
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un avis ou remplace le contenu d'un avis refusé.
     *
     * @return string `created` ou `resubmitted`.
     */
    public function submit(int $eventId, int $clientId, int $rating, string $comment): string
    {
        if ($rating < 1 || $rating > 5 || mb_strlen($comment) < 10 || mb_strlen($comment) > 2000) {
            throw new InvalidArgumentException('Avis invalide.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT client_id,status FROM events WHERE id=? FOR UPDATE');
            $stmt->execute([$eventId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$event || (int)$event['client_id'] !== $clientId || $event['status'] !== 'terminé') {
                throw new DomainException('Cet événement ne peut pas recevoir votre avis.');
            }

            $stmt = $this->db->prepare('SELECT id,status FROM reviews WHERE event_id=? FOR UPDATE');
            $stmt->execute([$eventId]);
            $review = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$review) {
                $stmt = $this->db->prepare('INSERT INTO reviews(event_id,rating,comment) VALUES(?,?,?)');
                $stmt->execute([$eventId, $rating, $comment]);
                $result = 'created';
            } elseif ($review['status'] === self::STATUS_REJECTED) {
                $stmt = $this->db->prepare("
                    UPDATE reviews SET rating=?,comment=?,status=?,rejection_reason=NULL,
                        moderated_by=NULL,moderated_at=NULL WHERE id=? AND status=?
                ");
                $stmt->execute([$rating, $comment, self::STATUS_PENDING, $review['id'], self::STATUS_REJECTED]);
                if ($stmt->rowCount() !== 1) {
                    throw new DomainException('Cet avis a déjà changé.');
                }
                $result = 'resubmitted';
            } else {
                throw new DomainException('Un avis est déjà en cours de traitement ou publié pour cet événement.');
            }

            $this->db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /** Retourne tous les avis pour l'écran du personnel, les attentes en premier. */
    public function findAllForModeration(): array
    {
        $stmt = $this->db->query("
            SELECT r.*,e.title AS event_title,e.start_date,
                   u.firstname,u.lastname,c.name AS company_name,
                   m.firstname AS moderator_firstname,m.lastname AS moderator_lastname
            FROM reviews r
            INNER JOIN events e ON e.id=r.event_id
            INNER JOIN users u ON u.id=e.client_id
            LEFT JOIN companies c ON c.id=e.company_id
            LEFT JOIN users m ON m.id=r.moderated_by
            ORDER BY (r.status='en attente') DESC,r.updated_at DESC,r.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Valide ou refuse un avis encore en attente. */
    public function moderate(int $reviewId, int $moderatorId, string $status, ?string $reason): bool
    {
        if (!in_array($status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            return false;
        }
        if ($status === self::STATUS_REJECTED && ($reason === null || mb_strlen($reason) < 5 || mb_strlen($reason) > 500)) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE reviews
            SET status=?,rejection_reason=?,moderated_by=?,moderated_at=NOW()
            WHERE id=? AND status=?
        ");
        $stmt->execute([
            $status,
            $status === self::STATUS_REJECTED ? $reason : null,
            $moderatorId,
            $reviewId,
            self::STATUS_PENDING,
        ]);
        return $stmt->rowCount() === 1;
    }

    /** Retourne uniquement les témoignages publiables, du plus récent au plus ancien. */
    public function findApproved(?int $limit = null): array
    {
        $sql = "
            SELECT r.id,r.rating,r.comment,r.moderated_at,r.created_at,u.firstname
            FROM reviews r
            INNER JOIN events e ON e.id=r.event_id
            INNER JOIN users u ON u.id=e.client_id
            WHERE r.status='validé'
            ORDER BY r.moderated_at DESC,r.id DESC
        ";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
