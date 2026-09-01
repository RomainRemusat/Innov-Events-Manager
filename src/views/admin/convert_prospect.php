<?php
/**
 * Vue : Formulaire de Conversion (Prospect -> Client, Entreprise & Événement)
 *
 * Cette interface d'administration est l'étape centrale du workflow commercial (AT2).
 * Elle permet à Chloé de convertir un prospect en client B2B tout en qualifiant
 * l'adresse postale légale et les paramètres complets du projet événementiel.
 *
 * Normes appliquées :
 * - Sécurité (AT1) : Jeton Anti-CSRF et échappement strict (XSS) via ENT_QUOTES.
 * - Accessibilité (RGAA) : Labels explicites, structuration sémantique et attributs ARIA.
 * - UI/UX : Charte Slate Dark (#0F172A) et Bleu (#3B82F6).
 *
 * @package    InnovEventsManager
 * @subpackage Views/Admin
 * @author     Romain Remusat
 * @version    1.3.0
 *
 * @var array $prospect Données brutes du prospect récupérées depuis MySQL
 */
?>
<div class="container-fluid bg-light min-vh-100 py-4">
    <div class="container">

        <!-- =============================================================== -->
        <!-- EN-TÊTE DE LA PAGE ET NAVIGATION                                -->
        <!-- =============================================================== -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-dark mb-1">
                    <i class="bi bi-magic text-primary me-2" aria-hidden="true"></i>Convertir le Prospect
                </h1>
                <p class="text-muted small mb-0">Création du compte client B2B, enregistrement légal de la société et initialisation du projet.</p>
            </div>
            <a href="index.php?action=dashboard" class="btn btn-outline-secondary btn-sm" aria-label="Retour au tableau de bord">
                <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Retour au tableau de bord
            </a>
        </div>

        <!-- =============================================================== -->
        <!-- FORMULAIRE DE CONVERSION (POST MULTIPART)                       -->
        <!-- =============================================================== -->
        <form action="index.php?action=process_conversion" method="POST" enctype="multipart/form-data" class="row g-4">

            <!-- Jeton de sécurité Anti-CSRF (Validation AT1) -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <!-- ID caché du prospect pour la liaison SQL -->
            <input type="hidden" name="prospect_id" value="<?= (int)$prospect['id'] ?>">

            <!-- =========================================================== -->
            <!-- COLONNE GAUCHE : Informations Client & Société (B2B / 3NF)  -->
            <!-- =========================================================== -->
            <div class="col-lg-5">

                <!-- Bloc Interlocuteur Client -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom border-light py-3">
                        <h2 class="h6 fw-bold mb-0" style="color: #0F172A;">
                            <i class="bi bi-person-badge text-primary me-2" aria-hidden="true"></i>1. Interlocuteur Référent
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="contact_name" class="form-label small fw-bold text-muted">Nom & Prénom du Contact *</label>
                            <input type="text" class="form-control" id="contact_name" name="contact_name"
                                   value="<?= htmlspecialchars($prospect['contact_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required aria-required="true">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label small fw-bold text-muted">Email professionnel (Identifiant de connexion) *</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($prospect['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required aria-required="true">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label small fw-bold text-muted">Téléphone direct *</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?= htmlspecialchars($prospect['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required aria-required="true">
                        </div>

                        <div class="alert alert-info small mb-0 border-0">
                            <i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i>
                            Si ce contact n'a pas encore de compte utilisateur, un compte lui sera créé automatiquement et ses accès lui seront envoyés par courriel.
                        </div>
                    </div>
                </div>

                <!-- Bloc Structure Légale (Table companies) -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom border-light py-3">
                        <h2 class="h6 fw-bold mb-0" style="color: #0F172A;">
                            <i class="bi bi-building text-primary me-2" aria-hidden="true"></i>2. Structure Morale (Entreprise B2B)
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="company_name" class="form-label small fw-bold text-muted">Raison Sociale / Nom *</label>
                            <input type="text" class="form-control" id="company_name" name="company_name"
                                   value="<?= htmlspecialchars($prospect['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required aria-required="true">
                        </div>
                        <div class="mb-3">
                            <label for="siren" class="form-label small fw-bold text-muted">Numéro SIREN (9 chiffres)</label>
                            <input type="text" class="form-control" id="siren" name="siren" maxlength="9" placeholder="Ex: 123456789">
                            <div class="form-text small">Facultatif pour la qualification initiale.</div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label small fw-bold text-muted">Adresse postale du siège</label>
                            <input type="text" class="form-control" id="address" name="address" placeholder="Ex: 12 rue de la Paix">
                        </div>
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label for="postal_code" class="form-label small fw-bold text-muted">Code Postal</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" maxlength="10" placeholder="75002">
                            </div>
                            <div class="col-md-7 mb-3">
                                <label for="city" class="form-label small fw-bold text-muted">Ville</label>
                                <input type="text" class="form-control" id="city" name="city" placeholder="Paris">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- =========================================================== -->
            <!-- COLONNE DROITE : Paramétrage du Projet Événementiel         -->
            <!-- =========================================================== -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom border-light py-3">
                        <h2 class="h6 fw-bold mb-0" style="color: #0F172A;">
                            <i class="bi bi-calendar-star text-primary me-2" aria-hidden="true"></i>3. Paramétrage du Projet Événementiel
                        </h2>
                    </div>
                    <div class="card-body">

                        <div class="mb-3">
                            <label for="event_title" class="form-label small fw-bold text-muted">Nom du projet événementiel *</label>
                            <input type="text" class="form-control" id="event_title" name="event_title"
                                   value="<?= htmlspecialchars(($prospect['event_type'] ?? '') . ' - ' . ($prospect['company_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required aria-required="true">
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label small fw-bold text-muted">Date & Heure de début *</label>
                                <input type="datetime-local" class="form-control" id="start_date" name="start_date"
                                       value="<?= !empty($prospect['event_date']) ? htmlspecialchars($prospect['event_date'], ENT_QUOTES, 'UTF-8') . 'T08:00' : '' ?>" required aria-required="true">
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label small fw-bold text-muted">Date & Heure de fin</label>
                                <input type="datetime-local" class="form-control" id="end_date" name="end_date"
                                       value="<?= !empty($prospect['event_date']) ? htmlspecialchars($prospect['event_date'], ENT_QUOTES, 'UTF-8') . 'T20:00' : '' ?>">
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="location" class="form-label small fw-bold text-muted">Lieu d'exécution de l'événement *</label>
                                <input type="text" class="form-control" id="location" name="location"
                                       value="<?= htmlspecialchars($prospect['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="Ex: Palais des Congrès, Paris" required aria-required="true">
                                <div class="form-text small">Ce lieu sera exploité pour l'itinéraire GPS sur l'application mobile.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="estimated_participants" class="form-label small fw-bold text-muted">Participants prévus</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-people"></i></span>
                                    <input type="number" class="form-control" id="estimated_participants" name="estimated_participants"
                                           value="<?= (int)($prospect['estimated_participants'] ?? 0) ?>" min="1">
                                </div>
                            </div
                        </div>

                        <div class="mb-3 mt-3">
                            <label for="event_image" class="form-label small fw-bold text-muted">Illustration de l'événement (Lieu, Affiche...)</label>
                            <input type="file" class="form-control" id="event_image" name="event_image" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small">Facultatif. Format JPEG, PNG ou WebP.</div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label small fw-bold text-muted">Cahier des charges & spécifications</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($prospect['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="form-text small">Pré-rempli avec la demande initiale. Vous pouvez compléter selon vos échanges de qualification.</div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="event_status" class="form-label small fw-bold text-muted">Statut initial</label>
                                <select class="form-select" id="event_status" name="event_status">
                                    <option value="brouillon" selected>Brouillon (Devis en cours)</option>
                                    <option value="accepté">Accepté (Validé par le client)</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_visible" name="is_visible" checked>
                                    <label class="form-check-label small" for="is_visible">Visible sur la galerie publique (si validé)</label>
                                </div>
                            </div>
                        </div>

                        <hr class="text-muted opacity-25">

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" style="background-color: #3B82F6; border: none;">
                                <i class="bi bi-magic me-2" aria-hidden="true"></i>Convertir & Éditer les Prestations
                            </button>
                        </div>

                    </div>
                </div>
            </div>

        </form>
    </div>
</div>