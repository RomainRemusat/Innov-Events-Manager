<?php
/**
 * Vue : Dashboard d'Administration (Haute Fidélité)
 *
 * Interface principale du Back-Office. Elle agrège les données issues de
 * la double persistance (Polyglot Persistence) :
 * 1. MySQL (Données structurées) : Calcul des KPI et gestion du cycle de vie des prospects.
 * 2. MongoDB (Données orientées documents) : Affichage du flux d'audit en temps réel.
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @author     Romain Remusat
 * @version    2.1.0
 *
 * @var array $prospects    Liste des prospects (MySQL) injectée par le DashboardController.
 * @var array $activityLogs Liste des logs d'audit (MongoDB) injectée par le DashboardController.
 */

// -----------------------------------------------------------------------------
// CLAUSE DE GARDE : Sécurisation de l'accès à la vue (Guard Pattern)
// -----------------------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit();
}

// -----------------------------------------------------------------------------
// MOTEUR DE CALCUL DES KPI (Indicateurs Clés de Performance)
// Algorithme d'agrégation basé sur les règles métiers de l'entreprise
// -----------------------------------------------------------------------------
$totalProspects = count($prospects);
$projetsEnAttente = 0;
$caPrevisionnel = 0;
$clientsActifs = 0;

foreach ($prospects as $p) {
    // Comptage des flux entrants nécessitant une action
    if ($p['status'] === 'en attente') {
        $projetsEnAttente++;
    }
    // Un devis accepté ou terminé transforme un prospect en "Client Actif"
    if ($p['status'] === 'accepté' || $p['status'] === 'terminé') {
        $clientsActifs++;
    }
    // Calcul du pipeline financier (on exclut uniquement les projets refusés)
    if ($p['status'] !== 'refusé') {
        $caPrevisionnel += $p['budget'];
    }
}
?>

<style>
    .kpi-card { border: none; border-radius: 10px; transition: transform 0.2s; }
    .kpi-card:hover { transform: translateY(-5px); } /* Effet de survol UX */
    .status-badge { font-size: 0.85rem; padding: 0.4em 0.6em; }
</style>

