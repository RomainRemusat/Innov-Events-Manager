<?php
/**
 * Composant : Pied de page global (Footer partial)
 *
 * Ce fichier implémente la structure de fermeture HTML commune à l'ensemble
 * des vues publiques de la plateforme Innov'Events Manager. Conçu de manière
 * modulaire, il assure l'uniformité visuelle du bas de page, fournit des accès de
 * navigation secondaires, affiche les mentions légales et intègre les dépendances
 * JavaScript requises.
 *
 * Spécifications techniques et conformité :
 * - Grille adaptative Bootstrap 5 (Flexbox / multi-colonnes responsive).
 * - Accessibilité sémantique via la balise normative <footer>.
 * - Prise en compte réglementaire des exigences CNIL / RGPD via des liens dédiés.
 * - Centralisation du chargement des modules asynchrones (Bootstrap Bundle).
 *
 * @package    InnovEventsManager
 * @subpackage Views/Partials
 * @author     Romain Remusat
 * @version    2.1.0
 */
?>
<footer class="bg-dark text-white-50 py-5" role="contentinfo">
    <div class="container py-3">
        <div class="row g-4">

            <div class="col-md-4">
                <h5 class="text-white fw-bold mb-3">
                    <i class="bi bi-layers-half text-primary me-2" aria-hidden="true"></i>INNOV'EVENTS
                </h5>
                <p class="small">L'art de créer des moments d'exception pour vos ambitions professionnelles et privées.</p>
            </div>

            <div class="col-md-3 offset-md-1">
                <h5 class="text-white mb-3 small text-uppercase fw-bold">Navigation</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="index.php" class="text-white-50 text-decoration-none">Accueil</a>
                    </li>
                    <li class="mb-2">
                        <a href="index.php#services" class="text-white-50 text-decoration-none">Nos Services</a>
                    </li>

                    <li class="mb-2">
                        <a href="index.php?action=show_register" class="text-white-50 text-decoration-none fw-semibold text-info">Créer un compte</a>
                    </li>

                    <li class="mb-2">
                        <a href="index.php?action=login" class="text-white-50 text-decoration-none"></i>Espace Gestion</a>
                    </li>
                </ul>
            </div>

            <div class="col-md-4">
                <h5 class="text-white mb-3 small text-uppercase fw-bold">Contact Pro</h5>
                <address class="mb-0 text-white-50 fs-6 fst-normal">
                    <p class="small mb-2">
                        <i class="bi bi-geo-alt me-2 text-primary" aria-hidden="true"></i>
                        15 Rue de l'Innovation, 75000 Paris.
                    </p>
                    <p class="small mb-2">
                        <i class="bi bi-telephone me-2 text-primary" aria-hidden="true"></i>
                        <a href="tel:0123456789" class="text-white-50 text-decoration-none">01 23 45 67 89</a>
                    </p>
                    <p class="small">
                        <i class="bi bi-envelope me-2 text-primary" aria-hidden="true"></i>
                        <a href="mailto:contact@innovevents.fr" class="text-white-50 text-decoration-none">contact@innovevents.fr</a>
                    </p>
                </address>
            </div>
        </div>

        <hr class="my-4 border-secondary opacity-25">

        <div class="row small">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                &copy; 2026 Innov'Events Manager. Tous droits réservés.
            </div>
            <div class="col-md-6 text-center text-md-end">
                <a href="index.php?action=mentions_legales" class="text-white-50 text-decoration-none me-3">Mentions Légales</a>
                <span class="text-secondary" aria-hidden="true">|</span>
                <a href="index.php?action=politique_confidentialite" class="text-white-50 text-decoration-none ms-3">Politique de Confidentialité (RGPD)</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>