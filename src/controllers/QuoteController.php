<?php
/**
 * Contrôleur : QuoteController (Gestion des demandes de devis)
 *
 * Ce contrôleur orchestre les flux d'exécution liés aux demandes de devis.
 * Il assure le rôle de médiateur dans le pattern architectural MVC : il intercepte
 * les requêtes HTTP, applique les validations de sécurité de premier niveau,
 * délègue la persistance des données au modèle SQL 'Prospect', puis sélectionne
 * et charge la réponse visuelle appropriée via le système de composants (Partials).
 * * NOUVEAU (AT2) : Implémentation du pattern de Double Persistance.
 * Génération asynchrone d'un log de traçabilité immuable (NoSQL - MongoDB)
 * à des fins d'audit technique, exécuté post-insertion SQL.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    2.1.0
 */

require_once __DIR__ . '/../models/sql/Prospect.php';

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
        // Validation stricte côté serveur
        if (empty($data['company_name']) || empty($data['email']) || empty($data['contact_name']) || empty($data['phone']) || empty($data['event_type'])) {
            die("Erreur de validation : L'ensemble des champs obligatoires marqués d'un astérisque (*) doivent être documentés.");
        }

        // Nettoyage des entrées utilisateurs (Sanitisation XSS)
        $sanitizedData = [
            'company_name' => htmlspecialchars(trim($data['company_name']), ENT_QUOTES, 'UTF-8'),
            'contact_name' => htmlspecialchars(trim($data['contact_name']), ENT_QUOTES, 'UTF-8'),
            'email'        => filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL),
            'phone'        => htmlspecialchars(trim($data['phone']), ENT_QUOTES, 'UTF-8'),
            'event_type'   => htmlspecialchars(trim($data['event_type']), ENT_QUOTES, 'UTF-8')
        ];

        // Validation stricte du format email
        if (!filter_var($sanitizedData['email'], FILTER_VALIDATE_EMAIL)) {
            die("Erreur de validation : Le format de l'adresse email professionnelle fourni est incorrect.");
        }

        // Instanciation de la couche d'accès aux données (SQL)
        $prospectModel = new Prospect();

        // 1. PERSISTANCE RELATIONNELLE (MySQL)
        $result = $prospectModel->create($sanitizedData);

        // --- NOUVEAUTÉ : DOUBLE PERSISTANCE MONGODB (AT2) ---
        // Si l'insertion SQL a réussi, on génère le log de traçabilité NoSQL
        if ($result) {
            try {
                // Instanciation du driver natif MongoDB (Ajuster l'hôte 'mongodb' selon le nom du service Docker)
                $mongoManager = new \MongoDB\Driver\Manager("mongodb://mongodb:27017");

                // Préparation du document BSON (NoSQL)
                $logDocument = [
                    'action'        => 'nouvelle_demande_devis',
                    'timestamp'     => new \MongoDB\BSON\UTCDateTime(), // Horodatage précis
                    'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? 'IP_INCONNUE',
                    'prospect_data' => $sanitizedData
                ];

                // Initialisation du buffer d'écriture
                $bulk = new \MongoDB\Driver\BulkWrite;
                $bulk->insert($logDocument);

                // Exécution de la requête dans la base 'innovevents_logs' et la collection 'devis_logs'
                $mongoManager->executeBulkWrite('innovevents_logs.devis_logs', $bulk);

            } catch (\Exception $e) {
                // Stratégie de tolérance aux pannes :
                // L'échec du log NoSQL ne doit pas bloquer le parcours client.
                error_log("Erreur de persistance MongoDB : " . $e->getMessage());
            }
        }
        // ----------------------------------------------------

        // Injection du titre global
        $pageTitle = "Statut de votre demande - Innov'Events";
        require __DIR__ . '/../views/partials/header.php';

        // Aiguillage algorithmique (Rendu Vues)
        if ($result) {
            ?>
            <main class="container my-5 py-5">
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 text-center">
                        <div class="card border-0 shadow-sm p-4 pt-5 divider-fine">
                            <div class="rounded-circle bg-success bg-opacity-10 text-success mx-auto mb-4 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="bi bi-check2-circle display-4"></i>
                            </div>
                            <h2 class="fw-bold text-dark mb-3">Demande reçue !</h2>
                            <p class="text-muted lh-base mb-4">
                                Merci pour votre confiance. Chloé vient de recevoir vos informations et étudie déjà la faisabilité de votre projet. Vous serez recontacté sous 48 heures ouvrées.
                            </p>
                            <div class="pt-2">
                                <a href="index.php" class="btn btn-primary-custom px-4"><i class="bi bi-house me-2"></i>Retour à l'accueil</a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php
        } else {
            ?>
            <main class="container my-5 py-5">
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 text-center">
                        <div class="card border-0 shadow-sm p-4 pt-5 divider-fine">
                            <div class="rounded-circle bg-danger bg-opacity-10 text-danger mx-auto mb-4 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="bi bi-exclamation-triangle display-4"></i>
                            </div>
                            <h2 class="fw-bold text-dark mb-3">Une erreur est survenue</h2>
                            <p class="text-muted lh-base mb-4">
                                Nos services techniques rencontrent actuellement une surcharge. Votre demande n'a pas pu être sauvegardée. Veuillez réitérer l'opération d'envoi.
                            </p>
                            <div class="pt-2">
                                <a href="index.php?action=devis" class="btn btn-light border px-4 text-dark"><i class="bi bi-arrow-left me-2"></i>Revenir au formulaire</a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php
        }

        require __DIR__ . '/../views/partials/footer.php';
    }
}