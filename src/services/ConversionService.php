<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/sql/Company.php';
require_once __DIR__ . '/../models/sql/Event.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../services/FileUploadService.php';

/**
 * Service métier : ConversionService
 *
 * Crée le client, l'événement et le devis dans une transaction SQL.
 * Supprime l'image créée en cas d'échec ; notifie et journalise après validation SQL.
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
     * Convertit un prospect en client, événement et devis après validation du formulaire.
     *
     * Le verrou SQL du prospect empêche deux conversions simultanées. Un compte
     * existant doit être actif, de rôle CLIENT et rattaché à la même entreprise
     * ou sans entreprise. L'image est supprimée si les écritures SQL échouent.
     * La notification et la journalisation interviennent après le commit.
     *
     * @param  array      $data        Données du formulaire POST à valider.
     * @param  array|null $file        Fichier uploadé ($_FILES['event_image'] ou null).
     * @param  int|null   $actorUserId Identifiant de l'agent exécutant l'action (audit).
     * @return int                     Identifiant unique du devis généré (`id_devis`).
     *
     * @throws InvalidArgumentException Si un invariant fonctionnel obligatoire est absent.
     * @throws Throwable                En cas d'échec de stockage (annulation SQL et nettoyage de l'image).
     */
    public function convertProspectToClient(array $data, ?array $file = null, ?int $actorUserId = null): int
    {
        // ---------------------------------------------------------------------
        // 1. VALIDATION ET NETTOYAGE MÉTIER (Invariants fonctionnels)
        // ---------------------------------------------------------------------
        foreach ($data as $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('Les champs du formulaire doivent contenir une valeur simple.');
            }
        }
        $prospectId = filter_var($data['prospect_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $companyName = trim($data['company_name'] ?? '');
        $contactName = trim($data['contact_name'] ?? '');
        $email       = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone       = trim($data['phone'] ?? '');

        // Données d'immatriculation B2B
        $siren       = trim($data['siren'] ?? '') ?: null;
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
        $participants = filter_var($data['estimated_participants'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        $description  = trim($data['description'] ?? '');
        $eventStatus  = Event::normalizeStatus($data['event_status'] ?? 'brouillon');
        $isPublished  = !empty($data['is_visible']) ? 1 : 0;
        $consentConfirmed = ($data['publication_consent'] ?? '') === '1';
        if ($isPublished && (!$consentConfirmed || !$actorUserId)) {
            throw new InvalidArgumentException("La publication nécessite la confirmation explicite de l'accord client par un administrateur.");
        }

        // Le devis créé par cette transaction est un brouillon : le projet ne peut
        // pas démarrer avant son acceptation commerciale.
        if (!isset(Event::STATUS_LABELS[$eventStatus]) || $eventStatus === 'en cours') {
            throw new InvalidArgumentException("Statut initial non autorisé : le passage en cours nécessite un devis accepté.");
        }

        // Validation stricte des champs obligatoires
        if (!$prospectId || $companyName === '' || $contactName === '' || !$email || $eventTitle === '' || $location === '' || $eventType === '') {
            throw new InvalidArgumentException("Paramètres métier obligatoires manquants ou invalides.");
        }
        if (!preg_match('/^\+?[0-9 () .-]+$/D', $phone) || strlen(preg_replace('/\D/', '', $phone)) < 6) {
            throw new InvalidArgumentException('Le numéro de téléphone est invalide.');
        }
        if ($participants === false) {
            throw new InvalidArgumentException('Le nombre de participants doit être un entier strictement positif.');
        }
        if (mb_strlen($description) < 5 || strlen($description) > 65535) {
            throw new InvalidArgumentException('La description doit comporter au moins 5 caractères et tenir dans 65 535 octets.');
        }
        foreach (['company_name' => 255, 'contact_name' => 255, 'email' => 255, 'phone' => 50,
            'event_title' => 255, 'location' => 255, 'event_type' => 100, 'theme' => 100,
            'address' => 255, 'postal_code' => 10, 'city' => 100] as $field => $maxLength) {
            if (mb_strlen(trim((string)($data[$field] ?? ''))) > $maxLength) {
                throw new InvalidArgumentException("Le champ $field dépasse $maxLength caractères.");
            }
        }
        if ($siren !== null && !preg_match('/^[0-9]{9}$/D', $siren)) {
            throw new InvalidArgumentException('Le SIREN doit contenir exactement 9 chiffres.');
        }
        $mysqlStartDate = $this->parseDate($startDate);
        $mysqlEndDate = $endDate !== null ? $this->parseDate($endDate) : null;
        if ($mysqlEndDate !== null && $mysqlEndDate <= $mysqlStartDate) {
            throw new InvalidArgumentException('La fin de l’événement doit être postérieure à son début.');
        }

        // Variables post-transactionnelles (envois emails après commit)
        $isNewUserCreated = false;
        $newUserEmail     = null;
        $newUserFirstname = null;
        $newUserTempPass  = null;

        // ---------------------------------------------------------------------
        // 2. EXÉCUTION TRANSACTIONNELLE (GARANTIE ACID)
        // ---------------------------------------------------------------------
        $imagePath = null;
        $committed = false;
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
            $stmtUser = $this->db->prepare('SELECT id, role, is_deleted, company_id FROM users WHERE email = ? FOR UPDATE');
            $stmtUser->execute([$email]);
            $existingUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {
                if ($existingUser['role'] !== 'CLIENT' || (int)$existingUser['is_deleted'] !== 0) {
                    throw new InvalidArgumentException('Cette adresse email ne correspond pas à un compte client actif.');
                }
                if ($existingUser['company_id'] !== null && (int)$existingUser['company_id'] !== $companyId) {
                    throw new InvalidArgumentException('Ce compte client est déjà rattaché à une autre entreprise.');
                }
                $clientId = (int)$existingUser['id'];
                $stmtLink = $this->db->prepare("UPDATE users SET company_id = ? WHERE id = ?");
                $stmtLink->execute([$companyId, $clientId]);
            } else {
                // Découpage sécurisé Prénom / Nom
                $nameParts = explode(' ', $contactName, 2);
                $firstname = $nameParts[0];
                $lastname  = $nameParts[1] ?? 'Client';
                if (mb_strlen($firstname) > 100 || mb_strlen($lastname) > 100) {
                    throw new InvalidArgumentException('Le prénom et le nom ne doivent pas dépasser 100 caractères chacun.');
                }

                // Le client devra remplacer ce mot de passe à sa première connexion.
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

            // Une image fournie doit être enregistrée pour poursuivre la conversion.
            if ($file !== null && ($file['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
                $imagePath = (new FileUploadService())->uploadEventImage($file);
                if ($imagePath === null) {
                    throw new RuntimeException("L'image n'a pas pu être enregistrée. La conversion a été annulée.");
                }
            }

            // D. Création du projet événementiel (events)

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
            $committed = true;

            // -----------------------------------------------------------------
            // 3. NOTIFICATION ET JOURNALISATION APRÈS VALIDATION SQL
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

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (!$committed && $imagePath !== null) {
                $absolutePath = __DIR__ . '/../../public/' . $imagePath;
                if (is_file($absolutePath) && !unlink($absolutePath)) {
                    error_log('Impossible de supprimer l’image de la conversion annulée : ' . $imagePath);
                }
            }
            throw $e;
        }
    }

    /**
     * Valide une date de formulaire sans accepter la correction automatique du calendrier.
     *
     * @param string $value Date et heure locales, avec secondes facultatives.
     * @return string Date normalisée pour MySQL.
     * @throws InvalidArgumentException Si le format ou la date est invalide.
     */
    private function parseDate(string $value): string
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date !== false && $date->format($format) === $value && (int)$date->format('Y') >= 1000) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        throw new InvalidArgumentException('La date et l’heure de l’événement sont invalides.');
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
