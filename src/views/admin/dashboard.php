<?php
/**
 * Vue : Dashboard d'Administration (Haute Fidélité)
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @version    3.2.0 (Conformité ECF - Pipeline Commercial & Événements)
 */

// -----------------------------------------------------------------------------
// CLAUSE DE GARDE : Sécurisation de l'accès à la vue (AT1)
// -----------------------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit();
}

// -----------------------------------------------------------------------------
// RÉCUPÉRATION DES KPI
// -----------------------------------------------------------------------------
$nbClientsActifs = $clientsActifs ?? 0;
$totalDemandes = $totalProspects ?? (isset($allProspects) ? count($allProspects) : 0);
$caPrev = $caPrevisionnel ?? 0;

$renderProspectTable = function(array $items, string $emptyMsg = "Aucun prospect dans cette catégorie.") {
    if (empty($items)): ?>
        <div class="p-4 text-center text-muted"><?= $emptyMsg ?></div>
    <?php else: ?>
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
                <?php foreach ($items as $prospect): ?>
                    <tr>
                        <td class="px-3">
                            <div class="fw-bold text-dark"><?= htmlspecialchars($prospect['company_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <small class="text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($prospect['contact_name'], ENT_QUOTES, 'UTF-8') ?></small>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($prospect['event_type'], ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-calendar me-1"></i>
                                <?= !empty($prospect['event_date']) ? date('d/m/Y', strtotime($prospect['event_date'])) : 'Non définie' ?>
                            </div>
                        </td>
                        <td class="fw-bold text-secondary">
                            <?= number_format($prospect['budget'] ?? 0, 2, ',', ' ') ?> €
                        </td>
                        <td>
                            <?php
                            $st = strtolower($prospect['status'] ?? '');
                            $badgeColor = 'text-bg-warning';
                            if (in_array($st, ['accepté', 'terminé', 'converti'], true)) $badgeColor = 'text-bg-success';
                            elseif (in_array($st, ['refusé', 'échoué'], true)) $badgeColor = 'text-bg-danger';
                            elseif (in_array($st, ['devis envoyé', 'en cours'], true)) $badgeColor = 'text-bg-info';
                            ?>
                            <span class="badge <?= $badgeColor ?> status-badge"><?= ucfirst(htmlspecialchars($prospect['status'], ENT_QUOTES, 'UTF-8')) ?></span>
                        </td>
                        <td class="text-center px-3">
                            <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Voir la fiche">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (!in_array($st, ['converti', 'échoué', 'refusé'], true)): ?>
                                <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>" class="btn btn-sm btn-outline-success" title="Traiter / Qualifier">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif;
};
?>

<style>
    .kpi-card { border: 1px solid #e2e8f0; border-radius: 8px; transition: transform 0.2s; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    .status-badge { font-size: 0.85rem; padding: 0.4em 0.6em; }
    .timeline { border-left: 2px solid #e9ecef; padding-left: 20px; margin-left: 10px; }
    .timeline-dot { left: -26px; top: 4px; font-size: 0.65rem; }
    .nav-tabs .nav-link { border: none; border-bottom: 2px solid transparent; color: #64748b; font-size: 0.9rem; }
    .nav-tabs .nav-link.active { border-color: #0d6efd; color: #0d6efd; background: transparent; font-weight: 600; }
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

            <!-- Alerte d'action requise : Demandes de modification en attente -->
            <?php if (!empty($pendingModificationsCount) && $pendingModificationsCount > 0): ?>
                <div class="alert alert-warning border-warning shadow-sm mb-4 p-3" role="alert">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill fs-3 text-warning me-3" aria-hidden="true"></i>
                            <div>
                                <h2 class="h6 fw-bold mb-0 text-dark">
                                    <?= $pendingModificationsCount; ?> demande<?= $pendingModificationsCount > 1 ? 's' : ''; ?> de modification de devis en attente
                                </h2>
                                <p class="mb-0 small text-muted">
                                    Un ou plusieurs clients ont soumis des ajustements sur leurs propositions commerciales.
                                </p>
                            </div>
                        </div>
                        <a href="index.php?action=admin_devis" class="btn btn-warning btn-sm fw-bold px-3">
                            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Traiter les devis
                        </a>
                    </div>
                </div>
            <?php endif; ?>

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
                            <h2 class="fw-bold mb-1" style="font-size: 2rem; color: #0F172A;"><?= (int)$draftEventsCount ?></h2>
                            <h6 class="text-muted mb-0"><i class="bi bi-clock-history me-2"></i>Événements brouillons</h6>
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

            <!-- Widgets : Pilotages (Événements & Notes) -->
            <div id="pilotage-v3" class="row g-4 mb-5">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.1rem;"><i class="bi bi-calendar-event text-primary me-2"></i>Prochains événements</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (!empty($upcomingEvents)): ?>
                                <?php foreach ($upcomingEvents as $event): ?>
                                    <a href="index.php?action=admin_event_detail&id=<?= (int)$event['id'] ?>" class="list-group-item list-group-item-action py-3">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <h6 class="mb-1 fw-bold text-dark"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></h6>
                                            <span class="badge text-bg-light border text-capitalize"><?= htmlspecialchars($event['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            <i class="bi bi-building me-1"></i><?= htmlspecialchars($event['company_name'] ?? 'Client direct', ENT_QUOTES, 'UTF-8') ?> &bull;
                                            <i class="bi bi-calendar-check me-1 ms-1"></i><?= date('d/m/Y', strtotime($event['start_date'])) ?>
                                        </div>
                                    </a>
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
                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.1rem;"><i class="bi bi-journal-text text-primary me-2"></i>Notes d'équipe récentes</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (!empty($recentNotes)): ?>
                                <?php foreach ($recentNotes as $note): ?>
                                    <div class="list-group-item py-3">
                                        <div class="small text-muted">
                                            <strong class="text-dark"><?= htmlspecialchars($note['firstname'] . ' ' . $note['lastname'], ENT_QUOTES, 'UTF-8') ?></strong>
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

            <!-- Widgets : Pipeline des Prospects & Audit MongoDB -->
            <div id="pilotage-v2" class="row g-4">
                <h2 class="text-dark fw-bold h4 mb-3 mt-2">Pipeline des Demandes & Audit</h2>

                <!-- Tableau des prospects par onglets de statut -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3 h-100 overflow-hidden">
                        <div class="card-header bg-white pt-3 pb-0 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-funnel-fill text-primary me-2"></i>Pipeline Commercial (Demandes de devis)</h5>
                            </div>
                            <ul class="nav nav-tabs card-header-tabs" id="prospectTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="encours-tab" data-bs-toggle="tab" data-bs-target="#encours" type="button" role="tab" aria-selected="true">
                                        <i class="bi bi-hourglass-split me-1 text-warning"></i> À traiter / En cours
                                        <span class="badge rounded-pill bg-warning text-dark ms-1"><?= count($prospectsEnCours ?? []) ?></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="convertis-tab" data-bs-toggle="tab" data-bs-target="#convertis" type="button" role="tab" aria-selected="false">
                                        <i class="bi bi-check-circle me-1 text-success"></i> Convertis
                                        <span class="badge rounded-pill bg-success ms-1"><?= count($prospectsConvertis ?? []) ?></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="echoues-tab" data-bs-toggle="tab" data-bs-target="#echoues" type="button" role="tab" aria-selected="false">
                                        <i class="bi bi-x-circle me-1 text-danger"></i> Refusés / Échoués
                                        <span class="badge rounded-pill bg-danger ms-1"><?= count($prospectsEchoues ?? []) ?></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tous-tab" data-bs-toggle="tab" data-bs-target="#tous" type="button" role="tab" aria-selected="false">
                                        <i class="bi bi-folder2-open me-1 text-secondary"></i> Tous
                                        <span class="badge rounded-pill bg-secondary ms-1"><?= count($allProspects ?? $prospects ?? []) ?></span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body p-0">
                            <div class="tab-content" id="prospectTabsContent">
                                <div class="tab-pane fade show active" id="encours" role="tabpanel" aria-labelledby="encours-tab">
                                    <?php $renderProspectTable($prospectsEnCours ?? [], "Aucune demande en attente de traitement."); ?>
                                </div>
                                <div class="tab-pane fade" id="convertis" role="tabpanel" aria-labelledby="convertis-tab">
                                    <?php $renderProspectTable($prospectsConvertis ?? [], "Aucun prospect converti pour le moment."); ?>
                                </div>
                                <div class="tab-pane fade" id="echoues" role="tabpanel" aria-labelledby="echoues-tab">
                                    <?php $renderProspectTable($prospectsEchoues ?? [], "Aucun prospect refusé ou échoué."); ?>
                                </div>
                                <div class="tab-pane fade" id="tous" role="tabpanel" aria-labelledby="tous-tab">
                                    <?php $renderProspectTable($allProspects ?? $prospects ?? [], "Aucune demande de devis enregistrée."); ?>
                                </div>
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
                                            $logAction = strtolower($log['type_action'] ?? '');
                                            if (strpos($logAction, 'erreur') !== false) $color = 'text-danger';
                                            if (strpos($logAction, 'succès') !== false) $color = 'text-success';
                                            ?>
                                            <i class="bi bi-circle-fill <?= $color ?> position-absolute bg-white timeline-dot"></i>

                                            <span class="small text-muted fw-bold d-block mb-1">
                                                <?= htmlspecialchars($log['timestamp'] ?? 'Date inconnue', ENT_QUOTES, 'UTF-8') ?>
                                            </span>

                                            <p class="mb-1 text-dark small">
                                                <?= htmlspecialchars($log['details']['message'] ?? $log['type_action'] ?? 'Action système enregistrée', ENT_QUOTES, 'UTF-8') ?>
                                            </p>

                                            <?php if (!empty($log['details']['ip_address'])): ?>
                                                <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                                                    <i class="bi bi-hdd-network me-1"></i>IP: <?= htmlspecialchars($log['details']['ip_address'], ENT_QUOTES, 'UTF-8') ?>
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
