<?php
/**
 * Routeur Central / Point d'entrée unique de l'application (Front Controller)
 *
 * Ce fichier agit comme le point d'entrée unique de l'application (pattern Front Controller).
 * Il intercepte toutes les requêtes HTTP entrantes, initialise le contexte global
 * (comme les sessions sécurisées), et délègue le traitement métier et l'affichage
 * au contrôleur approprié en fonction du paramètre 'action' passé dans l'URL.
 *
 * @package    InnovEventsManager
 * @subpackage Core
 * @author     Romain Remusat
 * @version    1.2.1
 */

// Chargement des dépendances métiers (Contrôleurs)
require_once __DIR__ . '/../vendor/autoload.php';

// Mise à jour des chemins : on remonte du dossier public/ vers le dossier src/
require_once __DIR__ . '/../src/controllers/QuoteController.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/controllers/DashboardController.php';
require_once __DIR__ . '/../src/controllers/LogController.php';
require_once __DIR__ . '/../src/controllers/PdfController.php';
require_once __DIR__ . '/../src/controllers/ClientController.php';


// Initialisation sécurisée du contexte utilisateur (Session)
// Permet de maintenir l'état d'authentification et les droits d'accès à travers l'application.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Récupération de la route demandée (Fallback sur 'home' si non spécifiée)
$action = $_GET['action'] ?? 'home';

// Système de routage principal (Délégation MVC)
switch (true) {

    // -------------------------------------------------------------------
    // ROUTE : GESTION DES DEVIS (Espace Public)
    // -------------------------------------------------------------------
    case ($action === 'devis'):
        $controller = new QuoteController();
        // Aiguillage selon le verbe HTTP : Traitement de formulaire (POST) ou Affichage (GET)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->submitQuote($_POST);
        } else {
            $controller->showForm();
        }
        break;

    // -------------------------------------------------------------------
    // ROUTE : AUTHENTIFICATION (Espace Public / Admin)
    // -------------------------------------------------------------------
    case ($action === 'login'):
        $authController = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->login($_POST);
        } else {
            $authController->showLoginForm();
        }
        break;

    // -------------------------------------------------------------------
    // ROUTE : Formulaire création compte (Espace Public)
    // -------------------------------------------------------------------
    case ($action === 'show_register'):
        $authController = new AuthController();
        $authController->showRegisterForm();
        break;

    // -------------------------------------------------------------------
    // ROUTE : Enregistrement compte (Espace Public)
    // -------------------------------------------------------------------
    case ($action === 'register'):
        $authController = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->register($_POST);
        } else {
            $authController->showRegisterForm();
        }
        break;


    // -------------------------------------------------------------------
    // ROUTE : DÉCONNEXION (Espace Admin)
    // -------------------------------------------------------------------
    case ($action === 'logout'):
        $authController = new AuthController();
        $authController->logout();
        break;

    // -------------------------------------------------------------------
    // ROUTE : TABLEAU DE BORD (Espace Admin Sécurisé)
    // -------------------------------------------------------------------
    case ($action === 'dashboard'):
        $dashboardController = new DashboardController();
        $dashboardController->showDashboard();
        break;

    // -------------------------------------------------------------------
    // ROUTE : LISTE DES PROSPECTS (Espace Admin Sécurisé)
    // -------------------------------------------------------------------
    case ($action === 'prospects'):
        $dashboardController = new DashboardController();
        $dashboardController->showProspectsList();
        break;

    // -------------------------------------------------------------------
    // ROUTE : JOURNAL D'AUDIT NOSQL (Logs MongoDB)
    // -------------------------------------------------------------------
    case ($action === 'mongo_logs'):
        $dashboardController = new LogController();
        $dashboardController->showMongoLogs();
        break;

    // -------------------------------------------------------------------
    // ROUTE : DÉTAILS D'UN PROSPECT (Espace Admin Sécurisé)
    // -------------------------------------------------------------------
    case ($action === 'view_prospect'):
        $dashboardController = new DashboardController();
        // On récupère l'ID depuis l'URL en le castant en int pour la sécurité
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $dashboardController->showProspectDetails($id);
        break;

    // -------------------------------------------------------------------
    // ROUTE : Génération du PDF (Espace Admin Sécurisé)
    // -------------------------------------------------------------------
    case ($action === 'generate_pdf'):
        $dashboardController = new PdfController();
        // On récupère l'ID depuis l'URL en le castant en int pour la sécurité
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $dashboardController->generatePdf($id);
        break;


    // -------------------------------------------------------------------
    // ROUTE : MISE À JOUR DU STATUT PROSPECT (Requête POST - Admin)
    // -------------------------------------------------------------------
    case ($action === 'update_prospect_status'):
        $dashboardController = new DashboardController();
        $dashboardController->updateProspectStatus();
        break;

    // -------------------------------------------------------------------
    // ROUTE : Espace client
    // -------------------------------------------------------------------
    case ($action === 'client_dashboard'):
        $clientController = new ClientController();
        $clientController->showDashboard();
        break;

    // -------------------------------------------------------------------
    // ROUTE : Traitement de la réponse au devis par le client
    // -------------------------------------------------------------------
    case ($action === 'respond_to_quote'):
        $clientController = new ClientController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $clientController->handleQuoteResponse($_POST);
        } else {
            header('Location: index.php?action=client_dashboard');
        }
        break;


    // -------------------------------------------------------------------
    // ROUTE : Affichage du profil client (RGPD)
    // -------------------------------------------------------------------
    case ($action === 'client_profile'):
        $clientController = new ClientController();
        $clientController->showProfile();
        break;

    // -------------------------------------------------------------------
    // ROUTE : Traitement de la suppression de compte (RGPD)
    // -------------------------------------------------------------------
    case ($action === 'delete_account'):
        $clientController = new ClientController();
        $clientController->deleteAccount();
        break;

    // -------------------------------------------------------------------
    // --- WORKFLOW MOT DE PASSE OUBLIÉ ---
    // -------------------------------------------------------------------

    case ($action === 'forgot_password'):
        $authController = new AuthController();
        $authController->showForgotPasswordForm();
        break;

    case ($action === 'reset_password_request'):
        $authController = new AuthController();
        $authController->resetPasswordRequest($_POST);
        break;

    case ($action === 'force_password_change'):
        // S'il n'y a pas de session temporaire en cours, on rejette l'accès
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['temp_user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }
        require __DIR__ . '/../src/views/public/force_password_change.php';
        break;

    case ($action === 'update_forced_password'):
        $authController = new AuthController();
        $authController->updateForcedPassword($_POST);
        break;


    // -------------------------------------------------------------------
    // ROUTE PAR DÉFAUT : PAGE D'ACCUEIL (Espace Public)
    // -------------------------------------------------------------------
    default:
        // Préparation des variables de contexte pour personnaliser l'affichage de l'accueil
        $isLoggedIn = isset($_SESSION['user_id']);
        $userName = $_SESSION['user_name'] ?? '';
        $userRole = $_SESSION['user_role'] ?? '';

        // Injection directe de la vue (Chemin mis à jour vers src/)
        require_once __DIR__ . '/../src/views/public/home.php';
        break;

}