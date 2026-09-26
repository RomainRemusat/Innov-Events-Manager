<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/Event.php';
require_once __DIR__ . '/../models/sql/Note.php';
require_once __DIR__ . '/../models/nosql/Log.php';

/** Interface mobile installable destinée au personnel en déplacement. */
class MobileController extends BaseController
{
    public function dashboard(): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
        $events = (new Event())->findUpcomingEvents(25);
        require __DIR__ . '/../views/mobile/dashboard.php';
    }

    public function event(): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $event = $id ? (new Event())->findMobileById((int)$id) : null;
        if (!$event) {
            http_response_code(404);
            $_SESSION['mobile_error'] = 'Événement introuvable.';
            header('Location: index.php?action=mobile_dashboard');
            exit;
        }
        $notes = (new Note())->findByEventId((int)$id);
        require __DIR__ . '/../views/mobile/event.php';
    }

    public function addNote(): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit;
        }
        $this->validateCsrf($_POST);
        $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $content = is_string($_POST['content'] ?? null) ? trim($_POST['content']) : '';
        if (!$eventId || !(new Event())->findMobileById((int)$eventId)) {
            $_SESSION['mobile_error'] = 'Événement introuvable.';
            header('Location: index.php?action=mobile_dashboard');
            exit;
        }
        if ($content === '' || mb_strlen($content) > 10000) {
            $_SESSION['mobile_error'] = 'La note est obligatoire et limitée à 10 000 caractères.';
        } elseif ((new Note())->create((int)$eventId, (int)$_SESSION['user_id'], $content)) {
            $_SESSION['mobile_success'] = 'Note enregistrée.';
            (new Log())->addLog('CREATION_NOTE', (int)$_SESSION['user_id'], [
                'event_id' => (int)$eventId,
                'source' => 'application_mobile',
            ]);
        } else {
            $_SESSION['mobile_error'] = 'La note n’a pas pu être enregistrée.';
        }
        header('Location: index.php?action=mobile_event&id=' . (int)$eventId);
        exit;
    }
}
