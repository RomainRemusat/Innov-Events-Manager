<?php
/**
 * Component Partial : Barre de navigation latérale (Sidebar Admin)
 *
 * @package    InnovEventsManager
 * @subpackage Views/Partials
 * @author     Romain Remusat
 * @version    1.3.0
 */

$currentAction = $_GET['action'] ?? 'dashboard';

// Récupération des données dynamiques pour les badges (Indicateurs clés)
require_once __DIR__ . '/../../models/sql/Prospect.php';
require_once __DIR__ . '/../../models/sql/Devis.php';

$prospectModel = new Prospect();
$nbActiveProspects = $prospectModel->NbActive();

$devisModel = new Devis();
$nbPendingModifications = $devisModel->countPendingModifications();

$navigation = [
        'Générales' => [
                [
                        'label'  => 'Dashboard',
                        'url'    => 'index.php?action=dashboard',
                        'active' => ['dashboard'],
                        'badge'  => null,
                        'badgeClass' => 'bg-secondary',
                ],
                [
                        'label'  => 'Prospects',
                        'url'    => 'index.php?action=prospects',
                        'active' => ['prospects'],
                        'badge'  => $nbActiveProspects > 0 ? $nbActiveProspects : null,
                        'badgeClass' => 'bg-primary',
                ],
                [
                        'label'  => 'Événements',
                        'url'    => 'index.php?action=admin_events',
                        'active' => ['admin_events', 'event_detail'],
                        'badge'  => null,
                        'badgeClass' => 'bg-secondary',
                ],
                [
                        'label'  => 'Clients',
                        'url'    => 'index.php?action=admin_clients',
                        'active' => ['admin_clients', 'clients', 'view_client', 'edit_client'],
                        'badge'  => null,
                        'badgeClass' => 'bg-secondary',
                ],
                [
                        'label'  => 'Devis & Facturation',
                        'url'    => 'index.php?action=admin_devis',
                        'active' => ['admin_devis', 'edit_devis'],
                        'badge'  => $nbPendingModifications > 0 ? $nbPendingModifications : null,
                        'badgeClass' => 'bg-danger', // Alerte visuelle rouge pour Chloé
                ],
        ],
        'Administration & Système' => [
                [
                        'label'  => 'Gestion d\'Équipe',
                        'url'    => '#',
                        'active' => ['teams'],
                        'badge'  => null,
                        'badgeClass' => 'bg-secondary',
                ],
                [
                        'label'  => 'Avis & Témoignages',
                        'url'    => '#',
                        'active' => ['reviews'],
                        'badge'  => null,
                        'badgeClass' => 'bg-secondary',
                ],
                [
                        'label'  => 'Logs (NoSQL)',
                        'url'    => 'index.php?action=mongo_logs',
                        'active' => ['mongo_logs'],
                        'badge'  => null,
                        'badgeClass' => 'bg-secondary',
                ],
        ],
        'Profils' => [
                [
                        'label'  => 'Mon Profil',
                        'url'    => '#',
                        'active' => ['profile'],
                        'badge'  => null,
                        'badgeClass' => 'bg-secondary',
                ],
                [
                        'label'     => 'Déconnexion',
                        'url'       => 'index.php?action=logout',
                        'active'    => [],
                        'badge'     => null,
                        'itemClass' => 'mt-3',
                        'badgeClass' => 'bg-secondary',
                ],
        ],
];
?>

<nav class="col-md-3 col-lg-2 d-md-block bg-secondary-subtle border-end min-vh-100 p-4">
    <?php foreach ($navigation as $sectionTitle => $items): ?>
        <ul class="nav flex-column mb-4">
            <div class="fs-6 text-dark mb-3 mt-2"><?= htmlspecialchars($sectionTitle) ?></div>

            <?php foreach ($items as $item): ?>
                <?php
                $isActive  = in_array($currentAction, $item['active'], true);
                $itemClass = $item['itemClass'] ?? 'mb-1';
                $badge     = $item['badge'] ?? null;
                ?>
                <li class="nav-item <?= $itemClass ?> d-flex align-items-center">
                    <a class="nav-link px-0 py-1 small <?= $isActive ? 'text-dark fw-bold' : 'text-secondary' ?>"
                       href="<?= htmlspecialchars($item['url']) ?>"
                            <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <?php if ($isActive): ?>
                            <i class="fa-solid fa-caret-right me-1" aria-hidden="true"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($item['label']) ?>
                    </a>

                    <?php if ($badge !== null && $badge !== ''): ?>
                        <span class="badge <?= htmlspecialchars($item['badgeClass'] ?? 'bg-secondary') ?> rounded-pill ms-auto" style="font-size: 0.65rem;">
                            <?= htmlspecialchars((string) $badge) ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</nav>