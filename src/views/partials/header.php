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

    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif !important;
            color: #475569 !important; /* Slate 600 : Réduction du contraste agressif pour le confort visuel */
        }
        .bg-navbar-custom {
            background-color: #0F172A !important; /* Slate Dark : Couleur dominante de la charte (#0F172A) */
        }
        .btn-primary-custom {
            background-color: #3B82F6 !important; /* Bleu Électrique : Couleur d'action de la charte (#3B82F6) */
            border-color: #3B82F6 !important;
            color: #FFFFFF !important;
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.6rem 1.5rem;
            border-radius: 4px; /* Format rectangulaire épuré calqué sur le fil de fer figma */
            transition: background-color 0.2s ease-in-out;
        }
        .btn-primary-custom:hover {
            background-color: #2563EB !important; /* Assombrissement ergonomique pour l'état de survol (Hover) */
        }
        /* Style minimaliste appliqué aux libellés de formulaires (High-Fidelity Wireframe Look) */
        .label-minimal {
            font-size: 0.72rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.08em !important;
            font-weight: 600 !important;
            color: #64748B !important; /* Slate 500 */
        }
        /* Intégration épurée des champs de saisie (Zéro boîte lourde, bordures ultra-fines) */
        .form-control-minimal {
            display: block;
            width: 100%;
            padding: 0.65rem 0.85rem;
            font-size: 0.9rem;
            color: #0F172A;
            background-color: #FFFFFF;
            border: 1px solid #E2E8F0; /* Slate 200 */
            border-radius: 4px;
        }
        .form-control-minimal:focus {
            border-color: #3B82F6;
            outline: 0;
            box-shadow: none; /* Suppression des halos lourds natifs Bootstrap pour préserver la légèreté visuelle */
        }
        .divider-fine {
            border-top: 1px solid #F1F5F9 !important; /* Slate 100 : Ligne fine de délimitation de section figma */
            opacity: 1;
        }
    </style>
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
                    <li class="nav-item"><a class="btn btn-danger btn-sm px-3" href="index.php?action=logout">Déconnexion</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="btn btn-sm btn-outline-light px-3" href="index.php?action=login" style="font-size: 0.85rem;">Espace Pro</a></li>
                <?php endif; ?>

                <li class="nav-item"><a class="btn btn-primary-custom btn-sm px-4 fw-bold shadow-sm" href="index.php?action=devis"><i class="bi bi-chat-left-dots me-2"></i>Demander un Devis</a></li>
            </ul>
        </div>
    </div>
</nav>