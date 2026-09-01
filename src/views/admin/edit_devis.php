<?php
/**
 * Vue : Édition du Devis et Gestion des Prestations Commerciales (Back-Office)
 *
 * Interface d'administration permettant la constitution granulaire d'une proposition
 * commerciale (Activité Type 2). Elle offre un récapitulatif du cahier des charges client,
 * la gestion dynamique des lignes de prestation (ajout/suppression) et le calcul
 * automatisé des agrégats financiers (HT, TVA à 20 %, TTC).
 *
 * Spécifications de sécurité et d'accessibilité :
 * - Protection Anti-CSRF sur l'ensemble des formulaires d'altération de données.
 * - Échappement systématique des données dynamiques via htmlspecialchars (XSS).
 * - Accessibilité RGAA (Attributs ARIA, liaisons for/id explicites, contrastes).
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @author     Innov'Events
 * @version    2.1.0
 *
 * @var array $devis       Données consolidées du devis et du prospect associé.
 * @var array $prestations Collection des lignes de prestations rattachées au devis.
 */

// -----------------------------------------------------------------------------
// CALCUL DES AGRÉGATS FINANCIERS (Agrégation dynamique des montants HT)
// -----------------------------------------------------------------------------
$totalHT = 0.0;
if (!empty($prestations) && is_array($prestations)) {
    foreach ($prestations as $prest) {
        $totalHT += (float)($prest['montant_ht'] ?? 0);
    }
}

$tvaRate  = 0.20; // Taux de TVA standard légal (20 %)
$totalTVA = $totalHT * $tvaRate;
$totalTTC = $totalHT + $totalTVA;
?>

