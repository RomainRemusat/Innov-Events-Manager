<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/Event.php';
require_once __DIR__ . '/../models/sql/Note.php';
require_once __DIR__ . '/../models/sql/Devis.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/FileUploadService.php';

/**
 * Contrôleur : AdminEventController (Back-Office)
 * Gère le cycle de vie des événements côté back-office (Chloé & José).
 */
class AdminEventController extends BaseController
{
    /**
     * Vérifie que l'utilisateur est membre du staff (ADMIN ou EMPLOYEE).
     */
    private function checkStaffAccess(): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
    }

    /**
     * Affiche le catalogue complet des événements dans le back-office.
     */
    public function listEvents(): void
    {
        $this->checkStaffAccess();

        $eventModel = new Event();
        $events = $eventModel->findAllWithClient();

        $pageTitle = "Catalogue des Événements - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/events_list.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Affiche la vue détaillée d'un événement avec ses notes et ses prestations de devis.
     */
    public function showEventDetail(): void
    {
        $this->checkStaffAccess();

        $eventId = (int)($_GET['id'] ?? 0);

        if ($eventId <= 0) {
            header('Location: index.php?action=admin_events');
            exit();
        }

        $eventModel = new Event();
        $event = $eventModel->findByIdWithClient($eventId);

        if (!$event) {
            header('Location: index.php?action=admin_events');
            exit();
        }

        $noteModel = new Note();
        $notes = $noteModel->findByEventId($eventId);

        // Ne jamais substituer le devis d'un autre projet du même client.
        $devisModel = new Devis();
        $associatedDevis = $devisModel->findByEventIdWithPrestations($eventId);

        $pageTitle = "Détail Événement - " . htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/event_detail.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Met à jour le statut opérationnel d'un événement (ADMIN uniquement).
     */
    public function updateStatus(): void
    {
        $this->checkAuth(['ADMIN']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_events');
            exit();
        }

        $this->validateCsrf($_POST);

        $eventId   = (int)($_POST['event_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');

        $validStatuses = ['brouillon', 'en cours', 'terminé', 'annulé'];

        if ($eventId > 0 && in_array($newStatus, $validStatuses, true)) {
            $eventModel = new Event();
            $eventModel->updateStatus($eventId, $newStatus);

            // Audit NoSQL
            $logger = new Log();
            $logger->addLog(
                'MODIFICATION_STATUT_EVENEMENT',
                (int)$_SESSION['user_id'],
                [
                    'event_id'   => $eventId,
                    'new_status' => $newStatus
                ]
            );

            $_SESSION['flash_success'] = "Statut de l'événement mis à jour avec succès.";
        }

        header("Location: index.php?action=admin_event_detail&id={$eventId}");
        exit();
    }

    /**
     * Bascule la visibilité publique d'un événement (ADMIN uniquement).
     */
    public function togglePublish(): void
    {
        $this->checkAuth(['ADMIN']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_events');
            exit();
        }

        $this->validateCsrf($_POST);

        $eventId = (int)($_POST['event_id'] ?? 0);

        if ($eventId > 0) {
            $eventModel = new Event();
            $eventModel->togglePublish($eventId);
        }

        header("Location: index.php?action=admin_event_detail&id={$eventId}");
        exit();
    }

    /**
     * Traite l'ajout d'une note collaborative sur un événement.
     */
    public function addNote(): void
    {
        $this->checkStaffAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_events');
            exit();
        }

        $this->validateCsrf($_POST);

        $eventId = isset($_POST['event_id']) && (int)$_POST['event_id'] > 0 ? (int)$_POST['event_id'] : null;
        $content = trim($_POST['content'] ?? '');

        // Restriction : un employé ne peut pas créer de note globale sans événement
        if ($eventId === null && ($_SESSION['user_role'] ?? '') === 'EMPLOYEE') {
            header('Location: index.php?action=admin_events');
            exit();
        }

        if (!empty($content)) {
            $noteModel = new Note();
            if ($noteModel->create($eventId, (int)$_SESSION['user_id'], $content)) {
                $_SESSION['flash_success'] = "Note ajoutée avec succès.";
            } else {
                $_SESSION['flash_error'] = "Erreur lors de l'enregistrement de la note.";
            }
        }

        if ($eventId !== null) {
            header("Location: index.php?action=admin_event_detail&id={$eventId}");
        } else {
            header('Location: index.php?action=admin_events');
        }
        exit();
    }

    /**
     * Traite l'upload d'image d'illustration pour un événement.
     */
    public function uploadImage(): void
    {
        $this->checkAuth(['ADMIN']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_events');
            exit();
        }

        $this->validateCsrf($_POST);

        $eventId = (int)($_POST['event_id'] ?? 0);

        if (!isset($_FILES['event_image']) || $_FILES['event_image']['error'] !== UPLOAD_ERR_OK) {
            header("Location: index.php?action=admin_event_detail&id={$eventId}&error=upload_failed");
            exit();
        }

        try {
            $uploader = new FileUploadService();
            $imagePath = $uploader->uploadEventImage($_FILES['event_image']);

            if ($imagePath) {
                $eventModel = new Event();
                $eventModel->updateImage($eventId, $imagePath);

                // Audit NoSQL
                $logger = new Log();
                $logger->addLog(
                    'UPLOAD_IMAGE_EVENEMENT',
                    (int)$_SESSION['user_id'],
                    [
                        'event_id'   => $eventId,
                        'image_path' => $imagePath
                    ]
                );

                header("Location: index.php?action=admin_event_detail&id={$eventId}&success=image_updated");
                exit();
            }

            header("Location: index.php?action=admin_event_detail&id={$eventId}&error=upload_failed");
            exit();
        } catch (\Exception $e) {
            header("Location: index.php?action=admin_event_detail&id={$eventId}&error=" . urlencode($e->getMessage()));
            exit();
        }
    }
}
