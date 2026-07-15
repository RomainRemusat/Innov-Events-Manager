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
 * @version    2.0.0
 */
?>
<footer class="bg-dark text-white-50 py-5">
    <div class="container py-3">
        <div class="row g-4">

            <div class="col-md-4">
                <h5 class="text-white fw-bold mb-3"><i class="bi bi-layers-half text-primary me-2"></i>INNOV'EVENTS</h5>
                <p class="">L'art de créer des moments d'exception pour vos ambitions professionnelles et privées</p>
            </div>

            <div class="col-md-2 offset-md-1">
                <h5 class="text-white mb-3 small text-uppercase fw-bold">Navigation</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="index.php" class="text-white-50 text-decoration-none">Accueil</a></li>
                    <li class="mb-2"><a href="index.php#services" class="text-white-50 text-decoration-none">Nos Services</a></li>
                    <li class="mb-2"><a href="index.php?action=login" class="text-white-50 text-decoration-none">Espace Gestion</a></li>
                </ul>
            </div>

            <div class="col-md-5">
                <h5 class="text-white mb-3 small text-uppercase fw-bold">Contact Pro</h5>
                <p class="small mb-1"><i class="bi bi-geo-alt me-2 text-primary"></i> 15 Rue de l'Innovation, 75000 Paris.</p>
                <p class="small mb-1"><i class="bi bi-telephone me-2 text-primary"></i> 01 23 45 67 89</p>
                <p class="small"><i class="bi bi-envelope me-2 text-primary"></i> contact@innovevents.fr</p>
            </div>
        </div>

        <hr class="my-4 border-secondary opacity-25">

        <div class="row small">
            <div class="col-md-6 text-center text-md-start">
                &copy; 2026 Innov'Events Manager. Tous droits réservés.
            </div>
            <div class="col-md-6 text-center text-md-end">
                <a href="#" class="text-white-50 text-decoration-none me-3">Mentions Légales</a> | <a href="#" class="text-white-50 text-decoration-none ms-3">RGPD</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>