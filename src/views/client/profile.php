<?php
/**
 * Vue : Profil du client et gestion RGPD
 *
 * @package    InnovEventsManager
 * @subpackage Views/Client
 */
$pageTitle = "Mon Profil - Innov'Events";
require __DIR__ . '/../partials/header.php';
?>

    <div class="container my-5 py-4">
        <div class="row mb-5">
            <div class="col-12">
                <a href="index.php?action=client_dashboard" class="text-decoration-none small text-muted opacity-75 mb-3 d-inline-block">← Retour au tableau de bord</a>
                <h1 class="fw-bold text-dark tracking-tight">Mon Profil</h1>
                <p class="text-muted">Gérez vos informations personnelles et vos paramètres de confidentialité.</p>
            </div>
        </div>

        <?php if (isset($_SESSION['client_error'])): ?>
            <div class="alert alert-danger mb-4">
                <?= htmlspecialchars($_SESSION['client_error'], ENT_QUOTES, 'UTF-8'); ?>
                <?php unset($_SESSION['client_error']); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="card-title fw-bold mb-0 text-dark">Informations personnelles</h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="mb-3">
                                <label class="form-label text-muted small fw-bold">Nom complet</label>
                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small fw-bold">Adresse Email (Login)</label>
                                <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($clientEmail, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-3 border-danger h-100">
                    <div class="card-header bg-danger text-white py-3">
                        <h5 class="card-title fw-bold mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Zone de danger</h5>
                    </div>
                    <div class="card-body bg-danger-subtle d-flex flex-column justify-content-center text-center p-4">
                        <p class="text-danger-emphasis fw-medium small mb-4">
                            Conformément au RGPD (Règlement Général sur la Protection des Données), vous disposez d'un droit d'effacement de vos données.
                            <strong>Attention, cette action est irréversible.</strong> Vos devis et événements seront détruits.
                        </p>
                        <form action="index.php?action=delete_account" method="POST">
                            <button type="submit" class="btn btn-danger fw-bold w-100 py-2 shadow-sm" onclick="return confirm('Êtes-vous absolument certain(e) de vouloir supprimer définitivement votre compte et l\'intégralité de vos données ? Cette action est immédiate et irréversible.');">
                                <i class="bi bi-trash3-fill me-2"></i>SUPPRIMER MON COMPTE
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/../partials/footer.php'; ?>