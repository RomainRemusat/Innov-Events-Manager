<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <nav aria-label="Fil d'Ariane" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="index.php?action=admin_events">Événements</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <span class="text-muted small">Fiche logistique et pilotage opérationnel</span>
                </div>
                <span class="badge bg-primary px-3 py-2 text-capitalize"><?= htmlspecialchars($event['status'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="row g-4 mb-5">
                <!-- Détails logistiques -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light fw-bold py-3">
                            <i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Informations Logistiques
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
                                <li><strong>Dates :</strong> Du <?= date('d/m/Y H:i', strtotime($event['start_date'])) ?> au <?= !empty($event['end_date']) ? date('d/m/Y H:i', strtotime($event['end_date'])) : 'Non précisée' ?></li>
                                <li><strong>Lieu :</strong> <?= htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8') ?></li>
                                <li><strong>Type :</strong> <?= htmlspecialchars($event['event_type'], ENT_QUOTES, 'UTF-8') ?></li>
                                <li><strong>Thème :</strong> <?= htmlspecialchars($event['theme'] ?? 'Non renseigné', ENT_QUOTES, 'UTF-8') ?></li>
                                <li><strong>Participants attendus :</strong> <?= (int)($event['estimated_participants'] ?? 0) ?> personnes</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Client rattaché -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light fw-bold py-3">
                            <i class="fa-solid fa-user-tie me-2 text-primary"></i>Client Rattaché
                        </div>
                        <div class="card-body">
                            <h2 class="h6 fw-bold text-dark"><?= htmlspecialchars($event['firstname'] . ' ' . $event['lastname'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="text-muted small mb-3"><?= htmlspecialchars($event['company_name'] ?? 'Compte Individuel', ENT_QUOTES, 'UTF-8') ?></p>

                            <div class="d-flex flex-wrap gap-2">
                                <a href="mailto:<?= htmlspecialchars($event['client_email'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-envelope me-1"></i>Envoyer un email
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Espace Collaboratif : Notes de terrain -->
            <section class="card shadow-sm border-0 mb-4" aria-labelledby="notes-section-title">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                    <h2 class="h6 fw-bold mb-0" id="notes-section-title">
                        <i class="fa-solid fa-comments me-2 text-primary"></i>Notes Collaboratives du Projet
                    </h2>
                </div>
                <div class="card-body p-4">
                    <!-- Formulaire d'ajout rapide "à chaud" (CDC p. 11 & 14) -->
                    <form method="POST" action="index.php?action=admin_add_note" class="mb-4">
                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                        <div class="mb-3">
                            <label for="content" class="form-label small fw-semibold">Ajouter une consigne ou un retour terrain :</label>
                            <textarea class="form-control" id="content" name="content" rows="3" placeholder="Informations prestataires, modifications de timing, contraintes d'accès..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-plus me-1"></i>Enregistrer la note
                        </button>
                    </form>

                    <!-- Flux des notes chronologiques -->
                    <div class="d-flex flex-column gap-3">
                        <?php if (empty($notes)): ?>
                            <p class="text-muted small mb-0">Aucune note enregistrée sur ce projet.</p>
                        <?php else: ?>
                            <?php foreach ($notes as $n): ?>
                                <div class="p-3 rounded-2 bg-light border-start border-4 border-primary">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold small text-dark">
                                            <?= htmlspecialchars($n['firstname'] . ' ' . $n['lastname'], ENT_QUOTES, 'UTF-8') ?>
                                            <span class="badge bg-secondary-subtle text-secondary ms-1"><?= htmlspecialchars($n['user_role'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                        <time class="text-muted small" datetime="<?= $n['created_at'] ?>">
                                            <?= date('d/m/Y à H:i', strtotime($n['created_at'])) ?>
                                        </time>
                                    </div>
                                    <p class="mb-0 text-secondary small" style="white-space: pre-line;">
                                        <?= htmlspecialchars($n['content'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>