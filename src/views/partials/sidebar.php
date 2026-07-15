<?php
/**
 * Component Partial : Barre de navigation latérale (Sidebar Admin)
 *
 * Ce composant centralise la navigation du Back-Office. L'état actif du lien
 * est géré dynamiquement en évaluant la variable globale d'aiguillage $action.
 *
 * @package    InnovEventsManager
 * @subpackage Views/Partials
 * @author     Romain Remusat
 * @version    1.0.0
 */

// Récupération sécurisée de l'action courante pour gérer la classe active
$currentAction = $_GET['action'] ?? 'dashboard';
?>
<nav class="col-md-3 col-lg-2 d-md-block bg-secondary-subtle border-end min-vh-100 p-4">

    <ul class="nav flex-column mb-4">
        <div class="fs-6 text-dark mb-3">Générales</div>

        <li class="nav-item mb-1">
            <a class="nav-link px-0 py-1 small <?= $currentAction === 'dashboard' ? 'text-dark fw-bold' : 'text-secondary' ?>" href="index.php?action=dashboard">
                <?= $currentAction === 'dashboard' ? '<i class="fa-solid fa-caret-right me-1"></i>' : '' ?>Dashboard
            </a>
        </li>
        <li class="nav-item mb-1">

            <a class="nav-link px-0 py-1 small <?= $currentAction === 'prospects' ? 'text-dark fw-bold' : 'text-secondary' ?>" href="index.php?action=prospects">
                <?= $currentAction === 'prospects' ? '<i class="fa-solid fa-caret-right me-1"></i>' : '' ?>Prospects
                <span class="badge bg-secondary rounded-pill ms-1" style="font-size: 0.65rem;">1</span>
            </a>
        </li>
        <li class="nav-item mb-1">
            <a class="nav-link px-0 py-1 small <?= $currentAction === 'events' ? 'text-dark fw-bold' : 'text-secondary' ?>" href="#">
                <?= $currentAction === 'events' ? '<i class="fa-solid fa-caret-right me-1"></i>' : '' ?>Événements
            </a>
        </li>
        <li class="nav-item mb-1">
            <a class="nav-link px-0 py-1 small <?= $currentAction === 'clients' ? 'text-dark fw-bold' : 'text-secondary' ?>" href="#">
                <?= $currentAction === 'clients' ? '<i class="fa-solid fa-caret-right me-1"></i>' : '' ?>Clients
            </a>
        </li>
        <li class="nav-item mb-1">
            <a class="nav-link px-0 py-1 small <?= $currentAction === 'devis_factures' ? 'text-dark fw-bold' : 'text-secondary' ?>" href="#">
                <?= $currentAction === 'devis_factures' ? '<i class="fa-solid fa-caret-right me-1"></i>' : '' ?>Devis & Facturation
            </a>
        </li>
    </ul>

    <ul class="nav flex-column mb-4">
        <div class="fs-6 text-dark mb-3 mt-2">Administration & Système</div>

        <li class="nav-item mb-1">
            <a class="nav-link px-0 py-1 small <?= $currentAction === 'teams' ? 'text-dark fw-bold' : 'text-secondary' ?>" href="#">
                <?= $currentAction === 'teams' ? '<i class="fa-solid fa-caret-right me-1"></i>' : '' ?>Gestion d'Équipe
            </a>
        </li>
        <li class="nav-item mb-1">
            <a class="nav-link px-0 py-1 small <?= $currentAction === 'reviews' ? 'text-dark fw-bold' : 'text-secondary' ?>" href="#">
                <?= $currentAction === 'reviews' ? '<i class="fa-solid fa-caret-right me-1"></i>' : '' ?>Avis & Témoignages
            </a>
        </li>
        <li class="nav-item mb-1">
            <a class="nav-link px-0 py-1 small <?= $currentAction === 'mongo_logs' ? 'text-dark fw-bold' : 'text-secondary' ?>" href="index.php?action=mongo_logs">
                <?= $currentAction === 'mongo_logs' ? '<i class="fa-solid fa-caret-right me-1"></i>' : '' ?>Logs (NoSQL)
            </a>
        </li>
    </ul>

    <ul class="nav flex-column mb-4">
        <div class="fs-6 text-dark mb-3 mt-2">Profils</div>

        <li class="nav-item mb-1">
            <a class="nav-link px-0 py-1 small <?= $currentAction === 'profile' ? 'text-dark fw-bold' : 'text-secondary' ?>" href="#">
                <?= $currentAction === 'profile' ? '<i class="fa-solid fa-caret-right me-1"></i>' : '' ?>Mon Profil
            </a>
        </li>
        <li class="nav-item mt-3">
            <a class="nav-link px-0 py-1 small text-secondary" href="index.php?action=logout">
                Déconnexion
            </a>
        </li>
    </ul>
</nav>