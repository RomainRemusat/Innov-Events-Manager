<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/Company.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../services/AccountDeletionService.php';

/** Gère les comptes clients et employés ; aucun administrateur ne peut être créé ou supprimé ici. */
class AdminAccountController extends BaseController
{
    /** Affiche les comptes actifs ou suspendus et le formulaire de création. */
    public function index(): void
    {
        $this->checkAuth(['ADMIN']);
        $accounts = (new User())->findManagedAccounts();
        $companies = (new Company())->findAll();
        $inputs = $_SESSION['account_inputs'] ?? [];
        unset($_SESSION['account_inputs']);
        $pageTitle = 'Gestion des comptes';
        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/accounts.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Crée, suspend, réactive ou supprime un compte selon une action explicite.
     * @param array $data Champs POST ; l'identité de l'administrateur vient de sa session.
     */
    public function manage(array $data): void
    {
        $this->checkAuth(['ADMIN']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_accounts');
            exit;
        }
        $this->validateCsrf($data);
        unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);
        $users = new User();
        try {
            foreach ($data as $value) {
                if (!is_scalar($value)) throw new InvalidArgumentException('Les champs du formulaire sont invalides.');
            }
            $operation = $data['operation'] ?? '';
            if ($operation === 'create') {
                $firstname = trim($data['firstname'] ?? '');
                $lastname = trim($data['lastname'] ?? '');
                $email = trim($data['email'] ?? '');
                $role = $data['role'] ?? '';
                if ($firstname === '' || mb_strlen($firstname) > 100 || $lastname === '' || mb_strlen($lastname) > 100
                    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
                    || !in_array($role, ['CLIENT', 'EMPLOYEE'], true)) {
                    throw new InvalidArgumentException('Renseignez une identité, un email valide et un rôle client ou employé.');
                }
                $companyId = null;
                if ($role === 'CLIENT' && ($data['company_id'] ?? '') !== '') {
                    $companyId = filter_var($data['company_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if (!$companyId || !(new Company())->findById($companyId)) {
                        throw new InvalidArgumentException('L’entreprise sélectionnée est introuvable.');
                    }
                }
                $password = 'Temp_' . bin2hex(random_bytes(10)) . 'A1!';
                $id = $users->create([
                    'firstname' => $firstname, 'lastname' => $lastname, 'email' => $email, 'role' => $role,
                    'company_id' => $companyId, 'password' => password_hash($password, PASSWORD_BCRYPT),
                    'must_change_password' => true,
                ]);
                if (!$id) throw new InvalidArgumentException('Création impossible : adresse déjà utilisée ou erreur d’enregistrement.');
                unset($_SESSION['account_inputs']);
                $_SESSION['flash_success'] = 'Le compte a été créé. Le mot de passe devra être changé à la première connexion.';
                if (!(new MailService())->sendTemporaryPasswordEmail($email, $firstname, $password)) {
                    $_SESSION['flash_warning'] = 'Le compte est créé, mais l’email des accès n’a pas pu être envoyé. Utilisez « Mot de passe oublié » pour demander de nouveaux accès.';
                }
                (new Log())->addLog('CREATION_' . $role, (int)$_SESSION['user_id'], [
                    'client_id' => $role === 'CLIENT' ? $id : null, 'account_id' => $id,
                    'name' => $firstname . ' ' . $lastname,
                ]);
            } else {
                $id = filter_var($data['account_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $account = $id ? $users->findById($id) : false;
                if (!$account || !in_array($account['role'], ['CLIENT', 'EMPLOYEE'], true)
                    || !in_array($operation, ['suspend', 'restore', 'delete'], true)) {
                    throw new InvalidArgumentException('Compte ou opération non autorisés.');
                }
                if ($operation === 'delete') {
                    if (($data['confirm_delete'] ?? '') !== '1') throw new InvalidArgumentException('Confirmez la suppression définitive.');
                    $success = $account['role'] === 'CLIENT'
                        ? (new AccountDeletionService())->delete($id) : $users->deleteEmployee($id);
                } else {
                    $success = $users->setSuspended($id, $operation === 'suspend');
                }
                if (!$success) throw new RuntimeException('Opération non finalisée : rechargez la liste avant de réessayer.');
                $_SESSION['flash_success'] = match ($operation) {
                    'delete' => 'Compte supprimé définitivement.', 'restore' => 'Compte réactivé.', default => 'Compte suspendu. Ses dossiers sont conservés.',
                };
                $action = ['delete' => 'SUPPRESSION', 'restore' => 'REACTIVATION', 'suspend' => 'SUSPENSION'][$operation];
                (new Log())->addLog($action . '_' . $account['role'], (int)$_SESSION['user_id'], [
                    'client_id' => $account['role'] === 'CLIENT' ? $id : null,
                    'account_id' => $id, 'name' => $account['firstname'] . ' ' . $account['lastname'],
                ]);
            }
        } catch (InvalidArgumentException $error) {
            $_SESSION['flash_error'] = $error->getMessage();
            if (($data['operation'] ?? '') === 'create') {
                $_SESSION['account_inputs'] = array_filter(array_intersect_key($data,
                    array_flip(['firstname', 'lastname', 'email', 'role', 'company_id'])), 'is_scalar');
            }
        } catch (Throwable $error) {
            error_log('[AdminAccountController] ' . $error->getMessage());
            $_SESSION['flash_error'] = 'L’opération n’a pas pu être finalisée. Rechargez la liste pour vérifier l’état du compte avant de réessayer.';
        }
        header('Location: index.php?action=admin_accounts');
        exit;
    }
}
