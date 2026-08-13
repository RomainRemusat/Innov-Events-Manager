<?php
/**
 * Vue : Forcer le changement de mot de passe
 * Cette page s'affiche uniquement si l'utilisateur tente de se connecter
 * avec un mot de passe temporaire (must_change_password = 1).
 */
$pageTitle = "Mise à jour de sécurité - Innov'Events";
require __DIR__ . '/../partials/header.php';
?>

    <div class="container my-5 py-5">
        <div class="row justify-content-center my-4">
            <div class="col-md-6 col-lg-5">
                <div class="text-center mb-4">
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="bi bi-shield-lock-fill fs-2"></i>
                    </div>
                    <h2 class="fw-bold text-dark tracking-tight">Sécurité de votre compte</h2>
                    <p class="text-muted small">Vous utilisez actuellement un mot de passe temporaire. Conformément à notre politique de sécurité, vous devez impérativement définir un nouveau mot de passe personnel pour accéder à votre espace.</p>
                </div>

                <div class="pt-4 border-top">
                    <?php if (isset($_SESSION['auth_message'])): ?>
                        <div class="alert alert-info text-center small mb-4" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['auth_message'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php unset($_SESSION['auth_message']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['auth_error'])): ?>
                        <div class="alert alert-danger text-center small mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['auth_error'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php unset($_SESSION['auth_error']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="index.php?action=update_forced_password" method="POST" class="d-flex flex-column gap-3">
                        <div>
                            <label for="new_password" class="form-label text-muted small fw-bold">NOUVEAU MOT DE PASSE <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text text-muted" style="font-size: 0.75rem;">
                                Doit contenir au moins 8 caractères, dont 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.
                            </div>
                        </div>

                        <div>
                            <label for="confirm_password" class="form-label text-muted small fw-bold">CONFIRMER LE MOT DE PASSE <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold text-white" style="background-color: #3b82f6; border: none;">
                                Mettre à jour et continuer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/../partials/footer.php'; ?>