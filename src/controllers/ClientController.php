<?php
/**
 * Contrôleur : ClientController (Espace Personnel & Suivi Commercial B2B)
 *
 * Ce contrôleur orchestre le parcours utilisateur au sein de l'espace client dédié :
 * - Consultation de l'état d'avancement des devis.
 * - Réponse contractuelle (Validation, Refus, Demande de modification).
 * - Traçabilité multi-bases (Mise à jour MySQL et flux d'audit MongoDB).
 * - Notifications e-mail transactionnelles vers l'équipe commerciale (Chloé).
 * - Gestion du profil et droit à l'oubli RGPD (Suppression définitive sécurisée).
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    2.5.0
 */

// 1. Héritage du contrôleur de base (Sécurité centralisée)
require_once __DIR__ . '/BaseController.php';

// 2. Modèles et Services nécessaires
require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/sql/Prospect.php';
require_once __DIR__ . '/../models/sql/Devis.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/MailService.php';

class ClientController extends BaseController
{
    /**
     * Point d'entrée principal : Affiche le tableau de bord client avec la liste des devis.
     *
     * @return void
     */
    public function showDashboard(): void
    {
        $this->checkAuth(['CLIENT']);

        $clientId = (int)$_SESSION['user_id'];
        $clientName = trim(($_SESSION['user_firstname'] ?? '') . ' ' . ($_SESSION['user_lastname'] ?? ''));
        $clientEmail = $_SESSION['user_email'] ?? '';

        $prospectModel = new Prospect();
        $myQuotes = $prospectModel->findClientRequests($clientId);
        foreach ($myQuotes as &$quote) {
            $fileName = $quote['reference_pdf'] ?? '';
            $quote['is_pdf_available'] = $fileName !== ''
                && $fileName === basename($fileName)
                && strtolower($quote['status'] ?? 'brouillon') !== 'brouillon'
                && is_file(__DIR__ . '/../../storage/devis/' . $fileName);
        }
        unset($quote);

        require __DIR__ . '/../views/client/dashboard.php';
    }

