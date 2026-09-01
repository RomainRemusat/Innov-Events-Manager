<?php
/**
 * Vue : Dossier Client (Informations & Historique des Devis)
 *
 * @var array $client Informations du client (Table Users)
 * @var array $clientQuotes Historique des devis du client
 */
?>
<div class="container-fluid bg-light min-vh-100 py-4">
    <div class="container">

        <!-- En-tête et navigation -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-dark mb-1">
                    <i class="fa-solid fa-folder-open text-info me-2" aria-hidden="true"></i>Dossier Client
                </h1>
                <p class="text-muted small mb-0">Historique commercial et informations de <?= htmlspecialchars($client['firstname'] . ' ' . $client['lastname'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <a href="index.php?action=admin_clients" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Retour aux clients
            </a>
        </div>

        <div class="row g-4">
            <!-- Bloc Informations Personnelles -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h2 class="h6 fw-bold mb-0 text-dark">Informations Contact</h2>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Société :</strong> <span class="fs-5"><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></span></p>
                        <p class="mb-2"><strong>Nom :</strong> <?= htmlspecialchars($client['lastname'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mb-2"><strong>Prénom :</strong> <?= htmlspecialchars($client['firstname'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mb-2">
                            <strong>Email :</strong>
                            <a href="mailto:<?= htmlspecialchars($client['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($client['email'], ENT_QUOTES, 'UTF-8') ?></a>
                        </p>
                        <p class="mb-0 text-muted small mt-4">Client depuis le <?= date('d/m/Y', strtotime($client['created_at'])) ?></p>

                        <!-- Action métier : Modifier -->
                        <div class="mt-4 pt-3 border-top">
                            <a href="index.php?action=edit_client&id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-primary w-100">
                                <i class="fa-solid fa-pen me-2"></i>Modifier les informations
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bloc Historique des Devis (Exigence du cahier des charges) -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h2 class="h6 fw-bold mb-0 text-dark">Historique des Devis & Projets</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" aria-label="Devis du client">
                                <thead style="background-color: #F8FAFC;">
                                <tr>
                                    <th scope="col" class="py-3 px-4">Projet / Type</th>
                                    <th scope="col" class="py-3 px-4">Montant HT</th>
                                    <th scope="col" class="py-3 px-4 text-center">Statut</th>
                                    <th scope="col" class="py-3 px-4 text-center">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($clientQuotes)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">Aucun devis généré pour ce client.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clientQuotes as $quote): ?>
                                        <tr>
                                            <td class="px-4 py-3 fw-bold text-dark">
                                                <?= htmlspecialchars($quote['event_type'], ENT_QUOTES, 'UTF-8') ?>
                                                <div class="small text-muted fw-normal">Réf: <?= htmlspecialchars($quote['reference_pdf'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>
                                            </td>
                                            <td class="px-4 py-3 fw-semibold">
                                                <?= !empty($quote['montant_ht']) ? number_format((float)$quote['montant_ht'], 2, ',', ' ') . ' €' : '--' ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php
                                                $badge = 'bg-secondary';
                                                $status = strtolower($quote['status']);
                                                if ($status === 'accepté') $badge = 'bg-success';
                                                if ($status === 'refusé') $badge = 'bg-danger';
                                                if ($status === 'étude côté client') $badge = 'bg-info text-dark';
                                                ?>
                                                <span class="badge <?= $badge ?> px-2 py-1"><?= ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8')) ?></span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php if (!empty($quote['id_devis'])): ?>
                                                    <a href="index.php?action=edit_devis&id=<?= (int)$quote['id_devis'] ?>" class="btn btn-sm btn-outline-primary" title="Ouvrir le devis">
                                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">Pas de devis</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>