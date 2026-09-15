<?php
/**
 * Contrôleur : PdfController (Génération et diffusion de devis PDF)
 *
 * Ce composant gère la chaîne d'édition et de distribution documentaire :
 * 1. Rendu HTML complet du devis avec mise en page normalisée.
 * 2. Compilation en document PDF physique via la bibliothèque Dompdf.
 * 3. Persistance du document dans le stockage sécurisé (`/storage/devis/`).
 * 4. Transmission sécurisée par courriel au client (Pièce jointe SMTP).
 * 5. Traçabilité complète des émissions documentaires dans MongoDB (AT2).
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    2.5.0
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/sql/Devis.php';
require_once __DIR__ . '/../models/sql/Prestation.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/../services/MailService.php';

// Chargement de l'autoloader Composer pour Dompdf
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfController extends BaseController
{
    /**
     * Génère physiquement le fichier PDF du devis et le sauvegarde sur le serveur.
     *
     * @param  int  $devisId    Identifiant unique du devis concerné.
     * @param  bool $autoStream Si vrai, télécharge directement le fichier dans le navigateur.
     * @return string|null      Nom du fichier PDF créé, ou null en cas d'erreur.
     */
    public function generatePdf(int $devisId, bool $autoStream = false): ?string
    {
        $this->checkAuth(['ADMIN']);

        // 1. Extraction des données financières et métier
        $devisModel = new Devis();
        $devis = $devisModel->findWithProspect($devisId);

        if (!$devis) {
            $_SESSION['flash_error'] = "Devis introuvable pour la génération du document.";
            header('Location: index.php?action=admin_devis');
            exit;
        }

        $prestationModel = new Prestation();
        $prestations = $prestationModel->findByDevisId($devisId);

        // 2. Préparation du répertoire de stockage sécurisé
        $storageDir = __DIR__ . '/../../storage/devis/';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        // 3. Définition du nom de fichier unique et sécurisé
        $safeFileName = !empty($devis['reference_pdf'])
            ? basename($devis['reference_pdf'])
            : 'Devis_' . $devisId . '_' . date('Ymd_His') . '.pdf';

        $fullPath = $storageDir . $safeFileName;

        // 4. Capture du template HTML du devis
        ob_start();
        require __DIR__ . '/../views/admin/pdf_template.php';
        $htmlContent = ob_get_clean();

        // 5. Configuration et instanciation de Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 6. Écriture physique sur le disque
        $pdfOutput = $dompdf->output();
        file_put_contents($fullPath, $pdfOutput);

        // 7. Synchronisation du nom de référence en base de données si nécessaire
        if (empty($devis['reference_pdf']) || $devis['reference_pdf'] !== $safeFileName) {
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE devis SET reference_pdf = ? WHERE id_devis = ?");
            $stmt->execute([$safeFileName, $devisId]);
        }

        // 8. Journalisation NoSQL de l'opération
        try {
            $logModel = new Log();
            $logModel->addLog("GENERATION_PDF_DEVIS", (int)$_SESSION['user_id'], [
                'message'       => "PDF généré pour le devis #{$devisId} ({$safeFileName})",
                'devis_id'      => $devisId,
                'reference_pdf' => $safeFileName,
                'file_size'     => strlen($pdfOutput)
            ]);
        } catch (\Exception $e) {
            error_log("Erreur Log MongoDB (generatePdf) : " . $e->getMessage());
        }

        // 9. Flux direct vers le navigateur si demandé
        if ($autoStream) {
            $dompdf->stream($safeFileName, ['Attachment' => 0]);
            exit;
        }

        return $safeFileName;
    }

    /**
     * Génère et expédie le devis PDF par e-mail au client (AT2).
     *
     * @param  int $devisId Identifiant unique du devis à expédier.
     * @return void
     */
    public function sendQuoteToClient(int $devisId): void
    {
        $this->checkAuth(['ADMIN']);
        $this->validateCsrf($_POST);

        // 1. Récupération des données du devis
        $devisModel = new Devis();
        $devis = $devisModel->findWithProspect($devisId);

        if (!$devis || empty($devis['email'])) {
            $_SESSION['flash_error'] = "Impossible d'envoyer le devis : données de contact introuvables.";
            header('Location: index.php?action=edit_devis&id=' . $devisId);
            exit;
        }

        // 2. Génération / régénération du PDF physique pour garantir des données à jour
        $fileName = $this->generatePdf($devisId, false);

        if (!$fileName) {
            $_SESSION['flash_error'] = "Échec de la génération du document PDF.";
            header('Location: index.php?action=edit_devis&id=' . $devisId);
            exit;
        }

        $pdfFilePath = __DIR__ . '/../../storage/devis/' . $fileName;

        // 3. Envoi du courriel transactionnel avec pièce jointe
        $mailService = new MailService();
        $clientName  = !empty($devis['contact_name']) ? $devis['contact_name'] : $devis['company_name'];
        $mailSent    = $mailService->sendQuoteEmail($devis['email'], $clientName, $pdfFilePath);

        if ($mailSent) {
            // 4. Transition d'état : Passage au statut 'étude côté client'
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE devis SET status = 'étude côté client' WHERE id_devis = ?");
            $stmt->execute([$devisId]);

            // 5. Journalisation d'audit MongoDB
            try {
                $logModel = new Log();
                $logModel->addLog("ENVOI_DEVIS_CLIENT", (int)$_SESSION['user_id'], [
                    'message'       => "Devis #{$devisId} transmis par e-mail à {$devis['email']}",
                    'devis_id'      => $devisId,
                    'recipient'     => $devis['email'],
                    'reference_pdf' => $fileName,
                    'new_status'    => 'étude côté client'
                ]);
            } catch (\Exception $e) {
                error_log("Erreur Log MongoDB (sendQuoteToClient) : " . $e->getMessage());
            }

            $_SESSION['flash_success'] = "Le devis a été envoyé avec succès au client ({$devis['email']}) !";
        } else {
            $_SESSION['flash_error'] = "Une erreur technique a empêché l'envoi du devis.";
        }

        header('Location: index.php?action=edit_devis&id=' . $devisId);
        exit;
    }

    /**
     * Permet au client connecté ou à l'administrateur de télécharger son devis PDF.
     * Sécurisé contre l'IDOR en vérifiant l'appartenance du fichier.
     *
     * @param  string $fileName Nom du fichier demandé dans le stockage sécurisé.
     * @return void
     */
    public function downloadPdf(string $fileName): void
    {
        $this->startSession();

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        // Nettoyage contre les attaques par traversée de répertoire (Path Traversal)
        $safeFileName = basename($fileName);
        if (!str_ends_with(strtolower($safeFileName), '.pdf')) {
            $safeFileName .= '.pdf';
        }

        $userRole = $_SESSION['user_role'] ?? '';
        $userId   = (int)$_SESSION['user_id'];

        // Contrôle d'autorisation IDOR : Un client ne peut télécharger QUE ses propres devis
        if ($userRole === 'CLIENT') {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT d.id_devis
                FROM devis d
                JOIN prospects p ON d.id_prospect = p.id
                WHERE d.reference_pdf = ? AND p.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$safeFileName, $userId]);
            $owned = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$owned) {
                $_SESSION['client_error'] = "Vous n'êtes pas autorisé à accéder à ce document.";
                header('Location: index.php?action=client_dashboard');
                exit();
            }
        } elseif (!in_array($userRole, ['ADMIN', 'EMPLOYEE'], true)) {
            header('Location: index.php?action=login');
            exit();
        }

        $filePath = __DIR__ . '/../../storage/devis/' . $safeFileName;

        // Contrôle d'existence physique
        if (empty($safeFileName) || !file_exists($filePath)) {
            $_SESSION['client_error'] = "Le document PDF demandé n'est pas encore disponible.";
            header('Location: ' . ($userRole === 'CLIENT' ? 'index.php?action=client_dashboard' : 'index.php?action=admin_devis'));
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
