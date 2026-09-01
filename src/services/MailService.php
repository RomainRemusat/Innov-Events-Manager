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
 * @version    1.1.0
 */

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
     * Envoie une alerte d'administration interne à Chloé lors du dépôt d'une nouvelle demande de devis.
     *
     * Permet le suivi en temps réel du tunnel de conversion commercial (Notification B2B).
     *
     * @param array $prospectData Tableau associatif contenant les attributs assainis du lead (company_name, contact_name, email, phone, event_type).
     * @return bool Vrai en cas de transmission SMTP réussie.
     */
    public function sendNewQuoteNotificationToAdmin(array $prospectData): bool
    {
        try {
            $mail = $this->createMailer();

            // Routage interne vers la boîte professionnelle d'administration de l'agence
            $mail->addAddress('contact@innovevents.com', 'Chloé - Direction Commerciale');
            $mail->isHTML(true);
            $mail->Subject = "⚠️ Alerte Business : Nouvelle demande de devis - " . $prospectData['company_name'];

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #334155; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #fee2e2; border-radius: 8px; background-color: #ffff8;'>
                    <h2 style='color: #991b1b; font-size: 20px; margin-top: 0;'>Nouvelle opportunité à qualifier !</h2>
                    <p>Un nouveau formulaire de demande de devis vient d'être complété sur le site public.</p>
                    <div style='background-color: #ffffff; padding: 15px; border-radius: 6px; border: 1px solid #f3f4f6; margin: 20px 0;'>
                        <h3 style='margin-top:0; font-size:14px; color:#6b7280; text-transform:uppercase;'>Fiche de qualification</h3>
                        <table style='width: 100%; font-size: 14px;'>
                            <tr><td style='padding: 5px 0; font-weight: bold; width: 40%;'>Raison Sociale :</td><td>{$prospectData['company_name']}</td></tr>
                            <tr><td style='padding: 5px 0; font-weight: bold;'>Responsable :</td><td>{$prospectData['contact_name']}</td></tr>
                            <tr><td style='padding: 5px 0; font-weight: bold;'>Courriel :</td><td><a href='mailto:{$prospectData['email']}'>{$prospectData['email']}</a></td></tr>
                            <tr><td style='padding: 5px 0; font-weight: bold;'>Téléphone :</td><td>{$prospectData['phone']}</td></tr>
                            <tr><td style='padding: 5px 0; font-weight: bold;'>Typologie :</td><td><span style='background-color:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; font-size:12px;'>{$prospectData['event_type']}</span></td></tr>
                        </table>
                    </div>
                    <p style='font-size: 13px; color: #6b7280;'>Veuillez vous connecter à la console d'administration (Back-Office) pour étudier et convertir ce prospect.</p>
                </div>
            ";

            return $mail->send();

        } catch (Exception $e) {
            error_log("Défaut MailService lors de la notification d'administration : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie un e-mail contenant un jeton d'accès ou mot de passe temporaire suite à un oubli.
     *
     * Répond aux exigences de gestion sécurisée du cycle de vie des informations secrètes (Habilitations).
     *
     * @param string $email        Adresse email du compte utilisateur à réinitialiser.
     * @param string $tempPassword Chaîne de caractères alphanumérique aléatoire générée par le contrôleur.
     * @return bool Vrai en cas de distribution réussie.
     */

    public function sendTemporaryPassword(string $email, string $tempPassword): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Procédure de récupération : Votre mot de passe temporaire - Innov'Events";

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8081';
            $loginUrl = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/index.php?action=login';

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #334155; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <h2 style='color: #0F172A; font-size: 20px; margin-top: 0;'>Réinitialisation de vos accès de sécurité</h2>
                    <p style='line-height: 1.6;'>Une demande de récupération de mot de passe a été initiée pour votre espace de gestion.</p>
                    <p style='line-height: 1.6;'>Voici vos identifiants temporaires générés automatiquement par le système :</p>
                    
                    <div style='text-align: center; margin: 25px 0; background-color: #f8fafc; padding: 15px; border-radius: 6px; border: 1px dashed #cbd5e1;'>
                        <a href=''><span style='font-family: monospace; font-size: 22px; font-weight: bold; color: #3B82F6; letter-spacing: 2px;'>{$tempPassword}</span></a>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$loginUrl}' style='background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; font-weight: bold; border-radius: 6px; display: inline-block;'>
                            Se connecter à mon espace
                        </a>
                    </div>
                    
                    <p style='line-height: 1.6; color: #b91c1c; font-weight: bold;'>🚨 Directive de Sécurité Obligatoire :</p>
                    <p style='line-height: 1.6; font-size: 13px; color: #6b7280;'>Ce code est à usage unique. Pour des raisons strictes de confidentialité et de conformité avec notre politique de sécurité interne, vous serez contraint et forcé de redéfinir un mot de passe personnel hautement sécurisé dès votre prochaine connexion.</p>
                    <hr style='border: 0; border-top: 1px solid #f1f5f9; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #94a3b8; text-align: center;'>Si vous n'êtes pas à l'origine de cette demande, veuillez ignorer ce message ou contacter immédiatement l'assistance.</p>
                </div>
            ";

            return $mail->send();

        } catch (Exception $e) {
            error_log("Défaut MailService lors du processus de réinitialisation de mot de passe : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie un e-mail contenant les identifiants initiaux suite à la conversion d'un prospect.
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

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8081';
            $loginUrl = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/index.php?action=login';

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
            } else {
                error_log("Fichier introuvable pour l'attachement : " . $filePath);
            }

            return $mail->send();
        } catch (Exception $e) {
            error_log("Erreur MailService (Envoi Devis) : " . $e->getMessage());
            return false;
        }
    }

}