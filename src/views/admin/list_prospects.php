<?php
/**
 * Vue : Liste complète des Prospects (Data Table avec Segmentation)
 *
 * Interface d'administration centralisant le listing tabulaire de l'ensemble
 * des demandes de devis entrantes. Elle offre aux gestionnaires
 * une vue synthétique des leads commerciaux, le suivi de leur état de qualification
 * et un accès direct au tunnel de conversion B2B.
 *
 * Normes et conventions appliquées :
 * - Sécurité : Clause de garde sur la session et protection XSS via htmlspecialchars.
 * - Accessibilité (RGAA) : Structuration sémantique des tableaux (scope, labels ARIA).
 * - Ergonomie UI/UX : Segmentation par onglets (En cours, Convertis, Refusés) et badges d'état.
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @author     Romain Remusat
 * @version    2.3.0
 *
 * @var array $allProspects
 * @var array $prospectsEnCours
 * @var array $prospectsConvertis
 * @var array $prospectsEchoues
 * @var array $prospects
 */

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit();
}

/**
 * Fonction helper locale de rendu d'un tableau de prospects.
 */
function renderProspectTable(array $list): void
{
    ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" aria-label="Tableau des prospects">
            <thead class="table-light text-uppercase fs-7">
            <tr>
                <th scope="col" class="px-3">Entreprise / Contact</th>
                <th scope="col">Événement / Date</th>
                <th scope="col">Budget</th>
                <th scope="col">Statut</th>
                <th scope="col" class="text-center px-3">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($list)): ?>
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <em>Aucun prospect dans cette catégorie pour le moment.</em>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($list as $prospect): ?>
                    <?php
                    $st = strtolower($prospect['status'] ?? 'à contacter');
                    $badgeClass = 'bg-secondary';
                    if (in_array($st, ['accepté', 'converti'], true)) {
                        $badgeClass = 'bg-success';
                    } elseif (in_array($st, ['refusé', 'échoué'], true)) {
                        $badgeClass = 'bg-danger';
                    } elseif ($st === 'à contacter') {
                        $badgeClass = 'bg-warning text-dark';
                    } elseif ($st === 'en cours') {
                        $badgeClass = 'bg-primary';
                    }
                    ?>
                    <tr>
                        <td class="px-3">
                            <div class="fw-bold text-dark">
                                <?= htmlspecialchars($prospect['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <small class="text-muted">
                                <i class="bi bi-person me-1" aria-hidden="true"></i>
                                <?= htmlspecialchars($prospect['contact_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($prospect['email'])): ?>
                                    &bull; <?= htmlspecialchars($prospect['email'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <?= htmlspecialchars($prospect['event_type'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-calendar me-1" aria-hidden="true"></i>
                                <?= !empty($prospect['event_date']) ? date('d/m/Y', strtotime($prospect['event_date'])) : 'Non définie' ?>
                            </div>
                        </td>
                        <td class="fw-bold text-secondary">
                            <?= number_format((float)($prospect['budget'] ?? 0), 2, ',', ' ') ?> € HT
                        </td>
                        <td>
                            <span class="badge <?= $badgeClass ?> px-2 py-1">
                                <?= ucfirst(htmlspecialchars($prospect['status'] ?? 'À contacter', ENT_QUOTES, 'UTF-8')) ?>
                            </span>
                        </td>
                        <td class="text-center px-3">
                            <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>"
                               class="btn btn-sm btn-outline-primary me-1"
                               title="Voir la fiche complète"
                               aria-label="Consulter la fiche de <?= htmlspecialchars($prospect['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </a>
                            <?php if (!in_array($st, ['converti'], true)): ?>
                                <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>"
                                   class="btn btn-sm btn-outline-success"
                                   title="Traiter ou Convertir"
                                   aria-label="Traiter le dossier de <?= htmlspecialchars($prospect['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>

<div class="container-fluid bg-light min-vh-100">
    <div class="row">

        <!-- Barre latérale d'administration -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-3">

            <!-- En-tête de section -->
            <div class="d-flex flex-wrap justify-content-between align-items-center pt-2 pb-2 mb-4 border-bottom">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        <i class="bi bi-people-fill text-primary me-2" aria-hidden="true"></i>Gestion des Prospects
                    </h1>
                    <p class="text-muted small mb-0">
                        Pipeline commercial complet : qualification, suivi des leads et conversion B2B en projets événementiels.
                    </p>
                </div>
            </div>

            <!-- Messages Flash -->
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <!-- Navigation par Onglets (Segmentation) -->
            <ul class="nav nav-pills mb-3" id="prospectsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="tab-all" data-bs-toggle="pill" data-bs-target="#content-all" type="button" role="tab" aria-controls="content-all" aria-selected="true">
                        <i class="bi bi-list-ul me-1"></i>Tous les prospects <span class="badge bg-secondary ms-1"><?= count($allProspects ?? $prospects ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="tab-encours" data-bs-toggle="pill" data-bs-target="#content-encours" type="button" role="tab" aria-controls="content-encours" aria-selected="false">
                        <i class="bi bi-hourglass-split me-1 text-warning"></i>En cours / À traiter <span class="badge bg-warning text-dark ms-1"><?= count($prospectsEnCours ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="tab-convertis" data-bs-toggle="pill" data-bs-target="#content-convertis" type="button" role="tab" aria-controls="content-convertis" aria-selected="false">
                        <i class="bi bi-check-circle-fill me-1 text-success"></i>Convertis / Acceptés <span class="badge bg-success ms-1"><?= count($prospectsConvertis ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="tab-echoues" data-bs-toggle="pill" data-bs-target="#content-echoues" type="button" role="tab" aria-controls="content-echoues" aria-selected="false">
                        <i class="bi bi-x-circle-fill me-1 text-danger"></i>Refusés / Échoués <span class="badge bg-danger ms-1"><?= count($prospectsEchoues ?? []) ?></span>
                    </button>
                </li>
            </ul>

            <!-- Contenu des Onglets -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="tab-content" id="prospectsTabsContent">
                        <!-- Onglet Tous -->
                        <div class="tab-pane fade show active" id="content-all" role="tabpanel" aria-labelledby="tab-all">
                            <?php renderProspectTable($allProspects ?? $prospects ?? []); ?>
                        </div>

                        <!-- Onglet En cours -->
                        <div class="tab-pane fade" id="content-encours" role="tabpanel" aria-labelledby="tab-encours">
                            <?php renderProspectTable($prospectsEnCours ?? []); ?>
                        </div>

                        <!-- Onglet Convertis -->
                        <div class="tab-pane fade" id="content-convertis" role="tabpanel" aria-labelledby="tab-convertis">
                            <?php renderProspectTable($prospectsConvertis ?? []); ?>
                        </div>

                        <!-- Onglet Échoués -->
                        <div class="tab-pane fade" id="content-echoues" role="tabpanel" aria-labelledby="tab-echoues">
                            <?php renderProspectTable($prospectsEchoues ?? []); ?>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>
