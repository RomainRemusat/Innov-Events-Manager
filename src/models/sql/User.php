<?php
/**
 * Modèle : User (Gestion des accès aux données Utilisateurs)
 *
 * Ce composant agit comme une couche d'accès aux données (Data Access Object - DAO)
 * pour l'entité Utilisateur. Il interagit directement avec la table `users`
 * de la base de données relationnelle MySQL via une instance PDO sécurisée.
 *
 * @package    InnovEventsManager
 * @subpackage Models\SQL
 * @author     Romain Remusat
 * @version    1.1.0
 */

require_once __DIR__ . '/../../config/Database.php';

class User
{
    /**
     * @var PDO Instance de connexion active à la base de données.
     */
    private $db;

    /**
     * Constructeur de la classe User.
     *
     * Initialise la connexion à la base de données en récupérant
     * l'instance unique (Pattern Singleton) garantie par la classe Database,
     * évitant ainsi la multiplication des connexions au serveur SQL.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recherche et récupère un utilisateur spécifique grâce à son adresse email.
     *
     * Cette méthode utilise systématiquement une requête préparée PDO pour prévenir
     * de manière stricte toute tentative d'injection SQL. La clause `LIMIT 1` est
     * ajoutée pour optimiser les performances de lecture du moteur de base de données.
     *
     * @param string $email L'adresse email de l'utilisateur à rechercher.
     * @return array|false Retourne un tableau associatif contenant les données de l'utilisateur,
     * ou false (booléen) si aucune correspondance n'est trouvée.
     */
    public function findByEmail(string $email)
    {
        // Préparation de la requête SQL sécurisée avec un marqueur nommé (:email)
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);

        // Exécution de la requête en liant dynamiquement la variable nettoyée au paramètre
        $stmt->execute([':email' => $email]);

        // Récupération et retour du résultat formaté en tableau associatif pur
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Insère un nouvel utilisateur (généralement un client) dans la base de données.
     *
     * @param array $data Tableau associatif contenant les clés : email, password, firstname, lastname, role.
     * @return int|null L'identifiant (ID) généré en base de données, ou null en cas d'échec d'insertion.
     */
    public function create(array $data): ?int
    {
        try {
            // Préparation de la requête d'insertion sécurisée (Anti-Injection SQL)
            $stmt = $this->db->prepare("
                INSERT INTO users (email, password, firstname, lastname, role) 
                VALUES (:email, :password, :firstname, :lastname, :role)
            ");

            // Exécution avec liaison dynamique des paramètres assainis
            $success = $stmt->execute([
                'email'     => $data['email'],
                'password'  => $data['password'], // Doit être déjà haché en amont (Bcrypt)
                'firstname' => $data['firstname'],
                'lastname'  => $data['lastname'],
                'role'      => $data['role'] ?? 'CLIENT'
            ]);

            // Retourne l'ID auto-incrémenté généré par MySQL si l'insertion a fonctionné
            return $success ? (int)$this->db->lastInsertId() : null;

        } catch (PDOException $e) {
            // Journalisation de l'erreur dans les logs système sans la divulguer à l'écran
            error_log("Erreur SQL lors de la création de l'utilisateur : " . $e->getMessage());
            return null;
        }
    }
    /**
     * Supprime définitivement un compte utilisateur (Conformité RGPD - Droit à l'oubli).
     * Grâce à la contrainte ON DELETE CASCADE, les prospects et devis associés
     * seront automatiquement purgés par le moteur MySQL.
     *
     * @param int $userId Identifiant de l'utilisateur à supprimer.
     * @return bool Vrai en cas de succès de la suppression.
     */
    public function deleteAccount(int $userId): bool
    {
        try {
            $sql = "DELETE FROM users WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':id' => $userId]);
        } catch (PDOException $e) {
            error_log("Erreur critique (RGPD) lors de la suppression du compte $userId : " . $e->getMessage());
            return false;
        }
    }


    /**
     * Met à jour le mot de passe d'un utilisateur.
     *
     * Utilisé notamment lors de la procédure de mot de passe oublié pour
     * imposer un changement de mot de passe à la prochaine connexion.
     *
     * @param int $userId L'identifiant de l'utilisateur.
     * @param string $hashedPassword Le nouveau mot de passe haché (Bcrypt).
     * @param bool $mustChange Vrai si l'utilisateur doit changer son mot de passe, Faux sinon.
     * @return bool Retourne true en cas de succès, false en cas d'échec.
     */
    public function updatePassword(int $userId, string $hashedPassword, bool $mustChange): bool
    {
        try {
            // Préparation de la requête pour éviter les injections SQL
            $sql = "UPDATE users SET password = :password, must_change_password = :must_change WHERE id = :id";
            $stmt = $this->db->prepare($sql);

            // Exécution avec liaison dynamique des paramètres
            return $stmt->execute([
                ':password'    => $hashedPassword,
                ':must_change' => $mustChange ? 1 : 0, // Conversion du booléen en entier pour MySQL
                ':id'          => $userId
            ]);

        } catch (PDOException $e) {
            // Journalisation silencieuse de l'erreur
            error_log("Erreur SQL lors de la mise à jour du mot de passe (User ID $userId) : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère la liste de tous les clients finaux.
     *
     * Exclut les administrateurs et employés grâce à la clause WHERE role = 'CLIENT'.
     *
     * @return array Tableau associatif des clients
     */
    public function findAllClients(): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE role = 'CLIENT' ORDER BY created_at DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur lors de la récupération des clients : " . $e->getMessage());
            return [];
        }
    }


    /**
     * Effectue une suppression logique (Soft Delete) du client.
     * Conserve l'intégrité référentielle des devis et événements liés.
     *
     * @param int $id L'identifiant du client à supprimer
     * @return bool
     */
    public function softDeleteClient(int $id): bool
    {
        try {
            // On part du principe que tu as ajouté une colonne 'is_deleted' (TINYINT par défaut à 0)
            // ou que tu gères un statut de compte.
            $sql = "UPDATE users SET is_deleted = 1 WHERE id = :id AND role = 'CLIENT'";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':id' => $id]);
        } catch (\PDOException $e) {
            error_log("Erreur lors de la suppression logique du client $id : " . $e->getMessage());
            return false;
        }
    }


    /**
     * Met à jour les informations d'un utilisateur (Client).
     *
     * @param int $id Identifiant du client
     * @param string $firstname Prénom
     * @param string $lastname Nom de famille
     * @param string $email Adresse email
     * @return bool True en cas de succès, False sinon
     */
    public function updateClient(int $id, string $firstname, string $lastname, string $email): bool
    {
        try {
            $sql = "UPDATE users SET firstname = :firstname, lastname = :lastname, email = :email WHERE id = :id AND role = 'CLIENT'";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':firstname' => $firstname,
                ':lastname'  => $lastname,
                ':email'     => $email,
                ':id'        => $id
            ]);
        } catch (\PDOException $e) {
            error_log("Erreur lors de la mise à jour du client $id : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recherche et récupère un utilisateur unique par son identifiant.
     * Utilise une requête préparée pour prévenir les injections SQL.
     *
     * @param int $id L'identifiant unique de l'utilisateur.
     * @return array|false Tableau associatif des données ou false si non trouvé.
     */
    public function findById(int $id)
    {
        try {
            $query = "SELECT * FROM users WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $id]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur lors de la récupération de l'utilisateur $id : " . $e->getMessage());
            return false;
        }
    }

}


