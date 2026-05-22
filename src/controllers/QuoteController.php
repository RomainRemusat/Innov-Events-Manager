<?php
/**
 * Contrôleur : QuoteController (Gestion des demandes de devis)
 *
 * Ce contrôleur orchestre les flux d'exécution liés aux demandes de devis.
 * Il assure le rôle de médiateur dans le pattern architectural MVC : il intercepte
 * les requêtes HTTP, applique les validations de sécurité de premier niveau,
 * délègue la persistance des données au modèle SQL 'Prospect', puis sélectionne
 * et charge la réponse visuelle appropriée via le système de composants (Partials).
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    2.0.0
 */

require_once __DIR__ . '/../models/sql/Prospect.php';

class QuoteController
{
    /**
     * Point d'entrée pour l'affichage du formulaire de devis.
     * * Charge et injecte la vue contenant le formulaire HTML public au sein du
     * cycle de vie de la requête courante.
     *
     * @return void
     */
    public function showForm(): void
    {
        require __DIR__ . '/../views/public/devis.php';
    }

    /**
     * Traite la soumission des données du formulaire de devis (Requête POST).
     * * Valide la présence et la conformité des champs obligatoires côté serveur
     * afin de parer à tout contournement des validations natives HTML5. Transmet
     * les variables nettoyées à la couche d'accès aux données (DAL) et encapsule
     * le retour utilisateur au sein de la charte graphique globale.
     *
     * @param array $data Tableau associatif contenant les variables $_POST soumises.
     * @return void
     */
    public function submitQuote(array $data): void
    {
        // Validation stricte côté serveur : Sécurité de surface contre l'altération des requêtes HTTP
        if (empty($data['company_name']) || empty($data['email']) || empty($data['contact_name']) || empty($data['phone']) || empty($data['event_type'])) {
            die("Erreur de validation : L'ensemble des champs obligatoires marqués d'un astérisque (*) doivent être documentés.");
        }

        // Nettoyage des entrées utilisateurs (Sanitisation de base contre les failles XSS persistantes)
        $sanitizedData = [
            'company_name' => htmlspecialchars(trim($data['company_name']), ENT_QUOTES, 'UTF-8'),
            'contact_name' => htmlspecialchars(trim($data['contact_name']), ENT_QUOTES, 'UTF-8'),
            'email'        => filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL),
            'phone'        => htmlspecialchars(trim($data['phone']), ENT_QUOTES, 'UTF-8'),
            'event_type'   => htmlspecialchars(trim($data['event_type']), ENT_QUOTES, 'UTF-8')
        ];

        // Validation stricte du format de l'adresse de messagerie électronique
        if (!filter_var($sanitizedData['email'], FILTER_VALIDATE_EMAIL)) {
            die("Erreur de validation : Le format de l'adresse email professionnelle fourni est incorrect.");
        }

        // Instanciation de la couche d'accès aux données (Modèle SQL hérité du pattern Table Data Gateway)
        $prospectModel = new Prospect();

        // Exécution de la transaction d'insertion en base de données
        $result = $prospectModel->create($sanitizedData);

        // Injection du titre global pour la mise en forme de la réponse utilisateur
        $pageTitle = "Statut de votre demande - Innov'Events";

        // Initialisation du rendu graphique de la page de réponse (Header Partials)
        require __DIR__ . '/../views/partials/header.php';

        // Aiguillage algorithmique selon le statut de retour de la transaction d'écriture
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

        // Clôture sémantique et exécution des scripts (Footer Partials)
        require __DIR__ . '/../views/partials/footer.php';
    }
}