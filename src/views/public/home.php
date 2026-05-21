<?php
/**
 * Vue : Page d'Accueil (Landing Page) - Intégration Native Bootstrap 5
 *
 * Ce fichier implémente la page d'accueil d'Innov'Events en se basant
 * exclusivement sur les classes utilitaires de Bootstrap 5 pour garantir
 * la fluidité, le responsive et l'harmonisation des hauteurs (classes ratio).
 *
 * @package    InnovEventsManager
 * @subpackage Views/Public
 * @author     Romain Remusat
 * @version    1.5.0
 * @var bool   $isLoggedIn Spécifié par le routeur principal
 * @var string $userName   Nom de l'utilisateur en session
 * @var string $userRole   Rôle de l'utilisateur (admin|client)
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Innov'Events Manager - Accueil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-white text-secondary">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top border-bottom border-secondary">
    <div class="container">
        <a class="navbar-brand fw-bold text-uppercase tracking-wider fs-4 text-white" href="index.php">
            <i class="bi bi-layers-half text-primary me-2"></i>INNOV'EVENTS
        </a>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav align-items-center gap-3">
                <li class="nav-item"><a class="nav-link text-white-50 text-uppercase fw-semibold" style="font-size: 0.85rem;" href="#services">Services</a></li>
                <li class="nav-item"><a class="nav-link text-white-50 text-uppercase fw-semibold" style="font-size: 0.85rem;" href="#partenaires">Partenaires</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="btn btn-danger btn-sm px-3" href="index.php?action=logout">Déconnexion</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="btn btn-sm btn-outline-light px-3" href="index.php?action=login">Espace Pro</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="btn btn-primary btn-sm px-4 fw-bold shadow-sm" href="index.php?action=devis"><i class="bi bi-chat-left-dots me-2"></i>Demander un Devis</a></li>
            </ul>
        </div>
    </div>
</nav>

<header class="bg-dark text-white text-center py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
    <div class="container py-5">
        <h1 class="display-3 fw-bolder text-uppercase tracking-tight mb-3 text-white">Donnez vie à vos<br>événements d'entreprise.</h1>
        <p class="lead text-white-50 mb-5 mx-auto fs-4" style="max-width: 700px;">Séminaires, galas ou lancements de produits : nous créons des expériences marquantes et sur-mesure pour vos collaborateurs.</p>
        <div class="d-flex justify-content-center">
            <a href="index.php?action=devis" class="btn btn-primary btn-lg px-5 py-3 fw-bold shadow"><i class="bi bi-rocket-takeoff me-2"></i>Lancer mon projet</a>
        </div>
    </div>
</header>

<main class="container my-5 py-5" id="services">
    <div class="text-center mb-5 pb-3">
        <h2 class="fw-bold display-5 text-dark">Nos Domaines d'Expertise</h2>
        <p class="text-muted fs-5">Une prise en charge complète de A à Z selon vos besoins corporatifs.</p>
    </div>

    <div class="row align-items-center mb-5 py-4">
        <div class="col-md-6 mb-4 mb-md-0">
            <div class="ratio ratio-16x9 bg-primary bg-gradient rounded shadow d-flex align-items-center justify-content-center text-white">
                <div class="d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-building-gear display-1 opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-md-5 offset-md-1">
            <span class="display-3 fw-bold text-primary opacity-25 d-block mb-2">01</span>
            <h3 class="fw-bold h1 mb-3 text-dark">Séminaires & Team Building</h3>
            <p class="text-muted mb-4 fs-5 lh-base">Fédérez vos équipes et boostez la cohésion de vos collaborateurs dans des cadres d'exception, avec des activités sur-mesure adaptées à la culture de votre entreprise.</p>
            <a href="index.php?action=devis" class="btn btn-outline-primary px-4">Étudier mon besoin</a>
        </div>
    </div>

    <div class="row align-items-center mb-5 py-4">
        <div class="col-md-5 order-2 order-md-1">
            <span class="display-3 fw-bold text-success opacity-25 d-block mb-2">02</span>
            <h3 class="fw-bold h1 mb-3 text-dark">Soirées d'Entreprise & Galas</h3>
            <p class="text-muted mb-4 fs-5 lh-base">Marquez les esprits et célébrez vos succès professionnels. De la scénographie haut de gamme aux animations mémorables, nous gérons tout l'univers de votre soirée.</p>
            <a href="index.php?action=devis" class="btn btn-outline-success px-4">Organiser une soirée</a>
        </div>
        <div class="col-md-6 offset-md-1 order-1 order-md-2 mb-4 mb-md-0">
            <div class="ratio ratio-16x9 bg-success bg-gradient rounded shadow d-flex align-items-center justify-content-center text-white">
                <div class="d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-cup-straw display-1 opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row align-items-center mb-5 py-4">
        <div class="col-md-6 mb-4 mb-md-0">
            <div class="ratio ratio-16x9 bg-warning bg-gradient rounded shadow d-flex align-items-center justify-content-center text-white">
                <div class="d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-megaphone display-1 opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-md-5 offset-md-1">
            <span class="display-3 fw-bold text-warning opacity-25 d-block mb-2">03</span>
            <h3 class="fw-bold h1 mb-3 text-dark">Lancements de Produit</h3>
            <p class="text-muted mb-4 fs-5 lh-base">Valorisez vos innovations et donnez un impact maximal à vos lancements de marque auprès de vos clients et des médias grâce à une communication événementielle percutante.</p>
            <a href="index.php?action=devis" class="btn btn-outline-warning px-4">Planifier un lancement</a>
        </div>
    </div>

    <div class="row g-4 my-5 pt-4">
        <div class="col-md-6">
            <a href="#services" class="btn btn-light border p-4 w-100 h-100 d-flex align-items-center justify-content-center fw-bold fs-5 shadow-sm text-dark">
                <i class="bi bi-grid-3x3-gap me-3 text-primary"></i>Explorer tous nos services
            </a>
        </div>
        <div class="col-md-6">
            <a href="index.php?action=devis" class="btn btn-primary p-4 w-100 h-100 d-flex align-items-center justify-content-center fw-bold fs-5 shadow-sm text-white">
                <i class="bi bi-file-earmark-text me-3"></i>Demander un devis en ligne
            </a>
        </div>
    </div>
</main>

<section class="bg-light py-5 border-top border-bottom" id="partenaires">
    <div class="container">
        <h4 class="fw-bold mb-5 display-6 text-dark text-center">Ils nous font confiance</h4>
        <div class="row g-4 text-center">
            <div class="col-md-3 col-sm-6">
                <div class="bg-white border rounded p-4 h-100 d-flex flex-column justify-content-between">
                    <div class="rounded-circle bg-light text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;"><i class="bi bi-award fs-3"></i></div>
                    <h5 class="fw-bold mb-2 text-dark">Grands Comptes</h5>
                    <p class="small text-muted mb-0">Accompagnement de structures pour leurs AG et conventions.</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="bg-white border rounded p-4 h-100 d-flex flex-column justify-content-between">
                    <div class="rounded-circle bg-light text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;"><i class="bi bi-rocket fs-3"></i></div>
                    <h5 class="fw-bold mb-2 text-dark">Tech & Startups</h5>
                    <p class="small text-muted mb-0">Organisation de hackathons et lancements agiles.</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="bg-white border rounded p-4 h-100 d-flex flex-column justify-content-between">
                    <div class="rounded-circle bg-light text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;"><i class="bi bi-heart fs-3"></i></div>
                    <h5 class="fw-bold mb-2 text-dark">PME & Assos</h5>
                    <p class="small text-muted mb-0">Création de fêtes de fin d'année et anniversaires chaleureux.</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="bg-white border rounded p-4 h-100 d-flex flex-column justify-content-between">
                    <div class="rounded-circle bg-light text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;"><i class="bi bi-globe fs-3"></i></div>
                    <h5 class="fw-bold mb-2 text-dark">International</h5>
                    <p class="small text-muted mb-0">Coordination d'événements bilingues pour filiales.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="bg-dark text-white-50 py-5 border-top border-4 border-primary">
    <div class="container py-3">
        <div class="row g-4">
            <div class="col-md-4">
                <h5 class="text-white fw-bold mb-3"><i class="bi bi-layers-half text-primary me-2"></i>INNOV'EVENTS</h5>
                <p class="small">L'excellence événementielle au service de votre culture d'entreprise. Nous concevons vos plus beaux souvenirs professionnels.</p>
            </div>
            <div class="col-md-2 offset-md-1">
                <h5 class="text-white mb-3 small text-uppercase fw-bold">Navigation</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="index.php" class="text-white-50 text-decoration-none">Accueil</a></li>
                    <li class="mb-2"><a href="#services" class="text-white-50 text-decoration-none">Nos Services</a></li>
                    <li class="mb-2"><a href="index.php?action=login" class="text-white-50 text-decoration-none">Espace Gestion</a></li>
                </ul>
            </div>
            <div class="col-md-5">
                <h5 class="text-white mb-3 small text-uppercase fw-bold">Contact Pro</h5>
                <p class="small mb-1"><i class="bi bi-geo-alt me-2 text-primary"></i> 15 Rue de l'Innovation, 75000 Paris.</p>
                <p class="small mb-1"><i class="bi bi-telephone me-2 text-primary"></i> 01 23 45 67 89</p>
                <p class="small"><i class="bi bi-envelope me-2 text-primary"></i> contact@innovevents.fr</p>
            </div>
        </div>
        <hr class="my-4 border-secondary opacity-25">
        <div class="row small">
            <div class="col-md-6 text-center text-md-start">
                &copy; 2026 Innov'Events Manager. Tous droits réservés.
            </div>
            <div class="col-md-6 text-center text-md-end">
                <a href="#" class="text-white-50 text-decoration-none me-3">Mentions Légales</a> | <a href="#" class="text-white-50 text-decoration-none ms-3">RGPD</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>