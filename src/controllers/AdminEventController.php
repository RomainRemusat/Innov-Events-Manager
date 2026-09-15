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
     * Les refus métier et erreurs de persistance sont signalés à l'utilisateur.
     * Une modification effective journalise les états avant/après conformément à l'ECF p. 13.
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

        $newStatus = Event::normalizeStatus($newStatus);
        $eventModel = new Event();
        $event = $eventId > 0 ? $eventModel->findByIdWithClient($eventId) : null;
        if (!$event || !isset(Event::STATUS_LABELS[$newStatus])) {
            $_SESSION['flash_error'] = "Événement introuvable ou statut non autorisé.";
        } elseif ($event['status'] === $newStatus) {
            $_SESSION['flash_success'] = "Le statut de l'événement est déjà à jour.";
        } elseif ($eventModel->updateStatus($eventId, $newStatus, $event['status'])) {

            // Audit NoSQL
            $logger = new Log();
            $logger->addLog(
                'MODIFICATION_STATUT_EVENEMENT',
                (int)$_SESSION['user_id'],
                [
                    'event_id'   => $eventId,
                    'old_status' => $event['status'],
                    'new_status' => $newStatus
                ]
            );

            $_SESSION['flash_success'] = "Statut de l'événement mis à jour avec succès.";
        } else {
            $_SESSION['flash_error'] = "Statut non modifié. Le passage en cours exige un dernier devis associé accepté. Le dossier a aussi pu être modifié entre-temps ; rechargez la page.";
        }

        header('Location: ' . ($event ? "index.php?action=admin_event_detail&id={$eventId}" : 'index.php?action=admin_events'));
        exit();
    }

    /**
     * Applique la visibilité demandée avec attestation explicite de l'accord client.
     * Le nom de la route historique est conservé ; aucune bascule implicite n'est effectuée.
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

        $publication = $_POST['publish'] ?? '';
        $confirmed = ($_POST['publication_consent'] ?? '') === '1';
        if ($eventId <= 0 || !in_array($publication, ['0', '1'], true)) {
            $_SESSION['flash_error'] = 'Événement ou action de publication invalide.';
        } elseif ($publication === '1' && !$confirmed) {
            $_SESSION['flash_error'] = "Confirmez l'accord du client avant de demander la publication.";
        } elseif ((new Event())->setPublication($eventId, $publication === '1', $confirmed, (int)$_SESSION['user_id'])) {
            (new Log())->addLog('MODIFICATION_PUBLICATION_EVENEMENT', (int)$_SESSION['user_id'], [
                'event_id' => $eventId,
                'is_published' => $publication === '1',
                'publication_consent_confirmed' => $publication === '1' && $confirmed,
            ]);
            $_SESSION['flash_success'] = $publication === '1'
                ? 'Accord enregistré. La publication est activée uniquement hors brouillon.'
                : 'Événement masqué. Une republication nécessitera une nouvelle confirmation.';
        } else {
            $_SESSION['flash_error'] = 'Publication non modifiée : événement introuvable, déjà à jour ou erreur de sauvegarde. Rechargez la page.';
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
