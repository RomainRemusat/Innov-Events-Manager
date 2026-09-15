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
 * @version    2.5.0
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
    // 1. ESPACE PUBLIC : DEMANDE DE DEVIS
    // =========================================================================

    /**
     * Point d'entrée pour l'affichage du formulaire de devis.
     *
     * @return void
     */
    public function showForm(): void
    {
        $this->startSession();
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
        // Le formulaire est public, mais une demande liée à un compte exige une session valide.
        if (!empty($_SESSION['user_id'])) {
            $this->checkAuth();
        }

        // 1. Validation de sécurité CSRF (AT1)
        $this->validateCsrf($data);

        // 2. Validation et assainissement des données
        $companyName  = trim($data['company_name'] ?? '');
        $contactName  = trim($data['contact_name'] ?? '');
        $email        = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $phone        = trim($data['phone'] ?? '');
        $eventType    = trim($data['event_type'] ?? '');
        $eventDate    = trim($data['event_date'] ?? '');
        $location     = trim($data['location'] ?? '');
        $participants = isset($data['estimated_participants']) && $data['estimated_participants'] !== '' ? (int)$data['estimated_participants'] : null;
        $budget       = isset($data['budget']) && $data['budget'] !== '' ? (float)$data['budget'] : null;
        $description  = trim($data['description'] ?? '');

        // Validation des invariants obligatoires
        $errors = [];

        if (empty($companyName)) {
            $errors[] = "Le nom de l'entreprise est obligatoire.";
        }
        if (empty($contactName)) {
            $errors[] = "Le nom du contact est obligatoire.";
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Une adresse email professionnelle valide est requise.";
        }
        if (empty($phone)) {
            $errors[] = "Le numéro de téléphone est obligatoire.";
        }
        if (empty($eventType)) {
            $errors[] = "Le type d'événement doit être sélectionné.";
        }
        if (empty($location)) {
            $errors[] = "Le lieu ou la ville souhaitée est obligatoire.";
        }
        if (empty($eventDate)) {
            $errors[] = "La date souhaitée est obligatoire.";
        } else {
            $timestamp = strtotime($eventDate);
            if ($timestamp === false) {
                $errors[] = "Le format de la date d'événement est invalide.";
            } elseif ($timestamp < strtotime('today')) {
                $errors[] = "La date de l'événement ne peut pas être passée.";
            }
        }
        if ($participants === null || $participants <= 0) {
            $errors[] = "Le nombre estimé de participants doit être supérieur à 0.";
        }
        if (empty($description) || mb_strlen($description) < 5) {
            $errors[] = "La description du projet doit comporter au moins 5 caractères.";
        }
        if ($budget !== null && $budget < 0) {
            $errors[] = "Le budget estimé ne peut pas être négatif.";
        }

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode('<br>', $errors);
            $_SESSION['old_inputs']  = $data;
            header('Location: index.php?action=devis');
            exit();
        }

        $sanitizedData = [
            'company_name'           => $companyName,
            'contact_name'           => $contactName,
            'email'                  => $email,
            'phone'                  => $phone,
            'location'               => $location,
            'event_type'             => $eventType,
            'event_date'             => $eventDate,
            'estimated_participants' => $participants,
            'budget'                 => $budget,
            'description'            => $description,
            'user_id'                => (!empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'CLIENT') ? (int)$_SESSION['user_id'] : null
        ];

        // 3. Persistance relationnelle MySQL (AT2)
        $prospectModel = new Prospect();
        $result = $prospectModel->create($sanitizedData);

        if ($result) {
            // Nettoyage des anciennes saisies
            unset($_SESSION['old_inputs']);

            // 4. Double persistance NoSQL MongoDB (AT2)
            try {
                $logModel = new Log();
                $logModel->addLog(
                    'NOUVELLE_DEMANDE_DEVIS',
                    $sanitizedData['user_id'],
                    array_merge($sanitizedData, [
                        'message' => "Nouvelle demande de devis déposée par " . $sanitizedData['company_name'] . " pour un événement à " . $sanitizedData['location']
                    ])
                );
            } catch (\Exception $e) {
                error_log("Erreur MongoDB : " . $e->getMessage());
            }

            // 5. Notification e-mail à l'administration
            try {
                $mailService = new MailService();
                $mailService->sendNewQuoteAdminNotification($sanitizedData);
            } catch (\Exception $e) {
                error_log("Erreur MailService : " . $e->getMessage());
            }
        }

        // 6. Délégation à la vue de confirmation
        $isSuccess = (bool)$result;
        $pageTitle = "Statut de votre demande - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/public/devis_confirmation.php';
        require __DIR__ . '/../views/partials/footer.php';
    }


    // =========================================================================
    // 2. ESPACE ADMINISTRATION : GESTION DES DEVIS
    // =========================================================================

    public function showDevisList(): void
    {
        $this->checkAuth(['ADMIN']);

        $devisModel = new Devis();
        $devisList = $devisModel->findAllWithTotals();

        $pageTitle = "Pilotage Commercial - Devis & Propositions";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/list_devis.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function editDevis(int $devisId): void
    {
        $this->checkAuth(['ADMIN']);

        $devisModel = new Devis();
        $devis = $devisModel->findWithProspect($devisId);

        if (!$devis) {
            header('Location: index.php?action=dashboard');
            exit();
        }

        $prestationModel = new Prestation();
        $prestations = $prestationModel->findByDevisId($devisId);

        // Extraction de la remarque client depuis MongoDB si statut en modification
        $lastChangeReason = null;
        if (in_array(strtolower($devis['status'] ?? ''), ['modification', 'brouillon'], true)) {
            try {
                $logModel = new Log();
                $lastChangeReason = $logModel->getLatestChangeReason($devisId);
            } catch (\Exception $e) {
                error_log("Erreur lecture motif modification MongoDB : " . $e->getMessage());
            }
        }

        $pageTitle = "Édition Devis - " . htmlspecialchars($devis['company_name'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/edit_devis.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function addPrestation(array $postData): void
    {
        $this->checkAuth(['ADMIN']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_devis');
            exit();
        }

        $this->validateCsrf($postData);

        $devisId   = (int)($postData['devis_id'] ?? 0);
        $libelle   = trim($postData['libelle'] ?? '');
        $montantHt = (float)($postData['montant_ht'] ?? 0);

        if ($devisId > 0 && !empty($libelle) && $montantHt >= 0) {
            // Verrouillage contractuel : modification interdite sur un devis déjà validé/accepté
            $devisModel = new Devis();
            $devis = $devisModel->findWithProspect($devisId);
            if ($devis && strtolower($devis['status'] ?? '') === 'accepté') {
                $_SESSION['flash_error'] = "Impossible d'ajouter une prestation : ce devis a déjà été validé par le client.";
                header("Location: index.php?action=edit_devis&id=" . $devisId);
                exit;
            }

            $prestationModel = new Prestation();
            if (!$prestationModel->create($devisId, $libelle, $montantHt)) {
                $_SESSION['flash_error'] = "Prestation non ajoutée : devis verrouillé, introuvable ou erreur d'enregistrement.";
            }
        }

        header("Location: index.php?action=edit_devis&id=" . $devisId);
        exit;
    }

    public function deletePrestation(array $postData): void
    {
        $this->checkAuth(['ADMIN']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_devis');
            exit();
        }

        $this->validateCsrf($postData);

        $prestationId = (int)($postData['prestation_id'] ?? 0);
        $devisId      = (int)($postData['devis_id'] ?? 0);

        if ($prestationId > 0 && $devisId > 0) {
            // Verrouillage contractuel : suppression interdite sur un devis déjà validé/accepté
            $devisModel = new Devis();
            $devis = $devisModel->findWithProspect($devisId);
            if ($devis && strtolower($devis['status'] ?? '') === 'accepté') {
                $_SESSION['flash_error'] = "Impossible de supprimer une prestation : ce devis a déjà été validé par le client.";
                header("Location: index.php?action=edit_devis&id=" . $devisId);
                exit;
            }

            $prestationModel = new Prestation();
            if (!$prestationModel->delete($prestationId, $devisId)) {
                $_SESSION['flash_error'] = "Prestation non supprimée : devis verrouillé, ligne introuvable ou erreur d'enregistrement.";
            }
        }

        header("Location: index.php?action=edit_devis&id=" . $devisId);
        exit;
    }
}
