<?php
/**
 * Contrôleur : PdfController (Génération et distribution de documents commerciaux)
 *
 * Ce composant orchestre le cycle de vie des devis au format PDF.
 * Il assure la génération du document via Dompdf, l'aperçu administrateur,
 * l'expédition sécurisée au client et le téléchargement depuis l'espace client.
 *
 * Exigences ECF respectées :
 * - AT1 : Sécurisation des accès (Sessions) et protection contre les failles Path Traversal.
 * - AT2 : Extraction relationnelle (MySQL) et traçabilité de l'audit (MongoDB).
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    3.1.0
 */

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../config/Database.php';

class PdfController
{
    /**
     * Génère le flux binaire (string) du document PDF.
     *
     * Compiles les données métiers (MySQL) et le template HTML pour produire le rendu PDF.
     *
     * @param  int $devisId Identifiant unique du devis cible.
     * @return string       Le contenu binaire du fichier PDF généré.
     * @throws \Exception   Si le devis n'est pas trouvé en base de données.
     */
    private function buildPdfContent(int $devisId): string
    {
        $db = Database::getInstance();

        // 1. Récupération des données du devis et du prospect associé
        $stmt = $db->prepare("
            SELECT d.*, 
                   p.company_name, 
                   p.contact_name, 
                   p.email, 
                   p.phone, 
                   p.event_type, 
                   p.event_date, 
                   p.location, 
                   p.estimated_participants, 
                   p.description, 
                   p.budget
            FROM devis d
            JOIN prospects p ON d.id_prospect = p.id
            WHERE d.id_devis = ?
            LIMIT 1
        ");
        $stmt->execute([$devisId]);
        $devis = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$devis) {
            throw new \Exception("Anomalie métier : Devis introuvable.");
        }

        // 2. Récupération des lignes de facturation (Prestations)
        $stmtPrest = $db->prepare("SELECT * FROM prestations WHERE devis_id = ? ORDER BY id ASC");
        $stmtPrest->execute([$devisId]);
        $prestations = $stmtPrest->fetchAll(PDO::FETCH_ASSOC);

        // 3. Calcul des totaux pour injection directe dans la vue PDF
        $totalHT = 0.0;
        foreach ($prestations as $p) {
            $totalHT += (float)($p['montant_ht'] ?? 0);
        }
        $totalTVA = $totalHT * 0.20;
        $totalTTC = $totalHT + $totalTVA;

        // 4. Mise en mémoire tampon (Output Buffering) pour capturer la vue HTML
        ob_start();
        require __DIR__ . '/../views/admin/pdf_template.php';
        $html = ob_get_clean();

        // 5. Configuration et exécution du moteur de rendu Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Déclenche l'aperçu ou le téléchargement immédiat du PDF (Espace Admin).
     *
     * @param  int $devisId Identifiant du devis.
     * @return void
     */
    public function generatePdf(int $devisId): void
    {
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
            $pdfOutput = $this->buildPdfContent($devisId);

            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT p.company_name 
                FROM devis d 
                JOIN prospects p ON d.id_prospect = p.id 
                WHERE d.id_devis = ?
            ");
            $stmt->execute([$devisId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            $safeCompanyName = preg_replace('/[^a-zA-Z0-9]/', '_', $client['company_name'] ?? 'Client');
            $fileName = "Devis_InnovEvents_{$safeCompanyName}.pdf";

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $fileName . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $pdfOutput;
            exit();

        } catch (\Exception $e) {
            error_log("Erreur aperçu PDF : " . $e->getMessage());
            header('Location: index.php?action=dashboard');
            exit();
        }
    }

    /**
     * Traite l'expédition de la proposition commerciale au client (Admin / Employé).
     *
     * @param  int $devisId Identifiant du devis à expédier.
     * @return void
     */
    public function sendQuoteToClient(int $devisId): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'], true)) {
            header('Location: index.php?action=login');
            exit;
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT d.id_devis, d.id_prospect, d.reference_pdf, p.email, p.contact_name 
                FROM devis d 
                JOIN prospects p ON d.id_prospect = p.id 
                WHERE d.id_devis = ?
            ");
            $stmt->execute([$devisId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                header('Location: index.php?action=admin_devis');
                exit;
            }

            // 1. Production et Sauvegarde Physique du document
            $pdfOutput = $this->buildPdfContent($devisId);

            $secureDirectory = __DIR__ . '/../../storage/devis/';
            if (!is_dir($secureDirectory)) {
                mkdir($secureDirectory, 0777, true);
            }

            // Correction de l'extension pour éviter les doublons (.pdf.pdf)
            $refFile = $data['reference_pdf'];
            if (!str_ends_with(strtolower($refFile), '.pdf')) {
                $refFile .= '.pdf';
            }

            $fullPath = $secureDirectory . $refFile;
            file_put_contents($fullPath, $pdfOutput);

            // 2. Bascule du statut du DEVIS en BDD ('étude côté client')
            $stmtUpdate = $db->prepare("UPDATE devis SET status = 'étude côté client' WHERE id_devis = ?");
            $stmtUpdate->execute([$devisId]);

            // 3. Expédition du courrier électronique avec pièce jointe
            require_once __DIR__ . '/../services/MailService.php';
            $mailService = new MailService();
            $mailService->sendQuoteEmail($data['email'], $data['contact_name'], $fullPath);

            // 4. Audit & Traçabilité (MongoDB - AT2)
            require_once __DIR__ . '/../models/nosql/Log.php';
            $logModel = new Log();
            $logModel->addLog(
                "ENVOI_DEVIS",
                "Devis #{$devisId} envoyé au client " . $data['email'],
                $_SESSION['user_id'],
                ['devis_id' => $devisId, 'prospect_id' => $data['id_prospect']]
            );

            $_SESSION['flash_success'] = "Le devis a bien été généré, sauvegardé et envoyé au client.";

        } catch (\Exception $e) {
            error_log("Erreur envoi devis : " . $e->getMessage());
            $_SESSION['flash_error'] = "Une erreur technique a empêché l'envoi du devis.";
        }

        header('Location: index.php?action=edit_devis&id=' . $devisId);
        exit;
    }

    /**
     * Permet au client connecté de télécharger son devis PDF (Espace Client).
     *
     * @param  string $fileName Nom du fichier demandé dans le stockage sécurisé.
     * @return void
     */
    public function downloadPdf(string $fileName): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        // Nettoyage contre les attaques par traversée de répertoire (Path Traversal)
        $safeFileName = basename($fileName);
        if (!str_ends_with(strtolower($safeFileName), '.pdf')) {
            $safeFileName .= '.pdf';
        }

        $filePath = __DIR__ . '/../../storage/devis/' . $safeFileName;

        // Contrôle d'existence physique
        if (empty($safeFileName) || !file_exists($filePath)) {
            $_SESSION['client_error'] = "Le document PDF demandé n'est pas encore disponible.";
            header('Location: index.php?action=client_dashboard');
            exit();
        }

        // Transfert sécurisé du fichier binaire
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $safeFileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($filePath);
        exit();
    }
}