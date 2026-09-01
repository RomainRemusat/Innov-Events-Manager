<?php
/**
 * Modèle : Log (Persistance Documentaire MongoDB)
 *
 * Gère les opérations de lecture et d'écriture sur la collection d'audit
 * de sécurité et de traçabilité des flux métiers stockés dans MongoDB via le driver natif.
 *
 * Exigences respectées (ECF) :
 * - AT2 : Traçabilité des actions et journalisation d'audit NoSQL.
 *
 * @package    InnovEventsManager
 * @subpackage Models/NoSQL
 * @author     Romain Remusat
 * @version    1.2.0
 */

class Log
{
    /**
     * Manager de connexion au driver natif MongoDB.
     *
     * @var \MongoDB\Driver\Manager
     */
    private \MongoDB\Driver\Manager $manager;

    /**
     * Namespace de la collection MongoDB (Base.Collection).
     *
     * @var string
     */
    private string $dbCollection = "innovevents_db.logs";

    /**
     * Initialise la connexion au conteneur Docker "mongodb".
     */
    public function __construct()
    {
        $this->manager = new \MongoDB\Driver\Manager("mongodb://mongodb:27017");
    }

    /**
     * Récupère les derniers logs d'activité enregistrés.
     *
     * @param  int $limit Nombre maximum de documents à retourner.
     * @return array      Liste des logs convertis en tableaux associatifs.
     */
    public function getLatestLogs(int $limit = 5): array
    {
        try {
            $filter = [];
            $options = [
                'sort'  => ['created_at' => -1],
                'limit' => $limit
            ];

            $query = new \MongoDB\Driver\Query($filter, $options);
            $cursor = $this->manager->executeQuery($this->dbCollection, $query);

            $logs = [];
            foreach ($cursor as $document) {
                $logs[] = (array)$document;
            }

            return $logs;
        } catch (\Exception $e) {
            error_log("Erreur lors de la lecture des logs MongoDB : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Enregistre une action d'audit dans la base NoSQL MongoDB.
     *
     * @param  string   $typeAction    Libellé normalisé de l'action (ex: REPONSE_DEVIS_CLIENT).
     * @param  string   $message       Description textuelle de l'événement.
     * @param  int|null $idUtilisateur ID de l'utilisateur ou null (session active).
     * @param  array    $details       Métadonnées et contexte de l'action.
     * @return void
     */
    public function addLog(string $typeAction, string $message, ?int $idUtilisateur = null, array $details = []): void
    {
        try {
            if ($idUtilisateur === null && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
                $idUtilisateur = (int)$_SESSION['user_id'];
            }

            $bulk = new \MongoDB\Driver\BulkWrite;

            $doc = [
                'created_at'     => new \MongoDB\BSON\UTCDateTime(),
                'type_action'    => $typeAction,
                'id_utilisateur' => $idUtilisateur,
                'message'        => $message,
                'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'details'        => $details
            ];

            $bulk->insert($doc);
            $this->manager->executeBulkWrite($this->dbCollection, $bulk);
        } catch (\Exception $e) {
            error_log("Erreur lors de l'écriture du log NoSQL : " . $e->getMessage());
        }
    }

    /**
     * Récupère le dernier motif de modification soumis par le client pour un devis donné.
     *
     * Exploite le driver natif \MongoDB\Driver\Query pour effectuer un filtre
     * sur les champs imbriqués du document d'audit `type_action` et `details`.
     *
     * @param  int $devisId Identifiant du devis cible.
     * @return string|null  Le motif saisi par le client ou null si introuvable.
     */
    public function getLatestChangeReason(int $devisId): ?string
    {
        try {
            $filter = [
                'type_action'      => 'REPONSE_DEVIS_CLIENT',
                'details.devis_id' => $devisId,
                'details.action'   => 'request_change'
            ];
            $options = [
                'sort'  => ['created_at' => -1],
                'limit' => 1
            ];

            $query = new \MongoDB\Driver\Query($filter, $options);
            $cursor = $this->manager->executeQuery($this->dbCollection, $query);

            foreach ($cursor as $document) {
                $docArray = (array)$document;
                if (isset($docArray['details'])) {
                    $details = (array)$docArray['details'];
                    if (!empty($details['change_reason'])) {
                        return (string)$details['change_reason'];
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Erreur lors de la lecture du motif de modification NoSQL : " . $e->getMessage());
        }

        return null;
    }
}