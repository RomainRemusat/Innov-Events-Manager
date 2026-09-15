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
 * @version    2.2.0
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
                    <?php if ($status !== 'accepté'): ?>
                    <form action="index.php?action=send_quote_to_client" method="POST" class="d-inline"
                          onsubmit="return confirm('Confirmez-vous l\'envoi direct du devis au client par courriel ?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= (int)$devis['id_devis'] ?>">
                        <button type="submit" class="btn btn-success shadow-sm">
                            <i class="bi bi-send me-2" aria-hidden="true"></i>Envoyer au client
                        </button>
                    </form>
                    <?php endif; ?>
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

            <!-- Messages Flash -->
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>
                    <?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                    <?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <!-- Alerte explicite si le devis est en demande de modification -->
            <?php if (strtolower($devis['status'] ?? '') === 'modification'): ?>
                <div class="alert alert-warning border-warning shadow-sm mb-4 p-3" role="alert">
                    <div class="d-flex align-items-start gap-3">
                        <i class="bi bi-exclamation-triangle-fill fs-3 text-warning flex-shrink-0 mt-1" aria-hidden="true"></i>
                        <div>
                            <h2 class="h6 fw-bold mb-1 text-dark">
                                Demande de modification transmise par le client
                            </h2>
                            <p class="mb-2 text-dark small">
                                <strong>Remarque du client :</strong>
                                <span class="fst-italic bg-white px-2 py-1 rounded border d-inline-block mt-1">
                                    « <?= htmlspecialchars($lastChangeReason ?? 'Aucun détail renseigné', ENT_QUOTES, 'UTF-8') ?> »
                                </span>
                            </p>
                            <p class="mb-0 text-muted small">
                                Ajustez les prestations ci-dessous, puis cliquez sur <strong>« Envoyer au client »</strong> pour lui retransmettre la proposition révisée.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- =============================================================== -->
            <!-- CORPS PRINCIPAL : CAHIER DES CHARGES & GESTION DES PRESTATIONS -->
            <!-- =============================================================== -->
            <div class="row g-4">

                <!-- ----------------------------------------------------------- -->
                <!-- COLONNE GAUCHE : RECAPITULATIF DU PROJET ET CONTACT         -->
                <!-- ----------------------------------------------------------- -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white border-bottom border-light py-3">
                            <h2 class="h6 fw-bold mb-0 text-dark">
                                <i class="bi bi-card-text text-primary me-2" aria-hidden="true"></i>Cahier des Charges & Contact
                            </h2>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0 small">
                                <dt class="col-sm-5 text-muted">Interlocuteur :</dt>
                                <dd class="col-sm-7 fw-bold text-dark">
                                    <?= htmlspecialchars($devis['contact_name'] ?? 'Non renseigné', ENT_QUOTES, 'UTF-8') ?>
                                </dd>

                                <dt class="col-sm-5 text-muted">Courriel :</dt>
                                <dd class="col-sm-7">
                                    <a href="mailto:<?= htmlspecialchars($devis['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($devis['email'] ?? 'Non renseigné', ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </dd>

                                <dt class="col-sm-5 text-muted">Téléphone :</dt>
                                <dd class="col-sm-7">
                                    <?= htmlspecialchars($devis['phone'] ?? 'Non renseigné', ENT_QUOTES, 'UTF-8') ?>
                                </dd>

                                <dt class="col-sm-5 text-muted">Type de projet :</dt>
                                <dd class="col-sm-7">
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($devis['event_type'] ?? 'Autre', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </dd>

                                <dt class="col-sm-5 text-muted">Date projetée :</dt>
                                <dd class="col-sm-7">
                                    <?= !empty($devis['event_date']) ? date('d/m/Y', strtotime($devis['event_date'])) : 'À déterminer' ?>
                                </dd>

                                <dt class="col-sm-5 text-muted">Lieu envisagé :</dt>
                                <dd class="col-sm-7">
                                    <?= htmlspecialchars($devis['location'] ?? 'Non précisé', ENT_QUOTES, 'UTF-8') ?>
                                </dd>

                                <dt class="col-sm-5 text-muted">Participants :</dt>
                                <dd class="col-sm-7">
                                    <?= !empty($devis['estimated_participants']) ? number_format((int)$devis['estimated_participants'], 0, ',', ' ') . ' pers.' : 'Non précisé' ?>
                                </dd>

                                <dt class="col-sm-5 text-muted">Budget indicatif :</dt>
                                <dd class="col-sm-7 fw-bold text-success">
                                    <?= !empty($devis['budget']) ? number_format((float)$devis['budget'], 2, ',', ' ') . ' €' : 'Non précisé' ?>
                                </dd>
                            </dl>

                            <hr class="my-3 text-muted opacity-25">

                            <div>
                                <h3 class="h6 fw-bold text-muted small mb-2">Description & Attentes du client :</h3>
                                <div class="bg-light p-3 rounded-2 text-dark small" style="white-space: pre-line; line-height: 1.5;">
                                    <?= htmlspecialchars($devis['description'] ?? 'Aucun descriptif fourni.', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ----------------------------------------------------------- -->
                <!-- COLONNE DROITE : LIGNES COMMERCIALES & CONSOLIDATION         -->
                <!-- ----------------------------------------------------------- -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white border-bottom border-light py-3 d-flex justify-content-between align-items-center">
                            <h2 class="h6 fw-bold mb-0 text-dark">
                                <i class="bi bi-list-check text-primary me-2" aria-hidden="true"></i>Prestations du devis
                            </h2>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                <?= count($prestations) ?> ligne(s)
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" aria-label="Détail des prestations du devis">
                                    <thead class="table-light">
                                    <tr>
                                        <th scope="col" class="py-2 px-3">Description de la prestation</th>
                                        <th scope="col" class="py-2 px-3 text-end" style="width: 140px;">Montant HT</th>
                                        <th scope="col" class="py-2 px-3 text-center" style="width: 80px;">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($prestations)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted small">
                                                <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                                                Aucune prestation n'a encore été ajoutée à cette proposition.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($prestations as $prest): ?>
                                            <tr>
                                                <td class="py-2 px-3">
                                                    <span class="fw-medium text-dark">
                                                        <?= htmlspecialchars($prest['libelle'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td class="py-2 px-3 text-end fw-bold font-monospace">
                                                    <?= number_format((float)$prest['montant_ht'], 2, ',', ' ') ?> €
                                                </td>
                                                <td class="py-2 px-3 text-center">
                                                    <?php if (strtolower($devis['status'] ?? '') !== 'accepté'): ?>
                                                        <form action="index.php?action=delete_prestation" method="POST" class="d-inline"
                                                              onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette prestation ?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="prestation_id" value="<?= (int)$prest['id'] ?>">
                                                            <input type="hidden" name="devis_id" value="<?= (int)$devis['id_devis'] ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm p-1" title="Supprimer la prestation">
                                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge text-bg-light border text-muted">Verrouillé</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                    <tfoot class="border-top-2">
                                    <tr class="table-light">
                                        <td class="text-end fw-bold py-2 px-3">Total Hors Taxes (HT) :</td>
                                        <td class="text-end fw-bold font-monospace py-2 px-3">
                                            <?= number_format($totalHT, 2, ',', ' ') ?> €
                                        </td>
                                        <td></td>
                                    </tr>
                                    <tr class="table-light">
                                        <td class="text-end text-muted small py-1 px-3">TVA Collectée (20 %) :</td>
                                        <td class="text-end font-monospace text-muted small py-1 px-3">
                                            <?= number_format($totalTVA, 2, ',', ' ') ?> €
                                        </td>
                                        <td></td>
                                    </tr>
                                    <tr class="table-primary fw-bold fs-6">
                                        <td class="text-end py-2 px-3" style="color: #0F172A;">Total Toutes Taxes Comprises (TTC) :</td>
                                        <td class="text-end font-monospace py-2 px-3 text-primary">
                                            <?= number_format($totalTTC, 2, ',', ' ') ?> €
                                        </td>
                                        <td></td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Formulaire d'ajout de prestation -->
                    <?php if (strtolower($devis['status'] ?? '') !== 'accepté'): ?>
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-white border-bottom border-light py-3">
                                <h3 class="h6 fw-bold mb-0 text-dark">
                                    <i class="bi bi-plus-circle text-success me-2" aria-hidden="true"></i>Ajouter une ligne de prestation
                                </h3>
                            </div>
                            <div class="card-body">
                                <form action="index.php?action=add_prestation" method="POST" class="row g-3">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="devis_id" value="<?= (int)$devis['id_devis'] ?>">

                                    <div class="col-md-7">
                                        <label for="libelle" class="form-label small fw-bold text-muted">Désignation / Intitulé *</label>
                                        <input type="text" class="form-control" id="libelle" name="libelle"
                                               placeholder="Ex: Scénographie & Éclairage architectural" required aria-required="true">
                                    </div>

                                    <div class="col-md-5">
                                        <label for="montant_ht" class="form-label small fw-bold text-muted">Montant HT (€) *</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" class="form-control" id="montant_ht" name="montant_ht"
                                                   placeholder="0.00" required aria-required="true">
                                            <span class="input-group-text bg-light">€</span>
                                        </div>
                                    </div>

                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-primary px-3 shadow-sm">
                                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Ajouter au devis
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </main>
    </div>
</div>
