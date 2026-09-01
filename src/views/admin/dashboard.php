<?php
/**
 * Vue : Dashboard d'Administration (Haute Fidélité)
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @version    3.1.0 (Conformité ECF - AT1/AT2 + Rétrocompatibilité V2)
 */

// -----------------------------------------------------------------------------
// CLAUSE DE GARDE : Sécurisation de l'accès à la vue (AT1)
// -----------------------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit();
}

// -----------------------------------------------------------------------------
// RÉCUPÉRATION DES KPI (Doivent être calculés et envoyés par le DashboardController)
// -----------------------------------------------------------------------------
$nbClientsActifs = $clientsActifs ?? 0;
$nbProjetsEnAttente = isset($prospectsEnAttente) ? count($prospectsEnAttente) : 0;
$totalDemandes = $totalProspects ?? 0;
$caPrev = $caPrevisionnel ?? 0;
?>

<style>
    .kpi-card { border: 1px solid #e2e8f0; border-radius: 8px; transition: transform 0.2s; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    .status-badge { font-size: 0.85rem; padding: 0.4em 0.6em; }
    .timeline { border-left: 2px solid #e9ecef; padding-left: 20px; margin-left: 10px; }
    .timeline-dot { left: -26px; top: 4px; font-size: 0.65rem; }
</style>

<div class="container-fluid bg-light min-vh-100">
    <div class="row">

        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center pt-3 pb-3 mb-4 border-bottom">
                <div class="d-flex align-items-center">
                    <div class="fs-2 me-3 text-dark"><i class="bi bi-person-bounding-box"></i></div>
                    <div>
                        <div class="small text-muted">Bonjour,</div>
                        <h1 class="h4 text-dark m-0"><span class="fw-bold"> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Chloé', ENT_QUOTES, 'UTF-8') ?></span>, bienvenue dans l'espace administrateur</h1>
                    </div>
                </div>
                <div class="position-relative text-center">
                    <i class="fs-4 text-dark bi bi-bell"></i>
                    <span class="badge rounded-pill text-bg-danger position-absolute top-0 start-100 translate-middle">3</span>
                </div>
            </div>

            <!-- Widget : Indicateurs Clés -->
            <div id="indicateurs" class="mb-5">
                <h2 class="text-dark fw-bold h4 mb-3">Indicateurs clés</h2>
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card bg-white text-dark p-4 h-100">
                            <h2 class="fw-bold mb-1" style="font-size: 2rem; color: #0F172A;"><?= $nbClientsActifs ?></h2>
                            <h6 class="text-muted mb-0"><i class="bi bi-person-check me-2"></i>Clients actifs</h6>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card bg-white text-dark p-4 h-100 border-start border-warning border-4">
                            <h2 class="fw-bold mb-1" style="font-size: 2rem; color: #0F172A;"><?= $nbProjetsEnAttente ?></h2>
                            <h6 class="text-muted mb-0"><i class="bi bi-clock-history me-2"></i>Projets en attente</h6>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card bg-white text-dark p-4 h-100 border-start border-info border-4">
                            <h2 class="fw-bold mb-1" style="font-size: 2rem; color: #0F172A;"><?= $totalDemandes ?></h2>
                            <h6 class="text-muted mb-0"><i class="bi bi-folder2-open me-2"></i>Total Demandes</h6>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card kpi-card bg-white text-dark p-4 h-100 border-start border-success border-4">
                            <h2 class="fw-bold mb-1" style="font-size: 2rem; color: #198754;"><?= number_format($caPrev, 2, ',', ' ') ?> €</h2>
                            <h6 class="text-muted mb-0"><i class="bi bi-currency-euro me-2"></i>CA Prévisionnel</h6>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widgets : Pilotages (V3 : Événements & Notes) -->
            <div id="pilotage-v3" class="row g-4 mb-5">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.1rem;"><i class="bi bi-calendar-event text-primary me-2"></i>Prochains événements</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (!empty($upcomingEvents)): ?>
                                <?php foreach ($upcomingEvents as $event): ?>
                                    <div class="list-group-item py-3">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="small text-muted mt-1">
                                            <i class="bi bi-building me-1"></i><?= htmlspecialchars($event['company_name'] ?? $event['firstname'] . ' ' . $event['lastname'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="small fw-semibold mt-1" style="color: #3B82F6;">
                                            <i class="bi bi-calendar me-1"></i><?= date('d/m/Y', strtotime($event['event_date'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="list-group-item py-4 text-center text-muted">Aucun événement à venir.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.1rem;"><i class="bi bi-journal-text text-secondary me-2"></i>Notes récentes</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (!empty($recentNotes)): ?>
                                <?php foreach ($recentNotes as $note): ?>
                                    <div class="list-group-item py-3">
                                        <div class="small fw-bold text-dark">
                                            <?= htmlspecialchars($note['firstname'] . ' ' . $note['lastname'], ENT_QUOTES, 'UTF-8') ?>
                                            <span class="text-muted fw-normal ms-1">- Projet : <?= htmlspecialchars($note['event_title'] ?? 'Note globale', ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="small mt-2 p-2 bg-light rounded text-muted fst-italic border-start border-secondary border-2">
                                            "<?= nl2br(htmlspecialchars($note['content'], ENT_QUOTES, 'UTF-8')) ?>"
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="list-group-item py-4 text-center text-muted">Aucune note récente.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widgets : Pilotages (V2 Réintégrés : Tableau et MongoDB) -->
            <div id="pilotage-v2" class="row g-4">
                <h2 class="text-dark fw-bold h4 mb-3 mt-2">Gestion des demandes & Audit</h2>

                <!-- Tableau des devis -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check text-primary me-2"></i>Demandes de devis entrantes</h5>
                        </div>
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
                                            <td colspan="5" class="text-center py-4 text-muted">Aucune demande de devis enregistrée.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($prospects as $prospect): ?>
                                            <tr>
                                                <td class="px-3">
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($prospect['company_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    <small class="text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($prospect['contact_name'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($prospect['event_type'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <div class="small text-muted mt-1">
                                                        <i class="bi bi-calendar me-1"></i>
                                                        <?= $prospect['event_date'] ? date('d/m/Y', strtotime($prospect['event_date'])) : 'Non définie' ?>
                                                    </div>
                                                </td>
                                                <td class="fw-bold text-secondary">
                                                    <?= number_format($prospect['budget'] ?? 0, 2, ',', ' ') ?> €
                                                </td>
                                                <td>
                                                    <?php
                                                    $badgeColor = 'text-bg-warning';
                                                    if ($prospect['status'] === 'accepté' || $prospect['status'] === 'terminé') $badgeColor = 'text-bg-success';
                                                    if ($prospect['status'] === 'refusé') $badgeColor = 'text-bg-danger';
                                                    if ($prospect['status'] === 'devis envoyé') $badgeColor = 'text-bg-info';
                                                    ?>
                                                    <span class="badge <?= $badgeColor ?> status-badge"><?= ucfirst(htmlspecialchars($prospect['status'], ENT_QUOTES, 'UTF-8')) ?></span>
                                                </td>
                                                <td class="text-center px-3">
                                                    <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Voir">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>" class="btn btn-sm btn-outline-success" title="Traiter">
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
                </div>

                <!-- Flux d'audit MongoDB -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-lightning-fill text-warning me-2"></i>Pilotages & Flux</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php if (empty($activityLogs)): ?>
                                    <div class="text-muted small fst-italic">Aucune activité récente n'a été enregistrée.</div>
                                <?php else: ?>
                                    <?php foreach ($activityLogs as $log): ?>
                                        <div class="mb-4 position-relative">
                                            <?php
                                            $color = 'text-primary';
                                            $logAction = strtolower($log['action'] ?? '');
                                            if (strpos($logAction, 'erreur') !== false) $color = 'text-danger';
                                            if (strpos($logAction, 'succès') !== false) $color = 'text-success';
                                            ?>
                                            <i class="bi bi-circle-fill <?= $color ?> position-absolute bg-white timeline-dot"></i>

                                            <span class="small text-muted fw-bold d-block mb-1">
                                                <?php
                                                if (isset($log['created_at'])) {
                                                    if (is_object($log['created_at']) && method_exists($log['created_at'], 'toDateTime')) {
                                                        echo $log['created_at']->toDateTime()->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y, H:i');
                                                    } else {
                                                        echo date('d/m/Y, H:i', strtotime((string)$log['created_at']));
                                                    }
                                                } else {
                                                    echo 'Date inconnue';
                                                }
                                                ?>
                                            </span>

                                            <p class="mb-1 text-dark small">
                                                <?= htmlspecialchars($log['message'] ?? $log['action'] ?? 'Action système enregistrée', ENT_QUOTES, 'UTF-8') ?>
                                            </p>

                                            <?php if (!empty($log['ip_address'])): ?>
                                                <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                                                    <i class="bi bi-hdd-network me-1"></i>IP: <?= htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8') ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <hr class="mt-4 mb-3">
                            <small class="text-success d-flex justify-content-center align-items-center fw-bold">
                                <i class="bi bi-database me-2"></i> Flux d'audit sécurisé (MongoDB)
                            </small>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>