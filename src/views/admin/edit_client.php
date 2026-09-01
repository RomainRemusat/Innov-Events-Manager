<?php

/**
 * Vue : Formulaire de modification d'un client (Back-Office)
 *
 * @var array $client Données du client à éditer (issues de la table users)
 */
?>
<div class="container-fluid bg-light min-vh-100 py-4">

        <div class="d-flex justify-content-betw een align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-dark mb-1">
                    <i class="fa-solid fa-user-pen text-primary me-2" aria-hidden="true"></i>Modifier le client
                </h1>
                <p class="text-muted small mb-0">Mise à jour des coordonnées
                    de <?= htmlspecialchars($client['firstname'] . ' ' . $client['lastname'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <!-- Bouton de retour vers la fiche détaillée du client -->
            <a href="index.php?action=view_client&id=<?= (int)$client['id'] ?>"
               class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Retour au dossier
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-4">

                        <!-- Le formulaire pointe vers une future route de traitement POST -->
                        <form action="index.php?action=update_client" method="POST">

                            <!-- Sécurité AT1 : Jeton Anti-CSRF et ID caché -->
                            <input type="hidden" name="csrf_token"
                                   value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="firstname" class="form-label small fw-bold text-muted">Prénom *</label>
                                    <input type="text" class="form-control" id="firstname" name="firstname"
                                           value="<?= htmlspecialchars($client['firstname'], ENT_QUOTES, 'UTF-8') ?>"
                                           required aria-required="true">
                                </div>
                                <div class="col-md-6">
                                    <label for="lastname" class="form-label small fw-bold text-muted">Nom *</label>
                                    <input type="text" class="form-control" id="lastname" name="lastname"
                                           value="<?= htmlspecialchars($client['lastname'], ENT_QUOTES, 'UTF-8') ?>"
                                           required aria-required="true">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="email" class="form-label small fw-bold text-muted">Adresse Email
                                    (Identifiant de connexion) *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars($client['email'], ENT_QUOTES, 'UTF-8') ?>" required
                                       aria-required="true">
                            </div>

                            <hr class="text-muted opacity-25 mb-4">

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary px-4 fw-bold">
                                    <i class="fa-solid fa-save me-2" aria-hidden="true"></i>Enregistrer les
                                    modifications
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>