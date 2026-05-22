<?php
/**
 * Vue : Tableau de bord (Dashboard Admin)
 *
 * Cette vue est strictement réservée aux administrateurs authentifiés (Chloé).
 * Elle affiche la liste complète des demandes de devis (prospects) sous forme
 * de tableau de données interactif.
 *
 * Contraintes respectées :
 * - Sécurité (XSS) : Échappement systématique des données affichées via htmlspecialchars()
 * pour bloquer toute tentative d'injection via les champs du formulaire public.
 * - Ergonomie (UI/UX) : Design minimaliste, responsive, et gestion du "Empty State".
 *
 * @var array $prospects Tableau contenant les données des prospects (injecté par le Contrôleur).
 *
 * @package    InnovEventsManager
 * @subpackage Views/Admin
 * @author     Romain Remusat
 * @version    1.0.0
 */
?>

<main class="container px-4 py-5 my-3">
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Espace Administration</h1>
            <p class="text-muted mb-0">Gestion des demandes de devis entrantes.</p>
        </div>
        <div>
            <a href="index.php?action=logout" class="btn btn-outline-danger btn-sm px-3 shadow-sm transition-all">
                <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase tracking-wider">
                    <tr>
                        <th scope="col" class="px-4 py-3">Réf.</th>
                        <th scope="col" class="px-4 py-3">Entreprise</th>
                        <th scope="col" class="px-4 py-3">Contact</th>
                        <th scope="col" class="px-4 py-3">Événement</th>
                        <th scope="col" class="px-4 py-3">Statut</th>
                        <th scope="col" class="px-4 py-3 text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="border-top-0">
                    <?php if (!empty($prospects) && is_array($prospects)): ?>
                        <?php foreach ($prospects as $prospect): ?>
                            <tr>
                                <td class="px-4 py-3 text-muted fw-semibold">
                                    #<?= htmlspecialchars($prospect['id'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="fw-bold text-dark">
                                        <?= htmlspecialchars($prospect['company_name'] ?? 'Non renseigné', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="text-dark fw-medium">
                                        <?= htmlspecialchars($prospect['contact_name'] ?? 'Non renseigné', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <a href="mailto:<?= htmlspecialchars($prospect['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-primary">
                                            <?= htmlspecialchars($prospect['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <br>
                                        <span class="text-secondary"><?= htmlspecialchars($prospect['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-secondary">
                                    <?= htmlspecialchars($prospect['event_type'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td class="px-4 py-3">
                                    <?php
                                    // Normalisation du statut pour affichage et choix de la couleur du badge
                                    $status = strtolower($prospect['status'] ?? 'en attente');
                                    $badgeClass = ($status === 'en attente') ? 'bg-warning text-dark' : 'bg-success';
                                    ?>
                                    <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-2 fw-normal">
                                            <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                </td>

                                <td class="px-4 py-3 text-end">
                                    <a href="#" class="btn btn-sm btn-light border text-primary shadow-sm" title="Voir les détails complets">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted mb-3 opacity-50">
                                    <i class="bi bi-inbox display-3"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Aucune demande pour le moment</h5>
                                <p class="text-muted small">Les nouvelles requêtes de devis apparaîtront ici automatiquement.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>