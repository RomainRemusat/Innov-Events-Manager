<?php
/**
 * Contrôleur : DashboardController (Espace d'administration sécurisé)
 *
 * Ce contrôleur orchestre la logique métier de l'espace privé (Back-Office) d'Innov'Events.
 * Il agit comme un point de contrôle (Guard Pattern) en vérifiant systématiquement
 * les habilitations (Session/Rôles) avant d'autoriser l'accès aux données sensibles.
 *
 * Il implémente la logique de l'Activité Type 2 (AT2) en gérant le cycle de vie
 * des prospects, la génération des devis, et la double persistance (MySQL / MongoDB).
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.2.0
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/Prospect.php';
require_once __DIR__ . '/../models/nosql/Log.php'; // Nécessaire pour la journalisation MongoDB
require_once __DIR__ . '/../services/ConversionService.php';
require_once __DIR__ . '/../models/sql/Devis.php';
require_once __DIR__ . '/BaseController.php';

class DashboardController extends BaseController
{
    public function showDashboard(): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']); // Vérifie que l'utilisateur est connecté

        $prospectModel = new Prospect();
        $prospects = $prospectModel->findAllActive();

        $logModel = new Log();
        $activityLogs = $logModel->getLatestLogs(5);

        $pageTitle = "Tableau de Bord - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/dashboard.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function showConvertForm(int $id): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']); // Authentification + contrôle de rôle

        $prospectModel = new Prospect();
        $prospect = $prospectModel->find($id);

        if (!$prospect) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        $pageTitle = "Convertir Prospect - " . htmlspecialchars($prospect['company_name'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/convert_prospect.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function processConversion(array $postData): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
        $this->validateCsrf($postData); // Contrôle CSRF centralisé

        try {
            $conversionService = new ConversionService();
            $devisId = $conversionService->convertProspectToClient($postData, (int)$_SESSION['user_id']);

            header("Location: index.php?action=edit_devis&id=" . $devisId);
            exit();

        } catch (\InvalidArgumentException $e) {
            die("Erreur de validation : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        } catch (\Exception $e) {
            error_log("CRASH TRANSACTION CONVERSION : " . $e->getMessage());
            header('Location: index.php?action=dashboard');
            exit();
        }
    }

    /**
     * Affiche les détails complets d'un prospect spécifique.
     *
     * Agit en tant qu'interface décisionnelle permettant à l'administrateur
     * d'engager le tunnel de conversion ou de modifier le statut du lead.
     *
     * @param int $id Identifiant unique du prospect (Clé primaire)
     * @return void
     */
    public function showProspectDetails(int $id): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $prospectModel = new Prospect();
        $prospect = $prospectModel->find($id);

        // Fallback de sécurité (Soft 404) si l'ID a été manipulé ou purgé (RGPD)
        if (!$prospect) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        $pageTitle = "Détails du Prospect - " . htmlspecialchars($prospect['company_name'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/view_prospect.php';
        require __DIR__ . '/../views/partials/footer.php';
    }






    /**
     * Ajoute une nouvelle ligne de prestation commerciale au devis.
     *
     * Applique le pattern PRG (Post-Redirect-Get) pour sécuriser l'insertion
     * et forcer le recalcul automatique des totaux financiers (HT/TVA/TTC).
     *
     * @param array $postData Payload du formulaire d'ajout
     * @return void
     */
    public function addPrestation(array $postData): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            die("Accès refusé.");
        }

        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide.");
        }

        $devisId   = (int)($postData['devis_id'] ?? 0);
        $libelle   = htmlspecialchars(trim($postData['libelle'] ?? ''), ENT_QUOTES, 'UTF-8');
        $montantHt = (float)($postData['montant_ht'] ?? 0);

        if ($devisId > 0 && !empty($libelle) && $montantHt >= 0) {
            require_once __DIR__ . '/../models/sql/Prestation.php';
            $prestationModel = new Prestation();
            $prestationModel->create($devisId, $libelle, $montantHt);
        }

        header("Location: index.php?action=edit_devis&id=" . $devisId);
        exit;
    }

    /**
     * Supprime une prestation existante d'un devis.
     *
     * Sécurise la suppression via une double contrainte (ID prestation + ID Devis)
     * pour prévenir la manipulation d'identifiants (IDOR - Insecure Direct Object Reference).
     *
     * @param array $postData Payload du formulaire de suppression
     * @return void
     */
    public function deletePrestation(array $postData): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=login');
            exit();
        }

        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }

        $prestationId = (int)($postData['prestation_id'] ?? 0);
        $devisId      = (int)($postData['devis_id'] ?? 0);

        if ($prestationId > 0 && $devisId > 0) {
            try {
                require_once __DIR__ . '/../config/Database.php';
                $db = Database::getInstance();
                $stmt = $db->prepare("DELETE FROM prestations WHERE id = ? AND devis_id = ?");
                $stmt->execute([$prestationId, $devisId]);
            } catch (\PDOException $e) {
                error_log("Échec de la suppression de prestation : " . $e->getMessage());
            }
        }

        header("Location: index.php?action=edit_devis&id=" . $devisId);
        exit();
    }

    /**
     * Affiche l'interface de composition du devis.
     *
     * @param int $devisId Identifiant unique du devis
     * @return void
     */
    public function editDevis(int $devisId): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        $devisModel = new Devis();
        $devis = $devisModel->findWithProspect($devisId);

        if (!$devis) {
            header('Location: index.php?action=dashboard');
            exit();
        }

        // Récupération des prestations associées
        require_once __DIR__ . '/../models/sql/Prestation.php';
        $prestationModel = new Prestation();
        $prestations = $prestationModel->findByDevisId($devisId); // Assure-toi que la méthode existe dans Prestation.php, sinon réadapte

        $pageTitle = "Édition Devis - " . htmlspecialchars($devis['company_name'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/edit_devis.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Affiche le tableau de bord de pilotage global des devis.
     *
     * @return void
     */
    public function showDevisList(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        $devisModel = new Devis();
        $devisList = $devisModel->findAllWithTotals();

        $pageTitle = "Devis & Facturation - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/list_devis.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Met à jour l'état d'un prospect (ex: "en attente" -> "refusé").
     *
     * Implémentation du pattern de Persistance Polyglotte : met à jour l'entité
     * structurée dans MySQL et trace l'événement immuable dans MongoDB.
     *
     * @return void
     */
    public function updateProspectStatus(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id']) && !empty($_POST['status'])) {
            $id = (int)$_POST['id'];
            $status = htmlspecialchars(trim($_POST['status']), ENT_QUOTES, 'UTF-8');

            $prospectModel = new Prospect();
            $success = $prospectModel->updateStatus($id, $status);

            if ($success) {
                // Journalisation MongoDB (AT2)
                try {
                    $logModel = new Log();
                    $logModel->addLog("UPDATE_PROSPECT", "Statut du prospect #$id modifié en : $status", $_SESSION['user_id'] ?? null);
                } catch (\Exception $e) {
                    error_log("Erreur Log MongoDB : " . $e->getMessage());
                }

                header("Location: index.php?action=view_prospect&id=" . $id);
                exit;
            } else {
                error_log("Échec de la mise à jour du statut (Prospect ID: $id).");
                header("Location: index.php?action=view_prospect&id=" . $id);
                exit;
            }
        } else {
            header('Location: index.php?action=dashboard');
            exit;
        }
    }

    /**
     * Extrait et affiche le listing global des prospects.
     *
     * @see Prospect::findAll() Logique métier sous-jacente.
     * @return void
     */
    public function showProspectsList(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $prospectModel = new Prospect();
        $prospects = $prospectModel->findAllActive();

        $pageTitle = "Gestion des Prospects - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/list_prospects.php';
        require __DIR__ . '/../views/partials/footer.php';
    }


    /**
     * Affiche la liste globale des clients (Espace Admin).
     *
     * @return void
     */
    public function showClientsList(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=login');
            exit;
        }

        require_once __DIR__ . '/../models/sql/User.php';
        $userModel = new User();
        $clients = $userModel->findAllClients();

        $pageTitle = "Gestion des Clients - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/list_clients.php';
        require __DIR__ . '/../views/partials/footer.php';
    }


    /**
     * Affiche le dossier complet d'un client (Informations, Devis, Événements).
     *
     * @param int $clientId Identifiant du client
     * @return void
     */
    public function showClientDetails(int $clientId): void
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // 1. Récupérer les informations du client (Table Users)
        require_once __DIR__ . '/../models/sql/User.php';
        $userModel = new User();
        $client = $userModel->findById($clientId);

        if (!$client || $client['role'] !== 'CLIENT') {
            header('Location: index.php?action=admin_clients');
            exit;
        }

        // 2. Récupérer l'historique de ses devis/demandes (Table Prospects/Devis)
        require_once __DIR__ . '/../models/sql/Prospect.php';
        $prospectModel = new Prospect();
        $clientQuotes = $prospectModel->findClientRequests($clientId);

        // 3. Rendu de la vue
        $pageTitle = "Dossier Client - " . htmlspecialchars($client['firstname'] . ' ' . $client['lastname']);
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/view_client.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Traite la demande de suppression d'un client (Soft Delete) et journalise l'action.
     *
     * @param array $postData Les données soumises par le formulaire POST
     * @return void
     */
    public function deleteClient(array $postData): void
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        // Habilitation stricte (Sécurité AT1)
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // Validation CSRF
        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide.");
        }

        $clientId = (int)($postData['client_id'] ?? 0);

        if ($clientId > 0) {
            require_once __DIR__ . '/../models/sql/User.php';
            $userModel = new User();

            // On récupère les infos avant suppression pour le Log NoSQL
            $clientData = $userModel->findById($clientId);

            if ($clientData && $userModel->softDeleteClient($clientId)) {
                // EXIGENCE AT2 : Journalisation NoSQL de la suppression
                try {
                    require_once __DIR__ . '/../models/nosql/Log.php';
                    $logModel = new Log();
                    $clientFullName = $clientData['firstname'] . ' ' . $clientData['lastname'];

                    $logModel->addLog(
                        "SUPPRESSION_CLIENT",
                        "Suppression logique du client #$clientId ($clientFullName)",
                        $_SESSION['user_id'],
                        ['client_id' => $clientId, 'client_name' => $clientFullName] // Détails exigés par le CC
                    );
                } catch (\Exception $e) {
                    error_log("Erreur Log MongoDB (Suppression Client) : " . $e->getMessage());
                }
            }
        }

        // PRG Pattern : Redirection vers la liste des clients
        header('Location: index.php?action=admin_clients');
        exit;
    }

    /**
     * Affiche le formulaire d'édition des informations d'un client.
     *
     * @param int $clientId Identifiant unique du client à modifier
     * @return void
     */
    public function showEditClientForm(int $clientId): void
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        // Habilitation stricte (Sécurité AT1)
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=login');
            exit;
        }

        require_once __DIR__ . '/../models/sql/User.php';
        $userModel = new User();
        $client = $userModel->findById($clientId);

        // Clause de garde : si l'ID n'existe pas ou que ce n'est pas un client
        if (!$client || $client['role'] !== 'CLIENT') {
            header('Location: index.php?action=admin_clients');
            exit;
        }

        $pageTitle = "Modifier le client - " . htmlspecialchars($client['firstname'] . ' ' . $client['lastname'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/edit_client.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Traite la mise à jour des informations d'un client.
     *
     * @param array $postData Les données issues du formulaire POST
     * @return void
     */
    public function updateClient(array $postData): void
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        // 1. Habilitation (RBAC)
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            die("Accès refusé.");
        }

        // 2. Sécurité Anti-CSRF
        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }

        // 3. Assainissement des données (Sanitization)
        $clientId  = (int)($postData['client_id'] ?? 0);
        $firstname = htmlspecialchars(trim($postData['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lastname  = htmlspecialchars(trim($postData['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email     = filter_var(trim($postData['email'] ?? ''), FILTER_VALIDATE_EMAIL);

        // 4. Traitement Métier
        if ($clientId > 0 && !empty($firstname) && !empty($lastname) && $email) {
            require_once __DIR__ . '/../models/sql/User.php';
            $userModel = new User();
            $success = $userModel->updateClient($clientId, $firstname, $lastname, $email);

            if ($success) {
                // (Optionnel) Ajouter un log NoSQL ici si le cahier des charges l'exige pour les modifications
            }
        }

        // 5. Pattern PRG : Redirection vers le dossier du client mis à jour
        header('Location: index.php?action=view_client&id=' . $clientId);
        exit;
    }
}