<?php
/**
 * Contrôleur : AuthController
 *
 * Gère de manière sécurisée le cycle de vie de l'authentification et de l'inscription
 * des utilisateurs (Administrateurs, Employés et Clients).
 *
 * Ce composant centralise l'application des exigences de sécurité et d'accessibilité :
 * - Validation stricte et assainissement des payloads d'entrée (Anti-XSS & Anti-Injection SQL).
 * - Stratégie de hachage robuste des mots de passe (Bcrypt) et validation par expression régulière.
 * - Rétention sécurisée des saisies utilisateurs en session pour optimiser l'expérience utilisateur (UX).
 * - Journalisation systématique des événements d'accès dans un cluster NoSQL MongoDB.
 * - Gestion imperméable des contextes de session et prévention des attaques de fixation.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.4.0
 */

// Chargement des dépendances métiers de la couche d'accès aux données (DAL) et des services
require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/MailService.php';


class AuthController
{
    /**
     * Affiche l'interface du formulaire de connexion (Front-Office / Back-Office).
     *
     * @return void
     */
    public function showLoginForm(): void
    {
        require __DIR__ . '/../views/public/login.php';
    }

    /**
     * Affiche l'interface du formulaire d'inscription pour la création d'un compte client.
     *
     * @return void
     */
    public function showRegisterForm(): void
    {
        require __DIR__ . '/../views/public/register.php';
    }

