<?php
/**
 * Contrôleur : QuoteController (Gestion des demandes de devis & Pilotage financier)
 *
 * Ce contrôleur hybride gère à la fois l'Espace Public (Formulaire de demande)
 * et l'Espace Administration (Création, édition et suppression des prestations AT2).
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    2.3.0
 */

// 1. Héritage du contrôleur de base (Sécurité centralisée)
require_once __DIR__ . '/BaseController.php';

// 2. Modèles et Services nécessaires
require_once __DIR__ . '/../models/sql/Prospect.php';
require_once __DIR__ . '/../models/sql/Devis.php';
require_once __DIR__ . '/../models/sql/Prestation.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/MailService.php';

class QuoteController extends BaseController
{
    // =========================================================================
    // 1. ESPACE PUBLIC : DEMANDE DE DEVIS (Ton code d'origine intact)
    // =========================================================================

    /**
     * Point d'entrée pour l'affichage du formulaire de devis.
     *
     * @return void
     */
    public function showForm(): void
    {
        require __DIR__ . '/../views/public/devis.php';
    }

    /**
     * Traite la soumission des données du formulaire de devis (Requête POST).
     *
     * @param array $data Tableau associatif contenant les variables $_POST soumises.
     * @return void
     */
    public function submitQuote(array $data): void
    {
        $this->startSession();

        // 1. Validation de sécurité CSRF (AT1)
        if (empty($data['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }

        // 2. Validation des champs obligatoires du cahier des charges
        if (
            empty($data['company_name']) ||
            empty($data['email']) ||
            empty($data['contact_name']) ||
            empty($data['phone']) ||
            empty($data['event_type']) ||
            empty($data['location'])
        ) {
            die("Erreur de validation : L'ensemble des champs obligatoires (*) doivent être renseignés.");
        }

        // 3. Sanitisation des entrées
        $sanitizedData = [
            'company_name'           => trim($data['company_name']),
            'contact_name'           => trim($data['contact_name']),
            'email'                  => filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL),
            'phone'                  => trim($data['phone']),
            'location'               => trim($data['location']),
            'event_type'             => trim($data['event_type']),
            'event_date'             => !empty($data['event_date']) ? $data['event_date'] : null,
            'estimated_participants' => isset($data['estimated_participants']) ? (int)$data['estimated_participants'] : null,
            'budget'                 => isset($data['budget']) ? (float)$data['budget'] : null,
            'description'            => trim($data['description'] ?? ''),
            'user_id'                => (!empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'CLIENT') ? (int)$_SESSION['user_id'] : null
        ];

        // Validation stricte du format email
        if (!filter_var($sanitizedData['email'], FILTER_VALIDATE_EMAIL)) {
            die("Erreur de validation : Le format de l'adresse email professionnelle est incorrect.");
        }

        // 4. Persistance relationnelle MySQL (AT2)
        $prospectModel = new Prospect();
        $result = $prospectModel->create($sanitizedData);

        if ($result) {
            // 5. Double persistance NoSQL MongoDB (AT2)
            try {
                $logModel = new Log();
                $logModel->addLog(
                    'NOUVELLE_DEMANDE_DEVIS',
                    "Nouvelle demande de devis déposée par " . $sanitizedData['company_name'] . " pour un événement à " . $sanitizedData['location'],
                    $sanitizedData['user_id'],
                    $sanitizedData
                );
            } catch (\Exception $e) {
                error_log("Erreur MongoDB : " . $e->getMessage());
            }

            // 6. Notification e-mail à l'administration
            try {
                $mailService = new MailService();
                $mailService->sendNewQuoteNotificationToAdmin($sanitizedData);
            } catch (\Exception $e) {
                error_log("Erreur MailService : " . $e->getMessage());
            }
        }

        // 7. Délégation à la vue de confirmation
        $isSuccess = (bool)$result;
        $pageTitle = "Statut de votre demande - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/public/devis_confirmation.php';
        require __DIR__ . '/../views/partials/footer.php';
    }


    // =========================================================================
    // 2. ESPACE ADMINISTRATION : GESTION DES DEVIS (Nouvelles méthodes)
    // =========================================================================

    public function showDevisList(): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);

        $devisModel = new Devis();
        $devisList = $devisModel->findAllWithTotals();

        $pageTitle = "Devis & Facturation - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/list_devis.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function editDevis(int $devisId): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);

        $devisModel = new Devis();
        $devis = $devisModel->findWithProspect($devisId);

        if (!$devis) {
            header('Location: index.php?action=dashboard');
            exit();
        }

        $prestationModel = new Prestation();
        $prestations = $prestationModel->findByDevisId($devisId);

        $pageTitle = "Édition Devis - " . htmlspecialchars($devis['company_name'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/edit_devis.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function addPrestation(array $postData): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
        $this->validateCsrf($postData);

        $devisId   = (int)($postData['devis_id'] ?? 0);
        $libelle   = trim($postData['libelle'] ?? '');
        $montantHt = (float)($postData['montant_ht'] ?? 0);

        if ($devisId > 0 && !empty($libelle) && $montantHt >= 0) {
            $prestationModel = new Prestation();
            $prestationModel->create($devisId, $libelle, $montantHt);
        }

        header("Location: index.php?action=edit_devis&id=" . $devisId);
        exit;
    }

    public function deletePrestation(array $postData): void
    {
        $this->checkAuth(['ADMIN', 'EMPLOYEE']);
        $this->validateCsrf($postData);

        $prestationId = (int)($postData['prestation_id'] ?? 0);
        $devisId      = (int)($postData['devis_id'] ?? 0);

        if ($prestationId > 0 && $devisId > 0) {
            $prestationModel = new Prestation();
            $prestationModel->delete($prestationId, $devisId);
        }

        header("Location: index.php?action=edit_devis&id=" . $devisId);
        exit();
    }
}