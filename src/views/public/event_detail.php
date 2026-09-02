<main class="container py-5" id="main-content">
    <nav aria-label="Fil d'Ariane" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
            <li class="breadcrumb-item"><a href="index.php?action=events">Événements</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></li>
        </ol>
    </nav>

    <div class="row g-5">
        <div class="col-lg-8">
            <h1 class="h2 fw-bold text-dark mb-3"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></h1>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <span class="badge bg-primary px-3 py-2"><?= htmlspecialchars($event['event_type'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($event['theme'])): ?>
                    <span class="badge bg-secondary px-3 py-2"><?= htmlspecialchars($event['theme'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if (!empty($event['company_name'])): ?>
                    <span class="badge bg-light text-dark border px-3 py-2">Projet réalisé pour <?= htmlspecialchars($event['company_name'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($event['image_path'])): ?>
                <img src="<?= htmlspecialchars($event['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                     class="img-fluid rounded-3 mb-4 w-100 shadow-sm object-fit-cover"
                     alt="Visuel grand format pour <?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?>"
                     style="max-height: 450px;">
            <?php endif; ?>

            <section class="mb-5">
                <h2 class="h4 fw-bold text-dark mb-3">À propos de cet événement</h2>
                <p class="text-secondary leading-relaxed fs-6">
                    <?= nl2br(htmlspecialchars($event['description'] ?? 'Aucune description disponible pour cet événement.', ENT_QUOTES, 'UTF-8')) ?>
                </p>
            </section>

            <!-- Section obligatoire CDC : Prestations et Devis (Redirection) -->
            <section class="card bg-primary-subtle border-0 p-4 rounded-3" aria-labelledby="section-quote-cta">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="h5 fw-bold text-primary mb-2" id="section-quote-cta">Prestations &amp; Devis</h2>
                        <p class="text-secondary small mb-md-0">
                            Un projet similaire en tête ? Sollicitez notre équipe pour concevoir un programme sur-mesure adapté à vos exigences budgétaires et organisationnelles.
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="index.php?action=devis" class="btn btn-primary">
                            Demander un devis
                        </a>
                    </div>
                </div>
            </section>
        </div>

        <aside class="col-lg-4">
            <div class="card shadow-sm border-0 p-4 sticky-top" style="top: 2rem;">
                <h2 class="h5 fw-bold text-dark border-bottom pb-3 mb-3">Informations Clés</h2>
                <ul class="list-unstyled d-flex flex-column gap-3 mb-0">
                    <li>
                        <strong class="d-block text-muted small">Dates</strong>
                        <span>Du <?= date('d/m/Y à H:i', strtotime($event['start_date'])) ?></span>
                        <?php if (!empty($event['end_date'])): ?>
                            <span class="d-block">Au <?= date('d/m/Y à H:i', strtotime($event['end_date'])) ?></span>
                        <?php endif; ?>
                    </li>
                    <li>
                        <strong class="d-block text-muted small">Lieu</strong>
                        <span><?= htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                    <?php if (!empty($event['estimated_participants'])): ?>
                        <li>
                            <strong class="d-block text-muted small">Envergure</strong>
                            <span><?= (int)$event['estimated_participants'] ?> participants attendus</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </aside>
    </div>
</main>