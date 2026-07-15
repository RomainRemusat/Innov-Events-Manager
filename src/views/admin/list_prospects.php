<?php
/**
 * Vue : Liste complète des Prospects (Data Table)
 *
 * Ce composant d'interface utilisateur (UI) affiche la liste tabulaire de toutes
 * les demandes de devis entrantes. Il est conçu pour être "Responsive" et intègre
 * des repères visuels (Badges de couleur) pour faciliter la lecture rapide des statuts.
 *
 * Dépendances :
 * - Nécessite l'inclusion préalable de `header.php` (qui ouvre les balises HTML/Body).
 * - Nécessite l'inclusion de `footer.php` (pour fermer les balises et charger les scripts JS).
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @author     Romain Remusat
 * @version    1.0.0
 * * @var array $prospects Collection des prospects injectée depuis le DashboardController.
 */

// Clause de sécurité redondante : protection de la vue contre les accès directs (ex: via URL absolue)
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit();
}
?>

<div class="container-fluid">
    <div class="row">

        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2 fw-bold text-dark">
                    <i class="fa-solid fa-users me-2 text-primary"></i> Gestion des Prospects
                </h1>
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
                                        <td>
                                            <div class="fw-bold text-dark">
                                                <?= htmlspecialchars($prospect['company_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fa-solid fa-user me-1"></i>
                                                <?= htmlspecialchars($prospect['contact_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        </td>

                                        <td>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars($prospect['event_type'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
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
                                            // Pattern UX : Association de couleurs selon le cycle de vie du projet
                                            $badgeColor = 'bg-warning text-dark'; // Par défaut : En attente
                                            if ($prospect['status'] === 'accepté') $badgeColor = 'bg-success';
                                            if ($prospect['status'] === 'refusé') $badgeColor = 'bg-danger';
                                            if ($prospect['status'] === 'devis envoyé') $badgeColor = 'bg-info text-dark';
                                            ?>
                                            <span class="badge <?= $badgeColor ?> status-badge">
                                                    <?= ucfirst(htmlspecialchars($prospect['status'], ENT_QUOTES, 'UTF-8')) ?>
                                                </span>
                                        </td>

                                        <td class="text-center">
                                            <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Voir la fiche complète">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <a href="index.php?action=view_prospect&id=<?= (int)$prospect['id'] ?>" class="btn btn-sm btn-outline-success" title="Traiter la demande">
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

        </main>
    </div>
</div>