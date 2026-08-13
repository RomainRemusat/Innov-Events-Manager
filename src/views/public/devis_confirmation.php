<?php
/**
 * Vue : Confirmation d'envoi de demande de devis
 *
 * Affichage conditionnel (Succès ou Échec) suite à la soumission du formulaire public.
 *
 * @package    InnovEventsManager
 * @subpackage Views/Public
 * @var bool   $isSuccess Booléen injecté par le contrôleur indiquant l'état de la transaction.
 */
?>

<main class="container my-5 py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">

            <?php if ($isSuccess): ?>
                <!-- BLOC SUCCÈS -->
                <div class="card border-0 shadow-sm p-4 pt-5 divider-fine">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success mx-auto mb-4 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                        <i class="bi bi-check2-circle display-4"></i>
                    </div>
                    <h2 class="fw-bold text-dark mb-3">Demande reçue !</h2>
                    <p class="text-muted lh-base mb-4">
                        Merci pour votre confiance. Chloé vient de recevoir vos informations et étudie déjà la faisabilité de votre projet. Vous serez recontacté sous 48 heures ouvrées.
                    </p>
                    <div class="pt-2">
                        <a href="index.php" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-house me-2"></i>Retour à l'accueil</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- BLOC ERREUR -->
                <div class="card border-0 shadow-sm p-4 pt-5 divider-fine">
                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger mx-auto mb-4 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                        <i class="bi bi-exclamation-triangle display-4"></i>
                    </div>
                    <h2 class="fw-bold text-dark mb-3">Une erreur est survenue</h2>
                    <p class="text-muted lh-base mb-4">
                        Nos services techniques rencontrent actuellement une surcharge. Votre demande n'a pas pu être sauvegardée. Veuillez réitérer l'opération d'envoi.
                    </p>
                    <div class="pt-2">
                        <a href="index.php?action=devis" class="btn btn-light border px-4 text-dark shadow-sm"><i class="bi bi-arrow-left me-2"></i>Revenir au formulaire</a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>