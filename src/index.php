<?php
// Fichier : src/index.php
require_once __DIR__ . '/controllers/QuoteController.php';

$action = $_GET['action'] ?? 'home';

if ($action === 'devis') {
    $controller = new QuoteController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->submitQuote($_POST);
    } else {
        $controller->showForm();
    }
} else {
    // Accueil simplifié pour ton MVP
    echo "<!DOCTYPE html><html lang='fr'><head><title>Innov'Events Manager</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head>";
    echo "<body class='bg-light'><div class='container mt-5 text-center'>";
    echo "<h1 class='mb-4'>Bienvenue sur Innov'Events Manager</h1>";
    echo "<a href='index.php?action=devis' class='btn btn-success btn-lg'>Demander un devis</a>";
    echo "</div></body></html>";
}