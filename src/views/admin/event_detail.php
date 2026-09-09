<?php
/**
 * Vue d'administration : Fiche détaillée d'un événement et espace collaboratif.
 *
 * Cette vue constitue l'interface centrale de pilotage opérationnel pour Chloé (ADMIN)
 * et José (EMPLOYEE). Elle rassemble la synthèse logistique, la gestion des médias,
 * les actions rapides de contact client et le flux de notes collaboratives.
 *
 * Variables injectées par le contrôleur (AdminEventController::showEventDetail) :
 * @var array{
 *     id: int|string,
 *     title: string,
 *     status: string,
 *     start_date: string,
 *     end_date: ?string,
 *     location: string,
 *     event_type: string,
 *     theme: ?string,
 *     estimated_participants: int|string|null,
 *     is_published: int|string|bool,
 *     image_path: ?string,
 *     firstname: string,
 *     lastname: string,
 *     company_name: ?string,
 *     client_email: ?string,
 *     phone: ?string
 * } $event Détails complets de l'événement et du client rattaché.
 *
 * @var array<int, array{
 *     id: int|string,
 *     content: string,
 *     created_at: string,
 *     firstname: string,
 *     lastname: string,
 *     user_role: string
 * }> $notes Liste chronologique inversée des notes collaboratives de l'événement.
 *
 * @var string $pageTitle Titre de la page transmis au gabarit global.
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @author     Romain Rémusat
 * @version    1.2.0
 */
