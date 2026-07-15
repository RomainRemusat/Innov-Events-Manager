<?php
/**
 * Vue : Formulaire d'Inscription (Création de compte client)
 *
 * Ce composant gère l'interface publique de création de compte pour les clients.
 * Il implémente une stratégie de validation multiniveau (Défense en profondeur) :
 * 1. Validation HTML5 sémantique (attributs de contraintes natifs).
 * 2. Validation JavaScript asynchrone en temps réel (optimisation de l'UX/UI via Bootstrap 5).
 * 3. Récupération des données mémorisées en session ($_SESSION['old_inputs']) en cas de rejet serveur.
 *
 * Normes appliquées : Respect strict des critères d'accessibilité numérique (RGAA v4).
 *
 * @package    InnovEventsManager
 * @subpackage Views/Public
 * @author     Romain Remusat
 * @version    1.2.0
 */

// Configuration dynamique du titre de la page pour le composant d'en-tête global
$pageTitle = "Création de compte - Innov'Events Manager";

// Chargement des partials d'en-tête et de navigation globale
require __DIR__ . '/../partials/header.php';

// Extraction et consommation immédiate des anciennes saisies mémorisées suite à un échec serveur
$old = $_SESSION['old_inputs'] ?? [];
unset($_SESSION['old_inputs']);
?>

    <div class="container my-5 py-5">
        <div class="row justify-content-center my-4">
            <div class="col-md-8 col-lg-5">

                <div class="text-center mb-4">
                    <h2 class="fw-bold text-dark tracking-tight">Rejoignez Innov'Events</h2>
                    <p class="text-muted small">Créez votre compte pour suivre vos devis et planifier vos futurs événements.</p>
                </div>

                <div class="pt-4 border-top">

                    <?php if (isset($_SESSION['register_error'])): ?>
                        <div class="alert alert-danger text-center small mb-4" role="alert">
                            <i class="bi bi-exclamation-octagon-fill me-2" aria-hidden="true"></i>
                            <?= htmlspecialchars($_SESSION['register_error'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php unset($_SESSION['register_error']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="index.php?action=register" method="POST" id="registerForm" class="d-flex flex-column gap-3" novalidate>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="firstname" class="form-label text-muted small fw-bold">PRÉNOM <span class="text-danger" aria-hidden="true">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="firstname"
                                       name="firstname"
                                       required
                                       aria-required="true"
                                       placeholder="Ex: Alice"
                                       autocomplete="given-name"
                                       value="<?= htmlspecialchars($old['firstname'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback">Le prénom est obligatoire.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="lastname" class="form-label text-muted small fw-bold">NOM <span class="text-danger" aria-hidden="true">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="lastname"
                                       name="lastname"
                                       required
                                       aria-required="true"
                                       placeholder="Ex: Vancort"
                                       autocomplete="family-name"
                                       value="<?= htmlspecialchars($old['lastname'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback">Le nom de famille est obligatoire.</div>
                            </div>
                        </div>

                        <div>
                            <label for="username" class="form-label text-muted small fw-bold">NOM D'UTILISATEUR (PSEUDO) <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text"
                                   class="form-control"
                                   id="username"
                                   name="username"
                                   required
                                   aria-required="true"
                                   minlength="3"
                                   maxlength="20"
                                   pattern="^[a-zA-Z0-9_]+$"
                                   title="Le pseudo doit contenir entre 3 et 20 caractères (lettres, chiffres et underscore uniquement)."
                                   placeholder="Ex: alice_v"
                                   autocomplete="username"
                                   value="<?= htmlspecialchars($old['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="invalid-feedback">Le pseudo doit contenir entre 3 et 20 caractères alphanumériques.</div>
                        </div>

                        <div>
                            <label for="email" class="form-label text-muted small fw-bold">ADRESSE EMAIL (LOGIN) <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   required
                                   aria-required="true"
                                   placeholder="alice@luxe.com"
                                   autocomplete="email"
                                   aria-describedby="emailHelp">
                            <div id="emailHelp" class="form-text text-muted" style="font-size: 0.75rem;">Cette adresse servira d'identifiant de connexion.</div>
                            <div class="invalid-feedback">Veuillez saisir une adresse email valide.</div>
                        </div>

                        <div>
                            <label for="password" class="form-label text-muted small fw-bold">MOT DE PASSE <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="password"
                                   class="form-control"
                                   id="password"
                                   name="password"
                                   required
                                   aria-required="true"
                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$"
                                   title="Le mot de passe doit respecter l'intégralité des critères de complexité ci-dessous."
                                   placeholder="••••••••"
                                   autocomplete="new-password"
                                   aria-describedby="passwordHelp">

                            <div id="passwordHelp" class="form-text text-muted mt-2" style="font-size: 0.75rem;">
                                Le mot de passe doit contenir au minimum :
                                <ul class="ps-3 mb-0 mt-1">
                                    <li>8 caractères</li>
                                    <li>Une lettre majuscule</li>
                                    <li>Une lettre minuscule</li>
                                    <li>Un chiffre</li>
                                    <li>Un caractère spécial (ex: @, $, !, %, *, ?, &)</li>
                                </ul>
                            </div>
                            <div class="invalid-feedback">Le mot de passe ne respecte pas les critères de sécurité exigés.</div>
                        </div>

                        <div class="form-check my-2">
                            <input class="form-check-input" type="checkbox" id="rgpd_register" name="rgpd_consent" required aria-required="true">
                            <label class="form-check-label text-muted small" for="rgpd_register">
                                J'accepte que mes données personnelles soient collectées pour la création de mon espace client sécurisé.
                            </label>
                            <div class="invalid-feedback text-danger small mt-1" style="display:none;" id="rgpdFeedback">
                                Vous devez accepter la collecte de vos données pour valider votre inscription.
                            </div>
                        </div>

                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold text-white" style="background-color: #3b82f6; border: none;">
                                S'INSCRIRE
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="small text-muted">Déjà un compte ? <a href="index.php?action=login" class="text-primary text-decoration-none fw-semibold">Connectez-vous ici</a></p>
                        <a href="index.php" class="text-decoration-none small text-muted opacity-75">← Retour à l'accueil public</a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        /**
         * Module de Validation Évoluée Côté Client (Fail-Fast Logic)
         * * Intercepte les événements de saisie et de soumission du formulaire d'inscription
         * pour appliquer dynamiquement les pseudo-classes Bootstrap (.is-valid / .is-invalid).
         * Évite les allers-retours serveurs superflus tout en maintenant une UX réactive.
         */
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('registerForm');
            const inputs = form.querySelectorAll('input[required]');
            const passwordInput = document.getElementById('password');
            const rgpdCheckbox = document.getElementById('rgpd_register');
            const rgpdFeedback = document.getElementById('rgpdFeedback');

            // Expression régulière stricte de complexité de mot de passe (Miroir de la logique PHP)
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

            /**
             * Évalue la conformité d'un champ spécifique et lui affecte sa classe d'état visuel.
             * @param {HTMLInputElement} input
             */
            function validateField(input) {
                let isValid = true;

                if (input.type === 'checkbox') {
                    isValid = input.checked;
                    rgpdFeedback.style.display = isValid ? 'none' : 'block';
                } else if (input.id === 'password') {
                    isValid = passwordRegex.test(input.value);
                } else {
                    isValid = input.checkValidity();
                }

                if (isValid) {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                } else {
                    input.classList.remove('is-valid');
                    input.classList.add('is-invalid');
                }

                return isValid;
            }

            // 1. Validation en temps réel lors de la saisie (Sensation de fluidité pour l'utilisateur)
            inputs.forEach(input => {
                input.addEventListener('input', function () {
                    // Pour le mot de passe, on attend qu'il respecte la regex avant d'afficher l'état valide
                    if (input.id === 'password') {
                        if (passwordRegex.test(input.value)) {
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                        } else {
                            input.classList.remove('is-valid');
                        }
                    } else {
                        validateField(input);
                    }
                });

                // Validation stricte lorsque l'utilisateur quitte le champ focus (Blur Event)
                input.addEventListener('blur', function () {
                    validateField(input);
                });
            });

            // 2. Interception de la soumission globale du formulaire
            form.addEventListener('submit', function (event) {
                let isFormValid = true;

                // Évaluation séquentielle de chaque contrôle obligatoire
                inputs.forEach(input => {
                    const isFieldValid = validateField(input);
                    if (!isFieldValid) {
                        isFormValid = false;
                    }
                });

                // Clause de garde finale : blocage de la requête HTTP POST si une anomalie est détectée
                if (!isFormValid) {
                    event.preventDefault();
                    event.stopPropagation();

                    // Focus automatique sur le premier champ en erreur pour optimiser l'accessibilité
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
                }
            });
        });
    </script>

<?php
// Chargement du pied de page et fermeture des structures de scripts globales
require __DIR__ . '/../partials/footer.php';
?>