<div class="container-fluid bg-light min-vh-100">
    <div class="row">

        <!-- =============================================================== -->
        <!-- NAVIGATION LATÉRALE (SIDEBAR ADMIN)                            -->
        <!-- =============================================================== -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <!-- =============================================================== -->
            <!-- EN-TÊTE DE PAGE ET ACTIONS COMMERCIALES RAPIDES                 -->
            <!-- =============================================================== -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom pb-3 gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h1 class="h3 fw-bold text-dark mb-0">
                            Devis : <?= htmlspecialchars($devis['company_name'] ?? 'Client', ENT_QUOTES, 'UTF-8') ?>
                        </h1>
                        <?php
                        // Évaluation conditionnelle de la sémantique visuelle des statuts
                        $status = strtolower($devis['status'] ?? 'brouillon');
                        $badgeBg = 'text-bg-secondary';
                        if ($status === 'accepté') {
                            $badgeBg = 'text-bg-success';
                        } elseif ($status === 'refusé') {
                            $badgeBg = 'text-bg-danger';
                        } elseif (in_array($status, ['devis envoyé', 'étude côté client'], true)) {
                            $badgeBg = 'text-bg-info';
                        } elseif ($status === 'modification') {
                            $badgeBg = 'text-bg-warning';
                        }
                        ?>
                        <span class="badge <?= $badgeBg ?> text-uppercase px-2 py-1" style="font-size: 0.75rem;">
                            <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <p class="text-muted small mb-0">
                        Référence : <code class="text-primary fw-bold"><?= htmlspecialchars($devis['reference_pdf'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></code>
                        | Date de création : <?= !empty($devis['date_creation']) ? date('d/m/Y à H:i', strtotime($devis['date_creation'])) : date('d/m/Y') ?>
                    </p>
                </div>

                <!-- Boutons d'action : Expédition, Génération PDF et Navigation -->
                <div class="d-flex gap-2">
                    <a href="index.php?action=send_quote_to_client&id=<?= (int)$devis['id_devis'] ?>"
                       class="btn btn-success shadow-sm"
                       onclick="return confirm('Confirmez-vous l\'envoi direct du devis au client par courriel ?');">
                        <i class="bi bi-send me-2" aria-hidden="true"></i>Envoyer au client
                    </a>
                    <a href="index.php?action=generate_pdf&id=<?= (int)$devis['id_devis'] ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn-danger shadow-sm">
                        <i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>Aperçu PDF
                    </a>
                    <a href="index.php?action=admin_devis" class="btn btn-outline-secondary shadow-sm">
                        <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Retour
                    </a>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- SYNTHÈSE DU CAHIER DES CHARGES (BESOINS EXPRIMÉS ET BUDGET)     -->
            <!-- =============================================================== -->
            <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid #3B82F6 !important;">
                <div class="card-body bg-white p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 fw-bold mb-0 text-dark">
                            <i class="bi bi-clipboard-check text-primary me-2" aria-hidden="true"></i>Spécifications du projet & Besoins exprimés
                        </h2>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-3 py-2 fw-bold">
                            Budget indicatif : <?= number_format((float)($devis['budget'] ?? 0), 2, ',', ' ') ?> € HT
                        </span>
                    </div>

                    <!-- Métriques clés du projet -->
                    <div class="row g-3 text-muted small mb-3">
                        <div class="col-sm-6 col-md-3">
                            <span class="d-block text-uppercase fw-bold text-secondary" style="font-size:0.7rem;">Type d'événement</span>
                            <span class="text-dark fw-semibold"><?= htmlspecialchars($devis['event_type'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="d-block text-uppercase fw-bold text-secondary" style="font-size:0.7rem;">Date souhaitée</span>
                            <span class="text-dark fw-semibold"><?= !empty($devis['event_date']) ? date('d/m/Y', strtotime($devis['event_date'])) : 'Non définie' ?></span>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="d-block text-uppercase fw-bold text-secondary" style="font-size:0.7rem;">Lieu / Localisation</span>
                            <span class="text-dark fw-semibold">
                                <i class="bi bi-geo-alt text-danger me-1" aria-hidden="true"></i>
                                <?= htmlspecialchars($devis['location'] ?? 'Non précisé', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="d-block text-uppercase fw-bold text-secondary" style="font-size:0.7rem;">Participants prévus</span>
                            <span class="text-dark fw-semibold"><?= (int)($devis['estimated_participants'] ?? 0) ?> personnes</span>
                        </div>
                    </div>

                    <!-- Bloc textuel de description du besoin -->
                    <div class="p-3 bg-light rounded text-dark small lh-base border-start border-primary border-2">
                        <strong class="d-block mb-1 text-secondary">
                            <i class="bi bi-quote me-1" aria-hidden="true"></i> Description du besoin :
                        </strong>
                        <?= nl2br(htmlspecialchars($devis['description'] ?? 'Aucune spécification enregistrée.', ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- FORMULAIRE D'AJOUT D'UNE LIGNE DE PRESTATION (CSRF PROTECTED)    -->
            <!-- =============================================================== -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h6 mb-0 fw-bold" style="color: #0F172A;">
                        <i class="bi bi-plus-circle text-primary me-2" aria-hidden="true"></i>Ajouter une ligne commerciale
                    </h2>
                </div>
                <div class="card-body bg-light">
                    <form action="index.php?action=add_prestation" method="POST" class="row align-items-end g-3">

                        <!-- Jeton de sécurité Anti-CSRF -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <!-- Identifiant du devis cible -->
                        <input type="hidden" name="devis_id" value="<?= (int)$devis['id_devis'] ?>">

                        <div class="col-md-7">
                            <label for="libelle" class="form-label small fw-bold text-muted">Désignation de la prestation *</label>
                            <input type="text" class="form-control" id="libelle" name="libelle"
                                   placeholder="Ex : Traiteur cocktail déjeunatoire 150 pax" required aria-required="true">
                        </div>

                        <div class="col-md-3">
                            <label for="montant_ht" class="form-label small fw-bold text-muted">Montant HT (€) *</label>
                            <input type="number" class="form-control" id="montant_ht" name="montant_ht"
                                   step="0.01" min="0" placeholder="0.00" required aria-required="true">
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 fw-bold" style="background-color: #3B82F6; border: none;">
                                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Ajouter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- BORDEREAU COMPTABLE ET TABLEAU DES PRESTATIONS                  -->
            <!-- =============================================================== -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" aria-label="Bordereau des prestations enregistrées">
                            <thead style="background-color: #0F172A; color: white;">
                            <tr>
                                <th scope="col" class="py-3 px-4">Désignation</th>
                                <th scope="col" class="py-3 px-4 text-end" style="width: 200px;">Montant HT</th>
                                <th scope="col" class="py-3 px-4 text-center" style="width: 120px;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($prestations)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">
                                        Aucune prestation enregistrée sur ce bordereau. Utilisez le formulaire ci-dessus pour composer votre offre.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($prestations as $prest): ?>
                                    <tr>
                                        <td class="px-4 fw-semibold text-dark">
                                            <?= htmlspecialchars($prest['libelle'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-4 text-end fw-bold text-secondary">
                                            <?= number_format((float)$prest['montant_ht'], 2, ',', ' ') ?> € HT
                                        </td>
                                        <td class="px-4 text-center">
                                            <!-- Formulaire de suppression sécurisé (Pattern POST + CSRF) -->
                                            <form action="index.php?action=delete_prestation" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Confirmez-vous la suppression de cette ligne ?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="prestation_id" value="<?= (int)$prest['id'] ?>">
                                                <input type="hidden" name="devis_id" value="<?= (int)$devis['id_devis'] ?>">

                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        title="Supprimer la prestation"
                                                        aria-label="Supprimer <?= htmlspecialchars($prest['libelle'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- =========================================================== -->
                <!-- SYNTHÈSE DES AGRÉGATS FINANCIERS (HT / TVA / TTC)          -->
                <!-- =========================================================== -->
                <div class="card-footer bg-white border-top p-4">
                    <div class="row justify-content-end">
                        <div class="col-md-5 col-lg-4">
                            <div class="d-flex justify-content-between mb-2 small">
                                <span class="text-muted fw-bold">Total HT soumis à TVA</span>
                                <span class="fw-bold text-dark"><?= number_format($totalHT, 2, ',', ' ') ?> €</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 small">
                                <span class="text-muted fw-bold">TVA légale (20 %)</span>
                                <span class="fw-bold text-dark"><?= number_format($totalTVA, 2, ',', ' ') ?> €</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-dark fw-bold h6 mb-0">Total TTC</span>
                                <span class="text-success fw-bold h5 mb-0"><?= number_format($totalTTC, 2, ',', ' ') ?> €</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>