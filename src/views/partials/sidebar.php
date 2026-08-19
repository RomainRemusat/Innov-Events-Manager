<?php
/**
 * Component Partial : Barre de navigation latérale (Sidebar Admin)
 *
 * @package    InnovEventsManager
 * @subpackage Views/Partials
 * @author     Romain Remusat
 * @version    1.2.0
 */

$currentAction = $_GET['action'] ?? 'dashboard';

// Récupération des données dynamiques
$prospectModel = new Prospect();
$nbActiveProspects = $prospectModel->NbActive();

// Exemples futurs (à remplacer quand ils seront dev) :
// $nbEvents = $eventModel->count();
// $nbClients = $clientModel->count();
// $nbDevis = $devisModel->countPending();

$navigation = [
        'Générales' => [
                [
                        'label'  => 'Dashboard',
                        'url'    => 'index.php?action=dashboard',
                        'active' => ['dashboard'],
                        'badge'  => null,
                ],
                [
                        'label'  => 'Prospects',
                        'url'    => 'index.php?action=prospects',
                        'active' => ['prospects'],
                        'badge'  => $nbActiveProspects,
                ],
                [
                        'label'  => 'Événements',
                        'url'    => '#',
                        'active' => ['events'],
                        'badge'  => null, // ex: $nbEvents
                ],
                [
                        'label'  => 'Clients',
                        'url'    => 'index.php?action=admin_clients',
                        'active' => ['admin_clients', 'clients'],
                        'badge'  => null, // ex: $nbClients
                ],
                [
                        'label'  => 'Devis & Facturation',
                        'url'    => 'index.php?action=admin_devis',
                        'active' => ['admin_devis', 'edit_devis'],
                        'badge'  => null, // ex: $nbDevis
                ],
        ],
        'Administration & Système' => [
                [
                        'label'  => 'Gestion d\'Équipe',
                        'url'    => '#',
                        'active' => ['teams'],
                        'badge'  => null,
                ],
                [
                        'label'  => 'Avis & Témoignages',
                        'url'    => '#',
                        'active' => ['reviews'],
                        'badge'  => null,
                ],
                [
                        'label'  => 'Logs (NoSQL)',
                        'url'    => 'index.php?action=mongo_logs',
                        'active' => ['mongo_logs'],
                        'badge'  => null,
                ],
        ],
        'Profils' => [
                [
                        'label'  => 'Mon Profil',
                        'url'    => '#',
                        'active' => ['profile'],
                        'badge'  => null,
                ],
                [
                        'label'     => 'Déconnexion',
                        'url'       => 'index.php?action=logout',
                        'active'    => [],
                        'badge'     => null,
                        'itemClass' => 'mt-3',
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
                        <span class="badge bg-secondary rounded-pill ms-1" style="font-size: 0.65rem;">
                            <?= htmlspecialchars((string) $badge) ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</nav>