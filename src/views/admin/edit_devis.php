<?php
/**
 * Vue : Édition du Devis et Ajout de Prestations (Back-Office)
 *
 * Interface permettant à l'administrateur de construire la proposition commerciale
 * ligne par ligne, avec calcul dynamique et automatique des montants (HT, TVA, TTC)
 * conformément au cahier des charges (AT2).
 *
 * @var array $devis Données du devis et du prospect
 * @var array $prestations Liste des prestations déjà enregistrées
 */

// Algorithme métier : Calcul automatique des totaux
$totalHT = 0;
foreach ($prestations as $prest) {
    $totalHT += (float)$prest['montant_ht'];
}
$tvaRate = 0.20; // Taux de TVA standard (20%)
$totalTVA = $totalHT * $tvaRate;
$totalTTC = $totalHT + $totalTVA;
?>

<div class="container-fluid bg-light min-vh-100">
    <div class="row">

        <!-- Inclusion de la barre de navigation latérale -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        Édition du Devis : <?= htmlspecialchars($devis['company_name'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <p class="text-muted small mb-0">Réf : <?= htmlspecialchars($devis['reference_pdf'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <!-- Bouton pour générer le PDF final -->
                    <a href="index.php?action=generate_pdf&id=<?= (int)$devis['id_prospect'] ?>" class="btn btn-danger shadow-sm me-2">
                        <i class="fa-solid fa-file-pdf me-2"></i>Aperçu PDF
                    </a>
                    <a href="index.php?action=view_prospect&id=<?= (int)$devis['id_prospect'] ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
                        <i class="fa-solid fa-arrow-left me-2"></i>Retour
                    </a>
                </div>
            </div>


            <!-- ================================================================= -->
            <!-- RAPPEL DE LA DEMANDE INITIALE DU PROSPECT (UX / Back-Office)      -->
            <!-- ================================================================= -->
            <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid #3B82F6 !important;">
                <div class="card-body bg-white p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-clipboard-list text-primary me-2"></i>Cahier des charges & Demande initiale du prospect
                        </h2>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-3 py-2 fw-bold">
                Budget estimé : <?= number_format($devis['budget'] ?? 0, 2, ',', ' ') ?> € HT
            </span>
                    </div>

                    <hr class="my-3 opacity-25">

                    <!-- Métriques clés en coup d'œil -->
                    <div class="row g-3 text-muted small mb-3">
                        <div class="col-sm-6 col-md-3">
                            <span class="d-block text-uppercase fw-bold" style="font-size:0.7rem;">Type d'événement</span>
                            <span class="text-dark fw-semibold"><?= htmlspecialchars($devis['event_type'] ?? 'N/C', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="d-block text-uppercase fw-bold" style="font-size:0.7rem;">Date souhaitée</span>
                            <span class="text-dark fw-semibold"><?= !empty($devis['event_date']) ? date('d/m/Y', strtotime($devis['event_date'])) : 'Non définie' ?></span>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="d-block text-uppercase fw-bold" style="font-size:0.7rem;">Participants estimés</span>
                            <span class="text-dark fw-semibold"><?= (int)($devis['estimated_participants'] ?? 0) ?> personnes</span>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="d-block text-uppercase fw-bold" style="font-size:0.7rem;">Contact référent</span>
                            <span class="text-dark fw-semibold"><?= htmlspecialchars($devis['contact_name'] ?? 'N/C', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <!-- Description complète du besoin -->
                    <div class="p-3 bg-light rounded text-dark small lh-base border-start border-secondary border-2" style="white-space: pre-line;">
                        <strong class="d-block mb-1 text-secondary"><i class="fa-solid fa-quote-left me-1"></i> Description du besoin :</strong>
                        <?= htmlspecialchars($devis['description'] ?? 'Aucune spécification textuelle fournie.', ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
            </div>

            <!-- Ajout d'une nouvelle prestation -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom border-light py-3">
                    <h5 class="mb-0 fw-bold" style="color: #0F172A;">
                        <i class="fa-solid fa-plus-circle text-primary me-2"></i>Ajouter une prestation commerciale
                    </h5>
                </div>
                <div class="card-body bg-light">
                    <!-- Formulaire pour insérer une nouvelle ligne en BDD -->
                    <form action="index.php?action=add_prestation" method="POST" class="row align-items-end g-3">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="devis_id" value="<?= (int)$devis['id_devis'] ?>">

                        <div class="col-md-7">
                            <label for="libelle" class="form-label small fw-bold text-muted">Libellé de la prestation *</label>
                            <input type="text" class="form-control" id="libelle" name="libelle" placeholder="Ex: Location salle plénière 1000 places" required>
                        </div>
                        <div class="col-md-3">
                            <label for="montant_ht" class="form-label small fw-bold text-muted">Montant HT (€) *</label>
                            <input type="number" class="form-control" id="montant_ht" name="montant_ht" step="0.01" min="0" placeholder="Ex: 5000.00" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 fw-bold" style="background-color: #3B82F6; border: none;">
                                Ajouter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Liste des prestations et Calculs -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead style="background-color: #0F172A; color: white;">
                        <tr>
                            <th class="py-3 px-4">Description de la prestation</th>
                            <th class="py-3 px-4 text-end">Montant HT</th>
                            <th class="py-3 px-4 text-center" style="width: 100px;">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tbody>
                        <?php if (empty($prestations)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Aucune prestation n'a encore été ajoutée à ce devis.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prestations as $prest): ?>
                                <tr>
                                    <td class="px-4 align-middle"><?= htmlspecialchars($prest['libelle'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 text-end align-middle fw-semibold"><?= number_format($prest['montant_ht'], 2, ',', ' ') ?> €</td>
                                    <td class="px-4 text-center align-middle">

                                        <!-- FORMULAIRE DE SUPPRESSION SÉCURISÉ (POST + CSRF) -->
                                        <form action="index.php?action=delete_prestation" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer la prestation &quot;<?= htmlspecialchars($prest['libelle'], ENT_QUOTES, 'UTF-8') ?>&quot; ?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="prestation_id" value="<?= (int)$prest['id'] ?>">
                                            <input type="hidden" name="devis_id" value="<?= (int)$devis['id_devis'] ?>">

                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer la prestation">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                        </tbody>
                    </table>
                </div>

                <!-- Zone des totaux automatisés -->
                <div class="card-footer bg-white border-top p-4">
                    <div class="row justify-content-end">
                        <div class="col-md-5 col-lg-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted fw-bold">Total HT</span>
                                <span class="fw-bold"><?= number_format($totalHT, 2, ',', ' ') ?> €</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted fw-bold">TVA (20%)</span>
                                <span class="fw-bold"><?= number_format($totalTVA, 2, ',', ' ') ?> €</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span class="text-dark fw-bold h5 mb-0">Total TTC</span>
                                <span class="text-success fw-bold h5 mb-0"><?= number_format($totalTTC, 2, ',', ' ') ?> €</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>