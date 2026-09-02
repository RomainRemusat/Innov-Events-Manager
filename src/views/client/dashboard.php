<?php
/**
 * Vue : Tableau de bord de l'Espace Client
 *
 * Interface principale réservée aux clients B2B authentifiés.
 * Elle permet de suivre l'avancement des projets événementiels, d'examiner
 * et télécharger les propositions commerciales au format PDF, et d'exécuter
 * les arbitrages décisionnels (acceptation, demande de modification, refus).
 *
 * Exigences respectées (ECF) :
 * - AT1 : Interface responsive, contrôles CSRF, sécurisation XSS et gestion des identifiants uniques.
 * - AT2 : Gestion du cycle de vie des devis et des demandes de modification.
 *
 * @package    InnovEventsManager
 * @subpackage Views\Client
 * @author     Innov'Events
 * @version    2.2.0
 *
 * @var string $clientName Nom/Prénom ou raison sociale du client connecté.
 * @var array  $myQuotes   Liste des devis et projets rattachés au compte client.
 */

// -----------------------------------------------------------------------------
// CONFIGURATION DE LA PAGE ET EN-TÊTE
// -----------------------------------------------------------------------------
$pageTitle = "Mon Espace Client - Innov'Events";
require __DIR__ . '/../partials/header.php';
?>

    <div class="container my-5 py-4">

        <!-- =================================================================== -->
        <!-- EN-TÊTE DE BIENVENUE ET IDENTIFICATION DE L'ESPACE                  -->
        <!-- =================================================================== -->
        <div class="row mb-5">
            <div class="col-10">
                <h1 class="fw-bold text-dark">
                    Bonjour, <?= htmlspecialchars($clientName ?? 'Client', ENT_QUOTES, 'UTF-8'); ?> 👋
                </h1>
                <p class="text-muted">Bienvenue dans votre espace personnel. Suivez l'avancement de vos projets événementiels.</p>
            </div>
            <div class="col-2 text-end align-self-center">
                <span class="badge bg-secondary px-3 py-2 text-uppercase fw-semibold" style="font-size: 0.8rem;">Espace Client</span>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- GESTION DES MESSAGES FLASH (FEEDBACK UTILISATEUR)                   -->
        <!-- =================================================================== -->
        <?php if (isset($_SESSION['client_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>
                <?= htmlspecialchars($_SESSION['client_success'], ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                <?php unset($_SESSION['client_success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['client_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                <?= htmlspecialchars($_SESSION['client_error'], ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                <?php unset($_SESSION['client_error']); ?>
            </div>
        <?php endif; ?>

        <!-- =================================================================== -->
        <!-- TABLEAU DE BORD : DEVIS ET PROJETS ÉVÉNEMENTIELS                    -->
        <!-- =================================================================== -->
        <div class="row g-4">
            <div class="col-lg-12">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white border-bottom py-3">
                        <h2 class="card-title h5 fw-bold mb-0 text-dark">
                            <i class="bi bi-folder-check text-primary me-2" aria-hidden="true"></i>Mes Devis & Événements
                        </h2>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($myQuotes)): ?>
                            <!-- État vide : Aucune donnée enregistrée -->
                            <div class="text-center py-5">
                                <i class="bi bi-chat-left-text text-muted fs-1" aria-hidden="true"></i>
                                <p class="mt-3 text-muted mb-0">Vous n'avez pas encore de devis ou de dossier enregistré.</p>
                            </div>
                        <?php else: ?>
                            <!-- Listing tabulaire des propositions commerciales -->
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" aria-label="Liste de vos devis">
                                    <thead class="table-light text-uppercase fs-7">
                                    <tr>
                                        <th scope="col" class="ps-4">N° Dossier</th>
                                        <th scope="col">Type d'Événement</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Statut Devis</th>
                                        <th scope="col" class="text-end pe-4">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($myQuotes as $quote): ?>
                                        <?php
                                        // Normalisation du statut et résolution de l'identifiant unique
                                        $st = strtolower($quote['status'] ?? 'brouillon');
                                        $quoteUniqueId = (int)($quote['id_devis'] ?? $quote['prospect_id'] ?? $quote['id'] ?? 0);
                                        ?>
                                        <tr>
                                            <!-- Identifiant unique du dossier -->
                                            <td class="ps-4 fw-semibold text-secondary">
                                                #<?= $quoteUniqueId; ?>
                                            </td>

                                            <!-- Libellé de l'événement et entreprise -->
                                            <td>
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($quote['event_type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($quote['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>

                                            <!-- Date d'émission / création -->
                                            <td>
                                                <?= !empty($quote['created_at']) ? date('d/m/Y', strtotime($quote['created_at'])) : date('d/m/Y'); ?>
                                            </td>

                                            <!-- Badges sémantiques indiquant l'état du dossier -->
                                            <td>
                                                <?php if (in_array($st, ['étude côté client', 'devis envoyé'], true)): ?>
                                                    <span class="badge text-bg-info px-2.5 py-1.5 rounded-pill">
                                                    <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i> Proposition reçue
                                                </span>
                                                <?php elseif ($st === 'accepté'): ?>
                                                    <span class="badge text-bg-success px-2.5 py-1.5 rounded-pill">
                                                    <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> Validé
                                                </span>
                                                <?php elseif ($st === 'modification'): ?>
                                                    <span class="badge text-bg-warning px-2.5 py-1.5 rounded-pill">
                                                    <i class="bi bi-pencil me-1" aria-hidden="true"></i> Modification demandée
                                                </span>
                                                <?php elseif ($st === 'refusé'): ?>
                                                    <span class="badge text-bg-danger px-2.5 py-1.5 rounded-pill">
                                                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i> Refusé
                                                </span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-secondary px-2.5 py-1.5 rounded-pill">
                                                    En préparation
                                                </span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Actions : Téléchargement PDF et Tunnel décisionnel -->
                                            <td class="text-end pe-4">

                                                <?php
                                                $pdfPhysicalPath = __DIR__ . '/../../storage/devis/' . ($quote['reference_pdf'] ?? '');
                                                $isPdfAvailable = !empty($quote['reference_pdf']) && $st !== 'brouillon' && file_exists($pdfPhysicalPath);
                                                ?>

                                                <?php if ($isPdfAvailable): ?>
                                                    <a href="index.php?action=download_pdf&file=<?= urlencode($quote['reference_pdf']); ?>"
                                                       class="btn btn-outline-primary btn-sm rounded-2 mb-1"
                                                       aria-label="Télécharger le PDF du devis #<?= $quoteUniqueId; ?>">
                                                        <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i> Télécharger (<?= number_format((float)($quote['montant_ht'] ?? 0), 2, ',', ' '); ?> € HT)
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Tunnel d'interaction réservé aux devis en attente d'arbitrage -->
                                                <?php if (in_array($st, ['étude côté client', 'devis envoyé'], true)): ?>
                                                    <div class="mt-2 d-flex justify-content-end gap-1">

                                                        <!-- Formulaire 1 : Acceptation ferme du devis -->
                                                        <form action="index.php?action=respond_to_quote" method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="devis_id" value="<?= $quoteUniqueId; ?>">
                                                            <button type="submit" name="quote_action" value="accept" class="btn btn-success btn-sm"
                                                                    onclick="return confirm('En acceptant ce devis, vous validez la prestation et engagez le projet. Confirmer ?');">
                                                                <i class="bi bi-check-lg" aria-hidden="true"></i> Accepter
                                                            </button>
                                                        </form>

                                                        <!-- Bouton d'affichage du formulaire de demande d'ajustement -->
                                                        <button type="button" class="btn btn-warning btn-sm"
                                                                data-bs-toggle="collapse"
                                                                data-bs-target="#modiffForm<?= $quoteUniqueId; ?>"
                                                                aria-expanded="false"
                                                                aria-controls="modiffForm<?= $quoteUniqueId; ?>">
                                                            <i class="bi bi-pencil" aria-hidden="true"></i> Modifier
                                                        </button>

                                                        <!-- Formulaire 2 : Refus du devis -->
                                                        <form action="index.php?action=respond_to_quote" method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="devis_id" value="<?= $quoteUniqueId; ?>">
                                                            <button type="submit" name="quote_action" value="reject" class="btn btn-danger btn-sm"
                                                                    onclick="return confirm('Confirmez-vous le refus définitif de ce devis ?');">
                                                                <i class="bi bi-x-lg" aria-hidden="true"></i> Refuser
                                                            </button>
                                                        </form>
                                                    </div>

                                                    <!-- Formulaire escamotable : Saisie explicite du motif de modification -->
                                                    <div class="collapse mt-2 text-start" id="modiffForm<?= $quoteUniqueId; ?>">
                                                        <form action="index.php?action=respond_to_quote" method="POST" class="bg-light p-3 rounded border shadow-sm">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="devis_id" value="<?= $quoteUniqueId; ?>">
                                                            <input type="hidden" name="quote_action" value="request_change">

                                                            <label for="change_reason_<?= $quoteUniqueId; ?>" class="form-label small fw-bold mb-1">Motif des modifications souhaitées :</label>
                                                            <textarea id="change_reason_<?= $quoteUniqueId; ?>" name="change_reason" class="form-control form-control-sm mb-2" rows="2" required placeholder="Précisez les prestations ou les dates à ajuster..."></textarea>

                                                            <button type="submit" class="btn btn-sm btn-warning w-100 fw-bold">
                                                                <i class="bi bi-send me-1" aria-hidden="true"></i> Transmettre la demande de modification
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>

                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
// -----------------------------------------------------------------------------
// FERMETURE DES STRUCTURES HTML VIA LES PARTIALS
// -----------------------------------------------------------------------------
require __DIR__ . '/../partials/footer.php';
?>