?>
<div class="container-fluid">
    <div class="row">
        <!-- Menu latéral de navigation du Back-Office -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <!-- Zone de contenu principal (Repère sémantique RGAA pour lecteurs d'écran) -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" id="main-content">

            <!-- Fil d'Ariane contextuel (Critère accessibilité RGAA) -->
            <nav aria-label="Fil d'Ariane" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="index.php?action=admin_events">Événements</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></li>
                </ol>
            </nav>

            <!-- Retour visuel utilisateur : Notifications Flash d'état (Succès / Erreurs) -->
            <?php if (isset($_GET['success']) && $_GET['success'] === 'image_updated'): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                    <i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i>
                    <div>L'illustration de l'événement a été mise à jour avec succès.</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></i>
                    <div>
                        <strong>Erreur :</strong> <?= htmlspecialchars(urldecode($_GET['error']), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>

            <!-- En-tête de la fiche projet : Titre, Statut opérationnel et Navigation de retour -->
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-muted small mb-0">Fiche logistique et pilotage opérationnel</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-3 py-2 text-capitalize fs-6">
                        <?= htmlspecialchars($event['status'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <a href="index.php?action=admin_events" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Retour
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-5">
                <!-- COLONNE GAUCHE : Spécifications logistiques & Gestionnaire de média -->
                <div class="col-lg-6 d-flex flex-column gap-4">

                    <!-- Carte synthétique des données logistiques -->
                    <div class="card shadow-sm border-0 overflow-hidden h-100">
                        <?php require_once __DIR__ . '/../../utils/ImageHelper.php'; ?>
                        <!-- Rendu sécurisé de la vignette ou fallback vers le placeholder RGAA -->
                        <?= ImageHelper::renderThumbnail($event['image_path'] ?? null, $event['title'], '200px') ?>

                        <div class="card-header bg-light fw-bold py-3">
                            <i class="fa-solid fa-clipboard-list me-2 text-primary" aria-hidden="true"></i>Informations Logistiques
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
                                <li>
                                    <strong>Dates :</strong> Du <?= date('d/m/Y H:i', strtotime($event['start_date'])) ?> au <?= !empty($event['end_date']) ? date('d/m/Y H:i', strtotime($event['end_date'])) : 'Non précisée' ?>
                                </li>
                                <li>
                                    <strong>Lieu :</strong> <?= htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8') ?>
                                </li>
                                <li>
                                    <strong>Type :</strong> <?= htmlspecialchars($event['event_type'], ENT_QUOTES, 'UTF-8') ?>
                                </li>
                                <li>
                                    <strong>Thème :</strong> <?= htmlspecialchars($event['theme'] ?? 'Non renseigné', ENT_QUOTES, 'UTF-8') ?>
                                </li>
                                <li>
                                    <strong>Participants attendus :</strong> <?= (int)($event['estimated_participants'] ?? 0) ?> personnes
                                </li>
                                <li>
                                    <strong>Visibilité publique :</strong>
                                    <?= (!empty($event['is_published']))
                                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Publié sur la vitrine</span>'
                                        : '<span class="badge bg-warning-subtle text-dark border border-warning-subtle">Masqué (Privé)</span>' ?>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <?php if (($_SESSION['user_role'] ?? '') === 'ADMIN'): ?>
                    <!-- Carte de téléversement média (Sécurisation OWASP : jeton CSRF, MIME côté serveur) -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-light fw-bold py-3">
                            <i class="fa-solid fa-camera me-2 text-primary" aria-hidden="true"></i>Illustration de l'événement
                        </div>
                        <div class="card-body">
                            <form method="POST" action="index.php?action=admin_upload_image" enctype="multipart/form-data">
                                <!-- Protection contre les attaques Cross-Site Request Forgery (CWE-352) -->
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">

                                <div class="mb-3">
                                    <label for="event_image" class="form-label small fw-semibold">
                                        Sélectionner un nouveau visuel (JPG, PNG, WEBP — max 5 Mo) :
                                    </label>
                                    <input class="form-control form-control-sm"
                                           type="file"
                                           id="event_image"
                                           name="event_image"
                                           accept="image/jpeg,image/png,image/webp"
                                           required>
                                </div>

                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-upload me-1" aria-hidden="true"></i>Mettre à jour l'image
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- COLONNE DROITE : Client rattaché & Actions directes terrain (Mobilité CDC p. 14) -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light fw-bold py-3">
                            <i class="fa-solid fa-user-tie me-2 text-primary" aria-hidden="true"></i>Client Rattaché
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h2 class="h5 fw-bold text-dark mb-1"><?= htmlspecialchars($event['firstname'] . ' ' . $event['lastname'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="text-muted small mb-4"><?= htmlspecialchars($event['company_name'] ?? 'Compte Individuel', ENT_QUOTES, 'UTF-8') ?></p>

                            <!-- Déclencheurs natifs en mobilité (Protocoles mailto:, tel: et webmapping)[cite: 11] -->
                            <div class="d-flex flex-wrap gap-2 mt-auto pt-3 border-top">
                                <?php if (!empty($event['client_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($event['client_email'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-envelope me-1" aria-hidden="true"></i>Envoyer un email
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($event['phone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($event['phone'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fa-solid fa-phone me-1" aria-hidden="true"></i>Appeler
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($event['location'])): ?>
                                    <a href="https://maps.google.com/?q=<?= urlencode($event['location']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-map-location-dot me-1" aria-hidden="true"></i>Itinéraire
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION COLLABORATIVE : Flux des notes de projet (Exigence CDC p. 11 & 14) -->
            <section class="card shadow-sm border-0 mb-4" aria-labelledby="notes-section-title">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                    <h2 class="h6 fw-bold mb-0" id="notes-section-title">
                        <i class="fa-solid fa-comments me-2 text-primary" aria-hidden="true"></i>Notes Collaboratives du Projet
                    </h2>
                </div>
                <div class="card-body p-4">
                    <!-- Formulaire de consigne à chaud (Accessible sur desktop et mobile)[cite: 11] -->
                    <form method="POST" action="index.php?action=admin_add_note" class="mb-4">
                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                        <div class="mb-3">
                            <label for="note_content" class="form-label small fw-semibold">
                                Ajouter une consigne ou un retour terrain :
                            </label>
                            <textarea class="form-control"
                                      id="note_content"
                                      name="content"
                                      rows="3"
                                      placeholder="Informations prestataires, modifications de timing, contraintes d'accès..."
                                      required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Enregistrer la note
                        </button>
                    </form>

                    <!-- Affichage chronologique inversé des échanges enregistrés -->
                    <div class="d-flex flex-column gap-3">
                        <?php if (empty($notes)): ?>
                            <p class="text-muted small mb-0">Aucune note enregistrée sur ce projet.</p>
                        <?php else: ?>
                            <?php foreach ($notes as $n): ?>
                                <article class="p-3 rounded-2 bg-light border-start border-4 border-primary">
                                    <header class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold small text-dark">
                                            <?= htmlspecialchars($n['firstname'] . ' ' . $n['lastname'], ENT_QUOTES, 'UTF-8') ?>
                                            <span class="badge bg-secondary-subtle text-secondary ms-1">
                                                <?= htmlspecialchars($n['user_role'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </span>
                                        <time class="text-muted small" datetime="<?= htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= date('d/m/Y à H:i', strtotime($n['created_at'])) ?>
                                        </time>
                                    </header>
                                    <p class="mb-0 text-secondary small" style="white-space: pre-line;">
                                        <?= htmlspecialchars($n['content'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>