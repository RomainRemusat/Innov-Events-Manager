<?php
/**
 * Service : MailService
 *
 * Centralise, encapsule et orchestre l'ensemble des envois de courriels au sein
 * de la plateforme Innov'Events Manager.
 *
 * Avantages de cette implémentation découplée (Couche Service) :
 * - Centralisation de la configuration SMTP globale de l'application.
 * - Réutilisabilité immédiate par n'importe quel contrôleur (Auth, Quote, etc.).
 * - Isolation complète des templates HTML pour faciliter la maintenance de la charte graphique.
 * - Résilience : la capture des exceptions empêche les pannes de messagerie d'altérer le flux HTTP principal.
 *
 * @package    InnovEventsManager
 * @subpackage Services
 * @author     Romain Remusat
 * @version    1.3.0
 */

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Utilisation des classes officielles issues de la dépendance PHPMailer (installée via Composer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    /**
     * Initialise, configure et sécurise une instance de PHPMailer.
     *
     * Cette méthode centralise l'aiguillage SMTP vers le serveur d'interception
     * local MailHog s'exécutant au sein du réseau isolé de conteneurs Docker.
     *
     * @return PHPMailer Instance configurée prête pour l'injection de destinataires et de contenus.
     */
    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        // ---------------------------------------------------------------------
        // CONFIGURATION TECHNIQUE DU SERVEUR SMTP DE TEST (MailHog)
        // ---------------------------------------------------------------------
        $mail->isSMTP();
        $mail->Host        = 'mailhog'; // Résolution DNS Docker interne basée sur le nom du service
        $mail->Port        = 1025;      // Port d'écoute standard pour l'ingestion SMTP de MailHog
        $mail->SMTPAuth    = false;     // Authentification désactivée (sécurisé dans l'environnement local)
        $mail->SMTPAutoTLS = false;     // Désactive le chiffrement TLS explicite requis en production
        $mail->CharSet     = 'UTF-8';   // Encodage universel pour prévenir les altérations d'accents

        // Définition de l'identité de l'expéditeur unique (Conformité DKIM/SPF théorique)
        $mail->setFrom('no-reply@innovevents.fr', "L'équipe Innov'Events");

        return $mail;
    }

    /**
     * Envoie l'e-mail de bienvenue et de confirmation d'inscription à un nouveau client.
     *
     * Gère la notification transactionnelle suite au parcours d'inscription public.
     *
     * @param string $email     Adresse email de destination du client (Login).
     * @param string $firstname Prénom du destinataire pour personnalisation dynamique du template.
     * @return bool Vrai si le courriel a été accepté par le serveur SMTP, faux en cas d'anomalie.
     */
    public function sendRegisterConfirmation(string $email, string $firstname): bool
    {
        try {
            // Initialisation de la pile d'infrastructure de messagerie
            $mail = $this->createMailer();

            // Paramétrage des informations de routage
            $mail->addAddress($email, $firstname);
            $mail->isHTML(true);
            $mail->Subject = "Bienvenue chez Innov'Events - Activation de votre compte";

            // Injection du gabarit visuel HTML calqué sur la charte graphique de la marque
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #334155; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <div style='text-align: center; margin-bottom: 25px;'>
                        <h1 style='color: #0F172A; font-size: 24px; font-weight: bold; margin: 0;'>INNOV'EVENTS</h1>
                        <p style='color: #3B82F6; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; margin: 5px 0 0 0;'>Votre espace collaboratif</p>
                    </div>
                    <hr style='border: 0; border-top: 1px solid #f1f5f9; margin-bottom: 25px;'>
                    <h2 style='color: #0F172A; font-size: 18px; margin-top: 0;'>Bonjour {$firstname},</h2>
                    <p style='line-height: 1.6;'>Votre compte client a été créé avec succès sur notre plateforme d'accompagnement <strong>Innov'Events Manager</strong>.</p>
                    <p style='line-height: 1.6;'>Vous pouvez dès à présent vous connecter pour suivre l'édition de vos demandes de devis et collaborer en temps réel avec Chloé pour l'organisation de vos projets.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost:8081/index.php?action=login' style='background-color: #3B82F6; color: #ffffff; padding: 12px 24px; text-decoration: none; font-weight: bold; border-radius: 6px; display: inline-block;'>Accéder à mon espace sécurisé</a>
                    </div>
                    <p style='line-height: 1.6; margin-bottom: 0;'>À très bientôt,<br><strong>L'équipe Innov'Events</strong></p>
                </div>
            ";

            return $mail->send();

        } catch (Exception $e) {
            // Journalisation technique isolée : empêche un crash SMTP de paralyser le parcours d'inscription
            error_log("Défaut MailService critique lors de la confirmation d'inscription : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie l'e-mail de réinitialisation avec mot de passe temporaire suite à demande "Mot de passe oublié".
     *
     * @param string $email        Adresse email du destinataire.
     * @param string $firstname    Prénom du destinataire.
     * @param string $tempPassword Mot de passe temporaire en clair à transmettre de façon sécurisée.
     * @return bool Vrai en cas d'émission SMTP validée.
     */
    public function sendResetPasswordEmail(string $email, string $firstname, string $tempPassword): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($email, $firstname);
            $mail->isHTML(true);
            $mail->Subject = "Réinitialisation de votre mot de passe - Innov'Events";

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #334155; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <div style='text-align: center; margin-bottom: 25px;'>
                        <h1 style='color: #0F172A; font-size: 24px; font-weight: bold; margin: 0;'>INNOV'EVENTS</h1>
                    </div>
                    <h2 style='color: #0F172A; font-size: 18px;'>Bonjour {$firstname},</h2>
                    <p style='line-height: 1.6;'>Une demande de réinitialisation de vos identifiants a été enregistrée.</p>
                    <p style='line-height: 1.6;'>Voici votre mot de passe temporaire pour vous reconnecter :</p>
                    <div style='background: #f8fafc; border: 1px dashed #cbd5e1; padding: 15px; text-align: center; margin: 20px 0; border-radius: 6px;'>
                        <span style='font-family: monospace; font-size: 20px; font-weight: bold; color: #2563EB;'>{$tempPassword}</span>
                    </div>
                    <p style='line-height: 1.6; color: #dc2626; font-size: 13px;'><strong>Consigne de sécurité :</strong> Il vous sera expressément demandé de définir un nouveau mot de passe personnel dès votre accès à la plateforme.</p>
                    <div style='text-align: center; margin: 25px 0;'>
                        <a href='http://localhost:8081/index.php?action=login' style='background-color: #3B82F6; color: #ffffff; padding: 10px 20px; text-decoration: none; font-weight: bold; border-radius: 6px; display: inline-block;'>Se connecter</a>
                    </div>
                </div>
            ";

            return $mail->send();

        } catch (Exception $e) {
            error_log("Défaut MailService lors du reset de mot de passe : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Alias pour l'envoi de mot de passe temporaire (compatibilité AuthController).
     */
    public function sendTemporaryPassword(string $email, string $tempPassword): bool
    {
        return $this->sendResetPasswordEmail($email, 'Client', $tempPassword);
    }

    /**
     * Notifie l'administration (Chloé) de la réception d'une nouvelle demande de devis prospect.
     *
     * @param array $quoteData Données brutes du prospect saisies sur le formulaire public.
     * @return bool Vrai si la notification administrateur est acceptée.
     */
    public function sendNewQuoteAdminNotification(array $quoteData): bool
    {
        try {
            $mail = $this->createMailer();
            
            // Notification routée vers la boîte de gestion de Chloé
            $mail->addAddress('chloe@innovevents.fr', 'Chloé (Direction)');
            $mail->isHTML(true);
            $mail->Subject = "Nouvelle demande de devis reçue - " . htmlspecialchars($quoteData['company_name'] ?? 'B2B');

            $company      = htmlspecialchars($quoteData['company_name'] ?? 'N/A');
            $contact      = htmlspecialchars($quoteData['contact_name'] ?? 'N/A');
            $email        = htmlspecialchars($quoteData['email'] ?? 'N/A');
            $phone        = htmlspecialchars($quoteData['phone'] ?? 'N/A');
            $eventType    = htmlspecialchars($quoteData['event_type'] ?? 'N/A');
            $eventDate    = htmlspecialchars($quoteData['event_date'] ?? 'N/A');
            $location     = htmlspecialchars($quoteData['location'] ?? 'N/A');
            $participants = htmlspecialchars((string)($quoteData['estimated_participants'] ?? 'N/A'));
            $description  = nl2br(htmlspecialchars($quoteData['description'] ?? 'Aucun détail fourni.'));

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #334155; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <h2 style='color: #0F172A; border-bottom: 2px solid #3B82F6; padding-bottom: 8px; margin-top: 0;'>Nouvelle opportunité commerciale</h2>
                    <p>Un prospect vient de soumettre une demande de projet sur le portail public :</p>
                    <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                        <tr style='background-color: #f8fafc;'><td style='padding: 8px; font-weight: bold;'>Société :</td><td style='padding: 8px;'>{$company}</td></tr>
                        <tr><td style='padding: 8px; font-weight: bold;'>Contact :</td><td style='padding: 8px;'>{$contact} ({$email} / {$phone})</td></tr>
                        <tr style='background-color: #f8fafc;'><td style='padding: 8px; font-weight: bold;'>Type d'événement :</td><td style='padding: 8px;'>{$eventType}</td></tr>
                        <tr><td style='padding: 8px; font-weight: bold;'>Date souhaitée :</td><td style='padding: 8px;'>{$eventDate} à {$location}</td></tr>
                        <tr style='background-color: #f8fafc;'><td style='padding: 8px; font-weight: bold;'>Participants estimés :</td><td style='padding: 8px;'>{$participants} personnes</td></tr>
                    </table>
                    <p><strong>Détails du besoin :</strong></p>
                    <div style='background-color: #f1f5f9; padding: 12px; border-radius: 6px; font-style: italic;'>{$description}</div>
                    <div style='text-align: center; margin-top: 25px;'>
                        <a href='http://localhost:8081/index.php?action=admin_prospects' style='background-color: #0F172A; color: #ffffff; padding: 10px 20px; text-decoration: none; font-weight: bold; border-radius: 6px; display: inline-block;'>Traiter la demande sur le tableau de bord</a>
                    </div>
                </div>
            ";

            return $mail->send();

        } catch (Exception $e) {
            error_log("Défaut MailService lors de l'alerte admin prospect : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Transmet au nouveau client son mot de passe temporaire lors de la conversion administrative d'un prospect (AT2).
     *
     * @param string $email        Adresse email du nouveau compte client.
     * @param string $firstname    Prénom du client pour personnalisation.
     * @param string $tempPassword Mot de passe temporaire généré.
     * @return bool Vrai en cas de distribution réussie.
     */
    public function sendTemporaryPasswordEmail(string $email, string $firstname, string $tempPassword): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($email, $firstname);
            $mail->isHTML(true);
            $mail->Subject = "Vos accès à l'espace client Innov'Events";

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? null) == 443) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8081';
            $loginUrl = $protocol . $host . dirname($_SERVER['PHP_SELF'] ?? '') . '/index.php?action=login';

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #334155; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <h2 style='color: #0F172A; font-size: 20px; margin-top: 0;'>Bonjour {$firstname},</h2>
                    <p style='line-height: 1.6;'>Un compte client sécurisé a été créé par notre équipe pour le suivi de vos projets événementiels.</p>
                    <p style='line-height: 1.6;'>Voici vos identifiants temporaires générés automatiquement :</p>
                    
                    <div style='text-align: center; margin: 25px 0; background-color: #f8fafc; padding: 15px; border-radius: 6px; border: 1px dashed #cbd5e1;'>
                        <p style='margin: 5px 0;'><strong>Identifiant :</strong> {$email}</p>
                        <p style='margin: 5px 0;'><strong>Mot de passe :</strong> <span style='font-family: monospace; font-size: 18px; font-weight: bold; color: #3B82F6;'>{$tempPassword}</span></p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$loginUrl}' style='background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; font-weight: bold; border-radius: 6px; display: inline-block;'>
                            Accéder à mon espace
                        </a>
                    </div>
                    
                    <p style='line-height: 1.6; color: #b91c1c; font-weight: bold;'>🚨 Directive de Sécurité :</p>
                    <p style='line-height: 1.6; font-size: 13px; color: #6b7280;'>Il vous sera demandé de modifier ce mot de passe dès votre première connexion pour garantir la confidentialité de vos données.</p>
                </div>
            ";

            return $mail->send();

        } catch (Exception $e) {
            error_log("Défaut MailService (Création Client) : " . $e->getMessage());
            return false;
        }
    }

    public function sendQuoteEmail(string $email, string $clientName, string $filePath): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($email, $clientName);
            $mail->Subject = "Votre proposition commerciale - Innov'Events";

            $mail->isHTML(true);
            $mail->Body = "<p>Bonjour {$clientName},</p><p>Veuillez trouver ci-joint votre devis. Il est également consultable depuis votre espace client.</p>";

            // Attachement du document PDF physique
            if (file_exists($filePath)) {
                $mail->addAttachment($filePath);
            }

            return $mail->send();

        } catch (Exception $e) {
            error_log("Défaut MailService lors de l'envoi du devis : " . $e->getMessage());
            return false;
        }
    }

    public function sendQuoteAcceptedEmail(string $companyName, int $devisId): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress('chloe@innovevents.fr', 'Chloé (Direction)');
            $mail->isHTML(true);
            $mail->Subject = "Devis #{$devisId} ACCEPTÉ par {$companyName}";
            $mail->Body = "<p>Bonjour Chloé,</p><p>Excellente nouvelle ! L'entreprise <strong>" . htmlspecialchars($companyName) . "</strong> a accepté le devis <strong>#{$devisId}</strong>.</p>";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Défaut MailService (Devis accepté) : " . $e->getMessage());
            return false;
        }
    }

    public function sendModificationRequestEmail(string $companyName, int $devisId, string $reason): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress('chloe@innovevents.fr', 'Chloé (Direction)');
            $mail->isHTML(true);
            $mail->Subject = "Demande de modification pour le devis #{$devisId} - {$companyName}";
            $mail->Body = "<p>Bonjour Chloé,</p><p>L'entreprise <strong>" . htmlspecialchars($companyName) . "</strong> souhaite modifier le devis <strong>#{$devisId}</strong>.</p><p><strong>Motif indiqué :</strong> " . nl2br(htmlspecialchars($reason)) . "</p>";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Défaut MailService (Demande modif devis) : " . $e->getMessage());
            return false;
        }
    }

    public function sendQuoteRejectedEmail(string $companyName, int $devisId): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress('chloe@innovevents.fr', 'Chloé (Direction)');
            $mail->isHTML(true);
            $mail->Subject = "Devis #{$devisId} REFUSÉ par {$companyName}";
            $mail->Body = "<p>Bonjour Chloé,</p><p>L'entreprise <strong>" . htmlspecialchars($companyName) . "</strong> a décliné la proposition pour le devis <strong>#{$devisId}</strong>.</p>";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Défaut MailService (Devis refusé) : " . $e->getMessage());
            return false;
        }
    }

    public function sendQuoteRefusal(string $email, string $clientName, string $reason): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($email, $clientName);
            $mail->isHTML(true);
            $mail->Subject = "Information concernant votre demande d'événement - Innov'Events";
            $mail->Body = "<p>Bonjour " . htmlspecialchars($clientName) . ",</p><p>Nous ne pouvons malheureusement pas donner suite à votre demande pour la raison suivante :</p><p>" . nl2br(htmlspecialchars($reason)) . "</p><p>Cordialement,<br>L'équipe Innov'Events</p>";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Défaut MailService (sendQuoteRefusal) : " . $e->getMessage());
            return false;
        }
    }
}
