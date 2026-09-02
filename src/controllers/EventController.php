<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/Event.php';

/**
 * Contrôleur : EventController (Vitrine publique)
 *
 * Orchestre l'affichage public du portfolio des événements validés.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.0.0
 */
class EventController extends BaseController
{
    /**
     * Affiche la liste des événements publiés avec moteur de recherche multicritère.
     */
    public function listPublicEvents(): void
    {
        $this->startSession();

        $dateStart = !empty($_GET['date_start']) ? trim($_GET['date_start']) : null;
        $dateEnd   = !empty($_GET['date_end']) ? trim($_GET['date_end']) : null;
        $type      = !empty($_GET['type']) ? trim($_GET['type']) : null;
        $theme     = !empty($_GET['theme']) ? trim($_GET['theme']) : null;

        $eventModel = new Event();
        $events = $eventModel->findPublishedEvents($dateStart, $dateEnd, $type, $theme);
        $criteria = $eventModel->getFilterCriteria();

        $pageTitle = "Nos Réalisations Événementielles - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/public/events.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Affiche le détail d'un événement public avec la passerelle devis.
     */
    public function showPublicDetail(): void
    {
        $this->startSession();

        $id = (int)($_GET['id'] ?? 0);
        $eventModel = new Event();
        $event = $eventModel->findPublishedById($id);

        if (!$event) {
            header('Location: index.php?action=events');
            exit();
        }

        $pageTitle = htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') . " - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/public/event_detail.php';
        require __DIR__ . '/../views/partials/footer.php';
    }
}