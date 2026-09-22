<?php
/**
 * Point d'entrée unique de l'application (Front Controller).
 *
 * Toutes les requêtes HTTP passent par ce fichier. Il initialise l'environnement,
 * démarre la session utilisateur, et dispatche la requête vers le contrôleur approprié.
 *
 * @package    InnovEventsManager
 * @author     Romain Remusat
 * @version    3.3.0 (Intégration ECF - AT1 & AT2)
 */

declare(strict_types=1);

// PHP vide les champs POST lorsque la requête entière dépasse sa limite.
// Signaler ce cas avant le contrôle CSRF évite un faux diagnostic de session expirée.
$postLimit = ini_parse_quantity(ini_get('post_max_size'));
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $postLimit > 0
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $postLimit) {
    http_response_code(413);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Envoi trop volumineux : choisissez une image de 5 Mo maximum, puis revenez au formulaire pour réessayer.');
}

// -----------------------------------------------------------------------------
// 1. GESTION STRICTE DES SESSIONS (Conformité OWASP & RGPD)
// -----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// -----------------------------------------------------------------------------
// 2. GÉNÉRATION DU JETON ANTI-CSRF (Conformité AT1 - Sécurité des flux)
// -----------------------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -----------------------------------------------------------------------------
// 3. CHARGEMENT DES CONTRÔLEURS
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/controllers/EventController.php';
require_once __DIR__ . '/../src/controllers/QuoteController.php';
require_once __DIR__ . '/../src/controllers/PdfController.php';
require_once __DIR__ . '/../src/controllers/ClientController.php';
require_once __DIR__ . '/../src/controllers/AdminClientController.php';
require_once __DIR__ . '/../src/controllers/AdminAccountController.php';
require_once __DIR__ . '/../src/controllers/AdminEventController.php';
require_once __DIR__ . '/../src/controllers/DashboardController.php';

// -----------------------------------------------------------------------------
// 4. RÉSOLUTION DE L'ACTION ET ROUTAGE (White-list Pattern)
// -----------------------------------------------------------------------------
$action = filter_input(INPUT_GET, 'action', FILTER_DEFAULT) ?? 'home';

