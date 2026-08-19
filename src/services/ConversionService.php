<?php


/**
 * Service métier dédié à la conversion des prospects en clients.
 *
 * Encapsule la logique d'affaires complexe de l'Activité Type 2 (AT2) :
 * - Validation et assainissement des entrées.
 * - Garantie de l'intégrité transactionnelle (ACID) en base relationnelle (MySQL).
 * - Persistance polyglotte avec journalisation d'audit immuable (MongoDB).
 *
 * @package    InnovEventsManager
 * @subpackage Services
 * @author     Romain Remusat
 * @version    1.3.0
 */
class ConversionService
{
    /**
     * Instance de connexion à la base de données (PDO).
     *
     * @var PDO
     */
    private PDO $db;

    /**
     * Initialise le service avec le singleton de connexion BDD.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Orchestre le workflow complet de conversion d'un prospect.
     *
     * Opérations exécutées :
     * 1. Nettoyage et validation des données d'entrée.
     * 2. Création ou association du compte utilisateur (Role: CLIENT).
     * 3. Bascule du statut du prospect en 'accepté'.
     * 4. Création du projet événementiel associé.
     * 5. Génération du devis de référence à zéro euro (coquille financière).
     * 6. Écriture de la trace d'audit dans la BDD NoSQL.
     *
     * @param  array    $data        Données brutes issues du formulaire POST.
     * @param  int|null $actorUserId Identifiant de l'agent exécutant l'action (pour audit).
     *
     * @return int Identifiant unique du devis généré (`id_devis`).
     *
     * @throws InvalidArgumentException Si une contrainte de validation métier échoue.
     * @throws Exception                 En cas de défaillance SQL (rollback automatique).
     */
    public function convertProspectToClient(array $data, ?int $actorUserId): int
    {
        // --- 1. ASSAINISSEMENT & VALIDATION MÉTIER ---
        $prospectId  = (int)($data['prospect_id'] ?? 0);
        $companyName = htmlspecialchars(trim($data['company_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $contactName = htmlspecialchars(trim($data['contact_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email       = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $eventTitle  = htmlspecialchars(trim($data['event_title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(trim($data['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $startDate   = $data['start_date'] ?? '';
        $location    = htmlspecialchars(trim($data['location'] ?? ''), ENT_QUOTES, 'UTF-8');
        $eventStatus = htmlspecialchars(trim($data['event_status'] ?? 'brouillon'), ENT_QUOTES, 'UTF-8');

        // Validation des champs obligatoires (Invariant métier)
        if (!$prospectId || !$email || empty($eventTitle) || empty($startDate) || empty($location)) {
            throw new InvalidArgumentException("Paramètres métier manquants ou invalides.");
        }

        // --- 2. EXÉCUTION TRANSACTIONNELLE (GARANTIE ACID) ---
        $this->db->beginTransaction();

        try {
            // A. Gestion du compte Client (Liaison si existant, création sinon)
            $stmtCheck = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            $existingUser = $stmtCheck->fetch();

            if ($existingUser) {
                $clientId = (int)$existingUser['id'];
            } else {
                // Génération d'un mot de passe temporaire conforme aux règles OWASP
                $tempPassword   = 'Temp_' . bin2hex(random_bytes(4)) . '!Z';
                $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

                $nameParts = explode(' ', $contactName, 2);
                $firstname = $nameParts[0];
                $lastname  = $nameParts[1] ?? 'Contact';

                $stmtUser = $this->db->prepare("
                    INSERT INTO users (email, password, firstname, lastname, role, must_change_password) 
                    VALUES (?, ?, ?, ?, 'CLIENT', 1)
                ");
                $stmtUser->execute([$email, $hashedPassword, $firstname, $lastname]);
                $clientId = (int)$this->db->lastInsertId();
            }

            // B. Mise à jour de l'état du prospect
            $stmtProspect = $this->db->prepare("UPDATE prospects SET status = 'accepté', user_id = ? WHERE id = ?");
            $stmtProspect->execute([$clientId, $prospectId]);

            // C. Création de l'événement lié
            $mysqlDate = date('Y-m-d H:i:s', strtotime($startDate));
            $stmtEvent = $this->db->prepare("
                INSERT INTO events (client_id, title, description, event_date, location, status) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtEvent->execute([$clientId, $eventTitle, $description, $mysqlDate, $location, $eventStatus]);

            // D. Génération de la coquille financière (Devis initial)
            $refPdf = "Devis_" . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $companyName), 0, 5)) . "_" . date('Ymd_His') . ".pdf";
            $stmtDevis = $this->db->prepare("INSERT INTO devis (id_prospect, reference_pdf, montant_ht, tva) VALUES (?, ?, 0, 0)");
            $stmtDevis->execute([$prospectId, $refPdf]);
            $devisId = (int)$this->db->lastInsertId();

            // Validation définitive des écritures SQL
            $this->db->commit();

            // --- 3. TRAÇABILITÉ NOSQL (NON BLOQUANTE) ---
            $this->logActivity($prospectId, $devisId, $actorUserId);

            return $devisId;

        } catch (Exception $e) {
            // Annulation stricte en cas d'erreur SQL pour éviter l'incohérence des données
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Enregistre la trace d'audit de la conversion dans MongoDB.
     *
     * Isolé dans un bloc try/catch pour éviter qu'une défaillance du serveur de logs
     * ne stoppe un traitement métier déjà validé en BDD relationnelle.
     *
     * @param int      $prospectId  ID du prospect converti.
     * @param int      $devisId     ID du devis généré.
     * @param int|null $actorUserId ID de l'utilisateur ayant exécuté l'action.
     *
     * @return void
     */
    private function logActivity(int $prospectId, int $devisId, ?int $actorUserId): void
    {
        try {
            $logModel = new Log();
            $logModel->addLog(
                "CONVERSION_PROSPECT",
                "Prospect #$prospectId converti en client (Devis #$devisId généré).",
                $actorUserId
            );
        } catch (Exception $e) {
            error_log("Erreur Log MongoDB (ConversionProspect) : " . $e->getMessage());
        }
    }
}