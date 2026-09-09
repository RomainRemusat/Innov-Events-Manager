<?php
/**
 * Vue d'administration : Tableau de bord de gestion des événements.
 *
 * Affiche la liste consolidée de tous les événements de l'agence (y compris les brouillons
 * et événements privés non publiés sur la vitrine publique). Permet l'arbitrage
 * direct des statuts opérationnels et donne accès à la fiche projet détaillée.
 *
 * Contrat de vue (variables injectées par AdminEventController::listEvents) :
 * @var array<int, array{
 *     id: int|string,
 *     title: string,
 *     start_date: string,
 *     end_date: ?string,
 *     location: string,
 *     status: string,
 *     is_published: int|string|bool,
 *     event_type: string,
 *     theme: ?string,
 *     estimated_participants: int|string|null,
 *     firstname: string,
 *     lastname: string,
 *     company_name: ?string
 * }> $events Liste complète des événements retournée par Event::findAllAdmin().
 *
 * @var string $pageTitle Intitulé de l'onglet transmis au gabarit global header.php.
 *
 * @package    InnovEventsManager
 * @subpackage Views\Admin
 * @author     Romain Rémusat
 * @version    1.2.0
 */
?>
<div class="container-fluid">
    <div class="row">
        <!-- Barre latérale de navigation d'administration -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <!-- Zone de contenu principal (Repère pour aides techniques et lecteurs d'écran) -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" id="main-content">

            <!-- En-tête de section -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 fw-bold text-dark mb-1">Gestion des Événements</h1>
                    <p class="text-secondary small mb-0">Suivi logistique, publication vitrine et gestion des statuts opérationnels.</p>
                </div>
            </div>

            <!-- Tableau de supervision opérationnelle -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-4">Événement &amp; Client</th>
                                <th scope="col">Dates</th>
                                <th scope="col">Lieu</th>
                                <th scope="col">Vitrine</th>
                                <th scope="col">Statut</th>
                                <th scope="col" class="text-end pe-4">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($events)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <em>Aucun événement enregistré pour le moment.</em>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($events as $ev): ?>
                                    <tr>
                                        <!-- Titre de l'événement et identité du client B2B / B2C -->
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark">
                                                <?= htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div class="small text-muted">
                                                <?php
                                                $clientLabel = !empty($ev['company_name'])
                                                    ? htmlspecialchars($ev['company_name'], ENT_QUOTES, 'UTF-8') . ' — '
                                                    : '';
                                                $clientLabel .= htmlspecialchars($ev['firstname'] . ' ' . $ev['lastname'], ENT_QUOTES, 'UTF-8');
                                                echo $clientLabel;
                                                ?>
                                            </div>
                                        </td>

                                        <!-- Amplitude temporelle -->
                                        <td>
                                            <div class="small fw-semibold text-dark">
                                                Du <?= date('d/m/Y', strtotime($ev['start_date'])) ?>
                                            </div>
                                            <?php if (!empty($ev['end_date'])): ?>
                                                <div class="small text-muted">
                                                    Au <?= date('d/m/Y', strtotime($ev['end_date'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Emplacement physique de la prestation -->
                                        <td class="small">
                                            <?= htmlspecialchars($ev['location'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>

                                        <!-- Visibilité publique (Accord client CDC p. 7) -->
                                        <td>
                                            <?php if ((int)$ev['is_published'] === 1): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="fa-solid fa-eye me-1" aria-hidden="true"></i>Publié
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                    <i class="fa-solid fa-eye-slash me-1" aria-hidden="true"></i>Masqué
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Formulaire d'arbitrage direct du cycle de vie opérationnel (Sécurité OWASP CSRF) -->
                                        <td>
                                            <?php if (($_SESSION['user_role'] ?? '') === 'ADMIN'): ?>
                                            <form method="POST" action="index.php?action=admin_event_update_status" class="d-inline">
                                                <!-- Jeton de protection anti-CSRF -->
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">

                                                <select name="status"
                                                        class="form-select form-select-sm"
                                                        onchange="this.form.submit()"
                                                        aria-label="Modifier le statut de l'événement <?= htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?php
                                                    $statuses = [
                                                        'brouillon' => 'Brouillon',
                                                        'accepté'   => 'Accepté',
                                                        'en cours'  => 'En cours',
                                                        'terminé'   => 'Terminé',
                                                        'annuler'   => 'Annulé'
                                                    ];
                                                    foreach ($statuses as $val => $label):
                                                        ?>
                                                        <option value="<?= $val ?>" <?= ($ev['status'] === $val) ? 'selected' : '' ?>>
                                                            <?= $label ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                            <?php else: ?><?= htmlspecialchars($ev['status'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                        </td>

                                        <!-- Accès à la fiche projet détaillée et aux notes de terrain -->
                                        <td class="text-end pe-4">
                                            <a href="index.php?action=admin_event_detail&id=<?= (int)$ev['id'] ?>"
                                               class="btn btn-sm btn-outline-primary"
                                               title="Consulter la fiche logistique et les notes de <?= htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fa-solid fa-folder-open me-1" aria-hidden="true"></i>Détails
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>