switch (true) {
    // -------------------------------------------------------------------
    // ROUTES : AUTHENTIFICATION & COMPTE (AuthController)
    // -------------------------------------------------------------------
    case ($action === 'login'):
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new AuthController())->login($_POST);
        } else {
            (new AuthController())->showLoginForm();
        }
        break;

    case ($action === 'login_process'):
        (new AuthController())->login($_POST);
        break;

    case ($action === 'logout'):
        (new AuthController())->logout();
        break;

    case ($action === 'register'):
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new AuthController())->register($_POST);
        } else {
            (new AuthController())->showRegisterForm();
        }
        break;

    case ($action === 'show_register'):
        (new AuthController())->showRegisterForm();
        break;

    case ($action === 'reset_password_request' || $action === 'forgot_password'):
        (new AuthController())->resetPasswordRequest();
        break;

    case ($action === 'force_password_change' || $action === 'update_forced_password'):
        (new AuthController())->updateForcedPassword();
        break;

    // -------------------------------------------------------------------
    // ROUTES : VITRINE PUBLIQUE & PROSPECTS (Event / Quote)
    // -------------------------------------------------------------------
    case ($action === 'home'):
        (new EventController())->showHome();
        break;

    case ($action === 'events'):
        (new EventController())->listPublicEvents();
        break;

    case ($action === 'catalog'):
        (new EventController())->showCatalog();
        break;

    case ($action === 'event_detail'):
        (new EventController())->showPublicDetail();
        break;

    case ($action === 'devis'):
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new QuoteController())->submitQuote($_POST);
        } else {
            (new QuoteController())->showForm();
        }
        break;

    case ($action === 'submit_quote' || $action === 'process_quote'):
        (new QuoteController())->submitQuote($_POST);
        break;

    // -------------------------------------------------------------------
    // ROUTES : ADMINISTRATION DES COMPTES ET ÉVÉNEMENTS
    // -------------------------------------------------------------------
    case ($action === 'admin_accounts'):
        (new AdminAccountController())->index();
        break;

    case ($action === 'admin_manage_account'):
        (new AdminAccountController())->manage($_POST);
        break;

    case ($action === 'admin_edit_event'):
        (new AdminEventController())->editEvent((int)($_GET['id'] ?? 0));
        break;

    case ($action === 'admin_save_event'):
        (new AdminEventController())->saveEvent($_POST);
        break;

    case ($action === 'admin_create_event_quote'):
        (new AdminEventController())->createQuote($_POST);
        break;

    case ($action === 'admin_delete_event'):
        (new AdminEventController())->deleteEvent($_POST);
        break;

    // ROUTES : ESPACE CLIENT B2B (ClientController)
    case ($action === 'client_dashboard'):
        (new ClientController())->showDashboard();
        break;

    case ($action === 'respond_to_quote'):
        (new ClientController())->handleQuoteResponse($_POST);
        break;

    case ($action === 'client_profile'):
        (new ClientController())->showProfile();
        break;

    case ($action === 'client_update_profile'):
        (new ClientController())->updateProfile($_POST);
        break;

    case ($action === 'client_delete_account'):
        (new ClientController())->deleteAccount();
        break;

    // -------------------------------------------------------------------
    // ROUTES : TABLEAU DE BORD & PIPELINE PROSPECTS (DashboardController)
    // -------------------------------------------------------------------
    case ($action === 'dashboard'):
        (new DashboardController())->showDashboard();
        break;

    case ($action === 'prospects' || $action === 'admin_prospects' || $action === 'list_prospects'):
        (new DashboardController())->showProspectsList();
        break;

    case ($action === 'view_prospect'):
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        (new DashboardController())->showProspectDetails($id);
        break;

    case ($action === 'update_prospect_status'):
        (new DashboardController())->updateProspectStatus();
        break;

    case ($action === 'show_convert_form'):
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        (new DashboardController())->showConvertForm($id);
        break;

    case ($action === 'process_conversion'):
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new DashboardController())->processConversion($_POST);
        } else {
            header('Location: index.php?action=dashboard');
        }
        break;

    // -------------------------------------------------------------------
    // ROUTES : GESTION DES DEVIS BACK-OFFICE (QuoteController & PdfController)
    // -------------------------------------------------------------------
    case ($action === 'admin_devis'):
        (new QuoteController())->showDevisList();
        break;

    case ($action === 'edit_devis'):
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        (new QuoteController())->editDevis($id);
        break;

    case ($action === 'add_prestation'):
        (new QuoteController())->addPrestation($_POST);
        break;

    case ($action === 'delete_prestation'):
        (new QuoteController())->deletePrestation($_POST);
        break;

    case ($action === 'send_quote_to_client'):
        $id = (int)($_POST['id'] ?? $_POST['devis_id'] ?? $_GET['id'] ?? 0);
        (new PdfController())->sendQuoteToClient($id);
        break;

    case ($action === 'download_devis' || $action === 'download_pdf'):
        (new PdfController())->downloadDevis();
        break;

    case ($action === 'generate_pdf'):
        (new PdfController())->generatePdfAction();
        break;

    // -------------------------------------------------------------------
    // ROUTES : GESTION DES ÉVÉNEMENTS BACK-OFFICE (AdminEventController)
    // -------------------------------------------------------------------
    case ($action === 'admin_events'):
        (new AdminEventController())->listEvents();
        break;

    case ($action === 'admin_event_detail'):
        (new AdminEventController())->showEventDetail();
        break;

    case ($action === 'admin_event_update_status' || $action === 'admin_update_event_status'):
        (new AdminEventController())->updateStatus();
        break;

    case ($action === 'admin_event_toggle_publish'):
        (new AdminEventController())->togglePublish();
        break;

    case ($action === 'admin_add_note'):
        (new AdminEventController())->addNote();
        break;

    case ($action === 'admin_update_note'):
        (new AdminEventController())->updateNote();
        break;

    case ($action === 'admin_delete_note'):
        (new AdminEventController())->deleteNote();
        break;

    case ($action === 'admin_create_task'):
        (new AdminEventController())->createTask();
        break;

    case ($action === 'admin_update_task_status'):
        (new AdminEventController())->updateTaskStatus();
        break;

    case ($action === 'admin_delete_task'):
        (new AdminEventController())->deleteTask();
        break;

    case ($action === 'admin_upload_image'):
        (new AdminEventController())->uploadImage();
        break;

    // -------------------------------------------------------------------
    // ROUTES : GESTION DES CLIENTS BACK-OFFICE (AdminClientController)
    // -------------------------------------------------------------------
    case ($action === 'admin_clients' || $action === 'clients'):
        (new AdminClientController())->showClientsList();
        break;

    case ($action === 'view_client'):
        (new AdminClientController())->showClientDetails((int)($_GET['id'] ?? 0));
        break;

    case ($action === 'edit_client'):
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            (new AdminClientController())->updateClient($_POST);
        } else {
            (new AdminClientController())->showEditClientForm((int)($_GET['id'] ?? 0));
        }
        break;

    case ($action === 'update_client'):
        (new AdminClientController())->updateClient($_POST);
        break;

    case ($action === 'delete_client'):
        (new AdminClientController())->deleteClient($_POST);
        break;

    // -------------------------------------------------------------------
    // ROUTES : LOGS D'AUDIT NOSQL BACK-OFFICE
    // -------------------------------------------------------------------
    case ($action === 'mongo_logs'):
        require_once __DIR__ . '/../src/controllers/LogController.php';
        (new LogController())->showMongoLogs();
        break;

    // -------------------------------------------------------------------
    // ROUTE PAR DÉFAUT / 404 (Redirection d'étanchéité)
    // -------------------------------------------------------------------
    default:
        (new EventController())->listPublicEvents();
        break;
}
