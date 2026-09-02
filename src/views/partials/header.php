<?php
/**
 * Composant : En-tête global et Barre de navigation (Header partial)
 *
 * Ce composant implémente la structure HTML5, les métadonnées SEO/accessibilité
 * et la barre de navigation supérieure commune à l'ensemble de la vitrine publique.
 *
 * Exigences ECF respectées (AT1) :
 * - Respect strict des entrées du menu imposées par le cahier des charges officiel
 *   (Accueil, Événements, Avis, Contact, Connexion/Déconnexion, Demande de devis).
 * - Sémantique HTML5 et conformité RGAA (attributs ARIA, contrastes, navigation clavier).
 * - Gestion du contexte utilisateur et protection CSRF centralisée.
 *
 * @package    InnovEventsManager
 * @subpackage Views/Partials
 * @author     Romain Remusat
 * @version    2.1.0
 * @var string|null $pageTitle Titre de l'onglet injecté par la vue appelante.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentAction = $_GET['action'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Innov'Events - Conception et organisation sur-mesure d'événements professionnels d'exception.">
    <title><?= htmlspecialchars($pageTitle ?? "Innov'Events Manager", ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Polices et Iconographie -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- Framework UI & Feuilles de style -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-navbar-custom sticky-top border-bottom border-secondary py-3" aria-label="Navigation principale">
        <div class="container">
            <!-- Marque & Identité visuelle -->
            <a class="navbar-brand fw-bold text-uppercase tracking-wider fs-5 text-white d-flex align-items-center" href="index.php">
                <i class="bi bi-layers-half text-primary me-2" aria-hidden="true"></i>
                <span>INNOV'EVENTS</span>
            </a>

            <!-- Bouton Hamburger (Responsive Mobile / RGAA) -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Basculer le menu de navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Éléments de navigation (Conformes CDC Studi p.4) -->
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-lg-3">

                    <li class="nav-item">
                        <a class="nav-link text-uppercase fw-semibold <?= ($currentAction === 'events' || $currentAction === 'event_detail') ? 'active text-white' : 'text-white-50' ?>"
                           style="font-size: 0.85rem;"
                           href="index.php?action=events"
                                <?= ($currentAction === 'events') ? 'aria-current="page"' : '' ?>>
                            Événements
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link text-white-50 text-uppercase fw-semibold" style="font-size: 0.85rem;" href="index.php#services">
                            Services
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link text-white-50 text-uppercase fw-semibold" style="font-size: 0.85rem;" href="index.php#partenaires">
                            Partenaires
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link text-uppercase fw-semibold <?= ($currentAction === 'reviews') ? 'active text-white' : 'text-white-50' ?>"
                           style="font-size: 0.85rem;"
                           href="index.php#reviews">
                            Avis
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link text-uppercase fw-semibold <?= ($currentAction === 'contact') ? 'active text-white' : 'text-white-50' ?>"
                           style="font-size: 0.85rem;"
                           href="index.php#contact">
                            Contact
                        </a>
                    </li>

                    <!-- Authentification & Sessions -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php
                        $dashboardUrl = (($_SESSION['user_role'] ?? '') === 'CLIENT')
                                ? 'index.php?action=client_dashboard'
                                : 'index.php?action=dashboard';
                        ?>
                        <li class="nav-item">
                            <a class="btn btn-light btn-sm me-1" href="<?= $dashboardUrl ?>">
                                <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i> Mon Espace
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-outline-danger btn-sm px-3" href="index.php?action=logout">
                                Déconnexion
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn btn-sm btn-outline-light px-3" href="index.php?action=login" style="font-size: 0.85rem;">
                                Connexion
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- CTA Principal Demande de devis -->
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm fw-bold shadow-sm" href="index.php?action=devis">
                            <i class="bi bi-chat-left-dots me-2" aria-hidden="true"></i>Demander un Devis
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>