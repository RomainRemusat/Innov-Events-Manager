<?php
/**
 * Vue : Journal d'Audit et Sécurité (MongoDB)
 *
 * Interface dédiée à la consultation de l'historique d'activité de l'application.
 * Les données sont extraites directement de la base NoSQL (MongoDB).
 * Cette page est cruciale pour le respect du RGPD et la traçabilité des actions.
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @author     Romain Remusat
 * @var array  $allLogs Historique complet des logs injecté par le contrôleur.
 */

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit();
}
?>

<div class="container-fluid">
    <div class="row">

        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2 fw-bold text-dark"><i class="fa-solid fa-shield-halved me-2 text-danger"></i> Journal d'Audit Système</h1>
                <span class="badge bg-success p-2 fs-6"><i class="fa-solid fa-database me-2"></i>Source : MongoDB cluster</span>
            </div>

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-dark text-white py-3 border-0">
                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i> Historique des 100 dernières actions</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date & Heure</th>
                                    <th>Type d'Action</th>
                                    <th>Détail / Message</th>
                                    <th>Adresse IP (Traçabilité)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allLogs)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">Aucune donnée disponible dans MongoDB.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allLogs as $log): ?>
                                        <tr>
                                            <td class="fw-bold text-secondary text-nowrap">
                                                <i class="fa-regular fa-calendar-days me-1"></i>
                                                <?php
                                                if (isset($log['created_at'])) {
                                                    if (is_object($log['created_at']) && method_exists($log['created_at'], 'toDateTime')) {
                                                        echo $log['created_at']->toDateTime()->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y - H:i:s');
                                                    } else {
                                                        echo date('d/m/Y - H:i:s', strtotime((string)$log['created_at']));
                                                    }
                                                } else {
                                                    echo 'Inconnue';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $actionType = htmlspecialchars($log['action'] ?? 'Non défini', ENT_QUOTES, 'UTF-8');
                                                $badgeClass = 'bg-primary';
                                                if (strpos(strtolower($actionType), 'erreur') !== false) $badgeClass = 'bg-danger';
                                                if (strpos(strtolower($actionType), 'succès') !== false) $badgeClass = 'bg-success';
                                                ?>
                                                <span class="badge <?= $badgeClass ?>"><?= $actionType ?></span>
                                            </td>
                                            <td class="text-dark">
                                                <?= htmlspecialchars($log['message'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="text-muted font-monospace small">
                                                <?= htmlspecialchars($log['ip_address'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>