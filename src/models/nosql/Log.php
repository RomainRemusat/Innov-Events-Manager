<?php

declare(strict_types=1);

/**
 * Modèle : Log (Persistance Documentaire NoSQL MongoDB)
 *
 * Implémente la traçabilité des actions sensibles de sécurité et de gestion.
 *
 * Exigences respectées (ECF Titre CDA) :
 * - AT2 : Traçabilité documentaire NoSQL immuable.
 * - AT1 / RGPD (p. 13) : Anonymisation obligatoire de l'adresse IP collectée.
 * - Structure CDC (p. 12) : Horodatage (ISODate), type_action, id_utilisateur, details.
 *
 * @package    InnovEventsManager
 * @subpackage Models\NoSQL
 * @author     Romain Remusat
 * @version    1.3.0
 */
class Log
{
    private ?\MongoDB\Driver\Manager $manager = null;
    private string $namespace;

    public function __construct()
    {
        try {
            $uri = $_ENV['MONGO_URI'] ?? 'mongodb://mongodb:27017';
            $dbName = $_ENV['MONGO_DATABASE'] ?? 'innovevents_nosql';

            $this->namespace = "{$dbName}.logs";
            $this->manager = new \MongoDB\Driver\Manager($uri);
        } catch (\Throwable $e) {
            error_log("[Log::__construct] Échec connexion MongoDB : " . $e->getMessage());
            $this->manager = null;
        }
    }

    /**
     * Insère un document d'audit dans la collection 'logs'.
     *
     * @param string   $typeAction    Identifiant normalisé (ex: 'MODIFICATION_STATUT_EVENEMENT').
     * @param int|null $idUtilisateur Identifiant SQL ou session active résolue automatiquement.
     * @param array    $details       Métadonnées contextuelles.
     * @return bool
     */
    public function addLog(string $typeAction, ?int $idUtilisateur = null, array $details = []): bool
    {
        if ($this->manager === null) {
            return false;
        }

        try {
            if ($idUtilisateur === null && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
                $idUtilisateur = (int)$_SESSION['user_id'];
            }

            // Anonymisation RGPD du dernier octet IPv4 / prefixe IPv6 (CDC p. 13)
            $rawIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $details['ip_address'] = $this->anonymizeIp($rawIp);

            $bulk = new \MongoDB\Driver\BulkWrite();
            $bulk->insert([
                'Horodatage'     => new \MongoDB\BSON\UTCDateTime(),
                'type_action'    => trim(strtoupper($typeAction)),
                'id_utilisateur' => $idUtilisateur,
                'details'        => $details
            ]);

            $result = $this->manager->executeBulkWrite($this->namespace, $bulk);
            return $result->getInsertedCount() === 1;
        } catch (\Throwable $e) {
            error_log(sprintf("[Log::addLog] Erreur insertion log '%s' : %s", $typeAction, $e->getMessage()));
            return false;
        }
    }

    /**
     * Récupère l'historique des derniers logs pour la vue technique.
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getLatestLogs(int $limit = 20): array
    {
        if ($this->manager === null) {
            return [];
        }

        try {
            $query = new \MongoDB\Driver\Query([], [
                'sort'  => ['Horodatage' => -1],
                'limit' => $limit
            ]);

            $cursor = $this->manager->executeQuery($this->namespace, $query);
            $logs = [];

            foreach ($cursor as $doc) {
                $item = (array)$doc;
                $dateFormatted = 'N/A';

                if (isset($item['Horodatage']) && $item['Horodatage'] instanceof \MongoDB\BSON\UTCDateTime) {
                    $dateFormatted = $item['Horodatage']->toDateTime()->format('d/m/Y H:i:s');
                }

                $logs[] = [
                    'id'             => (string)$item['_id'],
                    'timestamp'      => $dateFormatted,
                    'type_action'    => (string)($item['type_action'] ?? 'NON_DEFINI'),
                    'id_utilisateur' => isset($item['id_utilisateur']) ? (int)$item['id_utilisateur'] : null,
                    'details'        => isset($item['details']) ? (array)$item['details'] : []
                ];
            }

            return $logs;
        } catch (\Throwable $e) {
            error_log("[Log::getLatestLogs] Erreur lecture : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère le motif d'une demande de modification client sur un devis.
     *
     * @param int $devisId
     * @return string|null
     */
    public function getLatestChangeReason(int $devisId): ?string
    {
        if ($this->manager === null) {
            return null;
        }

        try {
            $filter = [
                'type_action'      => 'REPONSE_DEVIS_CLIENT',
                'details.devis_id' => $devisId,
                'details.action'   => 'request_change'
            ];

            $query = new \MongoDB\Driver\Query($filter, [
                'sort'  => ['Horodatage' => -1],
                'limit' => 1
            ]);

            $cursor = $this->manager->executeQuery($this->namespace, $query);

            foreach ($cursor as $doc) {
                $data = (array)$doc;
                if (isset($data['details'])) {
                    $details = (array)$data['details'];
                    if (!empty($details['change_reason'])) {
                        return (string)$details['change_reason'];
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("[Log::getLatestChangeReason] Erreur lecture motif : " . $e->getMessage());
        }

        return null;
    }

    /**
     * Masque le dernier octet (IPv4) pour respecter l'anonymisation RGPD.
     */
    private function anonymizeIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed !== false) {
                $mask = inet_pton('ffff:ffff:ffff::');
                return inet_ntop($packed & $mask);
            }
        }

        return '127.0.0.0';
    }
}