<?php
/**
 * Vue : Formulaire de Demande de Devis Public - Version Full Width Hero Gallery
 *
 * Ce fichier implémente l'interface de demande de qualification de devis.
 * L'en-tête intègre une "Hero Gallery" animée via un Carousel Bootstrap épuré qui
 * s'étend sur l'intégralité de la largeur de la fenêtre graphique (Full-Width Viewport),
 * offrant une immersion visuelle maximale conforme aux standards UX événementiels.
 *
 * Spécifications techniques d'intégration :
 * - Conteneur global de bandeau en `container-fluid px-0` pour supprimer les gouttières.
 * - Carousel Bootstrap configuré en mode fondu croisé (carousel-fade).
 * - Alignement sémantique du formulaire préservé dans une grille fixe standard (container).
 *
 * @package    InnovEventsManager
 * @subpackage Views/Public
 * @author     Romain Remusat
 * @version    2.4.0
 */

// Injection dynamique du titre pour le composant global d'en-tête HTML
$pageTitle = "Innov'Events - Demande de Devis";

// Architecture Modulaire : Chargement de l'en-tête global et de la barre de navigation
require __DIR__ . '/../partials/header.php';
?>

    <header class="container-fluid px-0 mb-5">
        <div class="hero-gallery-fullwidth shadow-sm">
            <div class="gallery-overlay-immersive"></div>

            <div id="heroGallery" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="4000">
                <div class="carousel-inner gallery-carousel-inner">

                    <div class="carousel-item active h-100">
                        <img src="https://images.unsplash.com/photo-1511578314322-379afb476865?q=80&w=1600&auto=format&fit=crop" class="gallery-img" alt="Séminaire et convention d'entreprise Innov'Events">
                    </div>

                    <div class="carousel-item h-100">
                        <img src="https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=1600&auto=format&fit=crop" class="gallery-img" alt="Soirée de gala et animation haut de gamme">
                    </div>

                    <div class="carousel-item h-100">
                        <img src="https://images.unsplash.com/photo-1475721027785-f74eccf877e2?q=80&w=1600&auto=format&fit=crop" class="gallery-img" alt="Conférence et lancement de produit d'envergure">
                    </div>
                </div>
            </div>

            <div class="hero-gallery-content-wrapper">
                <div class="container">
                    <div class="row">
                        <div class="col-md-6 col-lg-5 px-4 px-md-0">
                            <span class="text-primary fw-semibold text-uppercase tracking-wider small d-block mb-2" style="font-size: 0.75rem; letter-spacing: 0.15em;">Votre projet commence ici</span>
                            <h1 class="fw-bold mb-3 text-white" style="font-size: 2.6rem; letter-spacing: -0.02em;">Prêt à créer l'inoubliable ?</h1>
                            <p class="text-white-50 small mb-0" style="font-size: 0.95rem; max-width: 95%; line-height: 1.6;">
                                Chaque événement est une page blanche. Remplissez ce formulaire et faites-nous part de votre vision : nos experts structurent votre cahier des charges sur-mesure.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container my-5">
        <div class="pt-4">
            <h2 class="fw-bold text-dark  mb-5  tracking-wide" >Parlez-nous de votre projet.</h2>
            <form action="index.php?action=devis" method="POST" class="p-4 border rounded shadow-sm bg-white">

                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="company_name" class="form-label text-muted small fw-bold">NOM DE L'ENTREPRISE *</label>
                        <input type="text" class="form-control" id="company_name" name="company_name" placeholder="Ex: TechCorp" required>
                    </div>
                    <div class="col-md-6">
                        <label for="contact_name" class="form-label text-muted small fw-bold">NOM & PRÉNOM DU CONTACT *</label>
                        <input type="text" class="form-control" id="contact_name" name="contact_name" placeholder="Ex: Jean Dupont" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="email" class="form-label text-muted small fw-bold">ADRESSE EMAIL PROFESSIONNELLE *</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Ex: j.dupont@entreprise.com" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label text-muted small fw-bold">NUMÉRO DE TÉLÉPHONE *</label>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="Ex: 01 23 45 67 89" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="event_type" class="form-label text-muted small fw-bold">TYPE D'ÉVÉNEMENT *</label>
                        <select class="form-select" id="event_type" name="event_type" required>
                            <option value="" selected disabled>Choisir une option...</option>
                            <option value="Séminaire">Séminaire</option>
                            <option value="Soirée de Gala">Soirée de Gala</option>
                            <option value="Lancement de produit">Lancement de produit</option>
                            <option value="Team Building">Team Building</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="event_date" class="form-label text-muted small fw-bold">DATE SOUHAITÉE *</label>
                        <input type="date" class="form-control" id="event_date" name="event_date" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="location" class="form-label text-muted small fw-bold">LIEU / VILLE SOUHAITÉ *</label>
                        <input type="text" class="form-control" id="location" name="location" placeholder="Ex: Paris, Lyon, Sur site entreprise..." required>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <label for="estimated_participants" class="form-label text-muted small fw-bold">PARTICIPANTS *</label>
                        <input type="number" class="form-control" id="estimated_participants" name="estimated_participants" placeholder="Ex: 50" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label for="budget" class="form-label text-muted small fw-bold">BUDGET ESTIMÉ (€) *</label>
                        <input type="number" class="form-control" id="budget" name="budget" placeholder="Ex: 5000" min="0" step="100" required>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <label for="description" class="form-label text-muted small fw-bold">DESCRIPTION DU PROJET *</label>
                        <textarea class="form-control" id="description" name="description" rows="8" placeholder="Décrivez brièvement vos attentes (ex: besoin d'un traiteur, location de salle...)" required></textarea>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="rgpd_consent" name="rgpd_consent" required>
                    <label class="form-check-label text-muted small" for="rgpd_consent">
                        J'accepte que mes données soient traitées dans le cadre de ma demande de devis conformément aux directives RGPD.
                    </label>
                </div>

                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                <button type="submit" class="btn btn-primary px-4 py-2 fw-bold" style="background-color: #4b6bfb; border: none;">
                    ENVOYER LA DEMANDE
                </button>
            </form>
        </div>
    </main>
    <section class="bg-light py-5">
        <div class="container">
            <div class="pt-5 divider-fine">
                <div class="mb-5">
                    <h3 class="fw-bold text-dark h2 tracking-wide">Quelques exemples.</h3>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="project-card-minimal">
                            <div class="ratio ratio-16x9 bg-light">
                                <img src="https://images.unsplash.com/photo-1431540015161-0bf868a2d407?q=80&w=600&auto=format&fit=crop" class="w-100 h-100" style="object-fit: cover;" alt="Séminaire TechCorp">
                            </div>
                            <div class="p-3">
                                <span class="text-primary label-minimal mb-1 d-block" style="font-size: 0.65rem;">Team Building</span>
                                <h4 class="h6 fw-semibold text-dark mb-2">Séminaire Annuel TechCorp</h4>
                                <p class="text-muted mb-0 small lh-base">Rassembler 150 collaborateurs dans un éco-lodge connecté avec ateliers collaboratifs et animations immersives.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="project-card-minimal">
                            <div class="ratio ratio-16x9 bg-light">
                                <img src="https://images.unsplash.com/photo-1469371670807-013ccf25f16a?q=80&w=600&auto=format&fit=crop" class="w-100 h-100" style="object-fit: cover;" alt="Soirée de Gala Luxury Hotel">
                            </div>
                            <div class="p-3">
                                <span class="text-success label-minimal mb-1 d-block" style="font-size: 0.65rem;">Soirée d'exception</span>
                                <h4 class="h6 fw-semibold text-dark mb-2">Gala Annuel Luxury Group</h4>
                                <p class="text-muted mb-0 small lh-base">Scénographie lumineuse haut de gamme, dîner gastronomique et concert privé pour célébrer les performances annuelles.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="project-card-minimal">
                            <div class="ratio ratio-16x9 bg-light">
                                <img src="https://images.unsplash.com/photo-1540575467063-178a50c2df87?q=80&w=600&auto=format&fit=crop" class="w-100 h-100" style="object-fit: cover;" alt="Lancement de produit NextGen">
                            </div>
                            <div class="p-3">
                                <span class="text-warning label-minimal mb-1 d-block" style="font-size: 0.65rem;">Lancement de marque</span>
                                <h4 class="h6 fw-semibold text-dark mb-2">Keynote Produit NextGen</h4>
                                <p class="text-muted mb-0 small lh-base">Organisation d'une conférence de presse interactive et retransmise en direct pour dévoiler la nouvelle gamme logicielle.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php
// Architecture Modulaire : Chargement du pied de page global
require __DIR__ . '/../partials/footer.php';
?>