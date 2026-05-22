<?php
/**
 * Vue : Page d'Accueil (Landing Page) - Version Modulaire Haute Fidélité
 *
 * Ce fichier implémente la page d'accueil publique de la plateforme en utilisant
 * l'architecture de composants (Partials). Il centralise l'affichage des domaines
 * d'expertise métier d'Innov'Events et sert de point d'entrée principal (tunnel de conversion).
 *
 * @package    InnovEventsManager
 * @subpackage Views/Public
 * @author     Romain Remusat
 * @version    2.0.0
 * @var bool   $isLoggedIn Spécifié par le routeur principal (Front Controller)
 */

// Configuration du titre dynamique de la page pour le composant Header
$pageTitle = "Innov'Events Manager - Accueil";

// Architecture Modulaire : Chargement de l'en-tête global et de la Navbar
require __DIR__ . '/../partials/header.php';
?>

    <header class="text-white text-center py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
        <div class="container py-5">
            <h1 class="display-3 fw-bolder text-uppercase tracking-tight mb-3 text-white">Donnez vie à vos<br>événements d'entreprise.</h1>
            <p class="lead text-white-50 mb-5 mx-auto fs-4" style="max-width: 700px;">Séminaires, galas ou lancements de produits : nous créons des expériences marquantes et sur-mesure pour vos collaborateurs.</p>
            <div class="d-flex justify-content-center">
                <a href="index.php?action=devis" class="btn btn-primary-custom btn-lg px-5 py-3 shadow"><i class="bi bi-rocket-takeoff me-2"></i>Lancer mon projet</a>
            </div>
        </div>
    </header>

    <main class="container my-5 py-5" id="services">
        <div class="mb-3 pb-3">
            <h2 class="fw-bold  text-dark">Nos Domaines d'Expertise.</h2>
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
                <span class="display-4 text-primary opacity-50 d-block mb-2">01</span>
                <h3 class="fw-bold h2 mb-3 text-dark">Séminaires & Team Building</h3>
                <p class="text-muted mb-4 fs-5 lh-base">Fédérez vos équipes et boostez la cohésion de vos collaborateurs dans des cadres d'exception, avec des activités sur-mesure adaptées à la culture de votre entreprise.</p>
                <a href="index.php?action=devis" class="btn btn-outline-primary px-4">Étudier mon besoin</a>
            </div>
        </div>

        <div class="row align-items-center mb-5 py-4">
            <div class="col-md-5 order-2 order-md-1">
                <span class="display-4 text-success opacity-50 d-block mb-2">02</span>
                <h3 class="fw-bold h2 mb-3 text-dark">Soirées d'Entreprise & Galas</h3>
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
                <span class="display-4  text-warning opacity-50 d-block mb-2">03</span>
                <h3 class="fw-bold h2 mb-3 text-dark">Lancements de Produit</h3>
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
                <a href="index.php?action=devis" class="btn btn-primary-custom p-4 w-100 h-100 d-flex align-items-center justify-content-center fw-bold fs-5 shadow-sm text-white">
                    <i class="bi bi-file-earmark-text me-3"></i>Demander un devis en ligne
                </a>
            </div>
        </div>
    </main>

    <section class="bg-light py-5 border-top border-bottom" id="partenaires">
        <div class="container">
            <h4 class="fw-bold h2 mb-5">Ils nous font confiance.</h4>
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

<?php
// Architecture Modulaire : Chargement du pied de page global (fermeture HTML incluse)
require __DIR__ . '/../partials/footer.php';
?>