    /**
     * Traite l'arbitrage du client sur un devis (Accepter / Refuser / Demande de modification).
     *
     * @param array $postData Payload soumis via formulaire POST
     * @return void
     */
    public function handleQuoteResponse(array $postData): void
    {
        $this->checkAuth(['CLIENT']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=client_dashboard');
            exit();
        }

        // 1. Validation Anti-CSRF
        $this->validateCsrf($postData);

        // 2. Extraction et nettoyage des entrées
        $devisId = (int)($postData['devis_id'] ?? 0);
        $action  = trim($postData['quote_action'] ?? '');
        $reason  = trim($postData['change_reason'] ?? '');
        $userId  = (int)$_SESSION['user_id'];

        if ($devisId <= 0 || !in_array($action, ['accept', 'reject', 'request_change'], true)) {
            $_SESSION['client_error'] = "Action non autorisée ou identifiant de devis manquant.";
            header('Location: index.php?action=client_dashboard');
            exit();
        }

        // 3. Vérification de la propriété du devis (Contrôle d'accès IDOR)
        $devisModel = new Devis();
        $devis = $devisModel->findWithProspect($devisId);

        if (!$devis || (int)$devis['user_id'] !== $userId) {
            $_SESSION['client_error'] = "Vous n'avez pas l'autorisation d'interagir avec ce devis.";
            header('Location: index.php?action=client_dashboard');
            exit();
        }

        // 4. Invariant de cycle de vie : Seuls les devis en cours d'examen peuvent recevoir une décision
        $currentStatus = strtolower($devis['status'] ?? '');
        if (!in_array($currentStatus, ['étude côté client', 'devis envoyé'], true)) {
            $_SESSION['client_error'] = "Ce devis ne peut plus être modifié (statut actuel : " . htmlspecialchars($currentStatus) . ").";
            header('Location: index.php?action=client_dashboard');
            exit();
        }

        // 5. Exécution de la transition d'état et règles métiers
        if ($action === 'request_change' && (empty($reason) || mb_strlen($reason) < 5)) {
            $_SESSION['client_error'] = "Veuillez préciser le motif de votre demande d'ajustement (au moins 5 caractères).";
            header('Location: index.php?action=client_dashboard');
            exit();
        }
        $newStatus = match ($action) {
            'accept' => 'accepté', 'reject' => 'refusé', 'request_change' => 'modification',
        };
        // La version vient de l'écran consulté, pas d'une relecture de la version courante.
        if (!$devisModel->updateStatus($devisId, $newStatus, $userId, (int)($postData['revision'] ?? 0))) {
            $_SESSION['client_error'] = "Cette proposition a changé ou a déjà reçu une réponse. Rechargez la page et consultez le devis actuel.";
            header('Location: index.php?action=client_dashboard');
            exit();
        }
        $mailService = new MailService();
        $companyName = $devis['company_name'] ?? 'Client B2B';

        switch ($action) {
            case 'accept':
                $mailService->sendQuoteAcceptedEmail($companyName, $devisId);
                break;

            case 'reject':
                $mailService->sendQuoteRejectedEmail($companyName, $devisId);
                break;

            case 'request_change':
                $mailService->sendModificationRequestEmail($companyName, $devisId, $reason);
                break;
        }


        // 6. Double persistance & Audit NoSQL MongoDB
        try {
            $logModel = new Log();
            $logModel->addLog("REPONSE_DEVIS_CLIENT", $userId, [
                'message'       => "Décision client enregistrée sur le devis #{$devisId} : {$action}",
                'devis_id'      => $devisId,
                'revision'      => (int)$postData['revision'],
                'action'        => $action,
                'change_reason' => $reason,
                'client_action' => $action,
                'reason'        => $reason,
                'new_status'    => ($action === 'accept') ? 'accepté' : (($action === 'reject') ? 'refusé' : 'modification')
            ]);
        } catch (\Exception $e) {
            error_log("Erreur Log MongoDB (handleQuoteResponse) : " . $e->getMessage());
        }

        // 7. Feedback visuel utilisateur
        $_SESSION['client_success'] = match ($action) {
            'accept'         => "Merci ! Votre devis a été validé avec succès. Notre équipe prend le relais.",
            'reject'         => "Votre refus a bien été pris en compte.",
            'request_change' => "Votre demande de modification a bien été transmise à notre équipe commerciale.",
        };

        header('Location: index.php?action=client_dashboard');
        exit();
    }

    /**
     * Alias de routage vers handleQuoteResponse pour la rétro-compatibilité.
     *
     * @param array $postData
     * @return void
     */
    public function respondToQuote(array $postData): void
    {
        $this->handleQuoteResponse($postData);
    }

    /**
     * Affiche la page de profil du client (Gestion des données et RGPD).
     */
    public function showProfile(): void
    {
        $this->checkAuth(['CLIENT']);

        $clientName = trim(($_SESSION['user_firstname'] ?? '') . ' ' . ($_SESSION['user_lastname'] ?? ''));
        $clientEmail = $_SESSION['user_email'] ?? '';

        require __DIR__ . '/../views/client/profile.php';
    }

    /**
     * Traite la demande de suppression définitive du compte (Droit à l'oubli RGPD).
     */
    public function deleteAccount(): void
    {
        $this->checkAuth(['CLIENT']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=client_profile');
            exit();
        }

        $this->validateCsrf($_POST);

        $userId = (int)($_SESSION['user_id'] ?? 0);
        require_once __DIR__ . '/../services/AccountDeletionService.php';
        if ((new AccountDeletionService())->delete($userId)) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'],
                    $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            header('Location: index.php?action=login');
            exit();
        }

        $_SESSION['client_error'] = "La suppression n'a pas pu être finalisée. Votre compte reste accessible ; certains éléments ont pu être effacés. Veuillez réessayer ou contacter l'équipe.";
        header('Location: index.php?action=client_profile');
        exit();
    }

    /**
     * Permet au client connecté de télécharger son devis PDF.
     * Sécurisé contre l'IDOR en vérifiant l'appartenance du fichier.
     *
     * @param string $fileName Nom du fichier PDF demandé.
     * @return void
     */
    public function downloadQuote(string $fileName): void
    {
        $this->checkAuth(['CLIENT']);

        require_once __DIR__ . '/PdfController.php';
        $pdfController = new PdfController();
        $pdfController->downloadPdf($fileName);
    }
}
