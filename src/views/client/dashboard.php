<?php
/**
 * Vue : Tableau de bord Espace Client
 *
 * Présente au client l'historique de ses demandes de devis, le statut
 * de traitement de ses dossiers et un accès direct au téléchargement de ses documents.
 *
 * @package    InnovEventsManager
 * @subpackage Views/Client
 * @author     Romain Remusat
 * @version    1.0.0
 */

// Configuration du titre de l'onglet de navigation
$pageTitle = "Mon Espace Client - Innov'Events";

// Chargement des partials d'en-tête et de navigation commune
require __DIR__ . '/../partials/header.php';
?>

    <div class="container my-5 py-4">
        <div class="row mb-5">
            <div class="col-10">
                <h1 class="fw-bold text-dark tracking-tight">Bonjour, <?= htmlspecialchars($clientName ?? 'Client', ENT_QUOTES, 'UTF-8'); ?> 👋</h1>
                <p class="text-muted">Bienvenue dans votre espace personnel. Suivez l'avancement de vos projets événementiels.</p>
            </div>
            <div class="col-2 text-end align-self-center">
                <span class="badge bg-secondary px-3 py-2 text-uppercase fw-semibold" style="font-size: 0.8rem;">Espace Client</span>
            </div>
        </div>

        <?php if (isset($_SESSION['client_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['client_success'], ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                <?php unset($_SESSION['client_success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['client_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['client_error'], ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                <?php unset($_SESSION['client_error']); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-12">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="card-title fw-bold mb-0 text-dark">
                            <i class="bi bi-folder-check text-primary me-2"></i>Mes Demandes de Devis & Événements
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($myQuotes)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-chat-left-text text-muted fs-1"></i>
                                <p class="mt-3 text-muted">Vous n'avez pas encore déposé de demande de devis.</p>
                                <a href="index.php?action=devis" class="btn btn-primary btn-sm mt-2">Faire une demande</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light text-uppercase fs-7 tracking-wider">
                                    <tr>
                                        <th class="ps-4">N° Dossier</th>
                                        <th>Type d'Événement</th>
                                        <th>Date de Demande</th>
                                        <th>Statut</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($myQuotes as $quote): ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold text-secondary">#<?= $quote['id']; ?></td>
                                            <td>
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($quote['event_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <br><small class="text-muted"><?= htmlspecialchars($quote['company_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                            <td><?= date('d/m/2026', strtotime($quote['created_at'])); ?></td>
                                            <td>
                                                <?php if ($quote['status'] === 'En cours d\'étude'): ?>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1.5 rounded-pill fw-medium">
                                                        <i class="bi bi-hourglass-split me-1"></i> En cours d'étude
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill fw-medium">
                                                        <i class="bi bi-check-circle-fill me-1"></i> Validé / Événement
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <?php if (!empty($quote['reference_pdf'])): ?>
                                                    <a href="index.php?action=download_pdf&file=<?= urlencode($quote['reference_pdf']); ?>" class="btn btn-outline-primary btn-sm rounded-2 mb-1 w-100">
                                                        <i class="bi bi-file-earmark-pdf me-1"></i> Devis (<?= number_format($quote['montant_ht'], 2, ',', ' '); ?> €)
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($quote['status'] === 'devis envoyé'): ?>
                                                    <form action="index.php?action=respond_to_quote" method="POST" class="d-flex gap-2 mt-1 justify-content-end">
                                                        <input type="hidden" name="prospect_id" value="<?= $quote['id']; ?>">

                                                        <button type="submit" name="quote_action" value="reject" class="btn btn-danger btn-sm rounded-2" title="Refuser ce devis" onclick="return confirm('Êtes-vous sûr de vouloir refuser ce devis ?');">
                                                            <i class="bi bi-x-circle"></i> Refuser
                                                        </button>

                                                        <button type="submit" name="quote_action" value="accept" class="btn btn-success btn-sm rounded-2" title="Accepter et signer ce devis" onclick="return confirm('En acceptant ce devis, vous vous engagez à poursuivre l\'événement avec Innov\'Events. Confirmer ?');">
                                                            <i class="bi bi-check2-circle"></i> Accepter
                                                        </button>
                                                    </form>
                                                <?php elseif ($quote['status'] === 'refusé'): ?>
                                                    <span class="badge bg-danger text-white rounded-2 px-2 py-1"><i class="bi bi-x"></i> Refusé par vous</span>
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
// Chargement du pied de page et fermeture des structures HTML
require __DIR__ . '/../partials/footer.php';
?>