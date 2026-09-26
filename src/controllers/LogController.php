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


require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/nosql/Log.php';

/** Affiche aux administrateurs les actions techniques conservées dans MongoDB. */
class LogController extends BaseController
{
    /**
     * Orchestre l'affichage de la page d'audit complet (Logs MongoDB).
     *
     * @return void
     */
    public function showMongoLogs(): void
    {
        $this->checkAuth(['ADMIN']);

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
