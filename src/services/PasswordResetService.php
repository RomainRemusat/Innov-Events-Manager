<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/MailService.php';

/**
 * Réinitialise un compte actif et conserve ses identifiants si l'envoi SMTP échoue.
 */
class PasswordResetService
{
    /** @param MailService $mailer Service d'envoi des identifiants temporaires. */
    public function __construct(private MailService $mailer = new MailService())
    {
    }

    /**
     * Verrouille le compte jusqu'à la fin de la tentative d'envoi et du commit.
     * SQL et SMTP restent indépendants : un échec du commit après envoi peut
     * laisser un mail reçu dont le mot de passe n'est pas utilisable.
     *
     * @param string $email Adresse du compte à réinitialiser.
     * @return int|null Identifiant du compte modifié, ou null si le compte est absent ou inactif.
     * @throws Throwable Si l'écriture ou l'envoi échoue ; la transaction SQL est annulée.
     */
    public function reset(string $email): ?int
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT id, firstname FROM users WHERE email = ? AND is_deleted = 0 FOR UPDATE');
            $query->execute([$email]);
            $user = $query->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $db->rollBack();
                return null;
            }
            $password = bin2hex(random_bytes(6)) . 'A1!';
            $update = $db->prepare('UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?');
            $update->execute([password_hash($password, PASSWORD_BCRYPT), $user['id']]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Le mot de passe temporaire n’a pas pu être enregistré.');
            }
            if (!$this->mailer->sendResetPasswordEmail($email, $user['firstname'], $password)) {
                throw new RuntimeException('Le serveur SMTP n’a pas accepté le mot de passe temporaire.');
            }
            $db->commit();
            return (int)$user['id'];
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }
}
