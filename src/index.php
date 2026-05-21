<?php
/**
 * Routeur Central / Point d'entrée unique de l'application (Front Controller)
 *
 * Ce fichier intercepte toutes les requêtes HTTP entrantes, gère la session globale,
 * et dispatche la requête vers le contrôleur approprié en fonction du paramètre 'action'.
 *
 * @package    InnovEventsManager
 * @subpackage Core
 * @author     Romain Remusat
 * @version    1.1.1
 */

// Inclusion des contrôleurs nécessaires au fonctionnement des routes
require_once __DIR__ . '/controllers/QuoteController.php';
require_once __DIR__ . '/controllers/AuthController.php';

/**
 * 1. Gestion des Sessions
 * Démarrage de la session globale si elle n'est pas déjà active.
 * Indispensable pour maintenir l'état d'authentification de l'utilisateur à travers l'application.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 2. Initialisation du routage
 * Récupération de l'action demandée dans l'URL (par défaut 'home' si non spécifiée).
 * @var string $action
 */
$action = $_GET['action'] ?? 'home';

/**
 * 3. Dispatching (Système de routage)
 * Redirection de la requête vers la méthode du contrôleur correspondante.
 */
if ($action === 'devis') {

    // Route : Gestion des demandes de devis (Prospects)
    $controller = new QuoteController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->submitQuote($_POST);
    } else {
        $controller->showForm();
    }

} elseif ($action === 'login') {

    // Route : Authentification (Affichage et Traitement de la connexion)
    $authController = new AuthController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $authController->login($_POST);
    } else {
        $authController->showLoginForm();
    }

} elseif ($action === 'logout') {

    // Route : Authentification (Déconnexion et destruction de la session)
    $authController = new AuthController();
    $authController->logout();

} else {

    // Route par défaut : Accueil
    // @todo: Externaliser ce bloc HTML dans un HomeController et une vue (ex: views/public/home.php) pour respecter le pattern MVC strict.

    echo "<!DOCTYPE html>\n";
    echo "<html lang='fr'>\n";
    echo "<head>\n";
    // CORRECTION ENCODAGE : Spécification stricte du jeu de caractères pour éviter l'affichage de caractères corrompus (ex: Chlo??)
    echo "    <meta charset='UTF-8'>\n";
    echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
    echo "    <title>Innov'Events Manager</title>\n";
    echo "    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n";
    echo "</head>\n";
    echo "<body class='bg-light'>\n";
    echo "    <div class='container mt-5 text-center'>\n";


    // Affichage dynamique selon l'état de connexion de l'utilisateur
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
}