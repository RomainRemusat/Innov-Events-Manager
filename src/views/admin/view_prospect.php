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

<div class="container-fluid">
    <div class="row">

        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2 fw-bold text-dark">Fiche Prospect : <?= htmlspecialchars($prospect['company_name'], ENT_QUOTES, 'UTF-8') ?></h1>
                <a href="index.php?action=generate_pdf&id=<?= (int)$prospect['id'] ?>" class="btn btn-danger shadow-sm">
                    <i class="fa-solid fa-file-pdf me-2"></i>Télécharger le Devis (PDF)
                </a>
                <a href="index.php?action=dashboard" class="btn btn-outline-secondary btn-sm shadow-sm">
                    <i class="fa-solid fa-arrow-left me-2"></i>Retour au tableau de bord
                </a>
            </div>

            <div class="row g-4">

                <div class="col-md-5">

                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-white py-3 border-0">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-user me-2 text-primary"></i> Coordonnées du contact</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Nom du contact :</strong> <?= htmlspecialchars($prospect['contact_name'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mb-2"><strong>Email :</strong> <a href="mailto:<?= htmlspecialchars($prospect['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($prospect['email'], ENT_QUOTES, 'UTF-8') ?></a></p>
                            <p class="mb-0"><strong>Téléphone :</strong> <?= htmlspecialchars($prospect['phone'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-0">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-gears me-2 text-warning"></i> Statut de la demande</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Statut actuel :</strong>
                                <?php
                                $badgeColor = 'bg-warning text-dark';
                                if ($prospect['status'] === 'accepté') $badgeColor = 'bg-success';
                                if ($prospect['status'] === 'refusé') $badgeColor = 'bg-danger';
                                if ($prospect['status'] === 'devis envoyé') $badgeColor = 'bg-info text-dark';
                                ?>
                                <span class="badge <?= $badgeColor ?> status-badge px-3 py-2"><?= ucfirst(htmlspecialchars($prospect['status'], ENT_QUOTES, 'UTF-8')) ?></span>
                            </p>
                            <hr>

                            <form action="index.php?action=update_prospect_status" method="POST">
                                <input type="hidden" name="id" value="<?= (int)$prospect['id'] ?>">

                                <label class="form-label fw-bold text-muted small">Changer le statut :</label>
                                <div class="input-group shadow-sm">
                                    <select class="form-select form-select-sm" name="status" aria-label="Sélection du statut">
                                        <option value="en attente" <?= $prospect['status'] === 'en attente' ? 'selected' : '' ?>>En attente</option>
                                        <option value="devis envoyé" <?= $prospect['status'] === 'devis envoyé' ? 'selected' : '' ?>>Devis envoyé</option>
                                        <option value="accepté" <?= $prospect['status'] === 'accepté' ? 'selected' : '' ?>>Accepté (Validé)</option>
                                        <option value="refusé" <?= $prospect['status'] === 'refusé' ? 'selected' : '' ?>>Refusé</option>
                                    </select>
                                    <button class="btn btn-sm btn-primary fw-bold" type="submit">Mettre à jour</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 border-0">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-cake-candles me-2 text-info"></i> Spécifications du projet</h5>
                        </div>
                        <div class="card-body">

                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center border-bottom border-secondary border-2">
                                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:0.75rem;">Type d'événement</small>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($prospect['event_type'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center border-bottom border-secondary border-2">
                                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:0.75rem;">Date prévue</small>
                                        <span class="fw-bold text-dark"><?= $prospect['event_date'] ? date('d/m/Y', strtotime($prospect['event_date'])) : 'Non définie' ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center border-bottom border-secondary border-2">
                                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:0.75rem;">Participants estimés</small>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($prospect['estimated_participants'] ?? 0, ENT_QUOTES, 'UTF-8') ?> personnes</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center border-bottom border-success border-2">
                                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:0.75rem;">Budget estimé</small>
                                        <span class="fw-bold text-success"><?= number_format($prospect['budget'] ?? 0, 2, ',', ' ') ?> €</span>
                                    </div>
                                </div>
                            </div>

                            <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-file-text me-1 text-muted"></i> Description fine du besoin :</h6>
                            <div class="p-3 bg-light rounded text-dark lh-base border-start border-primary border-3" style="min-height: 120px; white-space: pre-line;">
                                <?= htmlspecialchars($prospect['description'] ?? 'Aucune description textuelle fournie par l\'utilisateur.', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>

    </div>
</div>
