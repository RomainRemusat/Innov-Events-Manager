<?php
/**
 * Vue : Formulaire de Conversion (Prospect -> Client & Événement)
 *
 * Cette interface d'administration est l'étape centrale du workflow commercial (AT2).
 * Elle permet à l'administrateur de transformer un "Lead" (Prospect) en client
 * actif, tout en initialisant la coquille du projet événementiel.
 *
 * Normes appliquées :
 * - Sécurité (AT1) : Jeton Anti-CSRF et échappement strict (XSS) via ENT_QUOTES.
 * - Accessibilité (RGAA) : Attributs ARIA pour les lecteurs d'écran.
 * - UI/UX : Respect de la charte graphique Slate Dark (#0F172A) et Bleu (#3B82F6).
 *
 * @package    InnovEventsManager
 * @subpackage Views/Admin
 * @author     Romain Remusat
 * @version    1.2.0
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
                <h1 class="h3 fw-bold text-dark mb-1">Convertir le Prospect</h1>
                <p class="text-muted small">Création du compte client et initialisation du projet événementiel.</p>
            </div>
            <a href="index.php?action=dashboard" class="btn btn-outline-secondary btn-sm" aria-label="Retour au tableau de bord">
                <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Retour au tableau de bord
            </a>
        </div>

        <!-- =============================================================== -->
        <!-- FORMULAIRE DE CONVERSION (POST)                                 -->
        <!-- =============================================================== -->
        <form action="index.php?action=process_conversion" method="POST" class="row g-4">

            <!-- Jeton de sécurité Anti-CSRF (Validation AT1) -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <!-- ID caché du prospect pour la liaison SQL -->
            <input type="hidden" name="prospect_id" value="<?= (int)$prospect['id'] ?>">

            <!-- COLONNE GAUCHE : Informations Client (Pré-remplies) -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom border-light py-3">
                        <h2 class="h6 fw-bold mb-0" style="color: #0F172A;">
                            <i class="bi bi-person-badge text-primary me-2" aria-hidden="true"></i>1. Informations du Futur Client
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="company_name" class="form-label small fw-bold text-muted">Entreprise</label>
                            <input type="text" class="form-control bg-light" id="company_name" name="company_name"
                                   value="<?= htmlspecialchars($prospect['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required aria-required="true">
                        </div>
                        <div class="mb-3">
                            <label for="contact_name" class="form-label small fw-bold text-muted">Contact Référent</label>
                            <input type="text" class="form-control bg-light" id="contact_name" name="contact_name"
                                   value="<?= htmlspecialchars($prospect['contact_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required aria-required="true">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label small fw-bold text-muted">Email (Servira d'identifiant)</label>
                            <input type="email" class="form-control bg-light" id="email" name="email"
                                   value="<?= htmlspecialchars($prospect['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required aria-required="true">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label small fw-bold text-muted">Téléphone</label>
                            <input type="tel" class="form-control bg-light" id="phone" name="phone"
                                   value="<?= htmlspecialchars($prospect['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required aria-required="true">
                        </div>

                        <!-- Info métier UX -->
                        <div class="alert alert-info small mt-4 border-0">
                            <i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i>
                            Si ce client n'a pas de compte, le système lui en créera un automatiquement et lui enverra un mot de passe temporaire par e-mail.
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLONNE DROITE : Paramétrage de l'Événement -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom border-light py-3">
                        <h2 class="h6 fw-bold mb-0" style="color: #0F172A;">
                            <i class="bi bi-calendar-star text-primary me-2" aria-hidden="true"></i>2. Paramétrage de l'Événement
                        </h2>
                    </div>
                    <div class="card-body">

                        <div class="mb-3">
                            <label for="event_title" class="form-label small fw-bold text-muted">Nom du projet événementiel *</label>
                            <!-- Pré-remplissage astucieux avec le type d'événement + l'entreprise -->
                            <input type="text" class="form-control" id="event_title" name="event_title"
                                   value="<?= htmlspecialchars(($prospect['event_type'] ?? '') . ' - ' . ($prospect['company_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required aria-required="true">
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label small fw-bold text-muted">Date & Heure de début *</label>
                                <!-- Pré-remplissage avec la date souhaitée du prospect -->
                                <input type="datetime-local" class="form-control" id="start_date" name="start_date"
                                       value="<?= htmlspecialchars($prospect['event_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>T08:00" required aria-required="true">
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label small fw-bold text-muted">Date & Heure de fin *</label>
                                <input type="datetime-local" class="form-control" id="end_date" name="end_date"
                                       value="<?= htmlspecialchars($prospect['event_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>T20:00" required aria-required="true">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label small fw-bold text-muted">Lieu prévu *</label>
                            <input type="text" class="form-control" id="location" name="location" placeholder="Ex: Palais des Congrès, Paris" required aria-required="true">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label small fw-bold text-muted">Description / Cahier des charges de l'événement</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($prospect['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="form-text small" id="desc-help">Cette description a été pré-remplie avec la demande initiale du prospect. Vous pouvez l'enrichir suite à votre échange.</div>
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
                                    <label class="form-check-label small" for="is_visible">Visible sur le catalogue public</label>
                                </div>
                            </div>
                        </div>

                        <hr class="text-muted opacity-25">

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" style="background-color: #3B82F6; border: none;">
                                <i class="bi bi-magic me-2" aria-hidden="true"></i>Convertir & Créer le Devis
                            </button>
                        </div>

                    </div>
                </div>
            </div>

        </form>
    </div>
</div>