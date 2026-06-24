<?php
/**
 * Modèle : Log (Persistance Documentaire MongoDB)
 *
 * Gère les opérations de lecture sur la collection d'audit de sécurité
 * et de traçabilité des flux métiers stockés dans MongoDB.
 *
 * @package    InnovEventsManager
 * @subpackage Models/NoSQL
 * @author     Romain Remusat
 * @version    1.0.0
 */

class Log
{
    /** @var \MongoDB\Driver\Manager */
    private $manager;
    private $dbCollection = "innovevents_db.logs"; // Base.Collection

    /**
     * Initialise la connexion au cluster ou conteneur MongoDB.
     */
    public function __construct()
    {
        // Connexion au conteneur Docker "mongodb" sur le port standard 27017
        $this->manager = new \MongoDB\Driver\Manager("mongodb://mongodb:27017");
    }

    /**
     * Récupère les derniers logs d'activité enregistrés.
     *
     * @param int $limit Nombre maximum de documents à retourner.
     * @return array Liste des logs convertis en tableaux associatifs.
     */
    public function getLatestLogs(int $limit = 5): array
    {
        try {
            // Pas de filtre (on veut tout), mais on trie par date décroissante (les plus récents en premier)
            $filter = [];
            $options = [
                'sort' => ['created_at' => -1],
                'limit' => $limit
            ];

            $query = new \MongoDB\Driver\Query($filter, $options);
            $cursor = $this->manager->executeQuery($this->dbCollection, $query);

            // Conversion du curseur en tableau PHP standard
            $logs = [];
            foreach ($cursor as $document) {
                // On transforme l'objet standard en tableau associatif pour le contrôleur
                $logs[] = (array)$document;
            }

            return $logs;
        } catch (\Exception $e) {
            error_log("Erreur lors de la lecture des logs MongoDB : " . $e->getMessage());
            return []; // Fail-safe : retourne un tableau vide pour ne pas faire crash la vue
        }
    }

    /**
     * Enregistre une action dans la base MongoDB (Audit).
     * * @param string $action Libellé de l'action (ex: 'Mise à jour statut')
     * @param string $message Description détaillée
     */
    public function addLog(string $action, string $message): void
    {
        try {
            $bulk = new \MongoDB\Driver\BulkWrite;
            $doc = [
                'action'     => $action,
                'message'    => $message,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'created_at' => new \MongoDB\BSON\UTCDateTime()
            ];
            $bulk->insert($doc);
            $this->manager->executeBulkWrite($this->dbCollection, $bulk);
        } catch (\Exception $e) {
            // En cas d'échec d'écriture, on ne bloque pas l'application
            error_log("Erreur lors de l'écriture du log : " . $e->getMessage());
        }
    }



}