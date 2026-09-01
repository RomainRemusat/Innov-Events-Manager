<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/sql/Prospect.php';
require_once __DIR__ . '/../models/sql/Devis.php';
require_once __DIR__ . '/../models/nosql/Log.php';

/**
 * Contrôleur : ClientController (Front-Office)
 *
 * Gère l'espace privé réservé aux utilisateurs ayant le rôle CLIENT :
 * consultation du tableau de bord, arbitrage des devis (Acceptation / Refus / Modification),
 * gestion du profil et suppression du compte (RGPD).
 *
 * Exigences respectées (ECF) :
 * - AT1 : Contrôle d'accès par rôle (RBAC), validation Anti-CSRF.
 * - AT2 : Mise à jour des statuts de devis et journalisation NoSQL (MongoDB).
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    2.2.0
 */
class ClientController extends BaseController
{
    /**
     * Vérification stricte de l'authentification et du rôle CLIENT.
     *
     * @return void
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

    /**
     * Affiche le tableau de bord client avec la liste des devis et projets.
     *
     * @return void
     */
    public function showDashboard(): void
    {
        $this->checkClientPermission();
        $clientId = (int)$_SESSION['user_id'];
        $clientName = $_SESSION['user_name'] ?? 'Client';

        $prospectModel = new Prospect();
        $myQuotes = $prospectModel->findClientRequests($clientId);

        require __DIR__ . '/../views/client/dashboard.php';
    }

    /**
     * Traite l'arbitrage du client sur un devis (Accepter / Refuser / Demande de modification).
     *
     * @param  array $postData Données soumises via le formulaire POST.
     * @return void
     */
    public function handleQuoteResponse(array $postData): void
    {
        $this->checkClientPermission();
        $this->validateCsrf($postData);

        // Extraction de l'identifiant (compatibilité devis_id et prospect_id)
        $devisId = (int)($postData['devis_id'] ?? $postData['prospect_id'] ?? 0);
        $action  = trim($postData['quote_action'] ?? '');
        $reason  = trim($postData['change_reason'] ?? '');

        // 1. Validation des actions autorisées par le cahier des charges (AT2)
        if ($devisId <= 0 || !in_array($action, ['accept', 'reject', 'request_change'], true)) {
            $_SESSION['client_error'] = "Action non reconnue ou dossier invalide.";
            header('Location: index.php?action=client_dashboard');
            exit();
        }

        $db = Database::getInstance();

        // 2. Contrôle de propriété du devis (Sécurité Multi-Tenant)
        $stmt = $db->prepare("
            SELECT d.id_devis, d.id_prospect, p.user_id
            FROM devis d
            JOIN prospects p ON d.id_prospect = p.id
            WHERE d.id_devis = ? AND p.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$devisId, $_SESSION['user_id']]);
        $devis = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$devis) {
            $_SESSION['client_error'] = "Action non autorisée sur ce dossier.";
            header('Location: index.php?action=client_dashboard');
            exit();
        }

        // 3. Mapping vers le statut commercial BDD
        $newStatus = match ($action) {
            'accept'         => 'accepté',
            'reject'         => 'refusé',
            'request_change' => 'modification',
        };

        // 4. Mise à jour du statut du devis dans MySQL
        $stmtUpdate = $db->prepare("UPDATE devis SET status = ? WHERE id_devis = ?");
        $stmtUpdate->execute([$newStatus, $devisId]);


        // 5. Notification par courriel à l'équipe commerciale en cas de demande de modification
        if ($action === 'request_change') {
            try {
                require_once __DIR__ . '/../services/MailService.php';
                $mailService = new MailService();
                $mailService->sendModificationRequestEmail(
                    $devis['company_name'] ?? 'Client',
                    $devisId,
                    $reason
                );
            } catch (\Exception $e) {
                error_log("Erreur d'envoi du courriel de modification : " . $e->getMessage());
            }
        }

        // 6. Journalisation d'audit dans MongoDB (AT2)
        try {
            $logModel = new Log();
            $logMsg = match ($action) {
                'accept'         => "Devis #{$devisId} ACCEPTÉ par le client.",
                'reject'         => "Devis #{$devisId} REFUSÉ par le client.",
                'request_change' => "Demande de MODIFICATION du devis #{$devisId} par le client : {$reason}",
            };

            $logModel->addLog(
                "REPONSE_DEVIS_CLIENT",
                $logMsg,
                $_SESSION['user_id'],
                [
                    'devis_id'      => $devisId,
                    'action'        => $action,
                    'change_reason' => $reason
                ]
            );
        } catch (\Exception $e) {
            error_log("Erreur Log MongoDB (handleQuoteResponse) : " . $e->getMessage());
        }

        // 6. Feedback visuel utilisateur
        $_SESSION['client_success'] = match ($action) {
            'accept'         => "Merci ! Votre devis a été validé avec succès. Notre équipe prend le relais.",
            'reject'         => "Votre refus a bien été pris en compte.",
            'request_change' => "Votre demande de modification a bien été transmise à notre équipe commerciale.",
        };

        header('Location: index.php?action=client_dashboard');
        exit();
    }

    /**
     * Alias de routage vers handleQuoteResponse pour la compatibilité d'action URL.
     *
     * @param  array $postData
     * @return void
     */
    public function respondToQuote(array $postData): void
    {
        $this->handleQuoteResponse($postData);
    }

    /**
     * Affiche la page de profil du client connecté.
     *
     * @return void
     */
    public function showProfile(): void
    {
        $this->checkClientPermission();

        $clientName  = $_SESSION['user_name'] ?? '';
        $clientEmail = $_SESSION['user_email'] ?? '';

        require __DIR__ . '/../views/client/profile.php';
    }

    /**
     * Traite la suppression définitive du compte client (Conformité RGPD).
     *
     * @return void
     */
    public function deleteAccount(): void
    {
        $this->checkClientPermission();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)$_SESSION['user_id'];
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