<main class="container py-5" id="main-content">
    <div class="row mb-4">
        <div class="col-lg-8">
            <h1 class="h2 fw-bold text-dark mb-2">Nos Événements d'Exception</h1>
            <p class="text-secondary">Découvrez les moments sur-mesure conçus et coordonnés par Innov'Events.</p>
        </div>
    </div>

    <!-- Moteur de filtres obligatoire (Dates, Type, Thème) -->
    <div class="card shadow-sm border-0 mb-5 bg-light">
        <div class="card-body p-4">
            <form method="GET" action="index.php" class="row g-3 align-items-end" role="search" aria-label="Filtres événements">
                <input type="hidden" name="action" value="events">

                <div class="col-md-3">
                    <label for="date_start" class="form-label small fw-semibold">Date de début</label>
                    <input type="date" class="form-control" id="date_start" name="date_start"
                           value="<?= htmlspecialchars($_GET['date_start'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="col-md-3">
                    <label for="date_end" class="form-label small fw-semibold">Date de fin</label>
                    <input type="date" class="form-control" id="date_end" name="date_end"
                           value="<?= htmlspecialchars($_GET['date_end'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="col-md-2">
                    <label for="type" class="form-label small fw-semibold">Type d'événement</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Tous les types</option>
                        <?php foreach ($criteria['types'] as $typeOption): ?>
                            <option value="<?= htmlspecialchars($typeOption, ENT_QUOTES, 'UTF-8') ?>"
                                <?= (($_GET['type'] ?? '') === $typeOption) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($typeOption, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="theme" class="form-label small fw-semibold">Thème</label>
                    <select class="form-select" id="theme" name="theme">
                        <option value="">Tous les thèmes</option>
                        <?php foreach ($criteria['themes'] as $themeOption): ?>
                            <option value="<?= htmlspecialchars($themeOption, ENT_QUOTES, 'UTF-8') ?>"
                                <?= (($_GET['theme'] ?? '') === $themeOption) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($themeOption, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                    <a href="index.php?action=events" class="btn btn-outline-secondary" title="Réinitialiser les filtres">
                        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Grille des événements (ZÉRO PRIX AFFICHÉ) -->
    <div class="row g-4">
        <?php if (empty($events)): ?>
            <div class="col-12 text-center py-5">
                <p class="text-muted fs-5">Aucun événement ne correspond à vos critères de recherche.</p>
                <a href="index.php?action=events" class="btn btn-outline-primary btn-sm mt-2">Réinitialiser les filtres</a>
            </div>
        <?php else: ?>
            <?php require_once __DIR__ . '/../../utils/ImageHelper.php'; ?>
            <?php foreach ($events as $ev): ?>
                <div class="col-md-6 col-lg-4">
                    <article class="card h-100 shadow-sm border-0 overflow-hidden">
                        <!-- Rendu unique : gère l'image réelle ou le placeholder sans doublon -->
                        <?= ImageHelper::renderThumbnail($ev['image_path'] ?? null, $ev['title'], '220px') ?>

                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <?= htmlspecialchars($ev['event_type'] ?? 'Événement', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                                <?php if (!empty($ev['theme'])): ?>
                                    <span class="badge bg-secondary-subtle text-secondary">
                            <?= htmlspecialchars($ev['theme'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                                <?php endif; ?>
                            </div>

                            <h2 class="card-title h5 fw-bold text-dark mb-2">
                                <?= htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8') ?>
                            </h2>

                            <p class="card-text text-secondary small flex-grow-1">
                                <?= htmlspecialchars(mb_strimwidth($ev['description'] ?? '', 0, 120, '...'), ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                                <div class="small text-muted">
                                    <i class="fa-regular fa-calendar me-1" aria-hidden="true"></i>
                                    <?= date('d/m/Y', strtotime($ev['start_date'])) ?>
                                </div>
                                <!-- Jamais de prix affiché sur la vitrine (CDC p. 7) -->
                                <a href="index.php?action=event_detail&id=<?= (int)$ev['id'] ?>" class="btn btn-outline-primary btn-sm fw-semibold">
                                    Découvrir <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>