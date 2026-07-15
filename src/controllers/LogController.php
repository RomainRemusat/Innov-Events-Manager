<?php

/**
 * Contrôleur : LogController (Audit & Sécurité)
 *
 * Ce contrôleur est dédié exclusivement à la gestion et à l'affichage
 * des historiques de traçabilité issus de la base NoSQL (MongoDB).
 * Il respecte le Principe de Responsabilité Unique (SRP).
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.0.0
 */


/*
J'ai commencé par tout grouper, mais je me suis vite rendu compte que mon contrôleur principal s'alourdissait.
Pour respecter le principe de Responsabilité Unique (SRP) et isoler ma logique NoSQL,
j'ai refactorisé mon code en créant un LogController dédié. Cela rend mon code beaucoup plus évolutif.
*/


require_once __DIR__ . '/../models/nosql/Log.php';

class LogController
{
    /**
     * Orchestre l'affichage de la page d'audit complet (Logs MongoDB).
     *
     * @return void
     */
    public function showMongoLogs(): void
    {
        // 1. Contrôle de sécurité restrictif
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // 2. Extraction des données via le modèle NoSQL
        $logModel = new Log();
        $allLogs = $logModel->getLatestLogs(100);

        // 3. Préparation et rendu de la vue
        $pageTitle = "Journal d'Audit (NoSQL) - Innov'Events";
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/logs.php';
        require __DIR__ . '/../views/partials/footer.php';

    }
}