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
 * @version    1.4.0
 */

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Utilisation des classes officielles issues de la dépendance PHPMailer (installée via Composer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** Prépare et expédie les messages transactionnels de l'application. */
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
        $mail->Host        = $_ENV['SMTP_HOST'] ?? 'mailhog'; // Résolution DNS Docker interne basée sur le nom du service
        $mail->Port        = (int)($_ENV['SMTP_PORT'] ?? 1025);      // Port d'écoute standard pour l'ingestion SMTP de MailHog
        $mail->SMTPAuth    = false;     // Authentification désactivée (sécurisé dans l'environnement local)
        $mail->SMTPAutoTLS = false;     // Désactive le chiffrement TLS explicite requis en production
        $mail->Timeout = 10;
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
            $mail = $this->createMailer();

            $mail->addAddress($email, $firstname);
            $mail->isHTML(true);
            $mail->Subject = "Bienvenue chez Innov'Events - Activation de votre compte";

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
     * Alias pour l'envoi de mot de passe temporaire.
     */
    public function sendTempPasswordEmail(string $email, string $tempPassword, string $firstname = 'Client'): bool
    {
        return $this->sendResetPasswordEmail($email, $firstname, $tempPassword);
    }

    /**
     * Alias pour l'envoi de mot de passe temporaire avec prénom.
     */
    public function sendTemporaryPasswordEmail(string $email, string $firstname, string $tempPassword): bool
    {
        return $this->sendResetPasswordEmail($email, $firstname, $tempPassword);
    }

    /**
     * Notifie l'administration de la réception d'une nouvelle demande de devis.
     *
     * @param array $quoteData Données brutes du prospect saisies sur le formulaire public.
     * @return bool Vrai si la notification administrateur est acceptée.
     */
    public function sendNewQuoteAdminNotification(array $quoteData): bool
    {
        try {
            $mail = $this->createMailer();
            
            $mail->addAddress('chloe@innovevents.fr', 'Chloé (Direction)');
            $mail->isHTML(true);
            $mail->Subject = "Nouvelle demande de devis reçue - " . htmlspecialchars($quoteData['company_name'] ?? 'B2B');

            $company      = htmlspecialchars($quoteData['company_name'] ?? 'N/A');
            $contact      = htmlspecialchars($quoteData['contact_name'] ?? 'N/A');
            $email        = htmlspecialchars($quoteData['email'] ?? 'N/A');
            $phone        = htmlspecialchars($quoteData['phone'] ?? 'N/A');
            $eventType    = htmlspecialchars($quoteData['event_type'] ?? 'N/A');
            $eventDate    = htmlspecialchars($quoteData['event_date'] ?? 'N/A');
            $participants = htmlspecialchars((string)($quoteData['estimated_participants'] ?? 'N/A'));
            $budget       = !empty($quoteData['budget']) ? number_format((float)$quoteData['budget'], 2, ',', ' ') . ' €' : 'Non précisé';
            $description  = nl2br(htmlspecialchars($quoteData['description'] ?? 'Aucune description'));

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #334155; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <h2 style='color: #0F172A; margin-top: 0;'>Nouvelle Opportunité Commerciale</h2>
                    <p>Un prospect vient de soumettre une demande de devis depuis la vitrine publique.</p>
                    
                    <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                        <tr><td style='padding: 8px 0; font-weight: bold; width: 40%;'>Entreprise :</td><td>{$company}</td></tr>
                        <tr><td style='padding: 8px 0; font-weight: bold;'>Contact :</td><td>{$contact}</td></tr>
                        <tr><td style='padding: 8px 0; font-weight: bold;'>Email :</td><td><a href='mailto:{$email}'>{$email}</a></td></tr>
                        <tr><td style='padding: 8px 0; font-weight: bold;'>Téléphone :</td><td>{$phone}</td></tr>
                        <tr><td style='padding: 8px 0; font-weight: bold;'>Type d'événement :</td><td>{$eventType}</td></tr>
                        <tr><td style='padding: 8px 0; font-weight: bold;'>Date souhaitée :</td><td>{$eventDate}</td></tr>
                        <tr><td style='padding: 8px 0; font-weight: bold;'>Participants estimés :</td><td>{$participants}</td></tr>
                        <tr><td style='padding: 8px 0; font-weight: bold;'>Budget indicatif :</td><td>{$budget}</td></tr>
                    </table>

                    <div style='background-color: #f8fafc; padding: 15px; border-left: 4px solid #3B82F6; margin: 20px 0;'>
                        <strong>Description / Vision du projet :</strong><br>
                        <p style='margin: 8px 0 0 0;'>{$description}</p>
                    </div>

                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='http://localhost:8081/index.php?action=dashboard' style='background-color: #0F172A; color: #ffffff; padding: 10px 20px; text-decoration: none; font-weight: bold; border-radius: 6px;'>Accéder au tableau de bord</a>
                    </div>
                </div>
            ";

            return $mail->send();

        } catch (Exception $e) {
            error_log("Défaut MailService lors de l'alerte prospect : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie la proposition commerciale finalisée avec le devis officiel joint en PDF.
     *
     * @param string $clientEmail Adresse email du client corporate destinataire.
     * @param string $clientName  Nom ou dénomination de l'entreprise cliente.
     * @param string $pdfFilePath Chemin absolu sécurisé vers le fichier PDF généré.
     * @return bool Vrai en cas de succès d'expédition SMTP.
     */
    public function sendQuoteEmail(string $clientEmail, string $clientName, string $pdfFilePath): bool
    {
        try {
            $mail = $this->createMailer();

            $mail->addAddress($clientEmail, $clientName);
            $mail->isHTML(true);
            $mail->Subject = "Votre proposition commerciale personnalisée - Innov'Events";

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #334155; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <div style='text-align: center; margin-bottom: 25px;'>
                        <h1 style='color: #0F172A; font-size: 24px; font-weight: bold; margin: 0;'>INNOV'EVENTS</h1>
                    </div>
                    <h2 style='color: #0F172A; font-size: 18px;'>Bonjour {$clientName},</h2>
                    <p style='line-height: 1.6;'>Chloé et l'équipe Innov'Events ont le plaisir de vous transmettre la proposition commerciale chiffrée pour votre projet événementiel.</p>
                    <p style='line-height: 1.6;'>Vous trouverez en pièce jointe de ce courriel votre <strong>devis contractuel au format PDF</strong> détaillant l'ensemble des prestations retenues.</p>
                    <p style='line-height: 1.6;'>Vous pouvez également vous connecter directement à votre espace client pour valider ce document ou solliciter des ajustements :</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost:8081/index.php?action=client_dashboard' style='background-color: #3B82F6; color: #ffffff; padding: 12px 24px; text-decoration: none; font-weight: bold; border-radius: 6px; display: inline-block;'>Consulter sur mon espace client</a>
                    </div>
                    <p style='line-height: 1.6; margin-bottom: 0;'>Restant à votre entière disposition,<br><strong>Chloé - Direction Innov'Events</strong></p>
                </div>
            ";

            // Attachement du fichier PDF physique si disponible sur le serveur
            if (file_exists($pdfFilePath)) {
                $mail->addAttachment($pdfFilePath, basename($pdfFilePath));
            }

            return $mail->send();

        } catch (Exception $e) {
            error_log("Défaut MailService lors de l'envoi du devis PDF : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notifie la direction de l'acceptation d'un devis par le client.
     */
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

    /**
     * Notifie la direction d'une demande d'ajustement / modification émise par le client sur un devis.
     */
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

    /**
     * Notifie la direction du refus d'un devis par le client.
     */
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

    /**
     * Notifie le prospect du refus de sa demande de devis avec le motif explicatif.
     *
     * @param string $email         Adresse email du prospect.
     * @param string $contactName   Nom du contact.
     * @param string $refusalReason Motif explicite du refus.
     * @return bool Vrai si envoyé.
     */
    public function sendQuoteRefusal(string $email, string $contactName, string $refusalReason): bool
    {
        try {
            $mail = $this->createMailer();

            $mail->addAddress($email, $contactName);
            $mail->isHTML(true);
            $mail->Subject = "Information concernant votre demande de devis - Innov'Events";
            $safeContactName = htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8');

            $reasonHtml = !empty($refusalReason) 
                ? "<div style='background-color: #f8fafc; padding: 15px; border-left: 4px solid #ef4444; margin: 20px 0;'><strong>Motif :</strong><br>" . nl2br(htmlspecialchars($refusalReason, ENT_QUOTES, 'UTF-8')) . "</div>"
                : "";

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #334155; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <h2 style='color: #0F172A; font-size: 18px;'>Bonjour {$safeContactName},</h2>
                    <p style='line-height: 1.6;'>Nous vous remercions pour l'intérêt que vous portez aux services d'Innov'Events.</p>
                    <p style='line-height: 1.6;'>Après analyse attentive de votre cahier des charges, nous avons le regret de vous informer que nous ne pourrons pas donner une suite favorable à votre demande pour la date souhaitée.</p>
                    {$reasonHtml}
                    <p style='line-height: 1.6; margin-bottom: 0;'>Nous restons à votre disposition pour de futurs projets.<br><strong>L'équipe Innov'Events</strong></p>
                </div>
            ";

            return $mail->send();

        } catch (Exception $e) {
            error_log("Défaut MailService lors de la notification de refus : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Alias de sendQuoteRefusal pour compatibilité.
     */
    public function sendRejectionEmail(string $email, string $contactName, string $reason = ''): bool
    {
        return $this->sendQuoteRefusal($email, $contactName, $reason);
    }
}
