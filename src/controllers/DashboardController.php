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
 * @version    2.6.0
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/Prospect.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/ConversionService.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../models/sql/Devis.php';
require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/sql/Event.php';
require_once __DIR__ . '/../models/sql/Note.php';

class DashboardController extends BaseController
{
    public function showDashboard(): void
    {
        // Vérifie que l'utilisateur est connecté avec les bons droits
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
        if ($_SESSION['user_role'] === 'EMPLOYEE') {
            header('Location: index.php?action=admin_events');
            exit();
        }

        // 1. Instanciation des modèles
        $devisModel = new Devis();
        $prospectModel = new Prospect();
        $userModel = new User();
        $eventModel = new Event();
        $noteModel = new Note();
        $logModel = new Log();

        // 2. Récupération de l'ensemble des prospects pour le pipeline
        $allProspects = $prospectModel->findAll();

        $prospectsEnCours   = [];
        $prospectsConvertis = [];
        $prospectsEchoues   = [];
        $caPrevisionnel     = 0;

        foreach ($allProspects as $p) {
            $status = strtolower($p['status'] ?? '');

            if (in_array($status, ['échoué', 'refusé'], true)) {
                $prospectsEchoues[] = $p;
            } elseif (in_array($status, ['converti', 'accepté'], true)) {
                $prospectsConvertis[] = $p;
                $caPrevisionnel += (float)($p['budget'] ?? 0);
            } else {
                // 'à contacter', 'en cours', etc.
                $prospectsEnCours[] = $p;
                $caPrevisionnel += (float)($p['budget'] ?? 0);
            }
        }

        // KPI standards
        $prospectsEnAttente = $prospectsEnCours;
        $clientsActifs = $userModel->countActiveClients();
        $totalProspects = count($allProspects);
        $prospects = $allProspects; // Rétrocompatibilité

        // 4. Widgets de la colonne de droite (V3 + V2)
        $upcomingEvents = $eventModel->findUpcomingEvents(3);
        $recentNotes = $noteModel->findLatestNotes(5);
        $activityLogs = $logModel->getLatestLogs(5); // Flux d'audit NoSQL

        // Récupération des devis en attente de modification
        $pendingModifications = $devisModel->findByStatus('modification');
        $pendingModificationsCount = count($pendingModifications);

        $pageTitle = "Tableau de Bord - Innov'Events";

        // Rendu de la vue avec injection de toutes les variables nécessaires
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/dashboard.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Affiche l'interface dédiée de gestion et segmentation des prospects.
     */
    public function showProspectsList(): void
    {
        $this->checkAuth(['ADMIN']);

        $prospectModel = new Prospect();
        $allProspects = $prospectModel->findAll();

        $prospectsEnCours   = [];
        $prospectsConvertis = [];
        $prospectsEchoues   = [];

        foreach ($allProspects as $p) {
            $status = strtolower($p['status'] ?? '');

            if (in_array($status, ['échoué', 'refusé'], true)) {
                $prospectsEchoues[] = $p;
            } elseif (in_array($status, ['converti', 'accepté'], true)) {
                $prospectsConvertis[] = $p;
            } else {
                $prospectsEnCours[] = $p;
            }
        }

        $prospects = $allProspects;
        $pageTitle = "Gestion des Prospects - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/list_prospects.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function showConvertForm(int $id): void
    {
        $this->checkAuth(['ADMIN']); // Authentification + contrôle de rôle

        $prospectModel = new Prospect();
        $prospect = $prospectModel->find($id);

        if (!$prospect) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        if (strtolower($prospect['status'] ?? '') === 'converti') {
            $_SESSION['flash_warning'] = "Ce prospect a déjà été converti en client.";
            header('Location: index.php?action=view_prospect&id=' . $id);
            exit();
        }

        $pageTitle = "Conversion du Prospect : " . htmlspecialchars($prospect['company_name']);

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/convert_prospect.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function processConversion(array $postData = []): void
    {
        $this->checkAuth(['ADMIN']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=dashboard');
            exit();
        }

        $data = !empty($postData) ? $postData : $_POST;
        $this->validateCsrf($data);

        $prospectId = (int)($data['prospect_id'] ?? $data['id'] ?? 0);
        if ($prospectId <= 0) {
            $_SESSION['flash_error'] = "Identifiant de dossier invalide.";
            header('Location: index.php?action=dashboard');
            exit();
        }

        try {
            $conversionService = new ConversionService();
            $file = $_FILES['event_image'] ?? null;
            $actorId = (int)($_SESSION['user_id'] ?? 1);
            $devisId = $conversionService->convertProspectToClient($data, $file, $actorId);

            $_SESSION['flash_success'] = "Prospect converti avec succès en client et projet événementiel créé.";
            header("Location: index.php?action=edit_devis&id=" . $devisId);
            exit();
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header("Location: index.php?action=show_convert_form&id=" . $prospectId);
            exit();
        }
    }

    public function showProspectDetails(int $id): void
    {
        $this->checkAuth(['ADMIN']);

        $prospectModel = new Prospect();
        $prospect = $prospectModel->find($id);

        if (!$prospect) {
            header('Location: index.php?action=dashboard');
            exit();
        }

        $pageTitle = "Détail Prospect - " . htmlspecialchars($prospect['company_name']);

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/view_prospect.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function updateProspectStatus(): void
    {
        $this->checkAuth(['ADMIN']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=dashboard');
            exit();
        }

        $this->validateCsrf($_POST);

        $prospectId = (int)($_POST['prospect_id'] ?? $_POST['id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $refusalReason = trim($_POST['refusal_reason'] ?? '');

        if ($prospectId > 0 && !empty($newStatus)) {
            $prospectModel = new Prospect();
            $prospect = $prospectModel->find($prospectId);

            if ($prospect) {
                $prospectModel->updateStatus($prospectId, $newStatus);

                // Journalisation MongoDB
                try {
                    $logModel = new Log();
                    $logDetails = [
                        'prospect_id'   => $prospectId,
                        'ancien_statut' => $prospect['status'],
                        'nouveau_statut'=> $newStatus,
                        'company_name'  => $prospect['company_name']
                    ];
                    if (!empty($refusalReason)) {
                        $logDetails['motif_refus'] = $refusalReason;
                    }
                    $logModel->addLog("QUALIFICATION_PROSPECT", (int)$_SESSION['user_id'], $logDetails);
                } catch (\Exception $e) {
                    error_log("Erreur Log MongoDB qualification : " . $e->getMessage());
                }

                $_SESSION['flash_success'] = "Statut du prospect mis à jour avec succès.";
            }
        }

        header("Location: index.php?action=view_prospect&id=" . $prospectId);
        exit();
    }
}
