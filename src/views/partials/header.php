<?php
/**
 * Composant : En-tête global et Barre de navigation (Header partial)
 *
 * Ce fichier implémente la structure d'initialisation HTML5, la configuration du
 * document (balises meta, polices, feuilles de style) ainsi que la barre de navigation
 * supérieure (Navbar) commune à l'ensemble du tunnel public d'Innov'Events Manager.
 *
 * Spécifications techniques et conformité :
 * - Gestion du contexte de session : Initialisation fail-safe pour l'affichage dynamique des CTA.
 * - Centralisation du design system : Intégration de la police Inter et des couleurs de la charte.
 * - Surcharge utilitaire Bootstrap 5 pour l'élégance minimaliste (Figma Wireframe Look).
 * - Prise en charge du responsive sémantique via les composants natifs de grille.
 *
 * @package    InnovEventsManager
 * @subpackage Views/Partials
 * @author     Romain Remusat
 * @version    2.0.0
 * @var string $pageTitle Spécifié dynamiquement par la vue appelante pour le SEO des onglets.
 */

// Sécurisation de l'accès aux variables de session globales avant tout rendu de flux de données
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? "Innov'Events Manager" ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="views/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-navbar-custom sticky-top border-bottom border-secondary py-3">
    <div class="container">
        <a class="navbar-brand fw-bold text-uppercase tracking-wider fs-5 text-white" href="index.php">
            <i class="bi bi-layers-half text-primary me-2"></i>INNOV'EVENTS
        </a>

        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav align-items-center gap-3">
                <li class="nav-item"><a class="nav-link text-white-50 text-uppercase fw-semibold" style="font-size: 0.85rem;" href="index.php#services">Services</a></li>
                <li class="nav-item"><a class="nav-link text-white-50 text-uppercase fw-semibold" style="font-size: 0.85rem;" href="index.php#partenaires">Partenaires</a></li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="btn btn-light  btn-sm me-2" href="index.php?action=dashboard"><i class="bi bi-speedometer2 me-2"></i> Tableau de bord</a></li>
                    <li class="nav-item"><a class="btn btn-danger btn-sm px-3" href="index.php?action=logout">Déconnexion</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="btn btn-sm btn-outline-light px-3" href="index.php?action=login" style="font-size: 0.85rem;">Espace Pro</a></li>
                <?php endif; ?>

                <li class="nav-item"><a class="btn btn-primary-custom btn-sm px-4 fw-bold shadow-sm" href="index.php?action=devis"><i class="bi bi-chat-left-dots me-2"></i>Demander un Devis</a></li>
            </ul>
        </div>
    </div>
</nav>