<?php
require_once __DIR__ . '/../../config/Database.php';

class Note
{
    public function findLatestNotes(int $limit = 5): array
    {
        $db = Database::getInstance();

        $sql = "SELECT n.content, n.created_at, u.firstname, u.lastname, e.title AS event_title
                FROM notes n
                JOIN users u ON n.user_id = u.id
                LEFT JOIN events e ON n.event_id = e.id
                ORDER BY n.created_at DESC
                LIMIT :limit";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}