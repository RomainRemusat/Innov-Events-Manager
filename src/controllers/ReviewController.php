<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/Review.php';
require_once __DIR__ . '/../models/nosql/Log.php';

/** Coordonne le dépôt, la modération et la publication des avis. */
class ReviewController extends BaseController
{
    public function showPublic(): void
    {
        $this->startSession();
        $reviews = (new Review())->findApproved();
        $pageTitle = "Avis clients - Innov'Events";
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/public/reviews.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function submit(): void
    {
        $this->checkAuth(['CLIENT']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?action=client_dashboard');
            exit();
        }
        $this->validateCsrf($_POST);

        $eventId = (int)($_POST['event_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim((string)($_POST['comment'] ?? ''));
        if ($eventId <= 0 || $rating < 1 || $rating > 5 || mb_strlen($comment) < 10 || mb_strlen($comment) > 2000) {
            $_SESSION['client_error'] = 'Choisissez une note de 1 à 5 et saisissez un commentaire de 10 à 2 000 caractères.';
            header('Location: index.php?action=client_dashboard#reviews');
            exit();
        }

        try {
            $result = (new Review())->submit($eventId, (int)$_SESSION['user_id'], $rating, $comment);
            $this->log($result === 'created' ? 'AVIS_CLIENT_SOUMIS' : 'AVIS_CLIENT_RESOUMIS', [
                'message' => 'Avis client transmis pour modération.',
                'event_id' => $eventId,
                'rating' => $rating,
            ]);
            $_SESSION['client_success'] = 'Votre avis a été transmis à notre équipe pour modération.';
        } catch (Throwable $error) {
            $_SESSION['client_error'] = $error instanceof DomainException
                ? $error->getMessage()
                : 'Votre avis n’a pas pu être enregistré. Veuillez réessayer.';
        }
        header('Location: index.php?action=client_dashboard#reviews');
        exit();
    }

    public function showStaff(): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
        $reviews = (new Review())->findAllForModeration();
        $pageTitle = "Modération des avis - Innov'Events";
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/reviews.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function moderate(): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?action=staff_reviews');
            exit();
        }
        $this->validateCsrf($_POST);

        $reviewId = (int)($_POST['review_id'] ?? 0);
        $action = (string)($_POST['moderation_action'] ?? '');
        $reason = trim((string)($_POST['rejection_reason'] ?? ''));
        $status = match ($action) {
            'approve' => Review::STATUS_APPROVED,
            'reject' => Review::STATUS_REJECTED,
            default => '',
        };
        if ($reviewId <= 0 || $status === ''
            || ($status === Review::STATUS_REJECTED && (mb_strlen($reason) < 5 || mb_strlen($reason) > 500))) {
            $_SESSION['flash_error'] = 'Décision invalide. Un refus doit comporter un motif de 5 à 500 caractères.';
            header('Location: index.php?action=staff_reviews');
            exit();
        }

        if ((new Review())->moderate($reviewId, (int)$_SESSION['user_id'], $status, $reason ?: null)) {
            $this->log('AVIS_MODERE', [
                'message' => 'Décision de modération enregistrée.',
                'review_id' => $reviewId,
                'status' => $status,
            ]);
            $_SESSION['flash_success'] = $status === Review::STATUS_APPROVED ? 'Avis publié.' : 'Avis refusé.';
        } else {
            $_SESSION['flash_error'] = 'Cet avis a déjà été traité ou la décision est invalide.';
        }
        header('Location: index.php?action=staff_reviews');
        exit();
    }

    private function log(string $action, array $details): void
    {
        try {
            (new Log())->addLog($action, (int)$_SESSION['user_id'], $details);
        } catch (Throwable $error) {
            error_log('[ReviewController] ' . $error->getMessage());
        }
    }
}
