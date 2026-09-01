<?php
/**
 * Vue : Liste complète des Prospects (Data Table)
 *
 * Interface d'administration centralisant le listing tabulaire de l'ensemble
 * des demandes de devis entrantes (Activité Type 2). Elle offre aux gestionnaires
 * une vue synthétique des leads commerciaux, le suivi de leur état de qualification
 * et un accès direct au tunnel de conversion B2B.
 *
 * Normes et conventions appliquées :
 * - Sécurité (AT1) : Clause de garde sur la session et protection XSS via htmlspecialchars.
 * - Accessibilité (RGAA) : Structuration sémantique des tableaux (scope, labels ARIA).
 * - Ergonomie UI/UX : Badges d'état dynamiques et icônes vectorielles Bootstrap Icons.
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @author     Innov'Events
 * @version    2.1.0
 *
 * @var array $prospects Collection des prospects injectée depuis le DashboardController.
 */

// -----------------------------------------------------------------------------
// CLAUSE DE GARDE : Protection contre les accès directs non authentifiés (AT1)
// -----------------------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit();
}
?>

<div class="container-fluid bg-light min-vh-100 py-4">
    <div class="row">

        <!-- =============================================================== -->
        <!-- NAVIGATION LATÉRALE (SIDEBAR ADMIN)                            -->
        <!-- =============================================================== -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-2">

            <!-- =============================================================== -->
            <!-- EN-TÊTE DE PAGE ET CONTEXTE FONCTIONNEL                        -->
            <!-- =============================================================== -->
            <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        <i class="bi bi-people-fill text-primary me-2" aria-hidden="true"></i>Gestion des Prospects
                    </h1>
                    <p class="text-muted small mb-0">
                        Cet espace centralise toutes les demandes de devis entrantes. Qualifiez-les et convertissez-les en clients pour initialiser leur projet événementiel.
                    </p>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- TABLEAU DES PROSPECTS (DATA TABLE)                              -->
            <!-- =============================================================== -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0" aria-label="Liste complète des prospects enregistrés">

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
                            <?php if (empty($prospects)): ?>
                                <!-- État vide : Aucune demande de devis enregistrée -->
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        Aucune demande de devis n'est actuellement enregistrée en base de données.
                                    </td>
                                </tr>

                            <?php else: ?>
                                <!-- Boucle d'affichage des enregistrements prospects -->
                                <?php foreach ($prospects as $prospect): ?>
                                    <tr>
                                        <!-- Raison sociale et interlocuteur référent -->
                                        <td class="px-3">
                                            <div class="fw-bold text-dark">
                                                <?= htmlspecialchars($prospect['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-person me-1" aria-hidden="true"></i>
                                                <?= htmlspecialchars($prospect['contact_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        </td>

                                        <!-- Type d'événement et date souhaitée -->
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($prospect['event_type'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-calendar me-1" aria-hidden="true"></i>
                                                <?= !empty($prospect['event_date']) ? date('d/m/Y', strtotime($prospect['event_date'])) : 'Non définie' ?>
                                            </div>
                                        </td>

                                        <!-- Budget indicatif HT -->
                                        <td class="fw-bold text-secondary">
                                            <?= number_format((float)($prospect['budget'] ?? 0), 2, ',', ' ') ?> € HT
                                        </td>

                                        <!-- Badges de qualification dynamique du statut -->
                                        <td>
                                            <?php
                                            $status = strtolower($prospect['status'] ?? 'à contacter');
                                            $badgeClass = 'text-bg-warning';
                                            if ($status === 'en attente') {
                                                $badgeClass = 'text-bg-info';
                                            } elseif ($status === 'échoué' || $status === 'refusé') {
                                                $badgeClass = 'text-bg-danger';
                                            } elseif ($status === 'converti' || $status === 'accepté') {
                                                $badgeClass = 'text-bg-success';
                                            }
                                            ?>
                                            <span class="badge <?= $badgeClass ?> px-2 py-1">
                                                <?= ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8')) ?>
                                            </span>
                                        </td>

                                        <!-- Boutons d'action et de consultation -->
                                        <td class="text-center px-3">
                                            <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>"
                                               class="btn btn-sm btn-outline-primary me-1"
                                               title="Voir la fiche complète"
                                               aria-label="Consulter la fiche du prospect <?= htmlspecialchars($prospect['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </a>
                                            <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>"
                                               class="btn btn-sm btn-outline-success"
                                               title="Traiter la demande"
                                               aria-label="Traiter le dossier de <?= htmlspecialchars($prospect['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
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