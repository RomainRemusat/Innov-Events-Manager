<?php
/**
 * Vue : Liste globale des Devis et Suivi Commercial (Back-Office)
 *
 * Ce composant d'interface (Vue) est responsable de l'affichage du pipeline
 * commercial. Il intègre la présentation des montants financiers (HT/TTC)
 * et respecte les normes d'accessibilité (RGAA - W3C) ainsi que la charte
 * graphique officielle d'Innov'Events.
 *
 * @package    InnovEventsManager
 * @subpackage Views/Admin
 * @author     Romain Remusat
 * @version    1.3.0
 *
 * @var array $devisList Liste des devis enrichie des totaux (Injectée par le DashboardController)
 */
?>

<div class="container-fluid bg-light min-vh-100">
    <div class="row">

        <!-- Inclusion de la barre de navigation latérale modulaire -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <!-- En-tête de la page -->
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        <i class="fa-solid fa-file-invoice-dollar text-primary me-2" aria-hidden="true"></i>Devis & Facturation
                    </h1>
                    <p class="text-muted small mb-0">Suivi commercial des propositions et état des facturations en temps réel.</p>
                </div>
            </div>

            <!-- Tableau de suivi des devis -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" aria-label="Liste des devis commerciaux">

                            <thead style="background-color: #0F172A; color: white;">
                            <tr>
                                <th scope="col" class="py-3 px-4">Référence / Date</th>
                                <th scope="col" class="py-3 px-4">Client / Entreprise</th>
                                <th scope="col" class="py-3 px-4 text-end">Total HT</th>
                                <th scope="col" class="py-3 px-4 text-end">Total TTC (20%)</th>
                                <th scope="col" class="py-3 px-4 text-center">Statut</th>
                                <th scope="col" class="py-3 px-4 text-center">Actions</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php if (empty($devisList)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-folder-open display-6 d-block mb-3 opacity-50" aria-hidden="true"></i>
                                        Aucun devis n'a encore été généré. Convertissez un prospect pour initialiser un devis.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($devisList as $devis): ?>
                                    <?php
                                    // Calcul du montant HT et de la TVA à 20 %
                                    $ht = (float)($devis['montant_ht'] ?? $devis['total_ht'] ?? 0);
                                    $ttc = $ht * 1.20;
                                    ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($devis['reference_pdf'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                            <small class="text-muted">
                                                <i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>
                                                <?= !empty($devis['date_creation']) ? date('d/m/Y à H:i', strtotime($devis['date_creation'])) : date('d/m/Y') ?>
                                            </small>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($devis['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                            <small class="text-muted">
                                                <i class="fa-solid fa-user me-1" aria-hidden="true"></i>
                                                <?= htmlspecialchars($devis['contact_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        </td>
                                        <td class="px-4 py-3 text-end fw-semibold">
                                            <?= number_format($ht, 2, ',', ' ') ?> €
                                        </td>
                                        <td class="px-4 py-3 text-end fw-bold text-success">
                                            <?= number_format($ttc, 2, ',', ' ') ?> €
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php
                                            // Évaluation stricte du statut du devis (d.status)
                                            $st = strtolower($devis['status'] ?? 'brouillon');

                                            switch ($st) {
                                                case 'accepté':
                                                    $badgeClass = 'bg-success text-white';
                                                    $label = 'Accepté';
                                                    break;
                                                case 'refusé':
                                                    $badgeClass = 'bg-danger text-white';
                                                    $label = 'Refusé';
                                                    break;
                                                case 'modification':
                                                    $badgeClass = 'bg-warning text-dark';
                                                    $label = 'Modification demandée';
                                                    break;
                                                case 'étude côté client':
                                                case 'devis envoyé':
                                                    $badgeClass = 'bg-info text-dark';
                                                    $label = 'Étude côté client';
                                                    break;
                                                case 'brouillon':
                                                default:
                                                    $badgeClass = 'bg-secondary text-white';
                                                    $label = 'Brouillon';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?= $badgeClass ?> px-3 py-2">
                                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <!-- Bouton Éditer -->
                                            <a href="index.php?action=edit_devis&id=<?= (int)$devis['id_devis'] ?>"
                                               class="btn btn-sm btn-outline-primary me-1"
                                               title="Éditer les prestations"
                                               aria-label="Éditer les prestations du devis <?= htmlspecialchars($devis['reference_pdf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                            </a>

                                            <!-- Bouton PDF -->
                                            <a href="index.php?action=generate_pdf&id=<?= (int)$devis['id_devis'] ?>"
                                               target="_blank"
                                               class="btn btn-sm btn-outline-danger"
                                               title="Aperçu du PDF"
                                               aria-label="Aperçu du PDF du devis <?= htmlspecialchars($devis['reference_pdf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
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