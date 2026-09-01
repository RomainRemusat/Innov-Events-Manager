<?php
/**
 * Vue : Liste complète des Prospects (Data Table)
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @version    2.0.0 (Conformité Bootstrap Icons & Nouveaux Statuts)
 */

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit();
}
?>

<div class="container-fluid bg-light min-vh-100 py-4">
    <div class="row">

        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-2">

            <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        <i class="bi bi-people-fill text-primary me-2"></i> Gestion des Prospects
                    </h1>
                    <p class="text-muted small mb-0">
                        Cet espace centralise toutes les demandes de devis entrantes. Qualifiez-les et convertissez-les en clients pour initialiser leur projet événementiel.
                    </p>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">
                            <tr>
                                <th>Entreprise / Contact</th>
                                <th>Événement / Date</th>
                                <th>Budget</th>
                                <th>Statut</th>
                                <th class="text-center">Actions</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php if (empty($prospects)): ?>

                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        Aucune demande de devis n'est actuellement enregistrée en base de données.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($prospects as $prospect): ?>
                                    <tr>
                                        <td class="px-3">
                                            <div class="fw-bold text-dark">
                                                <?= htmlspecialchars($prospect['company_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-person me-1"></i>
                                                <?= htmlspecialchars($prospect['contact_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        </td>

                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($prospect['event_type'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-calendar me-1"></i>
                                                <?= $prospect['event_date'] ? date('d/m/Y', strtotime($prospect['event_date'])) : 'Non définie' ?>
                                            </div>
                                        </td>

                                        <td class="fw-bold text-secondary">
                                            <?= number_format($prospect['budget'] ?? 0, 2, ',', ' ') ?> € HT
                                        </td>

                                        <td>
                                            <?php
                                            $status = strtolower($prospect['status'] ?? 'à contacter');
                                            $badgeClass = 'text-bg-warning';
                                            if ($status === 'en attente') $badgeClass = 'text-bg-info';
                                            if ($status === 'échoué' || $status === 'refusé') $badgeClass = 'text-bg-danger';
                                            if ($status === 'converti' || $status === 'accepté') $badgeClass = 'text-bg-success';
                                            ?>
                                            <span class="badge <?= $badgeClass ?> status-badge px-2 py-1">
                                                <?= ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8')) ?>
                                            </span>
                                        </td>

                                        <td class="text-center px-3">
                                            <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Voir la fiche complète">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>" class="btn btn-sm btn-outline-success" title="Traiter la demande">
                                                <i class="bi bi-pencil-square"></i>
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