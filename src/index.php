<?php
/**
 * Routeur Central / Point d'entrée unique de l'application (Front Controller)
 *
 * @package    InnovEventsManager
 * @subpackage Core
 * @author     Romain Remusat
 * @version    1.1.2
 */

require_once __DIR__ . '/controllers/QuoteController.php';
require_once __DIR__ . '/controllers/AuthController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? 'home';

// Système de routage via structure de garde (Switch)
switch (true) {
    case ($action === 'devis'):
        $controller = new QuoteController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->submitQuote($_POST);
        } else {
            $controller->showForm();
        }
        break;

    case ($action === 'login'):
        $authController = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->login($_POST);
        } else {
            $authController->showLoginForm();
        }
        break;

    case ($action === 'logout'):
        $authController = new AuthController();
        $authController->logout();
        break;

    default: // Route par défaut : Accueil
        echo "<!DOCTYPE html>\n";
        echo "<html lang='fr'>\n";
        echo "<head>\n";
        echo "    <meta charset='UTF-8'>\n";
        echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
        echo "    <title>Innov'Events Manager</title>\n";
        echo "    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
        echo "</head>\n";
        echo "<body class='bg-light'>\n";
        echo "    <div class='container mt-5 text-center'>\n";

        if (isset($_SESSION['user_id'])) {
            echo "        <h1 class='mb-4'>Bonjour, " . htmlspecialchars($_SESSION['user_name']) . " 👋</h1>\n";
            echo "        <p class='lead'>Vous êtes connecté en tant que : <strong>" . htmlspecialchars($_SESSION['user_role']) . "</strong></p>\n";
            echo "        <a href='index.php?action=logout' class='btn btn-danger btn-lg me-2'>Se déconnecter</a>\n";
        } else {
            echo "        <h1 class='mb-4'>Bienvenue sur Innov'Events Manager</h1>\n";
            echo "        <a href='index.php?action=login' class='btn btn-primary btn-lg me-2'>Connexion Admin</a>\n";
        }

        echo "        <a href='index.php?action=devis' class='btn btn-success btn-lg'>Demander un devis</a>\n";
        echo "    </div>\n";
        echo "</body>\n";
        echo "</html>\n";
        break;
}