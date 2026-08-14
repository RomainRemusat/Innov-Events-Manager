<?php
/**
 * Contrôleur : DashboardController (Espace d'administration sécurisé)
 *
 * Ce contrôleur orchestre la logique métier de l'espace privé (Back-Office).
 * Il agit comme un point de contrôle (Guard) en vérifiant systématiquement
 * les habilitations de l'utilisateur avant d'autoriser l'accès aux données sensibles.
 * Une fois l'authentification validée, il interagit avec la couche d'accès
 * aux données (Modèle) pour préparer le contexte de la vue d'administration.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.1.0
 */

require_once __DIR__ . '/../models/sql/Prospect.php';
//
//// Clause de garde (Guard Clause) : Redirection si non authentifié ou non ADMIN
//if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'ADMIN') {
//    $_SESSION['access_error'] = "Accès refusé. Réservé à l'administration.";
//    header('Location: index.php');
//    exit;
//}

class DashboardController
{
    /**
     * Orchestre l'affichage du tableau de bord d'administration (Page d'accueil du Back-Office).
     *
     * Cette méthode agrège les données provenant de multiples sources (MySQL pour
     * les entités métiers, MongoDB pour les logs) afin de générer les indicateurs
     * de performance (KPIs) et le flux d'activité.
     *
     * @return void
     */
    public function showDashboard(): void
    {
        // ---------------------------------------------------------------------
        // 1. CONTRÔLE D'ACCÈS ET SÉCURITÉ
        // ---------------------------------------------------------------------
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clause de garde (Guard Clause) : Redirection si non authentifié OU si ce n'est pas Chloé (ADMIN)
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['ADMIN', 'EMPLOYEE'])) {
            $_SESSION['access_error'] = "Accès refusé. Cette zone est strictement réservée à l'administration.";
            header('Location: index.php');
            exit;
        }

        // ---------------------------------------------------------------------
        // 2. INTERACTION AVEC LES MODÈLES (Data Access Layer)
        // ---------------------------------------------------------------------
        // A. Base de données relationnelle (MySQL) : Récupération des leads
        $prospectModel = new Prospect();
        $prospects = $prospectModel->findAll();

        // B. Base de données orientée documents (MongoDB) : Récupération de l'audit
        $logModel = new Log();
        $activityLogs = $logModel->getLatestLogs(5); // Limitation aux 5 événements les plus récents

        // ---------------------------------------------------------------------
        // 3. PRÉPARATION DU CONTEXTE ET RENDU DE LA VUE
        // ---------------------------------------------------------------------
        $pageTitle = "Tableau de Bord - Innov'Events";

        // Assemblage modulaire de la réponse HTTP (Layout Pattern)
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/dashboard.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Orchestre l'affichage des détails complets d'un prospect spécifique.
     *
     * @param int $id Identifiant unique du prospect à récupérer en base de données.
     * @return void
     */
    public function showProspectDetails(int $id): void
    {
        // ---------------------------------------------------------------------
        // 1. CONTRÔLE D'ACCÈS ET SÉCURITÉ
        // ---------------------------------------------------------------------
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // ---------------------------------------------------------------------
        // 2. INTERACTION AVEC LE MODÈLE (Data Access Layer)
        // ---------------------------------------------------------------------
        $prospectModel = new Prospect();
        $prospect = $prospectModel->find($id);

        // Gestion de l'erreur 404 logique : Si l'ID n'existe pas ou a été supprimé,
        // on redirige l'utilisateur vers le tableau de bord par mesure de sécurité.
        if (!$prospect) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        // ---------------------------------------------------------------------
        // 3. PRÉPARATION DU CONTEXTE ET RENDU DE LA VUE
        // ---------------------------------------------------------------------
        // Injection dynamique du nom de l'entreprise dans la balise <title>
        $pageTitle = "Détails du Prospect - " . htmlspecialchars($prospect['company_name']);

        // Assemblage modulaire
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/view_prospect.php';
        require __DIR__ . '/../views/partials/footer.php';
    }


    /**
     * Affiche le formulaire de conversion d'un prospect en client (Workflow Devis).
     *
     * Pré-remplit les champs avec les données du prospect pour faciliter le travail
     * de l'administrateur, conformément au cahier des charges (AT2).
     *
     * @param int $id Identifiant du prospect à convertir
     * @return void
     */
    public function showConvertForm(int $id): void
    {
        // 1. CONTRÔLE D'ACCÈS (Sécurité AT1)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Seule l'administratrice (Chloé) peut convertir un prospect et engager la société
        if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'ADMIN') {
            header('Location: index.php?action=dashboard');
            exit;
        }

        // 2. RÉCUPÉRATION DES DONNÉES DU PROSPECT
        $prospectModel = new Prospect();
        $prospect = $prospectModel->find($id);

        if (!$prospect) {
            // Sécurité : Redirection si l'ID a été manipulé dans l'URL
            header('Location: index.php?action=dashboard');
            exit;
        }

        // 3. PRÉPARATION DU CONTEXTE ET RENDU DE LA VUE
        $pageTitle = "Convertir Prospect - " . htmlspecialchars($prospect['company_name']);

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/convert_prospect.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Traite la conversion d'un prospect en client, crée l'événement et le devis (AT2).
     *
     * @param array $postData Données soumises via le formulaire de conversion
     * @return void
     */
    public function processConversion(array $postData): void
    {
        // 1. Contrôle d'accès (Sécurité AT1)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // 2. Vérification Anti-CSRF
        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }

        // 3. Assainissement des données entrantes
        $prospectId  = (int)($postData['prospect_id'] ?? 0);
        $companyName = htmlspecialchars(trim($postData['company_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $contactName = htmlspecialchars(trim($postData['contact_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email       = filter_var(trim($postData['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone       = htmlspecialchars(trim($postData['phone'] ?? ''), ENT_QUOTES, 'UTF-8');

        $eventTitle  = htmlspecialchars(trim($postData['event_title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(trim($postData['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $startDate   = $postData['start_date'] ?? '';
        $location    = htmlspecialchars(trim($postData['location'] ?? ''), ENT_QUOTES, 'UTF-8');
        $eventStatus = htmlspecialchars(trim($postData['event_status'] ?? 'brouillon'), ENT_QUOTES, 'UTF-8');


        if (!$prospectId || !$email || empty($eventTitle) || empty($startDate) || empty($location)) {
            die("Erreur de validation : Tous les champs obligatoires ne sont pas renseignés.");
        }

        try {
            require_once __DIR__ . '/../config/Database.php';
            $db = Database::getInstance();

            // Transaction SQL globale (Intégrité des données - AT2)
            $db->beginTransaction();

            // A. Création ou Récupération du compte Client
            $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            $existingUser = $stmtCheck->fetch();

            if ($existingUser) {
                $clientId = (int)$existingUser['id'];
            } else {
                $tempPassword = 'Temp_' . bin2hex(random_bytes(4)) . '!Z';
                $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

                $nameParts = explode(' ', $contactName, 2);
                $firstname = $nameParts[0];
                $lastname = $nameParts[1] ?? 'Contact';

                $stmtUser = $db->prepare("INSERT INTO users (email, password, firstname, lastname, role, must_change_password) VALUES (?, ?, ?, ?, 'CLIENT', 1)");
                $stmtUser->execute([$email, $hashedPassword, $firstname, $lastname]);
                $clientId = (int)$db->lastInsertId();
            }

            // B. Mise à jour du prospect
            $stmtProspect = $db->prepare("UPDATE prospects SET status = 'accepté', user_id = ? WHERE id = ?");
            $stmtProspect->execute([$clientId, $prospectId]);

            // C. Création de l'Événement avec sa description
            $mysqlDate = date('Y-m-d H:i:s', strtotime($startDate));
            $stmtEvent = $db->prepare("INSERT INTO events (client_id, title, description, event_date, location, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtEvent->execute([$clientId, $eventTitle, $description, $mysqlDate, $location, $eventStatus]);
            $eventId = (int)$db->lastInsertId();

            // D. Création de la coquille du Devis
            $refPdf = "Devis_" . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $companyName), 0, 5)) . "_" . date('Ymd_His') . ".pdf";
            $stmtDevis = $db->prepare("INSERT INTO devis (id_prospect, reference_pdf, montant_ht, tva) VALUES (?, ?, 0, 0)");
            $stmtDevis->execute([$prospectId, $refPdf]);
            $devisId = (int)$db->lastInsertId(); // Récupération essentielle de l'ID du Devis

            // Validation finale de la transaction
            $db->commit();

            // E. Journalisation NoSQL (MongoDB)
            try {
                require_once __DIR__ . '/../models/nosql/Log.php';
                $logModel = new Log();
                $logModel->addLog("CREATION_CLIENT", "Nouveau client généré depuis prospect #$prospectId", $_SESSION['user_id'] ?? null, ['client_id' => $clientId]);
                $logModel->addLog("CREATION_EVENEMENT", "Événement '$eventTitle' créé", $_SESSION['user_id'] ?? null, ['event_id' => $eventId]);
            } catch (\Exception $e) {
                error_log("Erreur Log MongoDB : " . $e->getMessage());
            }

            // REDIRECTION DIRECTE VERS L'ÉDITION DU DEVIS :
            header("Location: index.php?action=edit_devis&id=" . $devisId);
            exit();

        } catch (\PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            // --- BLOC DEBUG TEMPORAIRE ---
            echo "<div style='background:#fee2e2; border:2px solid #ef4444; color:#991b1b; padding:20px; font-family:monospace; margin:20px; border-radius:8px;'>";
            echo "<h3 style='margin-top:0;'>⚠️ Erreur SQL lors de processConversion :</h3>";
            echo "<p><strong>Message :</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>Fichier :</strong> " . $e->getFile() . " (Ligne " . $e->getLine() . ")</p>";
            echo "</div>";
            die(); // On Stoppe l'exécution pour lire l'erreur


            error_log("Erreur Transaction Conversion : " . $e->getMessage());
            header('Location: index.php?action=dashboard');
            exit();
        }
    }

    /**
     * Affiche l'interface d'édition d'un devis pour y ajouter des prestations (AT2).
     *
     * @param int $devisId Identifiant unique du devis (table 'devis').
     * @return void
     */
    /**
     * Affiche l'interface d'édition du devis et ses prestations.
     *
     * @param int $devisId ID unique du devis (id_devis)
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

        // Récupération du devis et des informations du prospect rattaché
        $stmt = $db->prepare("
            SELECT d.*, p.* 
            FROM devis d
            JOIN prospects p ON d.id_prospect = p.id
            WHERE d.id_devis = ?
        ");
        $stmt->execute([$devisId]);
        $devis = $stmt->fetch();

        if (!$devis) {
            // Si l'ID de devis n'existe pas en BDD, sécurité fallback sur le Dashboard
            header('Location: index.php?action=dashboard');
            exit();
        }

        // Récupération des prestations liées à ce devis
        $stmtPrest = $db->prepare("SELECT * FROM prestations WHERE devis_id = ? ORDER BY id ASC");
        $stmtPrest->execute([$devisId]);
        $prestations = $stmtPrest->fetchAll();

        $pageTitle = "Édition Devis - " . htmlspecialchars($devis['company_name']);

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/edit_devis.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Ajoute une nouvelle ligne de prestation commerciale à un devis existant (AT2).
     *
     * Cette méthode respecte le pattern PRG (Post-Redirect-Get) pour éviter
     * les doublons d'insertion si l'utilisateur rafraîchit la page.
     *
     * @param array $postData Données issues du formulaire (libellé, montant HT, devis_id)
     */
    public function addPrestation(array $postData): void
    {
        // Initialisation de la session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérification de sécurité (Accès Admin + Anti-CSRF)
        if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'ADMIN') {
            die("Accès refusé.");
        }
        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }

        // Assainissement des entrées (Protection XSS et Typage strict)
        $devisId   = (int)$postData['devis_id'];
        $libelle   = htmlspecialchars(trim($postData['libelle']), ENT_QUOTES, 'UTF-8');
        $montantHt = (float)$postData['montant_ht'];

        // Si les données sont valides, on insère en base de données
        if ($devisId > 0 && !empty($libelle) && $montantHt >= 0) {
            require_once __DIR__ . '/../config/Database.php';
            $db = Database::getInstance();

            $stmt = $db->prepare("INSERT INTO prestations (devis_id, libelle, montant_ht) VALUES (?, ?, ?)");
            $stmt->execute([$devisId, $libelle, $montantHt]);
        }

        // Redirection vers la page d'édition pour voir la nouvelle ligne s'afficher et les totaux se mettre à jour
        header("Location: index.php?action=edit_devis&id=" . $devisId);
        exit;
    }

    /**
     * Supprime une prestation d'un devis en base de données (AT2).
     *
     * Incorpore des contrôles de sécurité stricts (Habilitation ADMIN, CSRF)
     * et applique le pattern PRG (Post-Redirect-Get) pour rafraîchir l'interface.
     *
     * @param array $postData Données transmises via $_POST (prestation_id, devis_id, csrf_token)
     * @return void
     */
    public function deletePrestation(array $postData): void
    {
        // 1. Contrôle de session et d'habilitation (Sécurité AT1)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=login');
            exit();
        }

        // 2. Protection contre les attaques CSRF (Sécurité AT1)
        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }

        // 3. Assainissement des identifiants (Cast explicite en entiers)
        $prestationId = (int)($postData['prestation_id'] ?? 0);
        $devisId      = (int)($postData['devis_id'] ?? 0);

        // 4. Traitement SQL de suppression
        if ($prestationId > 0 && $devisId > 0) {
            try {
                require_once __DIR__ . '/../config/Database.php';
                $db = Database::getInstance();

                // Requête préparée pour éviter toute injection SQL
                $stmt = $db->prepare("DELETE FROM prestations WHERE id = ? AND devis_id = ?");
                $stmt->execute([$prestationId, $devisId]);

            } catch (\PDOException $e) {
                error_log("Erreur lors de la suppression de la prestation : " . $e->getMessage());
            }
        }

        // 5. Pattern PRG : Redirection vers la page du devis pour recalculer automatiquement les totaux
        header("Location: index.php?action=edit_devis&id=" . $devisId);
        exit();
    }

    /**
     * Traite la soumission du formulaire de changement de statut (Processus métier).
     *
     * Cette méthode illustre le concept de "Persistance Polyglotte" :
     * Elle met à jour l'état du prospect dans MySQL (Règles métier strictes)
     * ET inscrit simultanément cette action dans MongoDB (Traçabilité/Audit).
     * Elle implémente également le pattern PRG (Post-Redirect-Get) pour éviter
     * la resoumission accidentelle du formulaire.
     *
     * @return void
     */
    public function updateProspectStatus(): void
    {
        // ---------------------------------------------------------------------
        // 1. CONTRÔLE D'ACCÈS ET SÉCURITÉ
        // ---------------------------------------------------------------------
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // ---------------------------------------------------------------------
        // 2. VALIDATION ET ASSAINISSEMENT DES DONNÉES ENTRANTES (Sanitization)
        // ---------------------------------------------------------------------
        // Vérification stricte du verbe HTTP et de la présence des payloads
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id']) && !empty($_POST['status'])) {

            // Cast explicite de l'ID en entier pour contrer les injections SQL
            $id = (int)$_POST['id'];

            // Échappement des caractères spéciaux sur la chaîne de caractères entrante
            $status = htmlspecialchars(trim($_POST['status']), ENT_QUOTES, 'UTF-8');

            // -----------------------------------------------------------------
            // 3. DOUBLE PERSISTANCE (MySQL + MongoDB)
            // -----------------------------------------------------------------
            $prospectModel = new Prospect();

            // Tentative de mise à jour dans la base relationnelle
            $success = $prospectModel->updateStatus($id, $status);

            if ($success) {
                // Succès MySQL : Inscription immédiate de l'action dans le journal MongoDB
                $logModel = new Log();
                $logModel->addLog("Mise à jour statut", "Le prospect ID #$id a été passé en statut : $status");

                // Pattern PRG : Redirection vers la vue de détail pour rafraîchir l'interface
                header("Location: index.php?action=view_prospect&id=" . $id);
                exit;
            } else {
                // Gestion d'erreur critique (Généralement catchée par un logger global en production)
                die("Erreur technique : Impossible de mettre à jour le statut commercial.");
            }
        } else {
            // Requête malformée ou accès direct à l'URL de traitement (GET au lieu de POST)
            header('Location: index.php?action=dashboard');
            exit;
        }
    }

    /**
     * Affiche la liste exhaustive des prospects (Leads commerciaux).
     *
     * Cette méthode orchestre la récupération de tous les prospects depuis
     * la base de données relationnelle et prépare le contexte d'affichage
     * pour la vue dédiée. Elle agit en tant que "Read" dans l'architecture CRUD.
     *
     * @see Prospect::findAll() Pour la logique de requête SQL sous-jacente.
     * @return void Ne retourne rien, effectue directement le rendu HTTP (require).
     */
    public function showProspectsList(): void
    {
        // ---------------------------------------------------------------------
        // 1. CONTRÔLE D'ACCÈS ET SÉCURITÉ
        // ---------------------------------------------------------------------
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // ---------------------------------------------------------------------
        // 2. INTERACTION AVEC LE MODÈLE (Data Access Layer)
        // ---------------------------------------------------------------------
        $prospectModel = new Prospect();
        $prospects = $prospectModel->findAll();

        // ---------------------------------------------------------------------
        // 3. PRÉPARATION DU CONTEXTE ET RENDU DE LA VUE
        // ---------------------------------------------------------------------
        $pageTitle = "Gestion des Prospects - Innov'Events";

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/list_prospects.php';
        require __DIR__ . '/../views/partials/footer.php';
    }
}