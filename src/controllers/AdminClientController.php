<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/sql/Prospect.php';
require_once __DIR__ . '/../models/sql/Event.php';
require_once __DIR__ . '/../models/nosql/Log.php';

/**
 * Contrôleur : AdminClientController (Back-Office)
 * Gère la consultation, modification et suppression des clients par le staff.
 */
class AdminClientController extends BaseController
{
    /** Affiche les clients filtrés par identité, email ou entreprise. */
    public function showClientsList(): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);

        $userModel = new User();
        $search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
        $clients = $userModel->findAllClients($search, true);

        $pageTitle = "Gestion des Clients - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/list_clients.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /** @param int $clientId Client dont les demandes et événements doivent être consultés. */
    public function showClientDetails(int $clientId): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);

        $userModel = new User();
        $client = $userModel->findById($clientId);

        if (!$client || $client['role'] !== 'CLIENT') {
            header('Location: index.php?action=admin_clients');
            exit;
        }

        $prospectModel = new Prospect();
        $clientQuotes = $prospectModel->findClientRequests($clientId);
        $clientEvents = (new Event())->findByClientId($clientId);

        $pageTitle = "Dossier Client - " . htmlspecialchars($client['firstname'] . ' ' . $client['lastname'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/view_client.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /** @param int $clientId Client actif à modifier. */
    public function showEditClientForm(int $clientId): void
    {
        $this->checkAuth(['ADMIN']);

        $userModel = new User();
        $client = $userModel->findById($clientId);

        if (!$client || $client['role'] !== 'CLIENT' || !empty($client['is_deleted'])) {
            header('Location: index.php?action=admin_clients');
            exit;
        }

        $pageTitle = "Modifier le client - " . htmlspecialchars($client['firstname'] . ' ' . $client['lastname'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/edit_client.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /** @param array $postData Coordonnées du client et jeton de soumission. */
    public function updateClient(array $postData): void
    {
        $this->checkAuth(['ADMIN']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_clients');
            exit;
        }
        $this->validateCsrf($postData);

        $clientId = (int)filter_var($postData['client_id'] ?? null, FILTER_VALIDATE_INT);
        $firstname = is_string($postData['firstname'] ?? null) ? trim($postData['firstname']) : '';
        $lastname = is_string($postData['lastname'] ?? null) ? trim($postData['lastname']) : '';
        $email = is_string($postData['email'] ?? null) ? filter_var(trim($postData['email']), FILTER_VALIDATE_EMAIL) : false;

        if ($clientId > 0 && $firstname !== '' && mb_strlen($firstname) <= 100
            && $lastname !== '' && mb_strlen($lastname) <= 100 && $email && strlen($email) <= 255) {
            $userModel = new User();
            if ($userModel->updateClient($clientId, $firstname, $lastname, $email)) {
                $_SESSION['flash_success'] = 'Les informations du client sont enregistrées.';
                (new Log())->addLog('MODIFICATION_CLIENT', (int)$_SESSION['user_id'], ['client_id' => $clientId]);
            } else {
                $_SESSION['flash_error'] = 'Les informations n’ont pas pu être enregistrées. Vérifiez le compte et l’adresse email.';
            }
        } else {
            $_SESSION['flash_error'] = 'Les coordonnées du client sont invalides.';
        }

        header('Location: index.php?action=view_client&id=' . $clientId);
        exit;
    }

    /** Suspend un client ; conserve la route historique sans effectuer d'effacement définitif. */
    public function deleteClient(array $postData): void
    {
        $this->checkAuth(['ADMIN']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_clients');
            exit;
        }
        $this->validateCsrf($postData);

        $clientId = (int)($postData['client_id'] ?? 0);

        $_SESSION['flash_error'] = 'Le compte client n’a pas pu être désactivé.';
        if ($clientId > 0) {
            $userModel = new User();
            $clientData = $userModel->findById($clientId);

            if ($clientData && $userModel->softDeleteClient($clientId)) {
                unset($_SESSION['flash_error']);
                $_SESSION['flash_success'] = 'Le compte client a été désactivé.';
                try {
                    $logModel = new Log();
                    $clientFullName = $clientData['firstname'] . ' ' . $clientData['lastname'];

                    $logModel->addLog(
                        "SUSPENSION_CLIENT",
                        (int)$_SESSION['user_id'],
                        [
                            'message' => "Suspension du client #$clientId ($clientFullName)",
                            'client_id' => $clientId,
                            'client_name' => $clientFullName
                        ]
                    );
                } catch (\Exception $e) {
                    error_log("Erreur Log MongoDB (Suppression Client) : " . $e->getMessage());
                }
            }
        }

        header('Location: index.php?action=admin_clients');
        exit;
    }
}
