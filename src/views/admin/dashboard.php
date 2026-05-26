<?php
/**
 * Vue : Dashboard d'Administration
 *
 * Interface principale du Back-Office (Tableau de bord Haute Fidélité).
 * Elle présente les indicateurs clés de performance (KPI) calculés dynamiquement,
 * la liste décisionnelle des prospects issus de MySQL, ainsi que le fil
 * d'activité transverse ("Pilotages").
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @author     Romain Remusat
 * @version    2.0.0
 * * @var array $prospects Liste des prospects transmise par le DashboardController.
 */

// Sécurité : Block d'accès direct si la session n'est pas initialisée
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit();
}

// --- CALCUL DYNAMIQUE DES KPI (Basé sur le jeu de données MySQL) ---
$totalProspects = count($prospects);
$projetsEnAttente = 0;
$caPrevisionnel = 0;
$clientsActifs = 0;

foreach ($prospects as $p) {
    if ($p['status'] === 'en attente') {
        $projetsEnAttente++;
    }
    if ($p['status'] === 'accepté' || $p['status'] === 'terminé') {
        $clientsActifs++; // Un devis accepté valide un client actif
    }
    if ($p['status'] !== 'refusé') {
        $caPrevisionnel += $p['budget']; // Cumul des budgets des projets viables
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Innov'Events Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background-color: #212529; min-height: 100vh; color: #fff; }
        .sidebar .nav-link { color: #c2c7d0; transition: all 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255,255,255,0.1); border-radius: 4px; }
        .sidebar-heading { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1rem; color: #6c757d; padding: 0.75rem 1rem 0.25rem; }
        .kpi-card { border: none; border-radius: 10px; transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-5px); }
        .status-badge { font-size: 0.85rem; padding: 0.4em 0.6em; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-3 shadow">
            <div class="d-flex align-items-center mb-4 px-2">
                <i class="fa-solid fa-calendar-days text-primary fs-3 me-2"></i>
                <span class="fs-5 fw-bold text-white">Innov'Events</span>
            </div>
            <hr class="text-secondary">

            <ul class="nav flex-column mb-3">
                <div class="sidebar-heading">Générales</div>
                <li class="nav-item">
                    <a class="nav-link active" href="index.php?action=dashboard">
                        <i class="fa-solid fa-chart-line me-2"></i> Tableau de bord
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fa-solid fa-file-invoice-dollar me-2"></i> Devis / Factures
                    </a>
                </li>
            </ul>

            <ul class="nav flex-column mb-3">
                <div class="sidebar-heading">Administration & Système</div>
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fa-solid fa-users me-2"></i> Équipes & Rôles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fa-solid fa-database me-2"></i> Logs de Sécurité (NoSQL)
                    </a>
                </li>
            </ul>

            <ul class="nav flex-column mb-3">
                <div class="sidebar-heading">Profils</div>
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fa-solid fa-user me-2"></i> Mon Compte
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-danger fw-bold" href="index.php?action=logout">
                        <i class="fa-solid fa-right-from-bracket me-2"></i> Déconnexion
                    </a>
                </li>
            </ul>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2 fw-bold text-dark">Tableau de bord</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="badge bg-dark p-2 fs-6">
                        <i class="fa-solid fa-user-shield me-2"></i> Session : <?= htmlspecialchars($_SESSION['user_name']) ?> (<?= htmlspecialchars($_SESSION['user_role']) ?>)
                    </span>
                </div>
            </div>

            <div class="row g-3 mb-4">
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

            <div class="row g-4">

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
                                            <td colspan="5" class="text-center py-4 text-muted">Aucune demande de devis enregistrée.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($prospects as $prospect): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($prospect['company_name']) ?></div>
                                                    <small class="text-muted"><i class="fa-solid fa-user me-1"></i><?= htmlspecialchars($prospect['contact_name']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($prospect['event_type']) ?></span>
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
                                                    $badgeColor = 'bg-warning text-dark';
                                                    if ($prospect['status'] === 'accepté') $badgeColor = 'bg-success';
                                                    if ($prospect['status'] === 'refusé') $badgeColor = 'bg-danger';
                                                    if ($prospect['status'] === 'devis envoyé') $badgeColor = 'bg-info text-dark';
                                                    ?>
                                                    <span class="badge <?= $badgeColor ?> status-badge"><?= ucfirst(htmlspecialchars($prospect['status'])) ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="#" class="btn btn-sm btn-outline-primary me-1" title="Voir les détails">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-sm btn-outline-success" title="Traiter">
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

                                <div class="mb-4 position-relative">
                                    <i class="fa-solid fa-circle text-primary position-absolute fs-6 bg-white" style="left: -26px; top: 4px;"></i>
                                    <span class="small text-muted">Aujourd'hui, 11:15</span>
                                    <p class="mb-0 text-dark"><strong>Chloé</strong> a validé la demande de <em>AeroSpace SA</em>.</p>
                                </div>

                                <div class="mb-4 position-relative">
                                    <i class="fa-solid fa-circle text-warning position-absolute fs-6 bg-white" style="left: -26px; top: 4px;"></i>
                                    <span class="small text-muted">Hier, 16:40</span>
                                    <p class="mb-0 text-dark"><strong>José</strong> a ajouté une note interne sur le prospect <em>Test NoSQL Corp</em>.</p>
                                </div>

                                <div class="mb-0 position-relative">
                                    <i class="fa-solid fa-circle text-success position-absolute fs-6 bg-white" style="left: -26px; top: 4px;"></i>
                                    <span class="small text-muted">22 Mai, 09:12</span>
                                    <p class="mb-0 text-dark">Nouvelle demande de devis reçue via le formulaire public.</p>
                                </div>

                            </div>
                            <hr>
                            <small class="text-muted d-block text-center"><i class="fa-solid fa-info-circle me-1"></i>Ce flux sera alimenté par MongoDB au Sprint 2.</small>
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