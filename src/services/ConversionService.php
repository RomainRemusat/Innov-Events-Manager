<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/sql/Company.php';
require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/MailService.php';

/**
 * Service métier : ConversionService
 *
 * Orchestre le workflow transactionnel de conversion d'un prospect en client B2B (AT2).
 * Applique le principe ACID et la persistance polyglotte (MySQL / MongoDB).
 *
 * @package    InnovEventsManager
 * @subpackage Services
 * @author     Romain Remusat
 * @version    2.1.0
 */
class ConversionService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Exécute le processus transactionnel complet de conversion.
     *
     * @param  array      $data        Payload assaini issu du formulaire POST.
     * @param  array|null $file        Fichier uploadé ($_FILES['event_image'] ou null).
     * @param  int|null   $actorUserId Identifiant de l'agent exécutant l'action (audit).
     * @return int Identifiant unique du devis généré (`id_devis`).
     *
     * @throws InvalidArgumentException Si un invariant fonctionnel obligatoire est absent.
     * @throws Exception                En cas de défaillance SQL (rollback automatique).
     */
    public function convertProspectToClient(array $data, ?array $file = null, ?int $actorUserId = null): int
    {
        // ---------------------------------------------------------------------
        // 1. VALIDATION ET NETTOYAGE MÉTIER (Données brutes pour la BDD)
        // ---------------------------------------------------------------------
        $prospectId  = (int)($data['prospect_id'] ?? 0);
        $companyName = trim($data['company_name'] ?? '');
        $contactName = trim($data['contact_name'] ?? '');
        $email       = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone       = trim($data['phone'] ?? '');

        // Données B2B complémentaires
        $siren       = !empty($data['siren']) ? preg_replace('/[^0-9]/', '', $data['siren']) : null;
        $address     = !empty($data['address']) ? trim($data['address']) : null;
        $postalCode  = !empty($data['postal_code']) ? trim($data['postal_code']) : null;
        $city        = !empty($data['city']) ? trim($data['city']) : null;

        // Données de l'événement
        $eventTitle   = trim($data['event_title'] ?? '');
        $startDate    = $data['start_date'] ?? '';
        $location     = trim($data['location'] ?? '');
        $participants = !empty($data['estimated_participants']) ? (int)$data['estimated_participants'] : null;
        $description  = trim($data['description'] ?? '');
        $eventStatus  = trim($data['event_status'] ?? 'brouillon');

        // Invariants fonctionnels
        if (!$prospectId || empty($companyName) || !$email || empty($eventTitle) || empty($startDate) || empty($location)) {
            throw new InvalidArgumentException("Paramètres métier obligatoires manquants ou invalides.");
        }

        // ---------------------------------------------------------------------
        // 2. EXÉCUTION TRANSACTIONNELLE (GARANTIE ACID)
        // ---------------------------------------------------------------------
        $this->db->beginTransaction();

        try {
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

                // Génération mot de passe temporaire robuste (OWASP)
                $tempPassword   = 'Temp_' . bin2hex(random_bytes(4)) . '!2026';
                $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

                $stmtUser = $this->db->prepare("
                    INSERT INTO users (company_id, email, password, firstname, lastname, role, must_change_password) 
                    VALUES (?, ?, ?, ?, ?, 'CLIENT', 1)
                ");
                $stmtUser->execute([$companyId, $email, $hashedPassword, $firstname, $lastname]);
                $clientId = (int)$this->db->lastInsertId();

                // Envoi des identifiants temporaires par courriel
                try {
                    $mailService = new MailService();
                    $mailService->sendTemporaryPasswordEmail($email, $firstname, $tempPassword);
                } catch (Exception $e) {
                    error_log("Avertissement MailService : " . $e->getMessage());
                }
            }

            // C. Traitement du téléversement de l'image (AT2)
            $imagePath = null;
            if ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($file['tmp_name']);

                if (in_array($mimeType, $allowedMimes, true)) {
                    $uploadDir = __DIR__ . '/../../public/uploads/events/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $fileName  = 'event_' . uniqid('', true) . '.' . strtolower($extension);

                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                        $imagePath = 'uploads/events/' . $fileName;
                    }
                }
            }

            // D. Création du projet événementiel (events) avec lieu, participants et image
            $mysqlDate = date('Y-m-d H:i:s', strtotime($startDate));
            $stmtEvent = $this->db->prepare("
                INSERT INTO events (client_id, company_id, title, description, event_date, location, estimated_participants, image_path, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtEvent->execute([
                $clientId,
                $companyId,
                $eventTitle,
                $description,
                $mysqlDate,
                $location,
                $participants,
                $imagePath,
                $eventStatus
            ]);
            $eventId = (int)$this->db->lastInsertId();

            // E. Mise à jour de l'état du prospect (prospects)
            $stmtProspect = $this->db->prepare("
                UPDATE prospects 
                SET status = 'accepté', 
                    user_id = ?, 
                    company_id = ? 
                WHERE id = ?
            ");
            $stmtProspect->execute([$clientId, $companyId, $prospectId]);

            // F. Génération de la coquille financière initiale (devis à 0 €)
            $safePrefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $companyName), 0, 5));
            $refPdf     = "Devis_" . $safePrefix . "_" . date('Ymd_His') . ".pdf";

            $stmtDevis = $this->db->prepare("
                INSERT INTO devis (id_prospect, reference_pdf, montant_ht, tva) 
                VALUES (?, ?, 0.00, 0.00)
            ");
            $stmtDevis->execute([$prospectId, $refPdf]);
            $devisId = (int)$this->db->lastInsertId();

            // Validation définitive des transactions
            $this->db->commit();

            // -----------------------------------------------------------------
            // 3. PERSISTANCE POLYGLOTTE : AUDIT NOSQL MONGODB (AT2)
            // -----------------------------------------------------------------
            $this->logActivity($prospectId, $clientId, $companyId, $eventId, $devisId, $actorUserId, [
                'company_name'           => $companyName,
                'location'               => $location,
                'estimated_participants' => $participants,
                'image_path'             => $imagePath
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
                "Prospect #$prospectId converti en Client #$clientId (Société #$companyId, Événement #$eventId, Devis #$devisId)",
                $actorUserId,
                array_merge([
                    'prospect_id' => $prospectId,
                    'client_id'   => $clientId,
                    'company_id'  => $companyId,
                    'event_id'    => $eventId,
                    'devis_id'    => $devisId
                ], $context)
            );
        } catch (Exception $e) {
            error_log("Erreur Log MongoDB (ConversionProspect) : " . $e->getMessage());
        }
    }
}