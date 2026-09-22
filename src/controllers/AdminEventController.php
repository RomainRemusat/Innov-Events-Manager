<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/Event.php';
require_once __DIR__ . '/../models/sql/Note.php';
require_once __DIR__ . '/../models/sql/Devis.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/EventManagementService.php';

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
     * Une modification effective journalise les états avant et après.
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
     * Ouvre un premier devis brouillon depuis la fiche du projet.
     * @param array $data Identifiant de l'événement, téléphone du contact et jeton CSRF.
     */
    public function createQuote(array $data): void
    {
        $this->checkAuth(['ADMIN']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_events');
            exit;
        }
        $this->validateCsrf($data);
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
        $id = filter_var($data['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        try {
            if (!$id || !is_string($data['phone'] ?? null)) throw new InvalidArgumentException('Événement ou téléphone invalide.');
            $quoteId = (new Devis())->createForEvent($id, $data['phone']);
            (new Log())->addLog('CREATION_DEVIS', (int)$_SESSION['user_id'], ['event_id' => $id, 'devis_id' => $quoteId]);
            $_SESSION['flash_success'] = 'Devis brouillon créé. Ajoutez les prestations avant de l’envoyer au client.';
            header('Location: index.php?action=edit_devis&id=' . $quoteId);
            exit;
        } catch (InvalidArgumentException $error) {
            $_SESSION['flash_error'] = $error->getMessage();
        } catch (Throwable $error) {
            error_log('[AdminEventController::createQuote] ' . $error->getMessage());
            $_SESSION['flash_error'] = 'Le devis n’a pas pu être créé. Veuillez réessayer.';
        }
        header('Location: index.php?action=admin_event_detail&id=' . ($id ?: 0));
        exit;
    }

    /** Affiche le formulaire de création ou d'édition, réservé à l'administrateur. */
    public function editEvent(int $id = 0): void
    {
        $this->checkAuth(['ADMIN']);
        $event = $id > 0 ? (new Event())->findByIdAdmin($id) : null;
        if ($id > 0 && !$event) {
            $_SESSION['flash_error'] = 'Événement introuvable.';
            header('Location: index.php?action=admin_events');
            exit;
        }
        $clients = (new User())->findAllClients();
        $form = $event ?? [];
        foreach (['start_date', 'end_date'] as $field) {
            $form[$field] = str_replace(' ', 'T', $form[$field] ?? '');
        }
        $form['publish'] = (string)($event['is_published'] ?? '0');
        if (($_SESSION['event_form']['id'] ?? null) === $id) {
            $form = array_replace($form, $_SESSION['event_form']['data']);
        }
        unset($_SESSION['event_form']);
        $pageTitle = $event ? 'Modifier l’événement' : 'Créer un événement';
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/edit_event.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /** @param array $data Informations logistiques soumises par l'administrateur. */
    public function saveEvent(array $data): void
    {
        $this->checkAuth(['ADMIN']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_events');
            exit;
        }
        $this->validateCsrf($data);
        unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);
        $id = filter_var($data['event_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        try {
            if ($id === false) throw new InvalidArgumentException('Identifiant d’événement invalide.');
            $result = (new EventManagementService())->save($id ?: null, $data, $_FILES['event_image'] ?? null, (int)$_SESSION['user_id']);
            unset($_SESSION['event_form']);
            $_SESSION['flash_success'] = 'Événement enregistré.';
            if ($result['image_cleanup_failed']) $_SESSION['flash_warning'] = 'Le projet est enregistré, mais l’ancienne image n’a pas pu être supprimée du disque.';
            header('Location: index.php?action=admin_event_detail&id=' . $result['id']);
            exit;
        } catch (InvalidArgumentException $error) {
            $_SESSION['flash_error'] = $error->getMessage();
        } catch (Throwable $error) {
            error_log('[AdminEventController::saveEvent] ' . $error->getMessage());
            $_SESSION['flash_error'] = 'L’événement n’a pas pu être enregistré. Veuillez réessayer.';
        }
        $_SESSION['event_form'] = ['id' => $id ?: 0, 'data' => array_filter($data, 'is_scalar')];
        header('Location: index.php?action=admin_edit_event&id=' . ($id ?: 0));
        exit;
    }

    /** Supprime un événement après confirmation, en conservant ses devis et leurs PDF. */
    public function deleteEvent(array $data): void
    {
        $this->checkAuth(['ADMIN']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_events');
            exit;
        }
        $this->validateCsrf($data);
        unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);
        try {
            $id = filter_var($data['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id || ($data['confirm_delete'] ?? '') !== '1') throw new InvalidArgumentException('Confirmez la suppression de l’événement.');
            $cleaned = (new EventManagementService())->delete($id, (int)$_SESSION['user_id']);
            $_SESSION['flash_success'] = 'Événement supprimé. Les devis associés sont conservés.';
            if (!$cleaned) $_SESSION['flash_warning'] = 'L’événement est supprimé, mais son ancienne image nécessite un nettoyage manuel.';
        } catch (InvalidArgumentException $error) {
            $_SESSION['flash_error'] = $error->getMessage();
        } catch (Throwable $error) {
            error_log('[AdminEventController::deleteEvent] ' . $error->getMessage());
            $_SESSION['flash_error'] = 'La suppression de l’événement n’a pas pu être finalisée.';
        }
        header('Location: index.php?action=admin_events');
        exit;
    }

    /** Remplace l'illustration sans écraser les autres informations du projet. */
    public function uploadImage(): void
    {
        $this->checkAuth(['ADMIN']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_events');
            exit;
        }
        $this->validateCsrf($_POST);
        unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);
        $id = (int)($_POST['event_id'] ?? 0);
        try {
            $file = $_FILES['event_image'] ?? null;
            if (!$file || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) throw new InvalidArgumentException('Sélectionnez une image.');
            $result = (new EventManagementService())->save($id, [], $file, (int)$_SESSION['user_id'], true);
            $_SESSION['flash_success'] = 'Illustration mise à jour.';
            if ($result['image_cleanup_failed']) $_SESSION['flash_warning'] = 'L’ancienne image n’a pas pu être supprimée du disque.';
        } catch (InvalidArgumentException $error) {
            $_SESSION['flash_error'] = $error->getMessage();
        } catch (Throwable $error) {
            error_log('[AdminEventController::uploadImage] ' . $error->getMessage());
            $_SESSION['flash_error'] = 'L’image n’a pas pu être enregistrée.';
        }
        header('Location: index.php?action=admin_event_detail&id=' . $id);
        exit;
    }
}
