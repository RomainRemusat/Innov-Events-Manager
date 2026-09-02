<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/Event.php';
require_once __DIR__ . '/../models/sql/Note.php';
require_once __DIR__ . '/../models/nosql/Log.php';

/**
 * Contrôleur Back-Office : Pilotage opérationnel des événements et notes.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.2.0
 */
class AdminEventController extends BaseController
{
    private function checkStaffAccess(): void
    {
        $this->startSession();
        $role = $_SESSION['user_role'] ?? '';
        if (!in_array($role, ['ADMIN', 'EMPLOYEE'], true)) {
            header('Location: index.php?action=login');
            exit();
        }
    }

    /**
     * Liste des événements de l'agence (vue tableau).
     */
    public function listEvents(): void
    {
        $this->checkStaffAccess();

        $eventModel = new Event();
        $events = $eventModel->findAllAdmin();

        $pageTitle = "Gestion des Événements - Administration";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/events_list.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Affiche la fiche détaillée d'un événement avec ses notes de terrain.
     */
    public function showEventDetail(): void
    {
        $this->checkStaffAccess();

        $eventId = (int)($_GET['id'] ?? 0);
        $eventModel = new Event();
        $event = $eventModel->findByIdAdmin($eventId);

        if (!$event) {
            header('Location: index.php?action=admin_events');
            exit();
        }

        $noteModel = new Note();
        $notes = $noteModel->findByEventId($eventId);

        $pageTitle = "Projet : " . htmlspecialchars($event['title']) . " - Back-Office";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/event_detail.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Modifie le statut opérationnel d'un événement avec audit NoSQL (CDC p. 13).
     */
    public function updateStatus(): void
    {
        $this->checkStaffAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_events');
            exit();
        }

        $eventId   = (int)($_POST['event_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');

        $eventModel = new Event();
        $currentEvent = $eventModel->findByIdAdmin($eventId);

        if ($currentEvent && $newStatus !== '') {
            $oldStatus = $currentEvent['status'];

            if ($eventModel->updateStatus($eventId, $newStatus)) {
                // Journalisation d'audit MongoDB conforme CDC p. 13
                $logger = new Log();
                $logger->addLog(
                    'MODIFICATION_STATUT_EVENEMENT',
                    (int)$_SESSION['user_id'],
                    [
                        'event_id'   => $eventId,
                        'event_title'=> $currentEvent['title'],
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus
                    ]
                );
            }
        }

        header('Location: index.php?action=admin_events');
        exit();
    }

    /**
     * Enregistre une note collaborative à chaud sur un projet.
     */
    public function addNote(): void
    {
        $this->checkStaffAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_events');
            exit();
        }

        $eventId = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
        $content = trim($_POST['content'] ?? '');
        $userId  = (int)$_SESSION['user_id'];

        if ($content !== '') {
            $noteModel = new Note();
            $noteModel->create($eventId, $userId, $content);
        }

        if ($eventId !== null) {
            header("Location: index.php?action=admin_event_detail&id={$eventId}");
        } else {
            header("Location: index.php?action=dashboard");
        }
        exit();
    }
}