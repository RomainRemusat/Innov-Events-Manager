<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/sql/Prospect.php';
require_once __DIR__ . '/../models/nosql/Log.php';

/**
 * Contrôleur : ClientController (Front-Office)
 * Gère l'espace privé réservé aux utilisateurs ayant le rôle CLIENT.
 */
class ClientController extends BaseController
{
    /**
     * Vérification stricte du rôle CLIENT.
     */
    private function checkClientPermission(): void
    {
        $this->startSession();

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        if (($_SESSION['user_role'] ?? '') !== 'CLIENT') {
            $_SESSION['access_error'] = "Accès refusé. Cet espace est réservé aux clients.";
            header('Location: index.php');
            exit();
        }
    }

    public function showDashboard(): void
    {
        $this->checkClientPermission();
        $clientId = $_SESSION['user_id'];

        $prospectModel = new Prospect();
        $myQuotes = $prospectModel->findClientRequests($clientId);

        require __DIR__ . '/../views/client/dashboard.php';
    }

    public function handleQuoteResponse(array $postData): void
    {
        $this->checkClientPermission();

        $prospectId = (int)($postData['prospect_id'] ?? 0);
        $action     = $postData['quote_action'] ?? '';

        if ($prospectId > 0 && in_array($action, ['accept', 'reject'], true)) {
            $newStatus = ($action === 'accept') ? 'accepté' : 'refusé';
            $prospectModel = new Prospect();

            if ($prospectModel->updateStatusByClient($prospectId, $_SESSION['user_id'], $newStatus)) {
                $_SESSION['client_success'] = "Votre choix a bien été enregistré. Le statut de votre projet est maintenant : " . ucfirst($newStatus) . ".";
            } else {
                $_SESSION['client_error'] = "Une erreur technique est survenue lors de la mise à jour.";
            }
        } else {
            $_SESSION['client_error'] = "Action non reconnue ou dossier invalide.";
        }

        header('Location: index.php?action=client_dashboard');
        exit();
    }

    public function showProfile(): void
    {
        $this->checkClientPermission();

        $clientName  = $_SESSION['user_name'] ?? '';
        $clientEmail = $_SESSION['user_email'] ?? '';

        require __DIR__ . '/../views/client/profile.php';
    }

    public function deleteAccount(): void
    {
        $this->checkClientPermission();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_SESSION['user_id'];
            $userModel = new User();

            if ($userModel->deleteAccount($userId)) {
                try {
                    $logModel = new Log();
                    $logModel->addLog("SUPPRESSION_RGPD", "L'utilisateur ID $userId a supprimé son compte et l'intégralité de ses données.");
                } catch (\Exception $e) {
                    error_log("Erreur d'audit MongoDB (Suppression RGPD) : " . $e->getMessage());
                }

                session_unset();
                session_destroy();

                session_start();
                $_SESSION['global_success'] = "Conformément au RGPD, votre compte et l'intégralité de vos données ont été définitivement supprimés de nos serveurs.";

                header('Location: index.php');
                exit();
            } else {
                $_SESSION['client_error'] = "Erreur technique lors de la suppression de vos données. Veuillez contacter le support.";
            }
        }

        header('Location: index.php?action=client_profile');
        exit();
    }
}