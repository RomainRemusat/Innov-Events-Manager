<?php
/**
 * Vue : Formulaire d'Authentification (Login) - Version Modulaire Épurée
 *
 * Ce fichier implémente l'interface de connexion sécurisée pour les utilisateurs
 * de la plateforme (Back-Office de Chloé, collaborateurs et clients corporate)[cite: 93, 261].
 *
 * Sécurité et Accessibilité (RGAA v4) :
 * - Chiffrement des flux : Transmission via la méthode HTTP POST[cite: 183].
 * - Attributs ARIA : Notification d'obligation sémantique et rôles d'alerte.
 * - Auto-complétion native : Optimisation de l'UX et réduction de la charge cognitive[cite: 506].
 * - Gestion défensive : Affichage dynamique et sécurisé (XSS protection) des erreurs.
 *
 * @package    InnovEventsManager
 * @subpackage Views/Public
 * @author     Romain Remusat
 * @version    2.1.0
 */

// Configuration du titre de la page pour le composant d'en-tête dynamique
$pageTitle = "Connexion - Innov'Events Manager";

// Architecture Modulaire : Chargement de l'en-tête global et de la barre de navigation
require __DIR__ . '/../partials/header.php';
?>

    <div class="container my-5 py-5">
        <div class="row justify-content-center my-4">
            <div class="col-md-6 col-lg-4">

                <div class="text-center mb-4">
                    <h2 class="fw-bold text-dark tracking-tight">Espace Gestion</h2>
                    <p class="text-muted small">Accédez à votre console d'administration sécurisée.</p>
                </div>

                <div class="pt-4 border-top">

                    <?php if (isset($_SESSION['login_error'])): ?>
                        <div class="alert alert-danger text-center small mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                            <?= htmlspecialchars($_SESSION['login_error'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php unset($_SESSION['login_error']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="index.php?action=login" method="POST" class="d-flex flex-column gap-4">

                        <div>
                            <label for="email" class="form-label label-minimal mb-2">
                                Adresse Email <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="email"
                                   class="form-control-minimal"
                                   id="email"
                                   name="email"
                                   required
                                   aria-required="true"
                                   placeholder="chloe@innovevents.fr"
                                   autocomplete="email">
                        </div>

                        <div>
                            <label for="password" class="form-label label-minimal mb-2">
                                Mot de passe <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="password"
                                   class="form-control-minimal mb-1"
                                   id="password"
                                   name="password"
                                   required
                                   aria-required="true"
                                   placeholder="••••••••"
                                   autocomplete="current-password">
                            <a href="index.php?action=forgot_password" class="small mt-4 text-decoration-none fw-semibold" style="color: #3b82f6;">Mot de passe oublié ?</a>

                        </div>

                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold text-white" style="background-color: #3b82f6; border: none;">
                                Se connecter
                            </button>
                        </div>


                    </form>

                    <div class="text-center mt-4 d-flex flex-column gap-2">
                        <p class="small text-muted mb-1">
                            Pas encore de compte ?
                            <a href="index.php?action=show_register" class="text-primary text-decoration-none fw-semibold" style="color: #3b82f6 !important;">
                                Créez un compte ici
                            </a>
                        </p>
                        <a href="index.php" class="text-decoration-none small text-muted opacity-75">← Retour à l'accueil public</a>
                    </div>
                </div>

            </div>
        </div>
    </div>

<?php
// Architecture Modulaire : Chargement du pied de page global (Fermeture des structures HTML)
require __DIR__ . '/../partials/footer.php';
?>