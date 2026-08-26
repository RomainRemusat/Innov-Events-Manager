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
        $this->checkAuth(['ADMIN', 'EMPLOYEE']); // RBAC strict
        $this->validateCsrf($postData);           // Validation Anti-CSRF

        // Validation des champs obligatoires
        if (
            empty($postData['prospect_id']) ||
            empty($postData['company_name']) ||
            empty($postData['contact_name']) ||
            empty($postData['email']) ||
            empty($postData['event_title']) ||
            empty($postData['start_date']) ||
            empty($postData['location'])
        ) {
            die("Erreur de validation : Tous les champs obligatoires (*) doivent être complétés.");
        }

        try {
            $conversionService = new ConversionService();

            // Délégation au service avec passage de $_FILES pour l'image
            $eventImage = $_FILES['event_image'] ?? null;
            $devisId = $conversionService->convertProspectToClient($postData, $eventImage, (int)$_SESSION['user_id']);

            // Pattern Post-Redirect-Get vers l'éditeur de devis
            header("Location: index.php?action=edit_devis&id=" . $devisId);
            exit();

        } catch (\InvalidArgumentException $e) {
            die("Erreur de données : " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        } catch (\Exception $e) {
            error_log("Crash transactionnel Conversion : " . $e->getMessage());
            $_SESSION['flash_error'] = "Une erreur technique est survenue lors de la conversion.";
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
            $status = trim($_POST['status']);

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



}