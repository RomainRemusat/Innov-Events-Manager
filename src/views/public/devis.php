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
 * @version    2.5.0
 */

// Injection dynamique du titre pour le composant global d'en-tête HTML
$pageTitle = "Innov'Events - Demande de Devis";

$old = $_SESSION['old_inputs'] ?? [];
unset($_SESSION['old_inputs']);

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
            <h2 class="fw-bold text-dark mb-4 tracking-wide">Parlez-nous de votre projet.</h2>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                    <strong>Veuillez corriger les éléments suivants :</strong><br>
                    <?= $_SESSION['flash_error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <form action="index.php?action=devis" method="POST" class="p-4 border rounded shadow-sm bg-white">

                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="company_name" class="form-label text-muted small fw-bold">NOM DE L'ENTREPRISE *</label>
                        <input type="text" class="form-control" id="company_name" name="company_name"
                               value="<?= htmlspecialchars($old['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Ex: TechCorp" required>
                    </div>
                    <div class="col-md-6">
                        <label for="contact_name" class="form-label text-muted small fw-bold">NOM & PRÉNOM DU CONTACT *</label>
                        <input type="text" class="form-control" id="contact_name" name="contact_name"
                               value="<?= htmlspecialchars($old['contact_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Ex: Jean Dupont" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="email" class="form-label text-muted small fw-bold">ADRESSE EMAIL PROFESSIONNELLE *</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Ex: j.dupont@entreprise.com" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label text-muted small fw-bold">NUMÉRO DE TÉLÉPHONE *</label>
                        <input type="tel" class="form-control" id="phone" name="phone"
                               value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Ex: 01 23 45 67 89" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="event_type" class="form-label text-muted small fw-bold">TYPE D'ÉVÉNEMENT *</label>
                        <?php $selectedType = $old['event_type'] ?? ''; ?>
                        <select class="form-select" id="event_type" name="event_type" required>
                            <option value="" <?= empty($selectedType) ? 'selected' : ''; ?> disabled>Choisir une option...</option>
                            <option value="Séminaire" <?= $selectedType === 'Séminaire' ? 'selected' : ''; ?>>Séminaire</option>
                            <option value="Soirée de Gala" <?= $selectedType === 'Soirée de Gala' ? 'selected' : ''; ?>>Soirée de Gala</option>
                            <option value="Lancement de produit" <?= $selectedType === 'Lancement de produit' ? 'selected' : ''; ?>>Lancement de produit</option>
                            <option value="Team Building" <?= $selectedType === 'Team Building' ? 'selected' : ''; ?>>Team Building</option>
                            <option value="Autre" <?= $selectedType === 'Autre' ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="event_date" class="form-label text-muted small fw-bold">DATE SOUHAITÉE *</label>
                        <input type="date" class="form-control" id="event_date" name="event_date"
                               min="<?= date('Y-m-d'); ?>"
                               value="<?= htmlspecialchars($old['event_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="location" class="form-label text-muted small fw-bold">LIEU / VILLE SOUHAITÉ *</label>
                        <input type="text" class="form-control" id="location" name="location"
                               value="<?= htmlspecialchars($old['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Ex: Paris, Lyon, Sur site entreprise..." required>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <label for="estimated_participants" class="form-label text-muted small fw-bold">PARTICIPANTS *</label>
                        <input type="number" class="form-control" id="estimated_participants" name="estimated_participants"
                               value="<?= htmlspecialchars((string)($old['estimated_participants'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Ex: 50" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label for="budget" class="form-label text-muted small fw-bold">BUDGET ESTIMÉ (€)</label>
                        <input type="number" class="form-control" id="budget" name="budget"
                               value="<?= htmlspecialchars((string)($old['budget'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Ex: 5000 (Facultatif)" min="0" step="100">
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <label for="description" class="form-label text-muted small fw-bold">DESCRIPTION DU PROJET *</label>
                        <textarea class="form-control" id="description" name="description" rows="6"
                                  placeholder="Décrivez brièvement vos attentes (ex: besoin d'un traiteur, location de salle, animations...)" required><?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
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
                                <span class="badge bg-secondary mb-2">Séminaire</span>
                                <h4 class="h6 fw-bold">Convention Nationale TechCorp</h4>
                                <p class="text-muted small mb-0">Paris — 150 participants</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="project-card-minimal">
                            <div class="ratio ratio-16x9 bg-light">
                                <img src="https://images.unsplash.com/photo-1511795409834-ef04bbd61622?q=80&w=600&auto=format&fit=crop" class="w-100 h-100" style="object-fit: cover;" alt="Gala Innov">
                            </div>
                            <div class="p-3">
                                <span class="badge bg-secondary mb-2">Gala</span>
                                <h4 class="h6 fw-bold">Soirée Annuelle des Lauréats</h4>
                                <p class="text-muted small mb-0">Lyon — 300 participants</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="project-card-minimal">
                            <div class="ratio ratio-16x9 bg-light">
                                <img src="https://images.unsplash.com/photo-1475721027785-f74eccf877e2?q=80&w=600&auto=format&fit=crop" class="w-100 h-100" style="object-fit: cover;" alt="Team Building">
                            </div>
                            <div class="p-3">
                                <span class="badge bg-secondary mb-2">Team Building</span>
                                <h4 class="h6 fw-bold">Challenge Outdoor Innovation</h4>
                                <p class="text-muted small mb-0">Annecy — 80 participants</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
