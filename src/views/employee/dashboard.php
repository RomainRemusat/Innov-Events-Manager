<?php

declare(strict_types=1);

/**
 * Tableau de bord opérationnel d'un employé.
 *
 * @var array<int, array<string, mixed>> $assignedTasks Tâches attribuées à l'employé connecté.
 * @var array<string, int> $taskCounts Nombre de tâches par statut.
 * @var array<int, array<string, mixed>> $upcomingEvents Prochains événements de l'agence.
 * @var array<int, array<string, mixed>> $recentNotes Dernières informations partagées par l'équipe.
 */

$nextStatuses = ['à faire' => 'en cours', 'en cours' => 'terminée'];
?>
<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" id="main-content">
            <?php require __DIR__ . '/../partials/admin_messages.php'; ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-3 mb-4">
                <div>
                    <h1 class="h2 fw-bold mb-1">Mon espace employé</h1>
                    <p class="text-secondary mb-0">
                        Bonjour <?= htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>,
                        voici les opérations à suivre.
                    </p>
                </div>
                <div class="d-flex gap-2 mt-3 mt-md-0">
                    <a class="btn btn-outline-primary" href="index.php?action=admin_clients">Consulter les clients</a>
                    <a class="btn btn-primary" href="index.php?action=admin_events">Voir les événements</a>
                </div>
            </div>

            <section class="row g-3 mb-4" aria-label="Synthèse des tâches">
                <?php foreach (Task::STATUS_LABELS as $status => $label): ?>
                    <div class="col-sm-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <p class="text-secondary small mb-1"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="display-6 fw-bold mb-0"><?= (int)($taskCounts[$status] ?? 0) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="card border-0 shadow-sm mb-4" aria-labelledby="employee-tasks-title">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0" id="employee-tasks-title">Mes tâches</h2>
                </div>
                <div class="card-body p-0">
                    <?php if (!$assignedTasks): ?>
                        <p class="text-secondary text-center p-4 mb-0">Aucune tâche ne vous est attribuée.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th scope="col" class="ps-4">Tâche</th>
                                    <th scope="col">Événement</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Statut</th>
                                    <th scope="col" class="text-end pe-4">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($assignedTasks as $task): ?>
                                    <?php $nextStatus = $nextStatuses[$task['status']] ?? null; ?>
                                    <tr>
                                        <td class="ps-4 fw-semibold"><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <a href="index.php?action=admin_event_detail&amp;id=<?= (int)$task['event_id'] ?>">
                                                <?= htmlspecialchars($task['event_title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <div class="small text-secondary">
                                                <?= htmlspecialchars($task['client_firstname'] . ' ' . $task['client_lastname'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($task['start_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="badge text-bg-secondary"><?= htmlspecialchars(Task::STATUS_LABELS[$task['status']] ?? $task['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td class="text-end pe-4">
                                            <?php if ($nextStatus): ?>
                                                <form method="post" action="index.php?action=admin_update_task_status">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                                    <input type="hidden" name="event_id" value="<?= (int)$task['event_id'] ?>">
                                                    <input type="hidden" name="status" value="<?= htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="return_to" value="dashboard">
                                                    <button class="btn btn-outline-primary btn-sm" type="submit">
                                                        Passer à « <?= htmlspecialchars(Task::STATUS_LABELS[$nextStatus], ENT_QUOTES, 'UTF-8') ?> »
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-success small">Terminée</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <div class="row g-4">
                <section class="col-lg-7" aria-labelledby="employee-events-title">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3">
                            <h2 class="h5 mb-0" id="employee-events-title">Prochains événements</h2>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (!$upcomingEvents): ?>
                                <p class="text-secondary text-center p-4 mb-0">Aucun événement à venir.</p>
                            <?php endif; ?>
                            <?php foreach ($upcomingEvents as $event): ?>
                                <a class="list-group-item list-group-item-action py-3" href="index.php?action=admin_event_detail&amp;id=<?= (int)$event['id'] ?>">
                                    <div class="d-flex justify-content-between gap-3">
                                        <strong><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span class="text-nowrap small"><?= htmlspecialchars(date('d/m/Y', strtotime($event['start_date'])), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <span class="small text-secondary">
                                        <?= htmlspecialchars(trim(($event['company_name'] ?: $event['firstname'] . ' ' . $event['lastname']) . ' · ' . $event['location']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="col-lg-5" aria-labelledby="employee-notes-title">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3">
                            <h2 class="h5 mb-0" id="employee-notes-title">Dernières notes</h2>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (!$recentNotes): ?>
                                <p class="text-secondary text-center p-4 mb-0">Aucune note partagée.</p>
                            <?php endif; ?>
                            <?php foreach ($recentNotes as $note): ?>
                                <div class="list-group-item py-3">
                                    <div class="d-flex justify-content-between gap-2 mb-1">
                                        <strong class="small"><?= htmlspecialchars($note['event_title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span class="text-secondary small"><?= htmlspecialchars(date('d/m/Y', strtotime($note['created_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <p class="small mb-1"><?= nl2br(htmlspecialchars($note['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    <span class="text-secondary small"><?= htmlspecialchars($note['firstname'] . ' ' . $note['lastname'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>
