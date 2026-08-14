<?php
/**
 * Contrôleur : DashboardController (Espace d'administration sécurisé)
 *
 * Ce contrôleur orchestre la logique métier de l'espace privé (Back-Office) d'Innov'Events.
 * Il agit comme un point de contrôle (Guard Pattern) en vérifiant systématiquement
 * les habilitations (Session/Rôles) avant d'autoriser l'accès aux données sensibles.
 *
 * Il implémente la logique de l'Activité Type 2 (AT2) en gérant le cycle de vie
 * des prospects, la génération des devis, et la double persistance (MySQL / MongoDB).
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.2.0
 */

require_once __DIR__ . '/../models/sql/Prospect.php';
require_once __DIR__ . '/../models/nosql/Log.php'; // Nécessaire pour la journalisation MongoDB

class DashboardController
{
    /**
     * Orchestre l'affichage du tableau de bord d'administration.
     *
     * Agrège les données provenant de multiples sources (MySQL pour les entités
     * métiers, MongoDB pour le flux d'audit) afin de générer les indicateurs
     * de performance (KPIs) en temps réel.
     *
     * @return void
     */
    public function showDashboard(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clause de garde (Guard Clause) : Redirection sécurisée si non authentifié
        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // 1. Data Access Layer : Récupération des entités relationnelles (MySQL)
        $prospectModel = new Prospect();
        $prospects = $prospectModel->findAll();

        // 2. Data Access Layer : Récupération du flux d'audit (MongoDB - AT2)
        $logModel = new Log();
        $activityLogs = $logModel->getLatestLogs(5);

        $pageTitle = "Tableau de Bord - Innov'Events";

        // Layout Pattern : Assemblage modulaire de la vue
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/dashboard.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Affiche les détails complets d'un prospect spécifique.
     *
     * Agit en tant qu'interface décisionnelle permettant à l'administrateur
     * d'engager le tunnel de conversion ou de modifier le statut du lead.
     *
     * @param int $id Identifiant unique du prospect (Clé primaire)
     * @return void
     */
    public function showProspectDetails(int $id): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $prospectModel = new Prospect();
        $prospect = $prospectModel->find($id);

        // Fallback de sécurité (Soft 404) si l'ID a été manipulé ou purgé (RGPD)
        if (!$prospect) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        $pageTitle = "Détails du Prospect - " . htmlspecialchars($prospect['company_name'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/view_prospect.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Affiche le formulaire de conversion (Prospect vers Client/Devis).
     *
     * Pré-remplit les champs avec les données d'acquisition du prospect
     * pour optimiser l'UX de l'administrateur (Exigence Métier AT2).
     *
     * @param int $id Identifiant du prospect à convertir
     * @return void
     */
    public function showConvertForm(int $id): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Contrôle d'habilitation strict (RBAC) : Seuls ADMIN et EMPLOYEE peuvent convertir
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        $prospectModel = new Prospect();
        $prospect = $prospectModel->find($id);

        if (!$prospect) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        $pageTitle = "Convertir Prospect - " . htmlspecialchars($prospect['company_name'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/convert_prospect.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Exécute le workflow transactionnel de conversion d'un prospect (AT2).
     *
     * Cette méthode critique applique le principe ACID via une transaction SQL.
     * Elle orchestre :
     * 1. La création du compte Client (Users).
     * 2. La bascule d'état du Prospect (Status = accepté).
     * 3. La création du projet (Events).
     * 4. L'initialisation du bordereau financier (Devis).
     * 5. La journalisation métier (MongoDB).
     *
     * @param array $postData Payload du formulaire POST
     * @return void
     * @throws \PDOException En cas d'échec d'intégrité référentielle
     */
    public function processConversion(array $postData): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // Protection Anti-CSRF
        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }

        // Assainissement strict (Data Sanitization)
        $prospectId  = (int)($postData['prospect_id'] ?? 0);
        $companyName = htmlspecialchars(trim($postData['company_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $contactName = htmlspecialchars(trim($postData['contact_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email       = filter_var(trim($postData['email'] ?? ''), FILTER_VALIDATE_EMAIL);

        $eventTitle  = htmlspecialchars(trim($postData['event_title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(trim($postData['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $startDate   = $postData['start_date'] ?? '';
        $location    = htmlspecialchars(trim($postData['location'] ?? ''), ENT_QUOTES, 'UTF-8');
        $eventStatus = htmlspecialchars(trim($postData['event_status'] ?? 'brouillon'), ENT_QUOTES, 'UTF-8');

        if (!$prospectId || !$email || empty($eventTitle) || empty($startDate) || empty($location)) {
            die("Erreur de validation : Paramètres métier manquants.");
        }

        try {
            require_once __DIR__ . '/../config/Database.php';
            $db = Database::getInstance();

            // Démarrage de la transaction SQL (Garantie d'intégrité AT2)
            $db->beginTransaction();

            // A. Gestion du compte utilisateur (Création ou liaison existante)
            $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            $existingUser = $stmtCheck->fetch();

            if ($existingUser) {
                $clientId = (int)$existingUser['id'];
            } else {
                // Génération d'un mot de passe temporaire robuste
                $tempPassword = 'Temp_' . bin2hex(random_bytes(4)) . '!Z';
                $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

                $nameParts = explode(' ', $contactName, 2);
                $firstname = $nameParts[0];
                $lastname = $nameParts[1] ?? 'Contact';

                $stmtUser = $db->prepare("INSERT INTO users (email, password, firstname, lastname, role, must_change_password) VALUES (?, ?, ?, ?, 'CLIENT', 1)");
                $stmtUser->execute([$email, $hashedPassword, $firstname, $lastname]);
                $clientId = (int)$db->lastInsertId();
            }

            // B. Mise à jour de l'état du prospect
            $stmtProspect = $db->prepare("UPDATE prospects SET status = 'accepté', user_id = ? WHERE id = ?");
            $stmtProspect->execute([$clientId, $prospectId]);

            // C. Instanciation du projet événementiel
            $mysqlDate = date('Y-m-d H:i:s', strtotime($startDate));
            $stmtEvent = $db->prepare("INSERT INTO events (client_id, title, description, event_date, location, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtEvent->execute([$clientId, $eventTitle, $description, $mysqlDate, $location, $eventStatus]);
            $eventId = (int)$db->lastInsertId();

            // D. Génération de la coquille financière (Devis)
            $refPdf = "Devis_" . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $companyName), 0, 5)) . "_" . date('Ymd_His') . ".pdf";
            $stmtDevis = $db->prepare("INSERT INTO devis (id_prospect, reference_pdf, montant_ht, tva) VALUES (?, ?, 0, 0)");
            $stmtDevis->execute([$prospectId, $refPdf]);
            $devisId = (int)$db->lastInsertId();

            // Validation des écritures
            $db->commit();

            // E. Trace d'audit (NoSQL)
            try {
                $logModel = new Log();
                $logModel->addLog("CONVERSION_PROSPECT", "Prospect #$prospectId converti en client (Devis #$devisId généré).", $_SESSION['user_id'] ?? null);
            } catch (\Exception $e) {
                error_log("Erreur Log MongoDB : " . $e->getMessage());
            }

            // Pattern PRG : Redirection vers l'outil d'édition financière
            header("Location: index.php?action=edit_devis&id=" . $devisId);
            exit();

        } catch (\PDOException $e) {
            // Annulation totale en cas d'erreur métier ou réseau
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("CRASH TRANSACTION CONVERSION : " . $e->getMessage());
            header('Location: index.php?action=dashboard');
            exit();
        }
    }

    /**
     * Affiche l'interface de composition du devis (Ajout de prestations).
     *
     * @param int $devisId Identifiant unique du devis (id_devis)
     * @return void
     */
    public function editDevis(int $devisId): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        require_once __DIR__ . '/../config/Database.php';
        $db = Database::getInstance();

        // Récupération des données du devis avec jointure sur le prospect (Rappel UX)
        $stmt = $db->prepare("
            SELECT d.*, p.* 
            FROM devis d
            JOIN prospects p ON d.id_prospect = p.id
            WHERE d.id_devis = ?
        ");
        $stmt->execute([$devisId]);
        $devis = $stmt->fetch();

        if (!$devis) {
            header('Location: index.php?action=dashboard');
            exit();
        }

        // Récupération des lignes de facturation associées
        $stmtPrest = $db->prepare("SELECT * FROM prestations WHERE devis_id = ? ORDER BY id ASC");
        $stmtPrest->execute([$devisId]);
        $prestations = $stmtPrest->fetchAll();

        $pageTitle = "Édition Devis - " . htmlspecialchars($devis['company_name'], ENT_QUOTES, 'UTF-8');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/edit_devis.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Ajoute une nouvelle ligne de prestation commerciale au devis.
     *
     * Applique le pattern PRG (Post-Redirect-Get) pour sécuriser l'insertion
     * et forcer le recalcul automatique des totaux financiers (HT/TVA/TTC).
     *
     * @param array $postData Payload du formulaire d'ajout
     * @return void
     */
    public function addPrestation(array $postData): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            die("Accès refusé.");
        }

        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }

        $devisId   = (int)($postData['devis_id'] ?? 0);
        $libelle   = htmlspecialchars(trim($postData['libelle'] ?? ''), ENT_QUOTES, 'UTF-8');
        $montantHt = (float)($postData['montant_ht'] ?? 0);

        if ($devisId > 0 && !empty($libelle) && $montantHt >= 0) {
            require_once __DIR__ . '/../config/Database.php';
            $db = Database::getInstance();
            $stmt = $db->prepare("INSERT INTO prestations (devis_id, libelle, montant_ht) VALUES (?, ?, ?)");
            $stmt->execute([$devisId, $libelle, $montantHt]);
        }

        header("Location: index.php?action=edit_devis&id=" . $devisId);
        exit;
    }

    /**
     * Supprime une prestation existante d'un devis.
     *
     * Sécurise la suppression via une double contrainte (ID prestation + ID Devis)
     * pour prévenir la manipulation d'identifiants (IDOR - Insecure Direct Object Reference).
     *
     * @param array $postData Payload du formulaire de suppression
     * @return void
     */
    public function deletePrestation(array $postData): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=login');
            exit();
        }

        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }

        $prestationId = (int)($postData['prestation_id'] ?? 0);
        $devisId      = (int)($postData['devis_id'] ?? 0);

        if ($prestationId > 0 && $devisId > 0) {
            try {
                require_once __DIR__ . '/../config/Database.php';
                $db = Database::getInstance();
                $stmt = $db->prepare("DELETE FROM prestations WHERE id = ? AND devis_id = ?");
                $stmt->execute([$prestationId, $devisId]);
            } catch (\PDOException $e) {
                error_log("Échec de la suppression de prestation : " . $e->getMessage());
            }
        }

        header("Location: index.php?action=edit_devis&id=" . $devisId);
        exit();
    }

    /**
     * Affiche le tableau de bord de pilotage global des devis.
     *
     * Utilise une agrégation SQL (LEFT JOIN + SUM) pour calculer et afficher
     * les encours financiers (Montants globaux) du pipeline commercial.
     *
     * @return void
     */
    public function showDevisList(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        require_once __DIR__ . '/../config/Database.php';
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT d.*, 
                   p.company_name, 
                   p.contact_name, 
                   p.email, 
                   p.status AS prospect_status,
                   COALESCE(SUM(pr.montant_ht), 0) AS total_ht
            FROM devis d
            JOIN prospects p ON d.id_prospect = p.id
            LEFT JOIN prestations pr ON d.id_devis = pr.devis_id
            GROUP BY d.id_devis
            ORDER BY d.date_creation DESC
        ");
        $stmt->execute();
        $devisList = $stmt->fetchAll();

        $pageTitle = "Devis & Facturation - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/list_devis.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Met à jour l'état d'un prospect (ex: "en attente" -> "refusé").
     *
     * Implémentation du pattern de Persistance Polyglotte : met à jour l'entité
     * structurée dans MySQL et trace l'événement immuable dans MongoDB.
     *
     * @return void
     */
    public function updateProspectStatus(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id']) && !empty($_POST['status'])) {
            $id = (int)$_POST['id'];
            $status = htmlspecialchars(trim($_POST['status']), ENT_QUOTES, 'UTF-8');

            $prospectModel = new Prospect();
            $success = $prospectModel->updateStatus($id, $status);

            if ($success) {
                // Journalisation MongoDB (AT2)
                try {
                    $logModel = new Log();
                    $logModel->addLog("UPDATE_PROSPECT", "Statut du prospect #$id modifié en : $status", $_SESSION['user_id'] ?? null);
                } catch (\Exception $e) {
                    error_log("Erreur Log MongoDB : " . $e->getMessage());
                }

                header("Location: index.php?action=view_prospect&id=" . $id);
                exit;
            } else {
                error_log("Échec de la mise à jour du statut (Prospect ID: $id).");
                header("Location: index.php?action=view_prospect&id=" . $id);
                exit;
            }
        } else {
            header('Location: index.php?action=dashboard');
            exit;
        }
    }

    /**
     * Extrait et affiche le listing global des prospects.
     *
     * @see Prospect::findAll() Logique métier sous-jacente.
     * @return void
     */
    public function showProspectsList(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $prospectModel = new Prospect();
        $prospects = $prospectModel->findAll();

        $pageTitle = "Gestion des Prospects - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/list_prospects.php';
        require __DIR__ . '/../views/partials/footer.php';
    }
}