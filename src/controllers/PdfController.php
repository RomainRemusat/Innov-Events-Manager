<?php
/**
 * Contrôleur : PdfController (Génération et distribution de documents commerciaux)
 *
 * Ce composant orchestre le cycle de vie des devis au format PDF.
 * Il assure la génération du document via Dompdf, son téléchargement direct
 * pour l'administrateur, et son expédition sécurisée au client.
 *
 * Exigences ECF respectées :
 * - AT1 : Sécurisation des accès (Sessions) et architecture POO (DRY, SRP).
 * - AT2 : Extraction relationnelle (MySQL) et traçabilité de l'audit (MongoDB).
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    3.0.0
 */

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../config/Database.php';

class PdfController
{
    /**
     * Génère le flux binaire (string) du document PDF.
     *
     * Méthode utilitaire privée appliquant le principe DRY (Don't Repeat Yourself).
     * Elle compile les données métiers (MySQL) et le template HTML pour produire le rendu PDF.
     *
     * @param int $devisId L'identifiant unique du devis cible.
     * @return string Le contenu du fichier PDF généré.
     * @throws \Exception Si le devis n'est pas trouvé en base de données.
     */
    private function buildPdfContent(int $devisId): string
    {
        $db = Database::getInstance();

        // 1. Récupération des données du devis et du prospect associé
        $stmt = $db->prepare("
            SELECT d.*, p.company_name, p.contact_name, p.email, p.phone, p.event_type, p.event_date, p.description, p.budget
            FROM devis d
            JOIN prospects p ON d.id_prospect = p.id
            WHERE d.id_devis = ?
            LIMIT 1
        ");
        $stmt->execute([$devisId]);
        $devis = $stmt->fetch();

        if (!$devis) {
            throw new \Exception("Anomalie métier : Devis introuvable.");
        }

        // 2. Récupération des lignes de facturation (Prestations)
        $stmtPrest = $db->prepare("SELECT * FROM prestations WHERE devis_id = ? ORDER BY id ASC");
        $stmtPrest->execute([$devisId]);
        $prestations = $stmtPrest->fetchAll();

        // 3. Mise en mémoire tampon (Output Buffering) pour capturer la vue HTML
        ob_start();
        require __DIR__ . '/../views/admin/pdf_template.php';
        $html = ob_get_clean();

        // 4. Configuration et exécution du moteur de rendu Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Autorise le chargement d'assets externes (CSS/Images)

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Déclenche le téléchargement immédiat du PDF dans le navigateur (Aperçu Admin).
     *
     * Contrôle l'habilitation de l'utilisateur avant d'autoriser l'accès au document.
     *
     * @param int $devisId Identifiant du devis à télécharger.
     * @return void
     */
    public function generatePdf(int $devisId): void
    {
        // Contrôle strict de l'état de session et des habilitations (AT1 - Sécurité)
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        if ($devisId <= 0) {
            header('Location: index.php?action=dashboard');
            exit();
        }

        try {
            // Génération centralisée du document
            $pdfOutput = $this->buildPdfContent($devisId);

            // Récupération dynamique du nom de l'entreprise pour nommer le fichier proprement
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT p.company_name 
                FROM devis d 
                JOIN prospects p ON d.id_prospect = p.id 
                WHERE d.id_devis = ?
            ");
            $stmt->execute([$devisId]);
            $client = $stmt->fetch();

            $safeCompanyName = preg_replace('/[^a-zA-Z0-9]/', '_', $client['company_name'] ?? 'Client');
            $fileName = "Devis_InnovEvents_{$safeCompanyName}.pdf";

            // Altération des en-têtes HTTP pour forcer le téléchargement sécurisé
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $pdfOutput;
            exit();

        } catch (\Exception $e) {
            error_log("Erreur lors de l'aperçu PDF : " . $e->getMessage());
            header('Location: index.php?action=dashboard');
            exit();
        }
    }

    /**
     * Traite l'expédition de la proposition commerciale au client.
     *
     * Cette méthode transactionnelle orchestre plusieurs actions :
     * 1. Génération et sauvegarde physique du PDF dans un dossier protégé (Storage).
     * 2. Mise à jour du statut relationnel du projet ("étude côté client").
     * 3. Délégation de l'envoi courriel au service dédié.
     * 4. Enregistrement d'une trace d'audit inaltérable dans MongoDB.
     *
     * @param int $devisId Identifiant du devis à expédier.
     * @return void
     */
    public function sendQuoteToClient(int $devisId): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // Vérification stricte des rôles autorisés à émettre un devis (RBAC)
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=login');
            exit;
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT d.id_prospect, d.reference_pdf, p.email, p.contact_name 
                FROM devis d 
                JOIN prospects p ON d.id_prospect = p.id 
                WHERE d.id_devis = ?
            ");
            $stmt->execute([$devisId]);
            $data = $stmt->fetch();

            if (!$data) {
                header('Location: index.php?action=admin_devis');
                exit;
            }

            $prospectId = (int)$data['id_prospect'];

            // 1. Production et Sauvegarde Physique du document
            $pdfOutput = $this->buildPdfContent($devisId);

            // Le dossier "storage" est strictement isolé du dossier "public" accessible via le Web
            $secureDirectory = __DIR__ . '/../../storage/devis/';
            if (!is_dir($secureDirectory)) {
                mkdir($secureDirectory, 0777, true);
            }

            $fullPath = $secureDirectory . $data['reference_pdf'] . '.pdf';
            file_put_contents($fullPath, $pdfOutput);

            // 2. Bascule du statut d'engagement commercial (MySQL)
            require_once __DIR__ . '/../models/sql/Prospect.php';
            $prospectModel = new Prospect();
            $prospectModel->updateStatus($prospectId, 'étude côté client');

            // 3. Expédition du courrier électronique avec pièce jointe
            require_once __DIR__ . '/../services/MailService.php';
            $mailService = new MailService();
            $mailService->sendQuoteEmail($data['email'], $data['contact_name'], $fullPath);

            // 4. Audit & Traçabilité (MongoDB - Exigence Cahier des Charges AT2)
            require_once __DIR__ . '/../models/nosql/Log.php';
            $logModel = new Log();
            $logModel->addLog(
                "ENVOI_DEVIS",
                "Devis #{$devisId} envoyé au client " . $data['email'],
                $_SESSION['user_id'],
                ['devis_id' => $devisId, 'prospect_id' => $prospectId]
            );

            // Retour visuel (Feedback UX) à l'administrateur
            $_SESSION['flash_success'] = "Le devis a bien été généré, sauvegardé et envoyé au client.";

        } catch (\Exception $e) {
            error_log("Erreur critique lors de l'envoi du devis : " . $e->getMessage());
            $_SESSION['flash_error'] = "Une erreur technique a empêché l'envoi du devis.";
        }

        header('Location: index.php?action=admin_devis');
        exit;
    }
}