<?php
require_once __DIR__ . '/../../config/Database.php';

class Event
{
    public function findUpcomingEvents(int $limit = 3): array
    {
        $db = Database::getInstance();

        // On joint la table users (et companies) pour récupérer le client associé
        $sql = "SELECT e.id, e.title, e.event_date, e.status, u.firstname, u.lastname, c.name AS company_name
                FROM events e
                JOIN users u ON e.client_id = u.id
                LEFT JOIN companies c ON e.company_id = c.id
                WHERE e.event_date >= NOW()
                ORDER BY e.event_date ASC
                LIMIT :limit";

        $stmt = $db->prepare($sql);
        // PDO::PARAM_INT est crucial ici pour que la clause LIMIT fonctionne correctement en PDO
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPastEvents(int $limit = 3): array
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM events WHERE event_date < NOW() ORDER BY event_date DESC LIMIT :limit";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}