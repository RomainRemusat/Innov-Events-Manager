<?php
/**
 * Vue : Formulaire de demande de réinitialisation de mot de passe
 *
 * S'inscrit dans le parcours de récupération de compte sécurisé.
 * Respecte les normes RGAA (labels explicites, attributs ARIA).
 *
 * @package    InnovEventsManager
 * @subpackage Views/Public
 * @author     Romain Remusat
 */

$pageTitle = "Mot de passe oublié - Innov'Events Manager";
require __DIR__ . '/../partials/header.php';
?>

    <div class="container my-5 py-5">
        <div class="row justify-content-center my-4">
            <div class="col-md-6 col-lg-5">
                <div class="text-center mb-4">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="bi bi-key-fill fs-2" aria-hidden="true"></i>
                    </div>
                    <h2 class="fw-bold text-dark tracking-tight">Mot de passe oublié ?</h2>
                    <p class="text-muted small">Saisissez l'adresse e-mail associée à votre compte. Si elle existe dans notre base, nous vous enverrons un mot de passe temporaire.</p>
                </div>

                <div class="pt-4 border-top">
                    <!-- Affichage des messages de retour (Anti-énumération) -->
                    <?php if (isset($_SESSION['auth_message'])): ?>
                        <div class="alert alert-info text-center small mb-4" role="alert">
                            <i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i>
                            <?= htmlspecialchars($_SESSION['auth_message'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php unset($_SESSION['auth_message']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Le formulaire pointe vers la route 'reset_password_request' -->
                    <form action="index.php?action=reset_password_request" method="POST" class="d-flex flex-column gap-3">
                        <div>
                            <label for="email" class="form-label text-muted small fw-bold">
                                ADRESSE E-MAIL <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   required
                                   aria-required="true"
                                   autocomplete="email"
                                   placeholder="votre@email.com">
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold text-white" style="background-color: #3b82f6; border: none;">
                                Envoyer la demande
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <a href="index.php?action=login" class="text-decoration-none small fw-semibold" style="color: #3b82f6;">
                            ← Retour à la page de connexion
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/../partials/footer.php'; ?>