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
 * @version    1.6.0
 */

// Chargement du contrôleur de base et des dépendances métiers
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/MailService.php';

class AuthController extends BaseController
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
     * Alias de routage vers le formulaire de connexion.
     */
    public function showLogin(): void
    {
        $this->showLoginForm();
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
     * Alias de routage vers le formulaire d'inscription.
     */
    public function showRegister(): void
    {
        $this->showRegisterForm();
    }

    /**
     * Alias de routage vers le traitement de connexion.
     */
    public function handleLogin(?array $postData = null): void
    {
        $this->login($postData ?? $_POST);
    }

    /**
     * Traite la soumission du formulaire d'inscription d'un nouveau client.
     *
     * @param array $postData Payload brut issu du tableau superglobal $_POST.
     * @return void
     */
    public function register(array $postData): void
    {
        $this->startSession();
        $this->validateCsrf($postData);

        // 1. ASSAINISSEMENT ET NETTOYAGE DES ENTRÉES (Anti-XSS)
        $firstname = trim($postData['firstname'] ?? '');
        $lastname  = trim($postData['lastname'] ?? '');
        $username  = trim($postData['username'] ?? '');
        $email     = filter_var(trim($postData['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password  = $postData['password'] ?? '';

        $oldInputs = [
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'username'  => $username,
            'email'     => $postData['email'] ?? ''
        ];

        // 2. PROGRAMMATION DÉFENSIVE : CLAUSES DE GARDE (Guard Clauses)
        if (empty($firstname) || empty($lastname) || empty($username) || !$email || empty($password)) {
            $_SESSION['old_inputs'] = $oldInputs;
            $_SESSION['register_error'] = "Tous les champs requis (*) doivent être correctement renseignés.";
            header('Location: index.php?action=show_register');
            exit();
        }

        if (!$this->isValidPassword($password)) {
            $_SESSION['old_inputs'] = $oldInputs;
            $_SESSION['register_error'] = "La sécurité de votre mot de passe est insuffisante. Veuillez respecter les critères exigés.";
            header('Location: index.php?action=show_register');
            exit();
        }

        $userModel = new User();

        if ($userModel->findByEmail($email)) {
            $_SESSION['old_inputs'] = $oldInputs;
            $_SESSION['register_error'] = "Cette adresse email est déjà associée à un compte.";
            header('Location: index.php?action=show_register');
            exit();
        }

        // 3. CHIFFREMENT STRICT DU MOT DE PASSE (Bcrypt conforme RGPD)
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $userId = $userModel->create([
            'email'     => $email,
            'password'  => $hashedPassword,
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'role'      => 'CLIENT'
        ]);

        // 4. GESTION DES RÉSULTATS, AUDIT NOSQL ET EXPÉDITION D'EMAIL
        if ($userId) {
            try {
                $logModel = new Log();
                $logModel->addLog("INSCRIPTION_CLIENT", (int)$userId, [
                    'message' => "Création de compte réussie pour : $email",
                    'user_id' => $userId,
                    'email'   => $email,
                    'role'    => 'CLIENT'
                ]);
            } catch (\Exception $e) {
                error_log("Alerte NoSQL : Échec de journalisation inscription (User ID $userId) : " . $e->getMessage());
            }

            $mailService = new MailService();
            $mailService->sendRegisterConfirmation($email, $firstname);

            $_SESSION['global_success'] = "Votre compte client a été créé avec succès ! Vous pouvez maintenant vous connecter.";
            header('Location: index.php?action=login');
            exit();

        } else {
            $_SESSION['old_inputs'] = $oldInputs;
            $_SESSION['register_error'] = "Une anomalie technique interne est survenue lors de votre enregistrement. Veuillez réessayer.";
            header('Location: index.php?action=show_register');
            exit();
        }
    }

    /**
     * Authentifie un utilisateur et initialise son environnement de session.
     *
     * @param array $postData Payload brut issu du tableau superglobal $_POST.
     * @return void
     */
    public function login(array $postData): void
    {
        $this->validateCsrf($postData);

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
            if (!empty($user['is_deleted'])) {
                $this->auditAuthAttempt("TENTATIVE_CONNEXION_REFUSEE", (int)$user['id'], [
                    'message'   => "Tentative de connexion sur un compte désactivé ou supprimé : $email",
                    'email'     => $email,
                    'user_role' => $user['role'] ?? 'CLIENT',
                    'reason'    => 'Tentative de connexion sur un compte désactivé ou supprimé'
                ]);

                $_SESSION['login_error'] = "Ce compte a été suspendu ou supprimé. Veuillez contacter l'administrateur.";
                $this->showLoginForm();
                return;
            }

            session_regenerate_id(true);

            $_SESSION['user_id']        = $user['id'];
            $_SESSION['user_email']     = $user['email'];
            $_SESSION['user_role']      = $user['role'];
            $_SESSION['user_firstname'] = $user['firstname'] ?? '';
            $_SESSION['user_name']      = $user['firstname'] ?? 'Utilisateur';

            if (!empty($user['must_change_password']) || !empty($user['force_password_change'])) {
                $_SESSION['force_password_change'] = true;
                header('Location: index.php?action=force_password_change');
                exit();
            }

            $this->auditAuthAttempt("CONNEXION_REUSSIE", (int)$user['id'], [
                'message'   => "Connexion réussie pour l'utilisateur : {$user['email']}",
                'email'     => $user['email'],
                'user_role' => $user['role']
            ]);

            if (in_array($user['role'], ['ADMIN', 'EMPLOYEE'], true)) {
                header('Location: index.php?action=dashboard');
            } else {
                header('Location: index.php?action=client_dashboard');
            }
            exit();

        } else {
            $this->auditAuthAttempt("CONNEXION_ECHOUEE", null, [
                'message'         => "Connexion échouée pour : $email (identifiants invalides)",
                'tentative_email' => $email,
                'motif'           => "Identifiants invalides"
            ]);

            $_SESSION['login_error'] = "Identifiants de connexion invalides. Veuillez réessayer.";
            header('Location: index.php?action=login');
            exit();
        }
    }

    public function logout(): void
    {
        $this->startSession();

        if (isset($_SESSION['user_id'])) {
            $this->auditAuthAttempt("DECONNEXION", (int)$_SESSION['user_id'], [
                'message' => "Déconnexion de l'utilisateur : " . ($_SESSION['user_email'] ?? 'inconnu'),
                'email'   => $_SESSION['user_email'] ?? 'inconnu'
            ]);
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();

        header('Location: index.php?action=login');
        exit();
    }

    public function resetPasswordRequest(): void
    {
        $this->startSession();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf($_POST);

            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

            if (!$email) {
                $_SESSION['flash_error'] = "Veuillez saisir une adresse email valide.";
                header('Location: index.php?action=forgot_password');
                exit();
            }

            $userModel = new User();
            $user = $userModel->findByEmail($email);

            if ($user && empty($user['is_deleted'])) {
                $tempPassword = bin2hex(random_bytes(6)) . 'A1!';
                $hashedTempPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

                $userModel->updatePassword((int)$user['id'], $hashedTempPassword, true);

                $mailService = new MailService();
                $mailService->sendTempPasswordEmail($email, $tempPassword, $user['firstname'] ?? 'Client');

                try {
                    $logModel = new Log();
                    $logModel->addLog("RESET_PASSWORD_REQUEST", (int)$user['id'], [
                        'email'   => $email,
                        'message' => "Réinitialisation de mot de passe demandée avec génération de mot de passe temporaire."
                    ]);
                } catch (\Exception $e) {
                    error_log("Erreur Log MongoDB reset pwd : " . $e->getMessage());
                }
            }

            $_SESSION['global_success'] = "Si cette adresse existe, des instructions temporaires de connexion vous ont été transmises par email.";
            header('Location: index.php?action=forgot_password');
            exit();
        }

        require __DIR__ . '/../views/public/forgot_password.php';
    }

    public function updateForcedPassword(): void
    {
        $this->startSession();

        if (!isset($_SESSION['user_id']) || empty($_SESSION['force_password_change'])) {
            header('Location: index.php?action=login');
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf($_POST);

            $newPassword     = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($newPassword) || $newPassword !== $confirmPassword) {
                $_SESSION['flash_error'] = "Les mots de passe ne correspondent pas ou sont vides.";
                header('Location: index.php?action=force_password_change');
                exit();
            }

            if (!$this->isValidPassword($newPassword)) {
                $_SESSION['flash_error'] = "Le nouveau mot de passe ne respecte pas les critères de sécurité requis.";
                header('Location: index.php?action=force_password_change');
                exit();
            }

            $userModel = new User();
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $userId = (int)$_SESSION['user_id'];

            $userModel->updatePassword($userId, $hashedPassword, false);
            unset($_SESSION['force_password_change']);

            try {
                $logModel = new Log();
                $logModel->addLog("UPDATE_FORCED_PASSWORD", $userId, [
                    'message' => "Mise à jour obligatoire du mot de passe temporaire effectuée avec succès."
                ]);
            } catch (\Exception $e) {
                error_log("Erreur Log MongoDB update forced pwd : " . $e->getMessage());
            }

            $_SESSION['global_success'] = "Votre mot de passe a été personnalisé avec succès ! Veuillez vous reconnecter.";
            header('Location: index.php?action=login');
            exit();
        }

        require __DIR__ . '/../views/public/force_password_change.php';
    }

    private function isValidPassword(string $password): bool
    {
        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
        return (bool)preg_match($pattern, $password);
    }

    private function auditAuthAttempt(string $typeAction, ?int $userId, array $details): void
    {
        try {
            $logModel = new Log();
            if (empty($details['message'])) {
                $details['message'] = "Connexion réussie de l'utilisateur : " . ($details['email'] ?? 'N/A');
            }
            $details['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $details['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu';
            $logModel->addLog($typeAction, $userId, $details);
        } catch (\Exception $e) {
            error_log("Alerte MongoDB (auditAuthAttempt) : " . $e->getMessage());
        }
    }
}
