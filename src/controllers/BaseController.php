<?php

require_once __DIR__ . '/../models/sql/User.php';

/**
 * Contrôleur de base (Abstract Controller)
 *
 * Centralise les mécanismes transversaux : gestion des sessions,
 * contrôles d'accès RBAC et validation des jetons anti-CSRF.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.2.0
 */
abstract class BaseController
{
    /**
     * Démarre la session PHP si aucune n'est active et génère le jeton CSRF si absent.
     */
    protected function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Vérifie l'authentification et le rôle de l'utilisateur.
     * Invalide la session et redirige si le compte est inexistant ou désactivé/supprimé.
     * Le rôle et l'obligation de changement sont relus en SQL à chaque accès :
     * une session ouverte ne doit pas conserver des droits devenus obsolètes.
     *
     * @param array $allowedRoles Liste des rôles autorisés (ex: ['ADMIN', 'EMPLOYEE']).
     * @param bool $allowPasswordChange Réservé à l'action de changement obligatoire ;
     *                                  ne doit jamais provenir des paramètres HTTP.
     */
    protected function checkAuth(array $allowedRoles = [], bool $allowPasswordChange = false): void
    {
        $this->startSession();

        if (empty($_SESSION['user_id'])) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
                if ($action !== '' && !in_array($action, ['login', 'logout', 'force_password_change'], true)
                    && preg_match('/^[a-zA-Z0-9_]+$/', $action)) {
                    $params = ['action' => $action];
                    foreach ($_GET as $key => $value) {
                        if ($key !== 'action' && is_string($key) && preg_match('/^[a-zA-Z0-9_]+$/', $key)
                            && is_scalar($value) && strlen((string)$value) <= 255) {
                            $params[$key] = (string)$value;
                        }
                    }
                    $_SESSION['login_return_to'] = 'index.php?' . http_build_query($params);
                }
            }
            header('Location: index.php?action=login');
            exit();
        }

        // Vérification de validité du compte utilisateur
        $userModel = new User();
        $user = $userModel->findById((int)$_SESSION['user_id']);
        if (!$user || !empty($user['is_deleted'])) {
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

        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_firstname'] = $user['firstname'];
        $_SESSION['user_lastname'] = $user['lastname'];
        $_SESSION['user_username'] = $user['username'] ?? '';
        $_SESSION['user_name'] = $user['username'] ?: $user['firstname'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['force_password_change'] = !empty($user['must_change_password']);
        if ($_SESSION['force_password_change'] && !$allowPasswordChange) {
            header('Location: index.php?action=force_password_change');
            exit();
        }

        if (!empty($allowedRoles) && !in_array($_SESSION['user_role'] ?? '', $allowedRoles, true)) {
            $destination = ($_SESSION['user_role'] ?? '') === 'EMPLOYEE' ? 'admin_events' : 'client_dashboard';
            header('Location: index.php?action=' . $destination);
            exit();
        }
    }

    /** Consomme la destination interne mémorisée avant l'authentification. */
    protected function pullLoginDestination(): ?string
    {
        $destination = $_SESSION['login_return_to'] ?? null;
        unset($_SESSION['login_return_to']);
        return is_string($destination) && str_starts_with($destination, 'index.php?action=')
            ? $destination : null;
    }

    /**
     * Valide le jeton CSRF soumis dans la requête POST.
     *
     * @param array $postData Payload du formulaire.
     */
    protected function validateCsrf(array $postData): void
    {
        $this->startSession();

        if (empty($postData['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $postData['csrf_token'])) {
            die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        }
    }
}
