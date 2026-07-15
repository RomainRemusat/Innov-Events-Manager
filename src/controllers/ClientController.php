<?php
/**
 * Contrôleur : ClientController
 *
 * Gère l'ensemble des fonctionnalités de l'Espace Client Privé.
 * Permet aux utilisateurs authentifiés avec le rôle 'CLIENT' de consulter
 * l'état de leurs demandes de devis et la planification de leurs événements.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.0.0
 */

// Chargement des modèles nécessaires pour l'extraction des données spécifiques au client
// Note : Ajuste les chemins selon tes fichiers réels
require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/sql/Prospect.php';
require_once __DIR__ . '/../models/nosql/Log.php';

class ClientController
{
    /**
     * Vérifie de manière stricte si l'utilisateur possède les autorisations 'CLIENT'.
     *
     * @return void
     */
    private function checkClientPermission(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clause de garde 1 : Non authentifié
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        // Clause de garde 2 : Rôle non conforme
        if ($_SESSION['user_role'] !== 'CLIENT') {
            $_SESSION['access_error'] = "Accès refusé. Cet espace est réservé aux clients.";
            header('Location: index.php');
            exit();
        }
    }

    /**
     * Affiche le tableau de bord de l'espace client.
     *
     * @return void
     */
    public function showDashboard(): void
    {
        $this->checkClientPermission();
        $clientId = $_SESSION['user_id'];
        $clientName = $_SESSION['user_name'];

        // On remplace le faux tableau par un véritable appel à la base MySQL
        $prospectModel = new Prospect();
        $myQuotes = $prospectModel->findClientRequests($clientId);

        require __DIR__ . '/../views/client/dashboard.php';
    }

    /**
     * Traite la décision du client (Acceptation ou Refus d'un devis).
     *
     * @param array $postData Les données envoyées par le formulaire (POST).
     * @return void
     */
    public function handleQuoteResponse(array $postData): void
    {
        // 🔒 Validation du périmètre de sécurité
        $this->checkClientPermission();

        // Récupération et nettoyage des données
        $prospectId = isset($postData['prospect_id']) ? (int)$postData['prospect_id'] : 0;
        $action = $postData['quote_action'] ?? ''; // Doit être 'accept' ou 'reject'

        if ($prospectId > 0 && in_array($action, ['accept', 'reject'])) {

            $newStatus = ($action === 'accept') ? 'accepté' : 'refusé';
            $prospectModel = new Prospect();

            // Exécution de la mise à jour sécurisée
            if ($prospectModel->updateStatusByClient($prospectId, $_SESSION['user_id'], $newStatus)) {
                $_SESSION['client_success'] = "Votre choix a bien été enregistré. Le statut de votre projet est maintenant : " . ucfirst($newStatus) . ".";
            } else {
                $_SESSION['client_error'] = "Une erreur technique est survenue lors de la mise à jour.";
            }
        } else {
            $_SESSION['client_error'] = "Action non reconnue ou dossier invalide.";
        }

        // Redirection vers le tableau de bord pour rafraîchir l'affichage
        header('Location: index.php?action=client_dashboard');
        exit();
    }

    /**
     * Affiche la page de profil du client (Gestion des données et RGPD).
     */
    public function showProfile(): void
    {
        $this->checkClientPermission();

        $clientName = $_SESSION['user_name'];
        $clientEmail = $_SESSION['user_email'];

        require __DIR__ . '/../views/client/profile.php';
    }

    /**
     * Traite la demande de suppression définitive du compte (Droit à l'oubli).
     */
    public function deleteAccount(): void
    {
        $this->checkClientPermission();

        // On exige une requête POST pour éviter qu'un simple lien déclenche la suppression (Anti-CSRF)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_SESSION['user_id'];
            $userModel = new User();

            if ($userModel->deleteAccount($userId)) {

                // 1. Audit NoSQL : Trace juridique de la suppression demandée par le client
                try {
                    $logModel = new Log();
                    $logModel->addLog("SUPPRESSION_RGPD", "L'utilisateur ID $userId a supprimé son compte et l'intégralité de ses données.");
                } catch (\Exception $e) {
                    error_log("Erreur d'audit MongoDB (Suppression RGPD) : " . $e->getMessage());
                }

                // 2. Destruction de la session locale
                session_unset();
                session_destroy();

                // 3. Relance d'une session vierge pour passer le message flash de confirmation
                session_start();
                $_SESSION['global_success'] = "Conformément au RGPD, votre compte et l'intégralité de vos données ont été définitivement supprimés de nos serveurs.";

                header('Location: index.php');
                exit();
            } else {
                $_SESSION['client_error'] = "Erreur technique lors de la suppression de vos données. Veuillez contacter le support.";
            }
        }

        // Redirection de repli si l'accès n'est pas en POST
        header('Location: index.php?action=client_profile');
        exit();
    }
}