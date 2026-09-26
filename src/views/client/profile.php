<?php
/**
 * Vue : Profil du client et gestion RGPD
 *
 * @package    InnovEventsManager
 * @subpackage Views/Client
 * @var array<string, string> $profile Coordonnées du compte ou dernières saisies valides.
 */
$pageTitle = "Mon Profil - Innov'Events";
require __DIR__ . '/../partials/header.php';
?>

    <main class="container my-5 py-4">
        <div class="row mb-5">
            <div class="col-12">
                <a href="index.php?action=client_dashboard" class="text-decoration-none small text-muted opacity-75 mb-3 d-inline-block">← Retour au tableau de bord</a>
                <h1 class="fw-bold text-dark tracking-tight">Mon Profil</h1>
                <p class="text-muted">Gérez vos informations personnelles et vos paramètres de confidentialité.</p>
            </div>
        </div>

        <?php if (isset($_SESSION['client_error'])): ?>
            <div class="alert alert-danger mb-4" role="alert">
                <?= htmlspecialchars($_SESSION['client_error'], ENT_QUOTES, 'UTF-8'); ?>
                <?php unset($_SESSION['client_error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['client_success'])): ?>
            <div class="alert alert-success mb-4" role="status">
                <?= htmlspecialchars($_SESSION['client_success'], ENT_QUOTES, 'UTF-8') ?>
                <?php unset($_SESSION['client_success']); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h2 class="card-title h5 fw-bold mb-0 text-dark">Informations personnelles</h2>
                    </div>
                    <div class="card-body">
                        <form action="index.php?action=client_update_profile" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mb-3">
                                <label for="firstname" class="form-label text-muted small fw-bold">Prénom *</label>
                                <input type="text" id="firstname" name="firstname" class="form-control" autocomplete="given-name" maxlength="100" required value="<?= htmlspecialchars($profile['firstname'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="lastname" class="form-label text-muted small fw-bold">Nom *</label>
                                <input type="text" id="lastname" name="lastname" class="form-control" autocomplete="family-name" maxlength="100" required value="<?= htmlspecialchars($profile['lastname'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label text-muted small fw-bold">Pseudo</label>
                                <input type="text" id="username" class="form-control" readonly value="<?= htmlspecialchars($profile['username'] ?: 'Non renseigné', ENT_QUOTES, 'UTF-8') ?>">
                                <p class="form-text">Le pseudo a été choisi lors de l’inscription.</p>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label text-muted small fw-bold">Adresse email de connexion *</label>
                                <input type="email" id="email" name="email" class="form-control" autocomplete="email" maxlength="255" required value="<?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="current_password" class="form-label text-muted small fw-bold">Mot de passe actuel</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password" aria-describedby="password_help">
                                <p id="password_help" class="form-text">Nécessaire uniquement pour modifier votre adresse email de connexion.</p>
                            </div>
                            <button type="submit" class="btn btn-primary">Enregistrer mes informations</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-3 border-danger h-100">
                    <div class="card-header bg-danger text-white py-3">
                        <h2 class="card-title h5 fw-bold mb-0"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>Zone de danger</h2>
                    </div>
                    <div class="card-body bg-danger-subtle d-flex flex-column justify-content-center text-center p-4">
                        <p class="text-danger-emphasis fw-medium small mb-4">
                            Conformément au RGPD (Règlement Général sur la Protection des Données), vous disposez d'un droit d'effacement de vos données.
                            <strong>Attention, cette action est irréversible.</strong> Votre compte, ses demandes, devis, événements et journaux associés seront supprimés, ainsi que leurs fichiers non partagés.
                            Les données de société et les fichiers utilisés par d'autres clients sont conservés.
                        </p>
                        <form action="index.php?action=client_delete_account" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-check text-start mb-3">
                                <input class="form-check-input" type="checkbox" id="confirm-delete-account" name="confirm_delete" value="1" required>
                                <label class="form-check-label small" for="confirm-delete-account">Je confirme la suppression définitive de mon compte et des données décrites ci-dessus.</label>
                            </div>
                            <button type="submit" class="btn btn-danger fw-bold w-100 py-2 shadow-sm">
                                <i class="bi bi-trash3-fill me-2"></i>SUPPRIMER MON COMPTE
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require __DIR__ . '/../partials/footer.php'; ?>
