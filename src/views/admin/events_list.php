<div class="container-fluid">
    <div class="row">
        <!-- Sidebar d'administration -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <!-- Contenu principal -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 fw-bold text-dark mb-1">Gestion des Événements</h1>
                    <p class="text-secondary small mb-0">Suivi logistique, publication vitrine et gestion des statuts opérationnels.</p>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-4">Événement &amp; Client</th>
                                <th scope="col">Dates</th>
                                <th scope="col">Lieu</th>
                                <th scope="col">Vitrine</th>
                                <th scope="col">Statut</th>
                                <th scope="col" class="text-end pe-4">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($events)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Aucun événement enregistré.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($events as $ev): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars(($ev['company_name'] ? $ev['company_name'] . ' — ' : '') . $ev['firstname'] . ' ' . $ev['lastname'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small">Du <?= date('d/m/Y', strtotime($ev['start_date'])) ?></div>
                                            <?php if (!empty($ev['end_date'])): ?>
                                                <div class="small text-muted">Au <?= date('d/m/Y', strtotime($ev['end_date'])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small"><?= htmlspecialchars($ev['location'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ((int)$ev['is_published'] === 1): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        <i class="fa-solid fa-eye me-1" aria-hidden="true"></i>Publié
                                                    </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                        <i class="fa-solid fa-eye-slash me-1" aria-hidden="true"></i>Masqué
                                                    </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Formulaire de changement de statut direct -->
                                            <form method="POST" action="index.php?action=admin_event_update_status" class="d-inline">
                                                <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Modifier le statut">
                                                    <?php
                                                    $statuses = [
                                                        'brouillon' => 'Brouillon',
                                                        'accepté'   => 'Accepté',
                                                        'en cours'  => 'En cours',
                                                        'terminé'   => 'Terminé',
                                                        'annuler'   => 'Annulé'
                                                    ];
                                                    foreach ($statuses as $val => $label):
                                                        ?>
                                                        <option value="<?= $val ?>" <?= ($ev['status'] === $val) ? 'selected' : '' ?>>
                                                            <?= $label ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="text-end pe-4">
                                            <!-- Lien vers la future fiche projet détaillée (support des notes) -->
                                            <a href="index.php?action=admin_event_detail&id=<?= (int)$ev['id'] ?>" class="btn btn-sm btn-outline-primary" title="Fiche projet & Notes">
                                                <i class="fa-solid fa-folder-open me-1" aria-hidden="true"></i>Détails
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>