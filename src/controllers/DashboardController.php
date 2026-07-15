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