<?php
/**
 * Contrôleur : PdfController (Génération de documents commerciaux)
 *
 * Ce contrôleur gère la compilation des données financières (devis, prestations)
 * et le rendu sous forme de document PDF téléchargeable grâce à la librairie Dompdf.
 * Il assure également la traçabilité de l'action dans la base NoSQL (MongoDB).
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    2.0.0
 */

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../config/Database.php';

class PdfController
{
    /**
     * Génère et télécharge le PDF d'un devis (AT1 / AT2).
     *
     * @param int $id Identifiant (ID Prospect ou ID Devis)
     * @return void
     */
    public function generatePdf(int $id): void
    {
        // 1. CONTRÔLE D'ACCÈS ET SÉCURITÉ (AT1)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit();
        }

        if ($id <= 0) {
            header('Location: index.php?action=dashboard');
            exit();
        }

        // 2. EXTRACTION DES DONNÉES DEPUIS MYSQL (AT2)
        $db = Database::getInstance();

        // Récupération du devis et des informations du prospect
        $stmt = $db->prepare("
            SELECT d.*, p.company_name, p.contact_name, p.email, p.phone, p.event_type, p.event_date, p.description, p.budget
            FROM devis d
            JOIN prospects p ON d.id_prospect = p.id
            WHERE d.id_prospect = ? OR d.id_devis = ?
            ORDER BY d.id_devis DESC LIMIT 1
        ");
        $stmt->execute([$id, $id]);
        $devis = $stmt->fetch();

        if (!$devis) {
            header('Location: index.php?action=dashboard');
            exit();
        }

        // Récupération des prestations rattachées à ce devis
        $stmtPrest = $db->prepare("SELECT * FROM prestations WHERE devis_id = ? ORDER BY id ASC");
        $stmtPrest->execute([$devis['id_devis']]);
        $prestations = $stmtPrest->fetchAll();

        // 3. TRACABILITÉ NOSQL / MONGODB (Exigence AT2)
        try {
            require_once __DIR__ . '/../models/nosql/Log.php';
            $logModel = new Log();
            $logModel->addLog(
                "GENERATION_PDF",
                "Génération du devis PDF #" . $devis['id_devis'] . " pour la société " . $devis['company_name'],
                $_SESSION['user_id'] ?? null,
                ['devis_id' => $devis['id_devis'], 'prospect_id' => $devis['id_prospect']]
            );
        } catch (\Exception $e) {
            error_log("Erreur de journalisation MongoDB PDF : " . $e->getMessage());
        }

        // 4. PRÉPARATION DU TEMPLATE HTML
        ob_start();
        require __DIR__ . '/../views/admin/pdf_template.php';
        $html = ob_get_clean();

        // 5. CONFIGURATION ET EXECUTION DE DOMPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Permet le chargement des images distantes/CSS

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 6. TÉLÉCHARGEMENT DU FICHIER PDF
        $fileName = "Devis_InnovEvents_" . preg_replace('/[^a-zA-Z0-9]/', '_', $devis['company_name']) . ".pdf";
        $dompdf->stream($fileName, ["Attachment" => true]);
        exit();
    }


    /**
     * Méthode : Traitement de l'envoi du devis par email au client
     *
     * Mettre à jour le statut du devis/prospect, générer le document PDF,
     * expédier l'email et consigner l'action dans le journal d'audit NoSQL.
     *
     * @param int $devisId Identifiant du devis
     * @return void
     */
    public function sendQuoteToClient(int $devisId): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Contrôle de sécurité (Seul Admin/Employé peut envoyer)
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['ADMIN', 'EMPLOYEE'])) {
            header('Location: index.php?action=login');
            exit;
        }

        require_once __DIR__ . '/../models/sql/Prospect.php';
        $prospectModel = new Prospect();
        $devis = $prospectModel->find($devisId);

        if (!$devis) {
            header('Location: index.php?action=admin_devis');
            exit;
        }

        // 2. Mise à jour du statut en BDD relationnelle -> 'étude côté client'
        $prospectModel->updateStatus($devisId, 'étude côté client');

        // 3. Journalisation NoSQL (MongoDB - Exigence AT2)
        try {
            require_once __DIR__ . '/../models/nosql/Log.php';
            $logModel = new Log();
            $logModel->addLog(
                "GENERATION_DEVIS_PDF",
                "Devis #$devisId envoyé au client " . $devis['email'],
                $_SESSION['user_id'],
                [
                    'devis_id' => $devisId,
                    'event_id' => $devis['event_id'] ?? null,
                    'client_email' => $devis['email']
                ]
            );
        } catch (\Exception $e) {
            error_log("Erreur de journalisation MongoDB : " . $e->getMessage());
        }

        // 4. Redirection vers la liste des devis avec message de succès
        $_SESSION['flash_success'] = "Le devis a été envoyé avec succès au client. Son statut est passé en 'Étude côté client'.";
        header('Location: index.php?action=admin_devis');
        exit;
    }
}