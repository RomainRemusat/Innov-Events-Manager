<?php
/**
 * Contrôleur : AuthController
 *
 * Gère le cycle de vie de l'authentification des utilisateurs (administrateurs,
 * employés et clients). Il assure la validation des entrées, la vérification
 * des correspondances d'identifiants auprès du modèle, l'initialisation des variables
 * de session globale et la déconnexion sécurisée.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.2.0
 */

require_once __DIR__ . '/../models/sql/User.php';

class AuthController
{
    /**
     * Affiche l'interface du formulaire de connexion.
     *
     * Charge la vue correspondante contenant le formulaire d'authentification
     * pour les utilisateurs de la plateforme.
     *
     * @return void
     */
    public function showLoginForm(): void
    {
        require __DIR__ . '/../views/public/login.php';
    }

    /**
     * Traite et valide la tentative de connexion d'un utilisateur.
     *
     * Cette méthode intercepte les données POST, applique des filtres de sécurité
     * sanitaires, interroge le modèle d'accès aux données pour vérifier l'existence
     * du compte, valide les droits d'accès et initialise la session en cas de succès.
     * Une fois le contexte de sécurité établi, l'utilisateur est redirigé vers
     * le tableau de bord d'administration (Back-Office).
     *
     * @param array $postData Tableau associatif contenant les données du formulaire ($_POST).
     * @return void
     */
    public function login(array $postData): void
    {
        // Assainissement et validation du format de l'adresse email (Programmation défensive)
        $email = filter_var($postData['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $postData['password'] ?? '';

        // Validation de la présence des champs obligatoires
        if (!$email || empty($password)) {
            echo "<div class='container mt-5'><div class='alert alert-danger text-center'>Veuillez remplir tous les champs correctement.</div></div>";
            $this->showLoginForm();
            return; // Interruption précoce du flux (Early Return)
        }

        // Instanciation de la couche d'accès aux données de l'utilisateur (DAL)
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        /**
         * Vérification des identifiants fournis.
         * Note technique : Pour le jeu d'essai initial (MVP), la vérification est effectuée
         * par comparaison brute. En production (Sprint V2), l'utilisation de password_verify()
         * sera obligatoire suite au hachage des mots de passe (via BCRYPT/ARGON2ID).
         */
        if ($user && $password === $user['password']) {

            // Initialisation sécurisée de la session globale si non active
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Hydratation des variables de session avec les données de l'utilisateur authentifié
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_email'] = $user['email'];

            // Alignement avec les rôles ENUM définis en base de données ('ADMIN', 'EMPLOYEE', 'CLIENT')
            $_SESSION['user_role']  = $user['role'] ?? 'ADMIN';

            // Alignement avec la structure physique de la table users (colonne 'firstname')
            $_SESSION['user_name']  = $user['firstname'] ?? 'Chloé';

            // --- CORRECTION APPORTÉE : REDIRECTION CIBLÉE ---
            // Redirection immédiate vers le point d'entrée de l'espace d'administration sécurisé
            header('Location: index.php?action=dashboard');
            exit();

        } else {
            // Gestion de l'échec d'authentification : notification générique pour prévenir l'énumération d'utilisateurs (User Enumeration)
            echo "<div class='container mt-5'><div class='alert alert-danger text-center'>Email ou mot de passe incorrect.</div></div>";
            $this->showLoginForm();
        }
    }

    /**
     * Clôture la session de l'utilisateur (Déconnexion sécurisée).
     *
     * Restaure le contexte de session, supprime toutes les variables globales associées,
     * détruit physiquement la session sur le serveur pour prévenir les attaques de fixation
     * ou de vol de session, puis redirige vers la page d'accueil publique.
     *
     * @return void
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Nettoyage complet du tableau des variables de session en mémoire
        $_SESSION = array();

        // Destruction physique des données de session stockées côté serveur
        session_destroy();

        // Redirection de courtoisie vers la page d'accueil publique
        header('Location: index.php');
        exit();
    }
}