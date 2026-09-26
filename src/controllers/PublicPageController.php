<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/SiteSetting.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/MailService.php';

/** Affiche les pages institutionnelles et traite le formulaire de contact. */
class PublicPageController extends BaseController
{
    public function showContact(): void
    {
        $this->startSession();
        $old = $_SESSION['contact_old'] ?? [];
        unset($_SESSION['contact_old']);
        $pageTitle = "Contact - Innov'Events";
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/public/contact.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function submitContact(): void
    {
        $this->startSession();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?action=contact');
            exit();
        }
        $this->validateCsrf($_POST);

        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        $consent = ($_POST['rgpd_consent'] ?? '') === '1';
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Le nom doit comporter de 2 à 100 caractères.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) $errors[] = 'Saisissez une adresse email valide.';
        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) $errors[] = 'L’objet doit comporter de 3 à 150 caractères.';
        if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) $errors[] = 'Le message doit comporter de 10 à 5 000 caractères.';
        if (!$consent) $errors[] = 'Votre accord est nécessaire pour traiter la demande.';

        if ($errors !== []) {
            $_SESSION['contact_error'] = implode(' ', $errors);
            $_SESSION['contact_old'] = compact('name', 'email', 'subject', 'message');
            header('Location: index.php?action=contact');
            exit();
        }

        if ((new MailService())->sendContactMessage($name, $email, $subject, $message)) {
            $_SESSION['contact_success'] = 'Votre message a bien été envoyé. Notre équipe vous répondra dans les meilleurs délais.';
            try {
                (new Log())->addLog('MESSAGE_CONTACT_ENVOYE', $_SESSION['user_id'] ?? null, [
                    'message' => 'Un message a été transmis depuis le formulaire public.',
                    'subject' => $subject,
                ]);
            } catch (Throwable $error) {
                error_log('[PublicPageController] ' . $error->getMessage());
            }
        } else {
            $_SESSION['contact_error'] = 'Le message n’a pas pu être envoyé. Veuillez réessayer ou écrire à contact@innovevents.fr.';
            $_SESSION['contact_old'] = compact('name', 'email', 'subject', 'message');
        }
        header('Location: index.php?action=contact');
        exit();
    }

    public function showLegal(string $page): void
    {
        $pages = [
            'mentions_legales' => ['Mentions légales', 'mentions_legales.php'],
            'cgu' => ['Conditions générales d’utilisation', 'cgu.php'],
            'cgv' => ['Conditions générales de vente', 'cgv.php'],
            'politique_confidentialite' => ['Politique de confidentialité', 'privacy.php'],
        ];
        if (!isset($pages[$page])) {
            http_response_code(404);
            return;
        }
        $this->startSession();
        [$heading, $view] = $pages[$page];
        $pageTitle = $heading . " - Innov'Events";
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/public/' . $view;
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function showSettings(): void
    {
        $this->checkAuth(['ADMIN']);
        $thankYouMessage = (new SiteSetting())->get(SiteSetting::QUOTE_THANK_YOU, SiteSetting::DEFAULT_QUOTE_THANK_YOU);
        $pageTitle = "Contenus publics - Innov'Events";
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/site_settings.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function updateSettings(): void
    {
        $this->checkAuth(['ADMIN']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: index.php?action=admin_site_settings');
            exit();
        }
        $this->validateCsrf($_POST);
        $message = trim((string)($_POST['quote_thank_you_message'] ?? ''));
        if (mb_strlen($message) < 20 || mb_strlen($message) > 1000) {
            $_SESSION['flash_error'] = 'Le message doit comporter de 20 à 1 000 caractères.';
        } elseif ((new SiteSetting())->set(SiteSetting::QUOTE_THANK_YOU, $message, (int)$_SESSION['user_id'])) {
            $_SESSION['flash_success'] = 'Le message de remerciement a été mis à jour.';
            try {
                (new Log())->addLog('CONTENU_PUBLIC_MODIFIE', (int)$_SESSION['user_id'], [
                    'message' => 'Le message de remerciement des demandes de devis a été modifié.',
                    'setting_key' => SiteSetting::QUOTE_THANK_YOU,
                ]);
            } catch (Throwable $error) {
                error_log('[PublicPageController] ' . $error->getMessage());
            }
        } else {
            $_SESSION['flash_error'] = 'La modification n’a pas pu être enregistrée.';
        }
        header('Location: index.php?action=admin_site_settings');
        exit();
    }
}
