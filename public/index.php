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
// Mise à jour des chemins : on remonte du dossier public/ vers le dossier src/
require_once __DIR__ . '/../src/controllers/QuoteController.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/controllers/DashboardController.php';

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
    // ROUTE : DÉTAILS D'UN PROSPECT (Espace Admin Sécurisé)
    // -------------------------------------------------------------------
    case ($action === 'view_prospect'):
        $dashboardController = new DashboardController();
        // On récupère l'ID depuis l'URL en le castant en int pour la sécurité
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $dashboardController->showProspectDetails($id);
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