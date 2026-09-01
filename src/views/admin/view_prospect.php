<?php
/**
 * Vue : Fiche de Détail et Traitement Prospect
 *
 * Cette vue gère l'affichage granulaire des données récoltées via le tunnel public.
 * Elle agit comme l'interface décisionnelle principale pour le gestionnaire (Back-Office).
 * Elle intègre un formulaire de transition d'état (State Pattern) permettant de faire
 * évoluer le cycle de vie commercial du lead directement en base relationnelle.
 *
 * Spécifications de sécurité et UX :
 * - Contrôle de session strict à l'initialisation du composant.
 * - Échappement systématique via htmlspecialchars (Atténuation des risques XSS).
 * - Formatage conditionnel des types primitifs (`null` fallbacks sur budgets et dates).
 *
 * @package    InnovEventsManager
 * @subpackage Views/Admin
 * @author     Romain Remusat
 * @version    2.1.0
 * * @var array  $prospect  Données du modèle relationnel injectées par le DashboardController.
 * @var string $pageTitle Titre dynamique injecté pour le référencement interne.
 */
?>

<div class="container-fluid bg-light min-vh-100 py-4">
    <div class="row">

        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-2">

            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        <i class="bi bi-person-lines-fill text-primary me-2"></i>Qualification du Prospect
                    </h1>
                    <p class="text-muted small mb-0">
                        Qualifiez la demande entrante. Si le projet est faisable, convertissez le prospect en client B2B.
                    </p>
                </div>
                <div>
                    <a href="index.php?action=show_convert_form&id=<?= (int)$prospect['id'] ?>" class="btn btn-primary shadow-sm me-2" style="background-color: #3B82F6; border: none;">
                        <i class="bi bi-magic me-2"></i>Convertir en Client
                    </a>

                    <a href="index.php?action=dashboard" class="btn btn-outline-secondary btn-sm shadow-sm">
                        <i class="bi bi-arrow-left me-2"></i>Retour
                    </a>
                </div>
            </div>

            <div class="row g-4">

                <!-- Colonne Gauche : Contact & Statut Prospect -->
                <div class="col-md-5">

                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.05rem;">
                                <i class="bi bi-person-badge text-primary me-2"></i>Coordonnées du contact
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Entreprise :</strong> <?= htmlspecialchars($prospect['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mb-2"><strong>Nom du contact :</strong> <?= htmlspecialchars($prospect['contact_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mb-2"><strong>Email :</strong> <a href="mailto:<?= htmlspecialchars($prospect['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($prospect['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></a></p>
                            <p class="mb-0"><strong>Téléphone :</strong> <?= htmlspecialchars($prospect['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.05rem;">
                                <i class="bi bi-gear-wide-connected text-warning me-2"></i>Qualification de la demande
                            </h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Statut actuel :</strong>
                                <?php
                                $status = strtolower($prospect['status'] ?? 'à contacter');
                                $badgeClass = 'text-bg-warning';
                                if ($status === 'en attente') $badgeClass = 'text-bg-info';
                                if ($status === 'échoué' || $status === 'refusé') $badgeClass = 'text-bg-danger';
                                if ($status === 'converti' || $status === 'accepté') $badgeClass = 'text-bg-success';
                                ?>
                                <span class="badge <?= $badgeClass ?> px-3 py-2"><?= ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8')) ?></span>
                            </p>
                            <hr>

                            <!-- Formulaire restreint aux seuls états de qualification prospect -->
                            <form action="index.php?action=update_prospect_status" method="POST">
                                <input type="hidden" name="id" value="<?= (int)$prospect['id'] ?>">

                                <label class="form-label fw-bold text-muted small">Changer l'état du prospect :</label>
                                <div class="input-group shadow-sm mb-2">
                                    <select class="form-select form-select-sm" name="status" aria-label="Sélection du statut prospect">
                                        <option value="à contacter" <?= $status === 'à contacter' ? 'selected' : '' ?>>À contacter</option>
                                        <option value="en attente" <?= $status === 'en attente' ? 'selected' : '' ?>>En attente (Échanges en cours)</option>
                                        <option value="échoué" <?= $status === 'échoué' ? 'selected' : '' ?>>Échoué (Projet infaisable)</option>
                                    </select>
                                    <button class="btn btn-sm btn-primary fw-bold" type="submit">Mettre à jour</button>
                                </div>
                                <small class="text-muted d-block" style="font-size: 0.75rem;">
                                    <i class="bi bi-info-circle me-1"></i>Passer le statut sur « Échoué » informe le prospect de l'impossibilité de donner suite à sa demande.
                                </small>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Colonne Droite : Spécifications du projet -->
                <div class="col-md-7">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.05rem;">
                                <i class="bi bi-card-checklist text-info me-2"></i>Besoins exprimés par le prospect
                            </h5>
                        </div>
                        <div class="card-body">

                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center border-bottom border-secondary border-2">
                                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:0.75rem;">Type d'événement</small>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($prospect['event_type'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center border-bottom border-secondary border-2">
                                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:0.75rem;">Date souhaitée</small>
                                        <span class="fw-bold text-dark"><?= !empty($prospect['event_date']) ? date('d/m/Y', strtotime($prospect['event_date'])) : 'Non définie' ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center border-bottom border-secondary border-2">
                                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:0.75rem;">Participants estimés</small>
                                        <span class="fw-bold text-dark"><?= (int)($prospect['estimated_participants'] ?? 0) ?> personnes</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center border-bottom border-success border-2">
                                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:0.75rem;">Budget indicatif</small>
                                        <span class="fw-bold text-success"><?= number_format($prospect['budget'] ?? 0, 2, ',', ' ') ?> € HT</span>
                                    </div>
                                </div>
                            </div>

                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-file-text me-1 text-muted"></i>Description du besoin :</h6>
                            <div class="p-3 bg-light rounded text-dark lh-base border-start border-primary border-3" style="min-height: 120px; white-space: pre-line;">
                                <?= htmlspecialchars($prospect['description'] ?? 'Aucune description textuelle fournie.', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>

    </div>
</div>