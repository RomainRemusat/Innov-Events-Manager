<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" id="main-content">
            <?php require __DIR__ . '/../partials/admin_messages.php'; ?>
            <div class="border-bottom mb-4 pb-3">
                <h1 class="h2 fw-bold">Modération des avis</h1>
                <p class="text-secondary mb-0">Validez les témoignages publiables ou indiquez au client ce qu’il doit corriger.</p>
            </div>

            <?php if (!$reviews): ?>
                <p class="alert alert-info">Aucun avis à modérer.</p>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($reviews as $review): ?>
                        <?php
                        $pending = $review['status'] === Review::STATUS_PENDING;
                        $badge = match ($review['status']) {
                            Review::STATUS_APPROVED => 'text-bg-success',
                            Review::STATUS_REJECTED => 'text-bg-danger',
                            default => 'text-bg-warning',
                        };
                        ?>
                        <div class="col-xl-6">
                            <article class="card h-100 shadow-sm">
                                <div class="card-header bg-white d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <h2 class="h5 mb-1"><?= htmlspecialchars($review['event_title'], ENT_QUOTES, 'UTF-8') ?></h2>
                                        <span class="text-secondary small">
                                            <?= htmlspecialchars($review['firstname'] . ' ' . $review['lastname'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($review['company_name']): ?>· <?= htmlspecialchars($review['company_name'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                        </span>
                                    </div>
                                    <span class="badge <?= $badge ?>"><?= htmlspecialchars(ucfirst($review['status']), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="card-body">
                                    <p class="text-warning mb-2" aria-label="Note : <?= (int)$review['rating'] ?> sur 5">
                                        <span aria-hidden="true"><?= str_repeat('★', (int)$review['rating']) ?><?= str_repeat('☆', 5 - (int)$review['rating']) ?></span>
                                    </p>
                                    <p><?= nl2br(htmlspecialchars($review['comment'], ENT_QUOTES, 'UTF-8')) ?></p>

                                    <?php if ($review['status'] === Review::STATUS_REJECTED): ?>
                                        <p class="alert alert-danger mb-0"><strong>Motif :</strong> <?= htmlspecialchars($review['rejection_reason'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php elseif ($pending): ?>
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <form method="post" action="index.php?action=staff_moderate_review">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                                                <button class="btn btn-success btn-sm" name="moderation_action" value="approve" type="submit">Valider et publier</button>
                                            </form>
                                        </div>
                                        <form method="post" action="index.php?action=staff_moderate_review">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                                            <input type="hidden" name="moderation_action" value="reject">
                                            <label class="form-label" for="rejection_reason_<?= (int)$review['id'] ?>">Motif du refus</label>
                                            <textarea class="form-control mb-2" id="rejection_reason_<?= (int)$review['id'] ?>" name="rejection_reason" minlength="5" maxlength="500" required></textarea>
                                            <button class="btn btn-outline-danger btn-sm" type="submit">Refuser et demander une correction</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
