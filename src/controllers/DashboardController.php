<?php
/**
 * Contrôleur : DashboardController (Espace d'administration sécurisé)
 *
 * Ce contrôleur orchestre la logique métier de l'espace privé (Back-Office).
 * Il agit comme un point de contrôle (Guard) en vérifiant systématiquement
 * les habilitations de l'utilisateur avant d'autoriser l'accès aux données sensibles.
 * Une fois l'authentification validée, il interagit avec la couche d'accès
 * aux données (Modèle) pour préparer le contexte de la vue d'administration.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.1.0
 */

require_once __DIR__ . '/../models/sql/Prospect.php';

class DashboardController
{
    /**
     * Orchestre l'affichage du tableau de bord d'administration.
     *
     * Séquence d'exécution :
     * 1. Vérification de la session active (Programmation défensive).
     * 2. Contrôle d'accès restrictif (Redirection si non-authentifié).
     * 3. Extraction des données via le Modèle.
     * 4. Assemblage et rendu des composants visuels.
     *
     * @return void
     */
    public function showDashboard(): void
    {
        // 1. Programmation défensive : S'assure que le contexte de session est disponible.
        // Bien que le routeur l'initialise, cette vérification garantit l'autonomie et la robustesse du contrôleur.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 2. Clause de garde (Guard Clause) : Contrôle strict de l'authentification.
        // Si l'identifiant utilisateur est absent de la session, la requête est rejetée
        // et l'utilisateur est redirigé vers le point d'entrée sécurisé.
        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit; // Arrêt immédiat du script pour prévenir toute fuite d'informations (Data Leak).
        }

        // 3. Sollicitation de la couche d'accès aux données (Data Access Layer).
        // Instanciation du modèle Prospect et récupération de l'ensemble des leads commerciaux.
        $prospectModel = new Prospect();
        $prospects = $prospectModel->findAll();

        // 4. Préparation du contexte d'affichage (Variables injectées dans les vues).
        $pageTitle = "Tableau de Bord - Innov'Events";

        // 5. Assemblage de la réponse HTTP via l'inclusion des composants visuels (Architecture Modulaire).
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/dashboard.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Orchestre l'affichage des détails complets d'un prospect.
     *
     * @param int $id Identifiant du prospect à afficher.
     * @return void
     */
    public function showProspectDetails(int $id): void
    {
        // 1. Contrôle de sécurité de la session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clause de garde (Guard Clause)
        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // 2. Récupération du prospect via le Modèle
        $prospectModel = new Prospect();
        $prospect = $prospectModel->find($id);

        // Si le prospect n'existe pas, redirection de sécurité vers le dashboard
        if (!$prospect) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        // 3. Préparation du contexte pour la vue
        $pageTitle = "Détails du Prospect - " . htmlspecialchars($prospect['company_name']);

        // 4. Rendu de la vue (Pas besoin de header.php/footer.php classiques car on garde la sidebar)
        require __DIR__ . '/../views/admin/view_prospect.php';

    }
}