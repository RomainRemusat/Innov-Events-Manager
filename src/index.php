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
        // On prépare les variables nécessaires pour la vue si besoin (ex: gestion session)
        $isLoggedIn = isset($_SESSION['user_id']);
        $userName = $_SESSION['user_name'] ?? '';
        $userRole = $_SESSION['user_role'] ?? '';

        // On appelle proprement la vue isolée
        require_once __DIR__ . '/views/public/home.php';
        break;
}