<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/sql/Prospect.php';
require_once __DIR__ . '/../models/nosql/Log.php';

/**
 * Contrôleur : AdminClientController (Back-Office)
 * Gère la consultation, modification et suppression des clients par le staff.
 */
class AdminClientController extends BaseController
{
    public function showClientsList(): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);

        $userModel = new User();
        $clients = $userModel->findAllClients();

        $pageTitle = "Gestion des Clients - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/list_clients.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

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

        $pageTitle = "Dossier Client - " . htmlspecialchars($client['firstname'] . ' ' . $client['lastname'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/view_client.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function showEditClientForm(int $clientId): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);

        $userModel = new User();
        $client = $userModel->findById($clientId);

        if (!$client || $client['role'] !== 'CLIENT') {
            header('Location: index.php?action=admin_clients');
            exit;
        }

        $pageTitle = "Modifier le client - " . htmlspecialchars($client['firstname'] . ' ' . $client['lastname'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/edit_client.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function updateClient(array $postData): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
        $this->validateCsrf($postData);

        $clientId  = (int)($postData['client_id'] ?? 0);
        $firstname = htmlspecialchars(trim($postData['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lastname  = htmlspecialchars(trim($postData['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email     = filter_var(trim($postData['email'] ?? ''), FILTER_VALIDATE_EMAIL);

        if ($clientId > 0 && !empty($firstname) && !empty($lastname) && $email) {
            $userModel = new User();
            $userModel->updateClient($clientId, $firstname, $lastname, $email);
        }

        header('Location: index.php?action=view_client&id=' . $clientId);
        exit;
    }

    public function deleteClient(array $postData): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
        $this->validateCsrf($postData);

        $clientId = (int)($postData['client_id'] ?? 0);

        if ($clientId > 0) {
            $userModel = new User();
            $clientData = $userModel->findById($clientId);

            if ($clientData && $userModel->softDeleteClient($clientId)) {
                try {
                    $logModel = new Log();
                    $clientFullName = $clientData['firstname'] . ' ' . $clientData['lastname'];

                    $logModel->addLog(
                        "SUPPRESSION_CLIENT",
                        "Suppression logique du client #$clientId ($clientFullName)",
                        $_SESSION['user_id'],
                        ['client_id' => $clientId, 'client_name' => $clientFullName]
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