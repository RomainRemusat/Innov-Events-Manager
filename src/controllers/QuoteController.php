<?php
/**
 * Contrôleur : QuoteController (Gestion des demandes de devis)
 *
 * Ce contrôleur orchestre les flux d'exécution liés aux demandes de devis.
 * Il assure le rôle de médiateur dans le pattern architectural MVC : il intercepte
 * les requêtes HTTP, applique les validations de sécurité de premier niveau,
 * délègue la persistance des données au modèle SQL 'Prospect', déclenche les notifications
 * et l'audit NoSQL, puis délègue le rendu visuel à la vue dédiée.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    2.2.0
 */

require_once __DIR__ . '/../models/sql/Prospect.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/MailService.php';

class QuoteController
{
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
        // 1. Validation stricte des champs obligatoires côté serveur
        if (empty($data['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }

        if (empty($data['company_name']) || empty($data['email']) || empty($data['contact_name']) || empty($data['phone']) || empty($data['event_type'])) {
            die("Erreur de validation : L'ensemble des champs obligatoires (*) doivent être renseignés.");
        }

        // 2. Sanitisation et captation de TOUS les champs transmis par devis.php
        $sanitizedData = [
                'company_name'           => htmlspecialchars(trim($data['company_name']), ENT_QUOTES, 'UTF-8'),
                'contact_name'           => htmlspecialchars(trim($data['contact_name']), ENT_QUOTES, 'UTF-8'),
                'email'                  => filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL),
                'phone'                  => htmlspecialchars(trim($data['phone']), ENT_QUOTES, 'UTF-8'),
                'event_type'             => htmlspecialchars(trim($data['event_type']), ENT_QUOTES, 'UTF-8'),
                'event_date'             => $data['event_date'] ?? null,
                'estimated_participants' => isset($data['estimated_participants']) ? (int)$data['estimated_participants'] : null,
                'budget'                 => isset($data['budget']) ? (float)$data['budget'] : null,
                'description'  => trim($data['description'] ?? '')
        ];

        // Validation stricte du format email
        if (!filter_var($sanitizedData['email'], FILTER_VALIDATE_EMAIL)) {
            die("Erreur de validation : Le format de l'adresse email professionnelle fourni est incorrect.");
        }

        // 3. PERSISTANCE RELATIONNELLE (MySQL)
        $prospectModel = new Prospect();
        $result = $prospectModel->create($sanitizedData);

        if ($result) {
            // 4. DOUBLE PERSISTANCE NOSQL (Utilisation du modèle Log dédié)
            try {
                $logModel = new Log();
                $logModel->addLog(
                        'NOUVELLE_DEMANDE_DEVIS',
                        "Nouvelle demande de devis déposée par " . $sanitizedData['company_name'],
                        null,
                        $sanitizedData
                );
            } catch (\Exception $e) {
                error_log("Erreur de persistance MongoDB : " . $e->getMessage());
            }

            // 5. NOTIFICATION PAR EMAIL À L'ADMINISTRATION (Exigence du Cahier des Charges - Chloé)
            try {
                $mailService = new MailService();
                $mailService->sendNewQuoteNotificationToAdmin($sanitizedData);
            } catch (\Exception $e) {
                error_log("Erreur d'envoi d'email admin : " . $e->getMessage());
            }
        }

        // 6. PRÉPARATION DU CONTEXTE ET DÉLÉGATION À LA VUE (Respect strict MVC)
        $isSuccess = (bool)$result;
        $pageTitle = "Statut de votre demande - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/public/devis_confirmation.php';
        require __DIR__ . '/../views/partials/footer.php';
    }
}