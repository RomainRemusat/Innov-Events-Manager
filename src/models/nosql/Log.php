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
    public function addLog(string $typeAction, string $message, ?int $idUtilisateur = null, array $details = []): void
    {
        try {
            // Optionnel : Si l'ID utilisateur n'est pas fourni, on tente de le récupérer
            // automatiquement depuis la session active s'il existe.
            if ($idUtilisateur === null && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
                $idUtilisateur = (int)$_SESSION['user_id'];
            }

            $bulk = new \MongoDB\Driver\BulkWrite;

            // Structure stricte demandée par le cahier des charges de l'ECF
            $doc = [
                'created_at'     => new \MongoDB\BSON\UTCDateTime(), // Horodatage ISODate
                'type_action'    => $typeAction,                    // Chaîne standardisée (ex: CREATION_CLIENT)
                'id_utilisateur' => $idUtilisateur,                 // ID de l'utilisateur
                'message'        => $message,                       // Ton message descriptif pour le Dashboard
                'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'details'        => $details                        // Objet flexible pour le contexte
            ];

            $bulk->insert($doc);
            $this->manager->executeBulkWrite($this->dbCollection, $bulk);
        } catch (\Exception $e) {
            // En cas d'échec d'écriture, on ne bloque pas l'application (programmation défensive)
            error_log("Erreur lors de l'écriture du log NoSQL : " . $e->getMessage());
        }
    }



}