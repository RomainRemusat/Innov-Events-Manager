<?php

/**
 * Contrôleur de base (Abstract Controller)
 *
 * Centralise les mécanismes transversaux : gestion des sessions,
 * contrôles d'accès RBAC et validation des jetons anti-CSRF.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.1.0
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
     * Redirige automatiquement vers le login ou le dashboard si l'accès est refusé.
     *
     * @param array $allowedRoles Liste des rôles autorisés (ex: ['ADMIN', 'EMPLOYEE']).
     */
    protected function checkAuth(array $allowedRoles = []): void
    {
        $this->startSession();

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        if (!empty($allowedRoles) && !in_array($_SESSION['user_role'] ?? '', $allowedRoles, true)) {
            $destination = ($_SESSION['user_role'] ?? '') === 'EMPLOYEE' ? 'admin_events' : 'client_dashboard';
            header('Location: index.php?action=' . $destination);
            exit();
        }
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