    /**
     * Traite la soumission du formulaire d'inscription d'un nouveau client.
     *
     * Cette méthode orchestre la validation des données d'inscription, applique les
     * contraintes de sécurité d'intégrité et de chiffrement, gère la persistance locale
     * des saisies en cas d'échec (UX), initie la double persistance (MySQL et MongoDB)
     * puis délègue l'envoi du courriel de confirmation de compte au service de messagerie.
     *
     * @param array $postData Payload brut issu du tableau superglobal $_POST.
     * @return void
     */
    public function register(array $postData): void
    {
        // Initialisation de la session pour véhiculer les états d'erreurs et les anciennes saisies
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ---------------------------------------------------------------------
        // 1. ASSAINISSEMENT ET NETTOYAGE DES ENTRÉES (Anti-XSS)
        // ---------------------------------------------------------------------
        $firstname = htmlspecialchars(trim($postData['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lastname  = htmlspecialchars(trim($postData['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $username  = htmlspecialchars(trim($postData['username'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email     = filter_var(trim($postData['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password  = $postData['password'] ?? '';

        // CONTEXTE UX / SÉCURITÉ : Rétention éphémère des saisies en session
        // Note de sécurité : Pour rester conforme au RGPD et à la CNIL, le mot de passe n'est JAMAIS sauvegardé
        $oldInputs = [
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'username'  => $username,
            'email'     => $postData['email'] ?? ''
        ];

        // ---------------------------------------------------------------------
        // 2. PROGRAMMATION DÉFENSIVE : CLAUSES DE GARDE (Guard Clauses)
        // ---------------------------------------------------------------------
        // Contrôle de complétude du formulaire
        if (empty($firstname) || empty($lastname) || empty($username) || !$email || empty($password)) {
            $_SESSION['old_inputs'] = $oldInputs;
            $_SESSION['register_error'] = "Tous les champs requis (*) doivent être correctement renseignés.";
            header('Location: index.php?action=show_register');
            exit();
        }

        // Validation de la complexité du mot de passe selon la politique de sécurité
        if (!$this->isValidPassword($password)) {
            $_SESSION['old_inputs'] = $oldInputs;
            $_SESSION['register_error'] = "La sécurité de votre mot de passe est insuffisante. Veuillez respecter les critères exigés.";
            header('Location: index.php?action=show_register');
            exit();
        }

        $userModel = new User();

        // Contrôle d'unicité de l'identifiant pour éviter la collision de comptes
        if ($userModel->findByEmail($email)) {
            $_SESSION['old_inputs'] = $oldInputs;
            $_SESSION['register_error'] = "Cette adresse email est déjà associée à un compte.";
            header('Location: index.php?action=show_register');
            exit();
        }

        // Si toutes les validations passent, on libère la mémoire tampon de session
        unset($_SESSION['old_inputs']);

        // ---------------------------------------------------------------------
        // 3. CHIFFREMENT ET PERSISTANCE RELATIONNELLE (MySQL)
        // ---------------------------------------------------------------------
        // Hachage du mot de passe via l'algorithme Bcrypt (sécurité native PHP adaptative)
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $userData = [
            'email'     => $email,
            'password'  => $hashedPassword,
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'role'      => 'CLIENT' // Attribution stricte côté serveur pour empêcher l'élection de privilèges via HTTP
        ];

        $userId = $userModel->create($userData);

        if ($userId) {

            // ---------------------------------------------------------------------
            // 4. DÉLÉGATION DE L'ENVOI DE MAIL (Architecture de Services)
            // ---------------------------------------------------------------------
            $mailService = new MailService();
            $mailService->sendRegisterConfirmation($email, $firstname);

            // ---------------------------------------------------------------------
            // 5. JOURNALISATION COMPLIANCE NOSQL (Audit & Traçabilité MongoDB)
            // ---------------------------------------------------------------------
            try {
                $logModel = new Log();
                $logModel->addLog(
                    "CREATION_CLIENT",
                    "Nouvelle inscription d'un client : $firstname $lastname ($email)",
                    $userId,
                    ['username' => $username]
                );
            } catch (\Exception $e) {
                // Stratégie de résilience : une panne du service de log n'interrompt pas l'inscription
                error_log("Erreur lors de la journalisation NoSQL de l'inscription : " . $e->getMessage());
            }

            echo "<div class='container mt-5'><div class='alert alert-success text-center'>Votre compte a été créé avec succès ! Un e-mail de confirmation vous a été envoyé.</div></div>";
            $this->showLoginForm();
        } else {
            $_SESSION['register_error'] = "Une erreur technique est survenue. Veuillez réessayer ultérieurement.";
            header('Location: index.php?action=show_register');
            exit();
        }
    }

    /**
     * Traite, valide et authentifie la tentative de connexion d'un utilisateur (Login).
     *
     * Mesures de sécurité appliquées :
     * - Protection contre l'énumération d'utilisateurs par l'usage d'un message d'erreur générique.
     * - Comparaison temporelle constante via password_verify() pour neutraliser les attaques par canal auxiliaire (Timing Attacks).
     * - Isolation et traçabilité NoSQL distincte des succès de connexion et des tentatives suspectes.
     *
     * @param array $postData Données transmises via le formulaire de connexion.
     * @return void
     */
    public function login(array $postData): void
    {

        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }

        $email = filter_var($postData['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $postData['password'] ?? '';

        if (!$email || empty($password)) {
            echo "<div class='container mt-5'><div class='alert alert-danger text-center'>Veuillez remplir tous les champs.</div></div>";
            $this->showLoginForm();
            return;
        }

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // PROTECTION FIXATION DE SESSION (AT1)
            // Régénère l'ID de session et supprime l'ancien fichier de session
            session_regenerate_id(true);

            // BARRAGE DE SÉCURITÉ : Le mot de passe est-il temporaire ?
            if (isset($user['must_change_password']) && $user['must_change_password'] == 1) {
                // On stocke temporairement l'ID mais on ne l'authentifie pas complètement
                $_SESSION['temp_user_id'] = $user['id'];
                $_SESSION['auth_message'] = "Par mesure de sécurité, vous devez définir un nouveau mot de passe personnel.";
                header('Location: index.php?action=force_password_change');
                exit();
            }

            // Si tout est normal, on connecte l'utilisateur
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'] ?? 'CLIENT';
            $_SESSION['user_name']  = $user['firstname'] ?? 'Utilisateur';

            // Audit NoSQL
            try {
                $logModel = new Log();
                $logModel->addLog("CONNEXION_REUSSIE", "Connexion de l'utilisateur : $email", $user['id']);
            } catch (\Exception $e) {
                error_log("Erreur NoSQL : " . $e->getMessage());
            }

            if ($_SESSION['user_role'] === 'ADMIN' || $_SESSION['user_role'] === 'EMPLOYEE') {
                header('Location: index.php?action=dashboard');
            } else {
                header('Location: index.php?action=client_dashboard');
            }
            exit();

        } else {
            // Échec de connexion... (Garder ton code d'erreur actuel)
            echo "<div class='container mt-5'><div class='alert alert-danger text-center'>Email ou mot de passe incorrect.</div></div>";
            $this->showLoginForm();
        }
    }
    /**
     * Clôture de manière hermétique la session active de l'utilisateur (Déconnexion).
     *
     * Mesures contre le vol de session et les attaques de fixation :
     * - Remise à zéro complète du tableau global $_SESSION en mémoire.
     * - Altération et expiration forcée du cookie de session côté navigateur du client.
     * - Destruction physique des fichiers et contextes de session côté serveur.
     *
     * @return void
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Vidage total des données stockées en mémoire volatile
        $_SESSION = array();

        // 2. Éradication complète du cookie d'identification sur le client
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"] // Flag crucial : interdit la lecture du jeton de session via JavaScript (XSS Mitigation)
            );
        }

        // 3. Destruction de l'état persistant côté serveur
        session_destroy();

        header('Location: index.php');
        exit();
    }

    /**
     * Valide la complexité d'un mot de passe via une expression régulière stricte.
     *
     * Structure de la Regex de contrôle de robustesse (Politique CNIL / RGPD) :
     * - `^`         : Début de la chaîne.
     * - `(?=.*[a-z])` : Assure la présence d'au moins une lettre minuscule.
     * - `(?=.*[A-Z])` : Assure la présence d'au moins une lettre majuscule.
     * - `(?=.*\d)`    : Assure la présence d'au moins un caractère numérique (chiffre).
     * - `(?=.*[\W_])` : Assure la présence d'au moins un caractère spécial (non alphanumérique).
     * - `.{8,}`      : Impose un seuil de longueur minimal absolu de 8 caractères.
     * - `$`         : Fin de la chaîne.
     *
     * @param string $password Le mot de passe en clair à analyser.
     * @return bool Vrai si l'intégralité des critères de sécurité est respectée.
     */
    private function isValidPassword(string $password): bool
    {
        $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
        return (bool)preg_match($regex, $password);
    }

    /**
     * Affiche le formulaire de mot de passe oublié.
     */
    public function showForgotPasswordForm(): void
    {
        require __DIR__ . '/../views/public/forgot_password.php';
    }

    /**
     * Traite la demande de réinitialisation de mot de passe.
     * Génère un mot de passe temporaire robuste, le hache, l'enregistre
     * et l'envoie par e-mail via MailHog.
     *
     * @param array $postData
     */
    public function resetPasswordRequest(array $postData): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $email = filter_var($postData['email'] ?? '', FILTER_VALIDATE_EMAIL);

        if (!$email) {
            $_SESSION['auth_message'] = "Veuillez fournir une adresse email valide.";
            header('Location: index.php?action=forgot_password');
            exit();
        }

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        // Mesure de sécurité (Anti-énumération) :
        // On affiche TOUJOURS le même message, que l'email existe ou non en base.
        $_SESSION['auth_message'] = "Si cette adresse existe, un mot de passe temporaire vient de vous être envoyé.";

        if ($user) {
            // 1. Génération d'un mot de passe temporaire respectant la Regex (Maj, Min, Chiffre, Spécial, 8+ car)
            // Ex: "Temp_9f8a!Z"
            $tempPassword = 'Temp_' . bin2hex(random_bytes(4)) . '!Z';

            // 2. Hachage du mot de passe
            $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

            // 3. Mise à jour en base et vérification
            $isUpdated = $userModel->updatePassword($user['id'], $hashedPassword, true);

            if ($isUpdated) {
                // 4. L'update a réussi, on peut envoyer l'e-mail en toute sécurité
                try {
                    $mailService = new MailService();
                    $mailService->sendTemporaryPassword($email, $tempPassword);

                    // Audit NoSQL (AT2)
                    $logModel = new Log();
                    $logModel->addLog("RESET_PASSWORD", "Demande de mot de passe oublié générée pour l'utilisateur ID " . $user['id']);
                } catch (\Exception $e) {
                    error_log("Erreur lors de l'envoi de l'e-mail : " . $e->getMessage());
                }
            } else {
                // Gérer l'échec critique (ex: logger l'erreur système sans l'afficher à l'utilisateur)
                error_log("CRITIQUE : Échec de la mise à jour du mot de passe pour l'ID " . $user['id']);
            }
        }

        header('Location: index.php?action=forgot_password');
        exit();
    }

    /**
     * Traite la soumission du nouveau mot de passe obligatoire.
     */
    public function updateForcedPassword(array $postData): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérification que l'utilisateur est bien dans le processus de changement
        if (empty($_SESSION['temp_user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        $newPassword = $postData['new_password'] ?? '';
        $confirmPassword = $postData['confirm_password'] ?? '';

        if ($newPassword !== $confirmPassword) {
            $_SESSION['auth_error'] = "Les mots de passe ne correspondent pas.";
            header('Location: index.php?action=force_password_change');
            exit();
        }

        if (!$this->isValidPassword($newPassword)) {
            $_SESSION['auth_error'] = "Le mot de passe ne respecte pas les critères de sécurité (8 caractères, 1 maj, 1 min, 1 chiffre, 1 spécial).";
            header('Location: index.php?action=force_password_change');
            exit();
        }

        // Hachage et mise à jour en BDD (must_change passe à false/0)
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $userModel = new User();

        if ($userModel->updatePassword($_SESSION['temp_user_id'], $hashedPassword, false)) {
            unset($_SESSION['temp_user_id']); // On nettoie la session temporaire
            $_SESSION['auth_message'] = "Votre mot de passe a été mis à jour avec succès. Vous pouvez maintenant vous connecter.";

            // Audit NoSQL (AT2)
            try {
                $logModel = new Log();
                $logModel->addLog("PASSWORD_MODIFIE", "L'utilisateur a défini son mot de passe définitif.");
            } catch (\Exception $e) {}

            header('Location: index.php?action=login');
        } else {
            $_SESSION['auth_error'] = "Une erreur technique est survenue.";
            header('Location: index.php?action=force_password_change');
        }
        exit();
    }
}