<?php
/**
 * Vue d'administration : Fiche détaillée d'un événement et espace collaboratif.
 *
 * Cette vue constitue l'interface centrale de pilotage opérationnel pour Chloé (ADMIN)
 * et José (EMPLOYEE). Elle rassemble la synthèse logistique, la gestion des médias,
 * les actions rapides de contact client, les prestations associées au devis et le flux de notes collaboratives.
 *
 * Variables injectées par le contrôleur (AdminEventController::showEventDetail) :
 * @var array $event Détails complets de l'événement et du client rattaché.
 * @var array $notes Liste chronologique inversée des notes collaboratives de l'événement.
 * @var array $tasks Tâches opérationnelles et employés assignés.
 * @var array $employees Employés actifs proposés à l'administrateur.
 * @var array|null $associatedDevis Devis et prestations chiffrées associés au projet.
 * @var string $pageTitle Titre de la page transmis au gabarit global.
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @author     Romain Rémusat
 * @version    1.3.0
 */
?>

<div class="container-fluid">
    <div class="row">
        <!-- Menu latéral de navigation du Back-Office -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <!-- Zone de contenu principal (Repère sémantique RGAA pour lecteurs d'écran) -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" id="main-content">

            <!-- Fil d'Ariane contextuel (Critère accessibilité RGAA) -->
            <nav aria-label="Fil d'Ariane" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="index.php?action=admin_events">Événements</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></li>
                </ol>
            </nav>

            <?php require __DIR__ . '/../partials/admin_messages.php'; ?>

            <!-- En-tête de la fiche projet -->
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom flex-wrap gap-2">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-muted small mb-0">Fiche logistique et pilotage opérationnel</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if (($_SESSION['user_role'] ?? '') === 'ADMIN'): ?>
                    <a href="index.php?action=admin_edit_event&id=<?= (int)$event['id'] ?>" class="btn btn-primary btn-sm">Modifier</a>
                    <form method="POST" action="index.php?action=admin_update_event_status" class="d-inline-flex align-items-center gap-1">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                        <select name="status" class="form-select form-select-sm fw-bold border-primary" aria-label="Statut de l'événement" onchange="this.form.submit()">
                            <?php foreach (Event::STATUS_LABELS as $value => $label): ?>
                                <option value="<?= $value ?>" <?= Event::normalizeStatus($event['status']) === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php else: ?>
                        <span class="badge bg-secondary"><?= htmlspecialchars(Event::normalizeStatus($event['status']), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>

                    <a href="index.php?action=admin_events" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Retour
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- COLONNE GAUCHE : Spécifications logistiques & Gestionnaire de média -->
                <div class="col-lg-6 d-flex flex-column gap-4">

                    <!-- Carte synthétique des données logistiques -->
                    <div class="card shadow-sm border-0 overflow-hidden h-100">
                        <?php require_once __DIR__ . '/../../utils/ImageHelper.php'; ?>
                        <?= ImageHelper::renderThumbnail($event['image_path'] ?? null, $event['title'], '200px') ?>

                        <div class="card-header bg-light fw-bold py-3">
                            <i class="fa-solid fa-clipboard-list me-2 text-primary" aria-hidden="true"></i>Informations Logistiques
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
                                <li>
                                    <strong>Dates :</strong> Du <?= date('d/m/Y H:i', strtotime($event['start_date'])) ?> au <?= !empty($event['end_date']) ? date('d/m/Y H:i', strtotime($event['end_date'])) : 'Non précisée' ?>
                                </li>
                                <li>
                                    <strong>Lieu :</strong> <?= htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8') ?>
                                </li>
                                <li>
                                    <strong>Type :</strong> <?= htmlspecialchars($event['event_type'], ENT_QUOTES, 'UTF-8') ?>
                                </li>
                                <li>
                                    <strong>Thème :</strong> <?= htmlspecialchars($event['theme'] ?? 'Non renseigné', ENT_QUOTES, 'UTF-8') ?>
                                </li>
                                <li>
                                    <strong>Participants attendus :</strong> <?= (int)($event['estimated_participants'] ?? 0) ?> personnes
                                </li>
                                <li>
                                    <strong>Visibilité publique :</strong>
                                    <?= (!empty($event['is_published']) && !empty($event['publication_consent_at']) && $event['status'] !== 'brouillon')
                                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Publié sur la vitrine</span>'
                                        : '<span class="badge bg-warning-subtle text-dark border border-warning-subtle">Masqué (Privé)</span>' ?>
                                </li>
                            </ul>
                            <h3 class="h6 mt-4">Description</h3>
                            <p class="mb-0"><?= nl2br(htmlspecialchars(trim($event['description'] ?? '') ?: 'Aucune description renseignée.', ENT_QUOTES, 'UTF-8')) ?></p>
                        </div>
                    </div>

                    <?php if (($_SESSION['user_role'] ?? '') === 'ADMIN'): ?>
                    <section class="card shadow-sm border-0" aria-labelledby="publication-title">
                        <div class="card-header fw-bold" id="publication-title">Publication et accord client</div>
                        <div class="card-body">
                            <?php if (!empty($event['publication_consent_at'])): ?>
                                <p class="small">Accord confirmé le <?= htmlspecialchars($event['publication_consent_at'], ENT_QUOTES, 'UTF-8') ?> par l'administrateur #<?= (int)$event['publication_consent_by'] ?>.</p>
                            <?php else: ?>
                                <p class="small">Aucun accord de publication confirmé. L'événement reste masqué.</p>
                            <?php endif; ?>
                            <p class="small text-muted">Un brouillon reste masqué même lorsque sa publication est préparée.</p>
                            <form method="POST" action="index.php?action=admin_event_toggle_publish">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                                <?php if (empty($event['is_published']) || empty($event['publication_consent_at'])): ?>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="publication_consent" name="publication_consent" value="1" required>
                                        <label class="form-check-label" for="publication_consent">Je confirme avoir recueilli l'accord du client pour la publication de cet événement et de son illustration.</label>
                                    </div>
                                    <button class="btn btn-primary btn-sm" name="publish" value="1">Confirmer l'accord et activer la publication</button>
                                <?php endif; ?>
                                <?php if (!empty($event['is_published'])): ?>
                                    <button class="btn btn-outline-secondary btn-sm" name="publish" value="0" formnovalidate>Retirer la publication et l'accord actif</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </section>

                    <!-- Carte de téléversement média -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-light fw-bold py-3">
                            <i class="fa-solid fa-camera me-2 text-primary" aria-hidden="true"></i>Illustration de l'événement
                        </div>
                        <div class="card-body">
                            <form method="POST" action="index.php?action=admin_upload_image" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">

                                <div class="mb-3">
                                    <label for="event_image" class="form-label small fw-semibold">
                                        Sélectionner un nouveau visuel (JPG, PNG, WEBP — max 5 Mo) :
                                    </label>
                                    <input class="form-control form-control-sm"
                                           type="file"
                                           id="event_image"
                                           name="event_image"
                                           accept="image/jpeg,image/png,image/webp"
                                           required>
                                </div>

                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-upload me-1" aria-hidden="true"></i>Mettre à jour l'image
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- COLONNE DROITE : Client rattaché & Actions directes terrain -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light fw-bold py-3">
                            <i class="fa-solid fa-user-tie me-2 text-primary" aria-hidden="true"></i>Client Rattaché
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h2 class="h5 fw-bold text-dark mb-1"><?= htmlspecialchars($event['firstname'] . ' ' . $event['lastname'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="text-muted small mb-4"><?= htmlspecialchars($event['company_name'] ?? 'Compte Individuel', ENT_QUOTES, 'UTF-8') ?></p>

                            <div class="d-flex flex-wrap gap-2 mt-auto pt-3 border-top">
                                <?php if (!empty($event['client_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($event['client_email'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-envelope me-1" aria-hidden="true"></i>Envoyer un email
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($event['phone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($event['phone'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fa-solid fa-phone me-1" aria-hidden="true"></i>Appeler
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($event['location'])): ?>
                                    <a href="https://maps.google.com/?q=<?= urlencode($event['location']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-map-location-dot me-1" aria-hidden="true"></i>Itinéraire
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION FINANCIÈRE & LOGISTIQUE : Devis commercial et Prestations convenues -->
            <section class="card shadow-sm border-0 mb-4" aria-labelledby="devis-section-title">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
                    <h2 class="h6 fw-bold mb-0" id="devis-section-title">
                        <i class="fa-solid fa-file-invoice-dollar me-2 text-primary" aria-hidden="true"></i>Devis & Prestations Validées
                    </h2>
                    <?php if (!empty($associatedDevis)): ?>
                        <div class="d-flex align-items-center gap-2">
                            <?php
                            $dStatus = strtolower($associatedDevis['status'] ?? '');
                            $dBadge = 'bg-warning text-dark';
                            if ($dStatus === 'accepté') $dBadge = 'bg-success text-white';
                            elseif ($dStatus === 'refusé') $dBadge = 'bg-danger text-white';
                            elseif ($dStatus === 'modification') $dBadge = 'bg-warning text-dark';
                            elseif ($dStatus === 'étude côté client') $dBadge = 'bg-info text-dark';
                            ?>
                            <span class="badge <?= $dBadge ?> px-3 py-1">
                                Devis #<?= (int)$associatedDevis['id_devis'] ?> : <?= ucfirst(htmlspecialchars($associatedDevis['status'] ?? 'Brouillon', ENT_QUOTES, 'UTF-8')) ?>
                            </span>
                            <?php if (($_SESSION['user_role'] ?? '') === 'ADMIN'): ?>
                                <a href="index.php?action=edit_devis&id=<?= (int)$associatedDevis['id_devis'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Gérer le devis
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($associatedDevis['reference_pdf'])): ?>
                                <a href="index.php?action=download_devis&id=<?= (int)$associatedDevis['id_devis'] ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
                                    <i class="fa-solid fa-file-pdf text-danger me-1"></i>PDF
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($associatedDevis)): ?>
                        <div class="alert alert-light border mb-0 text-muted d-flex align-items-center">
                            <i class="fa-solid fa-circle-info fs-4 me-3 text-secondary"></i>
                            <div>
                                <strong>Aucun devis commercial associé.</strong><br>
                                Ce projet n'a pas encore de devis rattaché.
                                <?php if (($_SESSION['user_role'] ?? '') === 'ADMIN'): ?>
                                    <form method="post" action="index.php?action=admin_create_event_quote" class="mt-3">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                                        <label for="quote-phone" class="form-label">Téléphone du contact *</label>
                                        <input type="tel" id="quote-phone" name="phone" class="form-control mb-2" maxlength="50" required>
                                        <p class="small">Le devis reprendra les coordonnées du client et les informations de cet événement. Vous pourrez ensuite ajouter les prestations et l’envoyer.</p>
                                        <button class="btn btn-primary btn-sm" type="submit">Créer un devis brouillon</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if (empty($associatedDevis['prestations'])): ?>
                            <p class="text-muted small mb-0">Aucune ligne de prestation détaillée n'a été saisie sur ce devis.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-3">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">#</th>
                                            <th>Prestation / Engagement logistique</th>
                                            <th class="text-end" style="width: 150px;">Montant HT</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $i = 1;
                                        foreach ($associatedDevis['prestations'] as $presta): ?>
                                            <tr>
                                                <td class="text-muted small"><?= $i++ ?></td>
                                                <td class="fw-semibold text-dark">
                                                    <i class="fa-solid fa-check text-success me-2"></i>
                                                    <?= htmlspecialchars($presta['libelle'], ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="text-end fw-bold text-secondary">
                                                    <?= number_format((float)$presta['montant_ht'], 2, ',', ' ') ?> €
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-group-divider">
                                        <tr>
                                            <th colspan="2" class="text-end text-muted">Total HT :</th>
                                            <th class="text-end fw-bold"><?= number_format((float)$associatedDevis['total_ht'], 2, ',', ' ') ?> €</th>
                                        </tr>
                                        <tr>
                                            <th colspan="2" class="text-end text-muted">TVA (20%) :</th>
                                            <th class="text-end text-muted"><?= number_format((float)$associatedDevis['total_tva'], 2, ',', ' ') ?> €</th>
                                        </tr>
                                        <tr class="table-primary">
                                            <th colspan="2" class="text-end text-primary fs-6">Total TTC validé :</th>
                                            <th class="text-end text-primary fs-6 fw-bold"><?= number_format((float)$associatedDevis['total_ttc'], 2, ',', ' ') ?> €</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card shadow-sm border-0 mb-4" aria-labelledby="tasks-section-title">
                <div class="card-header bg-light py-3">
                    <h2 class="h6 fw-bold mb-0" id="tasks-section-title"><i class="fa-solid fa-list-check me-2 text-primary" aria-hidden="true"></i>Tâches du projet</h2>
                </div>
                <div class="card-body p-4">
                    <?php if (($_SESSION['user_role'] ?? '') === 'ADMIN'): ?>
                        <form method="post" action="index.php?action=admin_create_task" class="row g-2 mb-4">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                            <div class="col-md-7"><label for="task-title" class="form-label">Nouvelle tâche *</label><input id="task-title" name="title" class="form-control" maxlength="255" required></div>
                            <div class="col-md-3"><label for="task-employee" class="form-label">Employé *</label><select id="task-employee" name="assigned_user_id" class="form-select" required>
                                <option value="">Sélectionner</option>
                                <?php foreach ($employees as $employee): ?><option value="<?= (int)$employee['id'] ?>"><?= htmlspecialchars($employee['firstname'] . ' ' . $employee['lastname'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                            </select></div>
                            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit" <?= !$employees ? 'disabled' : '' ?>>Assigner</button></div>
                            <?php if (!$employees): ?><p class="text-muted small mb-0">Aucun employé actif n’est disponible.</p><?php endif; ?>
                        </form>
                    <?php endif; ?>
                    <?php if (!$tasks): ?>
                        <p class="text-muted mb-0">Aucune tâche pour cet événement.</p>
                    <?php else: ?>
                        <div class="table-responsive"><table class="table align-middle mb-0">
                            <thead><tr><th scope="col">Tâche</th><th scope="col">Assignée à</th><th scope="col">Statut</th><th scope="col">Actions</th></tr></thead>
                            <tbody><?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($task['firstname'] . ' ' . $task['lastname'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="badge text-bg-secondary"><?= htmlspecialchars(Task::STATUS_LABELS[$task['status']] ?? $task['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><div class="d-flex gap-2 flex-wrap">
                                        <?php
                                        $isAdmin = ($_SESSION['user_role'] ?? '') === 'ADMIN';
                                        $isAssigned = (int)$task['assigned_user_id'] === (int)($_SESSION['user_id'] ?? 0);
                                        $nextStatus = ['à faire' => 'en cours', 'en cours' => 'terminée'][$task['status']] ?? null;
                                        ?>
                                        <?php if ($isAdmin || ($isAssigned && $nextStatus)): ?>
                                            <form method="post" action="index.php?action=admin_update_task_status" class="d-flex gap-2">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                                <?php if ($isAdmin): ?><select name="status" class="form-select form-select-sm" aria-label="Statut de la tâche">
                                                    <?php foreach (Task::STATUS_LABELS as $value => $label): ?><option value="<?= $value ?>" <?= $task['status'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                                                </select><button class="btn btn-outline-primary btn-sm" type="submit">Enregistrer</button>
                                                <?php else: ?><button class="btn btn-outline-primary btn-sm" type="submit" name="status" value="<?= htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8') ?>">Passer à « <?= htmlspecialchars(Task::STATUS_LABELS[$nextStatus], ENT_QUOTES, 'UTF-8') ?> »</button><?php endif; ?>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($isAdmin): ?><form method="post" action="index.php?action=admin_delete_task"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><button class="btn btn-outline-danger btn-sm" type="submit">Supprimer</button></form><?php endif; ?>
                                    </div></td>
                                </tr>
                            <?php endforeach; ?></tbody>
                        </table></div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- SECTION COLLABORATIVE : Flux des notes de projet -->
            <section class="card shadow-sm border-0 mb-4" aria-labelledby="notes-section-title">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                    <h2 class="h6 fw-bold mb-0" id="notes-section-title">
                        <i class="fa-solid fa-comments me-2 text-primary" aria-hidden="true"></i>Notes Collaboratives du Projet
                    </h2>
                </div>
                <div class="card-body p-4">
                    <!-- Formulaire de consigne à chaud -->
                    <form method="POST" action="index.php?action=admin_add_note" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                        <div class="mb-3">
                            <label for="note_content" class="form-label small fw-semibold">
                                Ajouter une consigne ou un retour terrain :
                            </label>
                            <textarea class="form-control"
                                      id="note_content"
                                      name="content"
                                      rows="3"
                                      maxlength="10000"
                                      placeholder="Informations prestataires, modifications de timing, contraintes d'accès..."
                                      required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Enregistrer la note
                        </button>
                    </form>

                    <!-- Affichage chronologique inversé des échanges enregistrés -->
                    <div class="d-flex flex-column gap-3">
                        <?php if (empty($notes)): ?>
                            <p class="text-muted small mb-0">Aucune note enregistrée sur ce projet.</p>
                        <?php else: ?>
                            <?php foreach ($notes as $n): ?>
                                <article class="p-3 rounded-2 bg-light border-start border-4 border-primary">
                                    <header class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold small text-dark">
                                            <?= htmlspecialchars($n['firstname'] . ' ' . $n['lastname'], ENT_QUOTES, 'UTF-8') ?>
                                            <span class="badge bg-secondary-subtle text-secondary ms-1">
                                                <?= htmlspecialchars($n['user_role'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </span>
                                        <time class="text-muted small" datetime="<?= htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= date('d/m/Y à H:i', strtotime($n['created_at'])) ?>
                                        </time>
                                    </header>
                                    <p class="mb-0 text-secondary small" style="white-space: pre-line;">
                                        <?= htmlspecialchars($n['content'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <?php if (($_SESSION['user_role'] ?? '') === 'ADMIN' || (int)$n['user_id'] === (int)($_SESSION['user_id'] ?? 0)): ?>
                                        <details class="mt-2"><summary class="small text-primary">Modifier ou supprimer</summary>
                                            <form method="post" action="index.php?action=admin_update_note" class="mt-2">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                                                <textarea name="content" class="form-control form-control-sm mb-2" maxlength="10000" required><?= htmlspecialchars($n['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                                <button class="btn btn-outline-primary btn-sm" type="submit">Enregistrer</button>
                                            </form>
                                            <form method="post" action="index.php?action=admin_delete_note" class="mt-2">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                                                <button class="btn btn-outline-danger btn-sm" type="submit">Supprimer la note</button>
                                            </form>
                                        </details>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <?php if (($_SESSION['user_role'] ?? '') === 'ADMIN'): ?>
                <details class="card card-body mt-4 border-danger">
                    <summary class="text-danger">Supprimer cet événement</summary>
                    <p class="mt-3">Les notes et l’image non partagée seront supprimées. Les devis et leurs PDF seront conservés.</p>
                    <form method="post" action="index.php?action=admin_delete_event">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                        <label class="d-block mb-2"><input type="checkbox" name="confirm_delete" value="1" required> Je confirme la suppression définitive de cet événement.</label>
                        <button class="btn btn-danger" type="submit">Supprimer l’événement</button>
                    </form>
                </details>
            <?php endif; ?>
        </main>
    </div>
</div>
