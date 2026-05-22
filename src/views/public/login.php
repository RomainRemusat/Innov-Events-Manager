<?php
/**
 * Vue : Formulaire d'Authentification (Login) - Version Modulaire Épurée
 *
 * Ce fichier implémente l'interface de connexion sécurisée pour les utilisateurs
 * de la plateforme (Back-Office de Chloé). Alignée sur les directives d'élégance
 * et de minimalisme de la charte graphique, elle utilise le mécanisme des composants
 * (Partials) pour l'en-tête et le pied de page afin d'assurer l'homogénéité du design.
 *
 * Sécurité et Ergonomie :
 * - Chiffrement des flux : Transmission des données sensibles via la méthode HTTP POST.
 * - Attributs d'accessibilité (RGAA) : Labels explicites liés aux contrôles correspondants.
 * - Auto-complétion native activée pour fluidifier l'expérience utilisateur (UX).
 *
 * @package    InnovEventsManager
 * @subpackage Views/Public
 * @author     Romain Remusat
 * @version    2.0.0
 */

// Définition du titre de l'onglet de navigation pour le composant d'en-tête global
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

                <div class="pt-4 divider-fine">

                    <form action="index.php?action=login" method="POST" class="d-flex flex-column gap-4">

                        <div>
                            <label for="email" class="form-label label-minimal mb-2">Adresse Email</label>
                            <input type="email" class="form-control-minimal" id="email" name="email" required placeholder="chloe@innovevents.fr" autocomplete="email">
                        </div>

                        <div>
                            <label for="password" class="form-label label-minimal mb-2">Mot de passe</label>
                            <input type="password" class="form-control-minimal" id="password" name="password" required placeholder="••••••••" autocomplete="current-password">
                        </div>

                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary-custom w-100">Se connecter</button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
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