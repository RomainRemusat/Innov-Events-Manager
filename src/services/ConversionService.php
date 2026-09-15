<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/sql/Company.php';
require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/sql/Event.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../services/FileUploadService.php';

/**
 * Service métier : ConversionService
 *
 * Orchestre le workflow transactionnel de conversion d'un prospect en client B2B (AT2).
 * Applique le principe ACID et la persistance polyglotte (MySQL / MongoDB).
 *
 * Exigences respectées (ECF) :
 * - AT1 : Sécurisation de la création de compte client et hachage OWASP (Bcrypt).
 * - AT2 : Gestion transactionnelle MySQL (ACID) et journalisation d'audit NoSQL MongoDB.
 *
 * @package    InnovEventsManager
 * @subpackage Services
 * @author     Romain Remusat
 * @version    2.4.0
 */
class ConversionService
{
    /**
     * Instance de connexion PDO à la base de données MySQL.
     *
     * @var PDO
     */
    private PDO $db;

    /**
     * Initialise le service via le singleton de connexion PDO.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Exécute le processus transactionnel complet de conversion d'un prospect en client B2B.
     *
     * Workflow métier transactionnel (ACID) :
     * 1. Nettoyage et validation des invariants fonctionnels.
     * 2. Création ou enrichissement de l'entreprise morale B2B (`companies`).
     * 3. Création du compte utilisateur client avec identifiants temporaires (`users`).
     * 4. Téléversement et enregistrement de l'image d'illustration de l'événement via FileUploadService.
     * 5. Création du projet événementiel au statut initial (`events`).
     * 6. Passage du prospect au statut 'converti' (`prospects`).
     * 7. Génération de la coquille financière initiale au statut 'brouillon' (`devis`).
     * 8. Journalisation d'audit dans la base orientée documents MongoDB (`logs`).
     *
     * @param  array      $data        Payload assaini issu du formulaire POST.
     * @param  array|null $file        Fichier uploadé ($_FILES['event_image'] ou null).
     * @param  int|null   $actorUserId Identifiant de l'agent exécutant l'action (audit).
     * @return int                     Identifiant unique du devis généré (`id_devis`).
     *
     * @throws InvalidArgumentException Si un invariant fonctionnel obligatoire est absent.
     * @throws Exception                En cas de défaillance SQL (rollback automatique).
     */
    public function convertProspectToClient(array $data, ?array $file = null, ?int $actorUserId = null): int
    {
        // ---------------------------------------------------------------------
        // 1. VALIDATION ET NETTOYAGE MÉTIER (Invariants fonctionnels)
        // ---------------------------------------------------------------------
        $prospectId  = (int)($data['prospect_id'] ?? 0);
        $companyName = trim($data['company_name'] ?? '');
        $contactName = trim($data['contact_name'] ?? '');
        $email       = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone       = trim($data['phone'] ?? '');

        // Données d'immatriculation B2B
        $siren       = !empty($data['siren']) ? preg_replace('/[^0-9]/', '', $data['siren']) : null;
        $address     = !empty($data['address']) ? trim($data['address']) : null;
        $postalCode  = !empty($data['postal_code']) ? trim($data['postal_code']) : null;
        $city        = !empty($data['city']) ? trim($data['city']) : null;

        // Données du projet événementiel
        $eventTitle   = trim($data['event_title'] ?? '');
        $startDate    = $data['start_date'] ?? '';
        $endDate      = !empty($data['end_date']) ? $data['end_date'] : null;
        $location     = trim($data['location'] ?? '');
        $eventType    = trim($data['event_type'] ?? 'Autre');
        $theme        = !empty($data['theme']) ? trim($data['theme']) : null;
        $participants = !empty($data['estimated_participants']) ? (int)$data['estimated_participants'] : null;
        $description  = trim($data['description'] ?? '');
        $eventStatus  = Event::normalizeStatus($data['event_status'] ?? 'brouillon');
        $isPublished  = !empty($data['is_visible']) ? 1 : 0;
        $consentConfirmed = ($data['publication_consent'] ?? '') === '1';
        if ($isPublished && (!$consentConfirmed || !$actorUserId)) {
            throw new InvalidArgumentException("La publication nécessite la confirmation explicite de l'accord client par un administrateur.");
        }

        // Le devis créé par cette transaction est un brouillon : le projet ne peut
        // pas démarrer avant son acceptation commerciale (ECF, p. 12).
        if (!isset(Event::STATUS_LABELS[$eventStatus]) || $eventStatus === 'en cours') {
            throw new InvalidArgumentException("Statut initial non autorisé : le passage en cours nécessite un devis accepté.");
        }

        // Validation stricte des champs obligatoires
        if (!$prospectId || empty($companyName) || !$email || empty($eventTitle) || empty($startDate) || empty($location)) {
            throw new InvalidArgumentException("Paramètres métier obligatoires manquants ou invalides.");
        }

        // Variables post-transactionnelles (envois emails après commit)
        $isNewUserCreated = false;
        $newUserEmail     = null;
        $newUserFirstname = null;
        $newUserTempPass  = null;

        // ---------------------------------------------------------------------
        // 2. EXÉCUTION TRANSACTIONNELLE (GARANTIE ACID)
        // ---------------------------------------------------------------------
        $this->db->beginTransaction();

        try {
            // Partagé avec la qualification : une décision concurrente ne doit pas
            // convertir un dossier refusé ni permettre une seconde conversion.
            $stmtCheck = $this->db->prepare('SELECT status FROM prospects WHERE id = ? FOR UPDATE');
            $stmtCheck->execute([$prospectId]);
            $currentStatus = $stmtCheck->fetchColumn();
            if ($currentStatus === false || in_array($currentStatus, ['converti', 'échoué'], true)) {
                throw new InvalidArgumentException('Prospect introuvable, déjà converti ou échoué. Requalifiez une demande échouée avant conversion.');
            }
            // A. Gestion de l'entité morale B2B (companies)
            $companyModel = new Company();
            $companyId = $companyModel->findOrCreateAndEnrich($companyName, $siren, $address, $postalCode, $city);

            // B. Gestion du compte utilisateur Client (users)
            $userModel = new User();
            $existingUser = $userModel->findByEmail($email);

            if ($existingUser) {
                $clientId = (int)$existingUser['id'];
                $stmtLink = $this->db->prepare("UPDATE users SET company_id = ? WHERE id = ?");
                $stmtLink->execute([$companyId, $clientId]);
            } else {
                // Découpage sécurisé Prénom / Nom
                $nameParts = explode(' ', $contactName, 2);
                $firstname = $nameParts[0];
                $lastname  = $nameParts[1] ?? 'Client';

                // Génération d'un mot de passe temporaire robuste (Normes OWASP)
                $tempPassword   = 'Temp_' . bin2hex(random_bytes(4)) . '!2026';
                $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

                $stmtUser = $this->db->prepare("
                    INSERT INTO users (company_id, email, password, firstname, lastname, role, must_change_password) 
                    VALUES (?, ?, ?, ?, ?, 'CLIENT', 1)
                ");
                $stmtUser->execute([$companyId, $email, $hashedPassword, $firstname, $lastname]);
                $clientId = (int)$this->db->lastInsertId();

                $isNewUserCreated = true;
                $newUserEmail     = $email;
                $newUserFirstname = $firstname;
                $newUserTempPass  = $tempPassword;
            }

            // C. Traitement du téléversement sécurisé de l'image d'illustration (OWASP CWE-434)
            $imagePath = null;
            if ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
                try {
                    $uploadService = new FileUploadService(5 * 1024 * 1024);
                    $uploadDir = __DIR__ . '/../../public/uploads/events/';
                    $fileName = $uploadService->uploadImage($file, $uploadDir, 'event_');
                    if ($fileName !== null) {
                        $imagePath = 'uploads/events/' . $fileName;
                    }
                } catch (\InvalidArgumentException $e) {
                    error_log("Avertissement FileUploadService : " . $e->getMessage());
                }
            }

            // D. Création du projet événementiel (events)
            $mysqlStartDate = date('Y-m-d H:i:s', strtotime($startDate));
            $mysqlEndDate   = $endDate ? date('Y-m-d H:i:s', strtotime($endDate)) : null;

            $stmtEvent = $this->db->prepare("
                INSERT INTO events (client_id, company_id, title, description, start_date, end_date, location, event_type, theme, estimated_participants, image_path, status, is_published)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtEvent->execute([
                $clientId,
                $companyId,
                $eventTitle,
                $description,
                $mysqlStartDate,
                $mysqlEndDate,
                $location,
                $eventType,
                $theme,
                $participants,
                $imagePath,
                $eventStatus,
                0
            ]);
            $eventId = (int)$this->db->lastInsertId();
            // L'accord et la visibilité sont enregistrés dans la même transaction que le projet.
            if ($isPublished && !(new Event())->setPublication($eventId, true, $consentConfirmed, $actorUserId)) {
                throw new InvalidArgumentException("L'accord de publication n'a pas pu être enregistré.");
            }

            // E. Mise à jour des coordonnées et passage du prospect au statut 'converti'
            $stmtProspect = $this->db->prepare("
                UPDATE prospects 
                SET status = 'converti', 
                    user_id = ?, 
                    company_id = ?,
                    company_name = ?,
                    contact_name = ?,
                    email = ?,
                    phone = ?,
                    location = ?,
                    event_date = ?,
                    event_type = ?,
                    estimated_participants = ?,
                    description = ?
                WHERE id = ?
            ");
            $stmtProspect->execute([
                $clientId,
                $companyId,
                $companyName,
                $contactName,
                $email,
                $phone,
                $location,
                substr($mysqlStartDate, 0, 10),
                $eventType,
                $participants,
                $description,
                $prospectId
            ]);

            // F. Génération de la coquille financière initiale au statut 'brouillon' (Table devis)
            $safePrefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $companyName), 0, 5));
            $refPdf     = "Devis_" . $safePrefix . "_" . $prospectId . "_" . date('Ymd_His') . ".pdf";

            $stmtDevis = $this->db->prepare("
                INSERT INTO devis (id_prospect, event_id, reference_pdf, montant_ht, tva, status)
                VALUES (?, ?, ?, 0.00, 0.00, 'brouillon')
            ");
            $stmtDevis->execute([$prospectId, $eventId, $refPdf]);
            $devisId = (int)$this->db->lastInsertId();

            // Commit final de la transaction MySQL
            $this->db->commit();

            // -----------------------------------------------------------------
            // 3. ACTIONS POST-TRANSACTION : EMAILS & AUDIT NOSQL MONGODB (AT2)
            // -----------------------------------------------------------------
            if ($isNewUserCreated && $newUserEmail && $newUserTempPass) {
                try {
                    $mailService = new MailService();
                    $mailService->sendTemporaryPasswordEmail($newUserEmail, $newUserFirstname, $newUserTempPass);
                } catch (\Exception $e) {
                    error_log("Avertissement MailService post-conversion : " . $e->getMessage());
                }
            }

            $this->logActivity($prospectId, $clientId, $companyId, $eventId, $devisId, $actorUserId, [
                'company_name'           => $companyName,
                'location'               => $location,
                'estimated_participants' => $participants,
                'image_path'             => $imagePath,
                'publication_requested'  => (bool)$isPublished,
                'publication_consent_confirmed' => (bool)$isPublished && $consentConfirmed
            ]);

            return $devisId;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Enregistre l'empreinte d'audit dans la collection MongoDB `logs`.
     *
     * @param int      $prospectId  Identifiant du prospect converti.
     * @param int      $clientId    Identifiant de l'utilisateur client lié.
     * @param int      $companyId   Identifiant de la société B2B.
     * @param int      $eventId     Identifiant du projet événementiel créé.
     * @param int      $devisId     Identifiant du devis initialisé.
     * @param int|null $actorUserId Identifiant de l'administrateur à l'origine de l'action.
     * @param array    $context     Métadonnées contextuelles additionnelles.
     * @return void
     */
    private function logActivity(
        int $prospectId,
        int $clientId,
        int $companyId,
        int $eventId,
        int $devisId,
        ?int $actorUserId,
        array $context = []
    ): void {
        try {
            $logModel = new Log();
            $logModel->addLog(
                "CONVERSION_PROSPECT",
                $actorUserId,
                array_merge([
                    'message' => "Prospect #$prospectId converti en Client #$clientId (Société #$companyId, Événement #$eventId, Devis #$devisId)",
                    'prospect_id' => $prospectId,
                    'client_id'   => $clientId,
                    'company_id'  => $companyId,
                    'event_id'    => $eventId,
                    'devis_id'    => $devisId
                ], $context)
            );
        } catch (Exception $e) {
            error_log("Erreur MongoDB lors de la conversion : " . $e->getMessage());
        }
    }
}
