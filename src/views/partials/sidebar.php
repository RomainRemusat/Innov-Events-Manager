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
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-3 shadow">
    <div class="d-flex align-items-center mb-4 px-2">
        <i class="fa-solid fa-calendar-days text-primary fs-3 me-2"></i>
        <span class="fs-5 fw-bold text-white">Innov'Events</span>
    </div>
    <hr class="text-secondary">

    <ul class="nav flex-column mb-3">
        <div class="sidebar-heading">Générales</div>
        <li class="nav-item">
            <a class="nav-link <?= $currentAction === 'dashboard' ? 'active' : '' ?>" href="index.php?action=dashboard">
                <i class="fa-solid fa-chart-line me-2"></i> Tableau de bord
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $currentAction === 'devis_factures' ? 'active' : '' ?>" href="#">
                <i class="fa-solid fa-file-invoice-dollar me-2"></i> Devis / Factures
            </a>
        </li>
    </ul>

    <ul class="nav flex-column mb-3">
        <div class="sidebar-heading">Administration & Système</div>
        <li class="nav-item">
            <a class="nav-link <?= $currentAction === 'teams' ? 'active' : '' ?>" href="#">
                <i class="fa-solid fa-users me-2"></i> Équipes & Rôles
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $currentAction === 'mongo_logs' ? 'active' : '' ?>" href="index.php?action=mongo_logs">
                <i class="fa-solid fa-database me-2"></i> Logs de Sécurité (NoSQL)
            </a>
        </li>
    </ul>

    <ul class="nav flex-column mb-3">
        <div class="sidebar-heading">Profils</div>
        <li class="nav-item">
            <a class="nav-link <?= $currentAction === 'profile' ? 'active' : '' ?>" href="#">
                <i class="fa-solid fa-user me-2"></i> Mon Compte
            </a>
        </li>
        <li class="nav-item mt-4">
            <a class="nav-link text-danger fw-bold" href="index.php?action=logout">
                <i class="fa-solid fa-right-from-bracket me-2"></i> Déconnexion
            </a>
        </li>
    </ul>
</nav>