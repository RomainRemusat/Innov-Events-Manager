<?php
/**
 * Routeur Central / Point d'entrée unique (Front Controller)
 *
 * Ce fichier est le point de passage obligatoire pour toutes les requêtes de l'application.
 * Il analyse l'action demandée dans l'URL (`$_GET['action']`), initialise le contrôleur
 * adéquat et appelle la méthode correspondante selon la méthode HTTP (GET/POST).
 *
 * @package    InnovEventsManager
 * @author     Romain Remusat
 * @version    1.0.0
 */

require_once __DIR__ . '/controllers/QuoteController.php';

// Capture de l'action demandée (Aiguillage par défaut vers 'home' si non spécifiée)
$action = $_GET['action'] ?? 'home';

// Système de routage (Routing)
if ($action === 'devis') {
    $controller = new QuoteController();

    // Distinction du verbe HTTP pour séparer l'affichage du traitement
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->submitQuote($_POST);
    } else {
        $controller->showForm();
    }
} else {
    /**
     * Page d'accueil temporaire - Version V1 (MVP)
     * Fournit un point d'accès rapide vers le formulaire de devis pour les tests.
     */
    echo "<!DOCTYPE html><html lang='fr'><head><title>Innov'Events Manager</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head>";
    echo "<body class='bg-light'><div class='container mt-5 text-center'>";
    echo "<h1 class='mb-4'>Bienvenue sur Innov'Events Manager</h1>";
    echo "<a href='index.php?action=devis' class='btn btn-success btn-lg'>Demander un devis</a>";
    echo "</div></body></html>";
}