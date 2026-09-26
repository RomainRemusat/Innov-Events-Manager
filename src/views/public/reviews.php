<main class="container py-5">
    <div class="text-center mb-5">
        <h1 class="fw-bold">Avis de nos clients</h1>
        <p class="text-secondary">Des retours publiés après vérification par notre équipe.</p>
    </div>

    <?php if (!$reviews): ?>
        <p class="alert alert-info text-center">Aucun avis publié pour le moment.</p>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($reviews as $review): ?>
                <div class="col-md-6 col-xl-4">
                    <article class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <p class="text-warning fs-5 mb-3" aria-label="Note : <?= (int)$review['rating'] ?> sur 5">
                                <span aria-hidden="true"><?= str_repeat('★', (int)$review['rating']) ?><?= str_repeat('☆', 5 - (int)$review['rating']) ?></span>
                            </p>
                            <blockquote class="mb-3">« <?= nl2br(htmlspecialchars($review['comment'], ENT_QUOTES, 'UTF-8')) ?> »</blockquote>
                            <footer class="text-secondary small">
                                <?= htmlspecialchars($review['firstname'], ENT_QUOTES, 'UTF-8') ?> ·
                                <time datetime="<?= htmlspecialchars(substr($review['moderated_at'] ?? $review['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(date('d/m/Y', strtotime($review['moderated_at'] ?? $review['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                </time>
                            </footer>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
