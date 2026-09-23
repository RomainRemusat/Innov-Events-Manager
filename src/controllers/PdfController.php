<?php
/**
 * Contrôleur : PdfController (Génération et diffusion de devis PDF)
 *
 * Ce composant gère la chaîne d'édition et de distribution documentaire :
 * 1. Rendu HTML complet du devis avec mise en page normalisée.
 * 2. Compilation en document PDF physique via la bibliothèque Dompdf.
 * 3. Persistance du document dans le stockage sécurisé (`/storage/devis/`).
 * 4. Transmission sécurisée par courriel au client (Pièce jointe SMTP).
 * 5. Journalisation des émissions documentaires dans MongoDB.
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

/** Génère, transmet et contrôle le téléchargement des devis PDF. */
class PdfController extends BaseController
{
    /**
     * Génère physiquement le fichier PDF du devis et le sauvegarde sur le serveur.
     * Les données restent verrouillées pendant le rendu. Le remplacement du fichier
     * est atomique ; le document d'un devis accepté n'est jamais réécrit.
     *
     * @param  int  $devisId    Identifiant unique du devis concerné.
     * @param  bool $autoStream Si vrai, télécharge directement le fichier dans le navigateur.
     * @return string|null      Nom du fichier PDF créé, ou null en cas d'erreur.
     */
    public function generatePdf(int $devisId, bool $autoStream = false): ?string
    {
        $this->checkAuth(['ADMIN']);
        $db = Database::getInstance();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT id_devis FROM devis WHERE id_devis = ? FOR UPDATE');
            $lock->execute([$devisId]);

            // 1. Extraction des données financières et métier
            $devisModel = new Devis();
            $devis = $devisModel->findWithProspect($devisId);

            if (!$devis) throw new RuntimeException('Devis introuvable.');
            if ($devis['status'] === 'accepté') {
                $name = basename($devis['reference_pdf']);
                if ($ownsTransaction) $db->commit();
                return is_file(__DIR__ . '/../../storage/devis/' . $name) ? $name : null;
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
            $temporaryPath = tempnam($storageDir, '.quote_');
            if ($temporaryPath === false) throw new RuntimeException('Fichier temporaire indisponible.');
            try {
                if (file_put_contents($temporaryPath, $pdfOutput) !== strlen($pdfOutput)
                    || !rename($temporaryPath, $fullPath)) {
                    throw new RuntimeException('Écriture du PDF impossible.');
                }
            } finally {
                if (is_file($temporaryPath)) unlink($temporaryPath);
            }

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
            if ($ownsTransaction) $db->commit();
            if ($autoStream) {
                $dompdf->stream($safeFileName, ['Attachment' => 0]);
                exit;
            }

            return $safeFileName;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            error_log('[PdfController::generatePdf] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Génère et transmet directement un devis PDF après contrôle du compte courant.
     * Les autorisations métier ci-dessous complètent le contrôle de session centralisé :
     * un client ne peut consulter que ses devis et un employé ne peut pas les générer.
     */
    public function generatePdfAction(): void
    {
        $this->checkAuth();

        $userRole = $_SESSION['user_role'] ?? '';
        $userId   = (int)$_SESSION['user_id'];
        $devisId  = (int)($_GET['id'] ?? 0);

        if ($userRole === 'EMPLOYEE') {
            http_response_code(403);
            echo "Accès interdit.";
            exit();
        }

        if ($devisId <= 0) {
            http_response_code(404);
            echo "Document introuvable.";
            exit();
        }

        $devisModel = new Devis();
        $devis = $devisModel->findWithProspect($devisId);

        if (!$devis) {
            http_response_code(404);
            echo "Document introuvable.";
            exit();
        }

        if ($userRole === 'CLIENT') {
            if ((int)($devis['user_id'] ?? 0) !== $userId) {
                http_response_code(404);
                echo "Document introuvable.";
                exit();
            }
        } elseif ($userRole !== 'ADMIN') {
            http_response_code(403);
            echo "Accès interdit.";
            exit();
        }

        // Le client consulte exclusivement le document diffusé, jamais un brouillon régénéré.
        if ($userRole === 'CLIENT') {
            $this->downloadPdf($devis['reference_pdf']);
            return;
        }

        $prestationModel = new Prestation();
        $prestations = $prestationModel->findByDevisId($devisId);

        ob_start();
        require __DIR__ . '/../views/admin/pdf_template.php';
        $htmlContent = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $safeFileName = !empty($devis['reference_pdf'])
            ? basename($devis['reference_pdf'])
            : 'Devis_' . $devisId . '.pdf';

        $dompdf->stream($safeFileName, ['Attachment' => 0]);
        exit();
    }

    /**
     * Génère et expédie le devis PDF par e-mail au client.
     * Refuse les devis acceptés et publie une nouvelle version uniquement après
     * acceptation de l'email par SMTP. Le verrou est conservé jusqu'à cette transition.
     *
     * @param  int $devisId Identifiant unique du devis à expédier.
     * @return void
     */
    public function sendQuoteToClient(int $devisId): void
    {
        $this->checkAuth(['ADMIN']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=admin_devis');
            exit();
        }

        $this->validateCsrf($_POST);

        $db = Database::getInstance();
        try {
            $db->beginTransaction();
            // Le verrou du devis couvre le PDF et SMTP : aucune décision ni modification
            // ne peut s'intercaler avant la publication de cette version.
            $lock = $db->prepare('SELECT status FROM devis WHERE id_devis = ? FOR UPDATE');
            $lock->execute([$devisId]);
            $status = $lock->fetchColumn();
            if ($status === false || $status === 'accepté') {
                throw new RuntimeException('Envoi impossible : devis introuvable ou déjà accepté.');
            }

            // 1. Récupération des données du devis
            $devisModel = new Devis();
            $devis = $devisModel->findWithProspect($devisId);

            if (!$devis || empty($devis['email'])) {
                throw new RuntimeException('Données de contact introuvables.');
            }

            // 2. Génération / régénération du PDF physique pour garantir des données à jour
            $fileName = $this->generatePdf($devisId, false);

            if (!$fileName) {
                throw new RuntimeException('Échec de la génération du document PDF.');
            }

            $pdfFilePath = __DIR__ . '/../../storage/devis/' . $fileName;

            // 3. Envoi du courriel transactionnel avec pièce jointe
            $mailService = new MailService();
            $clientName  = !empty($devis['contact_name']) ? $devis['contact_name'] : $devis['company_name'];
            $mailSent    = $mailService->sendQuoteEmail($devis['email'], $clientName, $pdfFilePath);

            if ($mailSent) {
                // 4. Transition d'état : Passage au statut 'étude côté client'
                $db = Database::getInstance();
                $stmt = $db->prepare("UPDATE devis SET status = 'étude côté client', revision = revision + 1 WHERE id_devis = ?");
                $stmt->execute([$devisId]);
                $db->commit();

                // 5. Journalisation d'audit MongoDB
                try {
                    $logModel = new Log();
                    $logModel->addLog("ENVOI_DEVIS_CLIENT", (int)$_SESSION['user_id'], [
                        'message'       => "Devis #{$devisId} transmis par e-mail à {$devis['email']}",
                        'devis_id'      => $devisId,
                        'recipient'     => $devis['email'],
                        'revision'      => (int)$devis['revision'] + 1,
                        'reference_pdf' => $fileName,
                        'new_status'    => 'étude côté client'
                    ]);
                } catch (\Exception $e) {
                    error_log("Erreur Log MongoDB (sendQuoteToClient) : " . $e->getMessage());
                }

                $_SESSION['flash_success'] = "Le devis a été envoyé avec succès au client ({$devis['email']}) !";
            } else {
                $db->rollBack();
                $_SESSION['flash_error'] = "Une erreur technique a empêché l'envoi du devis.";
            }
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[PdfController::sendQuoteToClient] ' . $e->getMessage());
            $_SESSION['flash_error'] = "Envoi non finalisé : devis verrouillé ou erreur technique. Vérifiez son état avant de réessayer.";
        }

        header('Location: index.php?action=edit_devis&id=' . $devisId);
        exit;
    }

    /**
     * Point d'entrée pour le téléchargement sécurisé d'un devis par ID ou nom de fichier.
     */
    public function downloadDevis(mixed $param = null): void
    {
        $this->checkAuth();
        $file = $_GET['file'] ?? $_GET['f'] ?? null;
        $id   = (int)($_GET['id'] ?? (is_numeric($param) ? $param : 0));

        if ($id > 0) {
            $devisModel = new Devis();
            $devis = $devisModel->findWithProspect($id);
            if ($devis && !empty($devis['reference_pdf'])) {
                $this->downloadPdf($devis['reference_pdf']);
                return;
            } elseif ($devis && in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'], true)) {
                $fileName = $this->generatePdf($id, false);
                if ($fileName) {
                    $this->downloadPdf($fileName);
                    return;
                }
            }
        } elseif (!empty($file)) {
            $this->downloadPdf((string)$file);
            return;
        }

        $_SESSION['client_error'] = "Document introuvable ou indisponible.";
        header('Location: ' . (($_SESSION['user_role'] ?? '') === 'CLIENT' ? 'index.php?action=client_dashboard' : 'index.php?action=admin_devis'));
        exit();
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
        $this->checkAuth();
        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            // Nettoyage contre les attaques par traversée de répertoire (Path Traversal)
            $safeFileName = basename($fileName);
            if (!str_ends_with(strtolower($safeFileName), '.pdf')) {
                $safeFileName .= '.pdf';
            }

            $userRole = $_SESSION['user_role'] ?? '';
            $userId   = (int)$_SESSION['user_id'];

            // Charger le fichier sous le verrou du devis évite de lire un PDF en cours de réécriture.
            $lock = $db->prepare("SELECT d.id_devis, d.status FROM devis d
                JOIN prospects p ON p.id = d.id_prospect
                WHERE d.reference_pdf = ? AND (? <> 'CLIENT' OR p.user_id = ?) FOR UPDATE");
            $lock->execute([$safeFileName, $userRole, $userId]);
            $document = $lock->fetch(PDO::FETCH_ASSOC);

            // Contrôle d'autorisation IDOR : Un client ne peut télécharger QUE ses propres devis
            if ($userRole === 'CLIENT') {
                if (!$document || $document['status'] === 'brouillon') {
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

            $pdfContent = file_get_contents($filePath);
            if ($pdfContent === false) throw new RuntimeException('Lecture PDF impossible.');
            $db->commit();

            // Transfert sécurisé du fichier binaire
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $safeFileName . '"');
            header('Content-Length: ' . strlen($pdfContent));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $pdfContent;
            exit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[PdfController::downloadPdf] ' . $e->getMessage());
            http_response_code(503);
            echo 'Document temporairement indisponible.';
            exit();
        }
    }
}