<div class="container-fluid">
    <div class="row">

        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex flex-wrap flex-md-nowrap align-items-center pt-3 pb-3 mb-5">
                <div class="fs-1 me-2 text-black"><i class="bi bi-person-bounding-box"></i></div>
                <div>
                    <div class="small">Bonjour,</div>
                    <h1 class="h4 text-dark fst-italic m-0"><span class="fw-bold"> <?= htmlspecialchars($_SESSION['user_name'] ?? 'John doe', ENT_QUOTES, 'UTF-8') ?></span>,bienvenue dans l'espace administrateur</h1>
                </div>
                <div class=" m-auto me-0 position-relative me-3 text-center">
                    <i class="fs-2 text-black bi bi-bell"></i>
                    <span class="badge rounded-pill text-bg-info position-absolute top-0 start-100 translate-middle">3</span>
                </div>
            </div>

            <div id="indicateur" class="row g-3 mb-5">
                <h2 class="text-black fw-bold h3 mb-3">Indicateur clés</h2>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card kpi-card bg-white text-dark shadow-sm h-100 p-3 border-start border-primary border-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1" style="font-size: 0.8rem;">Clients Actifs</h6>
                                <h2 class="fw-bold mb-0"><?= $clientsActifs ?></h2>
                            </div>
                            <div class="bg-light p-3 rounded-circle text-primary"><i class="fa-solid fa-user-check fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card kpi-card bg-white text-dark shadow-sm h-100 p-3 border-start border-warning border-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1" style="font-size: 0.8rem;">Projets en attente</h6>
                                <h2 class="fw-bold mb-0"><?= $projetsEnAttente ?></h2>
                            </div>
                            <div class="bg-light p-3 rounded-circle text-warning"><i class="fa-solid fa-clock fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card kpi-card bg-white text-dark shadow-sm h-100 p-3 border-start border-info border-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1" style="font-size: 0.8rem;">Total Demandes</h6>
                                <h2 class="fw-bold mb-0"><?= $totalProspects ?></h2>
                            </div>
                            <div class="bg-light p-3 rounded-circle text-info"><i class="fa-solid fa-folder-open fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card kpi-card bg-white text-dark shadow-sm h-100 p-3 border-start border-success border-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1" style="font-size: 0.8rem;">CA Prévisionnel</h6>
                                <h2 class="fw-bold mb-0"><?= number_format($caPrevisionnel, 2, ',', ' ') ?> €</h2>
                            </div>
                            <div class="bg-light p-3 rounded-circle text-success"><i class="fa-solid fa-euro-sign fs-4"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="pilotage" class="row g-4">
                <h2 class="text-black fw-bold h3 mb-3">Pilotages</h2>

                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i> Demandes de devis entrantes</h5>
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
                                            <td colspan="5" class="text-center py-4 text-muted">Aucune demande de devis enregistrée en base.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($prospects as $prospect): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($prospect['company_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    <small class="text-muted"><i class="fa-solid fa-user me-1"></i><?= htmlspecialchars($prospect['contact_name'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($prospect['event_type'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <div class="small text-muted mt-1">
                                                        <i class="fa-solid fa-calendar me-1"></i>
                                                        <?= $prospect['event_date'] ? date('d/m/Y', strtotime($prospect['event_date'])) : 'Non définie' ?>
                                                    </div>
                                                </td>
                                                <td class="fw-bold text-secondary">
                                                    <?= number_format($prospect['budget'] ?? 0, 2, ',', ' ') ?> €
                                                </td>
                                                <td>
                                                    <?php
                                                    // Sémantique visuelle dynamique des états (State Pattern UI)
                                                    $badgeColor = 'bg-warning text-dark';
                                                    if ($prospect['status'] === 'accepté') $badgeColor = 'bg-success';
                                                    if ($prospect['status'] === 'refusé') $badgeColor = 'bg-danger';
                                                    if ($prospect['status'] === 'devis envoyé') $badgeColor = 'bg-info text-dark';
                                                    ?>
                                                    <span class="badge <?= $badgeColor ?> status-badge"><?= ucfirst(htmlspecialchars($prospect['status'], ENT_QUOTES, 'UTF-8')) ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Voir les détails">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                    <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>" class="btn btn-sm btn-outline-success" title="Traiter">
                                                        <i class="fa-solid fa-pen-to-square"></i>
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

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-0">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-bolt me-2 text-warning"></i> Pilotages & Flux</h5>
                        </div>
                        <div class="card-body">

                            <div class="timeline" style="border-left: 2px solid #e9ecef; padding-left: 20px; margin-left: 10px;">
                                <?php if (empty($activityLogs)): ?>
                                    <div class="text-muted small fst-italic">Aucune activité récente n'a été enregistrée dans le journal système.</div>
                                <?php else: ?>
                                    <?php foreach ($activityLogs as $log): ?>
                                        <div class="mb-4 position-relative">
                                            <?php
                                            // Choix de la couleur selon le type d'action pour alerter visuellement l'admin
                                            $color = 'primary';
                                            $logAction = strtolower($log['action'] ?? '');
                                            if (strpos($logAction, 'erreur') !== false) $color = 'danger'; // Alerte critique
                                            if (strpos($logAction, 'succès') !== false) $color = 'success'; // Validation
                                            ?>
                                            <i class="fa-solid fa-circle text-<?= $color ?> position-absolute fs-6 bg-white" style="left: -26px; top: 4px;"></i>

                                            <span class="small text-muted fw-bold">
                                                <?php
                                                // Gestion de la compatibilité : MongoDB stocke par défaut en objet BSON UTCDateTime
                                                if (isset($log['created_at'])) {
                                                    if (is_object($log['created_at']) && method_exists($log['created_at'], 'toDateTime')) {
                                                        echo $log['created_at']->toDateTime()->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y, H:i');
                                                    } else {
                                                        // Fallback si la date est un simple string
                                                        echo date('d/m/Y, H:i', strtotime((string)$log['created_at']));
                                                    }
                                                } else {
                                                    echo 'Date inconnue';
                                                }
                                                ?>
                                            </span>

                                            <p class="mb-0 text-dark">
                                                <?= htmlspecialchars($log['message'] ?? $log['action'] ?? 'Action système enregistrée', ENT_QUOTES, 'UTF-8') ?>
                                            </p>

                                            <?php if (!empty($log['ip_address'])): ?>
                                                <small class="text-muted" style="font-size: 0.7rem;">
                                                    <i class="fa-solid fa-network-wired me-1"></i>IP: <?= htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8') ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <hr>
                            <small class="text-success d-block text-center fw-bold">
                                <i class="fa-solid fa-database me-1"></i>Flux d'audit sécurisé propulsé par MongoDB.
                            </small>
                        </div>
                    </div>
                </div>

            </div>
        </main>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>