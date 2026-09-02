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
 * @version    1.3.0
 */

// Chargement des dépendances métiers (Contrôleurs)
require_once __DIR__ . '/../vendor/autoload.php';

// Chargement de l'ensemble des contrôleurs applicatifs
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/controllers/DashboardController.php';
require_once __DIR__ . '/../src/controllers/ClientController.php';
require_once __DIR__ . '/../src/controllers/AdminClientController.php';
require_once __DIR__ . '/../src/controllers/QuoteController.php';
require_once __DIR__ . '/../src/controllers/LogController.php';
require_once __DIR__ . '/../src/controllers/PdfController.php';
require_once __DIR__ . '/../src/controllers/EventController.php';
require_once __DIR__ . '/../src/controllers/AdminEventController.php';

// Initialisation sécurisée du contexte utilisateur (Session)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupération de la route demandée (Fallback sur 'home' si non spécifiée)
$action = $_GET['action'] ?? 'home';

// Système de routage principal (Délégation MVC)
switch (true) {

    // -------------------------------------------------------------------
    // ROUTE : DEMANDE DE DEVIS (Espace Public)
    // -------------------------------------------------------------------
    case ($action === 'devis'):
        $quoteController = new QuoteController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $quoteController->submitQuote($_POST);
        } else {
            $quoteController->showForm();
        }
        break;

    // -------------------------------------------------------------------
    // ROUTES : AUTHENTIFICATION & COMPTE (Espace Public / Securisé)
    // -------------------------------------------------------------------
    case ($action === 'login'):
        $authController = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->login($_POST);
        } else {
            $authController->showLoginForm();
        }
        break;

    case ($action === 'show_register'):
        (new AuthController())->showRegisterForm();
        break;

    case ($action === 'register'):
        $authController = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->register($_POST);
        } else {
            $authController->showRegisterForm();
        }
        break;

    case ($action === 'logout'):
        (new AuthController())->logout();
        break;

    // -------------------------------------------------------------------
    // WORKFLOW MOT DE PASSE OUBLIÉ ET FORCÉ
    // -------------------------------------------------------------------
    case ($action === 'forgot_password'):
        (new AuthController())->showForgotPasswordForm();
        break;

    case ($action === 'reset_password_request'):
        (new AuthController())->resetPasswordRequest($_POST);
        break;

    case ($action === 'force_password_change'):
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['temp_user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }
        require __DIR__ . '/../src/views/public/force_password_change.php';
        break;

    case ($action === 'update_forced_password'):
        (new AuthController())->updateForcedPassword($_POST);
        break;

    // -------------------------------------------------------------------
    // ROUTES : TABLEAU DE BORD & PROSPECTS (Espace Admin / Staff)
    // -------------------------------------------------------------------
    case ($action === 'dashboard'):
        (new DashboardController())->showDashboard();
        break;

    case ($action === 'prospects'):
        (new DashboardController())->showProspectsList();
        break;

    case ($action === 'view_prospect'):
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        (new DashboardController())->showProspectDetails($id);
        break;

    case ($action === 'show_convert_form'):
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            (new DashboardController())->showConvertForm((int)$_GET['id']);
        } else {
            header('Location: index.php?action=dashboard');
        }
        break;

    case ($action === 'process_conversion'):
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new DashboardController())->processConversion($_POST);
        } else {
            header('Location: index.php?action=dashboard');
        }
        break;

    case ($action === 'update_prospect_status'):
        (new DashboardController())->updateProspectStatus();
        break;

    case ($action === 'admin_events'):
        (new AdminEventController())->listEvents();
        break;

    case ($action === 'admin_event_update_status'):
        (new AdminEventController())->updateStatus();
        break;

    case ($action === 'admin_event_detail'):
        (new AdminEventController())->showEventDetail();
        break;

    // -------------------------------------------------------------------
    // ROUTES : GESTION DES DEVIS & PRESTATIONS (QuoteController - Back-Office)
    // -------------------------------------------------------------------
    case ($action === 'admin_devis'):
        (new QuoteController())->showDevisList();
        break;

    case ($action === 'edit_devis'):
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id > 0) {
            (new QuoteController())->editDevis($id);
        } else {
            header('Location: index.php?action=admin_devis');
        }
        break;

    case ($action === 'add_prestation'):
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new QuoteController())->addPrestation($_POST);
        } else {
            header('Location: index.php?action=admin_devis');
        }
        break;

    case ($action === 'delete_prestation'):
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new QuoteController())->deletePrestation($_POST);
        } else {
            header('Location: index.php?action=admin_devis');
        }
        break;

    case ($action === 'admin_add_note'):
        (new AdminEventController())->addNote();
        break;

    // -------------------------------------------------------------------
    // ROUTES : ADMINISTRER LES CLIENTS (AdminClientController - Back-Office)
    // -------------------------------------------------------------------
    case ($action === 'admin_clients'):
        (new AdminClientController())->showClientsList();
        break;

    case ($action === 'view_client' && isset($_GET['id'])):
        (new AdminClientController())->showClientDetails((int)$_GET['id']);
        break;

    case ($action === 'edit_client' && isset($_GET['id'])):
        (new AdminClientController())->showEditClientForm((int)$_GET['id']);
        break;

    case ($action === 'update_client' && $_SERVER['REQUEST_METHOD'] === 'POST'):
        (new AdminClientController())->updateClient($_POST);
        break;

    case ($action === 'delete_client' && $_SERVER['REQUEST_METHOD'] === 'POST'):
        (new AdminClientController())->deleteClient($_POST);
        break;

    // -------------------------------------------------------------------
    // ROUTES : ESPACE CLIENT PRIVÉ (ClientController - Front-Office)
    // -------------------------------------------------------------------
    case ($action === 'client_dashboard'):
        (new ClientController())->showDashboard();
        break;

    case ($action === 'respond_to_quote'):
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new ClientController())->handleQuoteResponse($_POST);
        } else {
            header('Location: index.php?action=client_dashboard');
        }
        break;

    case ($action === 'client_profile'):
        (new ClientController())->showProfile();
        break;

    case ($action === 'delete_account'):
        (new ClientController())->deleteAccount();
        break;

    // -------------------------------------------------------------------
    // ROUTES : SERVICES COMPLÉMENTAIRES (PDF & AUDIT)
    // -------------------------------------------------------------------
    case ($action === 'mongo_logs'):
        (new LogController())->showMongoLogs();
        break;

    case ($action === 'generate_pdf'):
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        (new PdfController())->generatePdf($id);
        break;

    case ($action === 'send_quote_to_client'):
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id > 0) {
            (new PdfController())->sendQuoteToClient($id);
        } else {
            header('Location: index.php?action=admin_devis');
        }
        break;


    // -------------------------------------------------------------------
    // ROUTES : VITRINE ÉVÉNEMENTS (Espace Public)
    // -------------------------------------------------------------------
    case ($action === 'events'):
        (new EventController())->listPublicEvents();
        break;

    case ($action === 'event_detail'):
        (new EventController())->showPublicDetail();
        break;


    // -------------------------------------------------------------------
    // ROUTE PAR DÉFAUT : PAGE D'ACCUEIL (Espace Public)
    // -------------------------------------------------------------------
    default:
        $isLoggedIn = isset($_SESSION['user_id']);
        $userName = $_SESSION['user_name'] ?? '';
        $userRole = $_SESSION['user_role'] ?? '';

        require_once __DIR__ . '/../src/views/public/home.php';
        break;
}