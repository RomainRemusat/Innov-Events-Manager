<?php

use Dompdf\Dompdf;

class PdfController
{
    public function generatePdf(int $id): void
    {

        // 1. Récupérer les données du prospect (Modèle)
        $prospectModel = new Prospect();
        $prospect = $prospectModel->find($id);


        // --- ENREGISTREMENT DU LOG D'AUDIT (NoSQL) ---
        try {
            $logModel = new Log(); // Utilisation de ton modèle MongoDB
            $logModel->addLog(
                "GENERATION_PDF", // type_action
                "Génération du devis PDF pour le prospect " . $prospect['company_name'],
                $_SESSION['user_id'] ?? null, // id_utilisateur
                ['id_evenement' => $id] // Le détail avec l'ID requis par l'énoncé !
            );
        } catch (\Exception $e) {
            error_log("Erreur Log NoSQL : " . $e->getMessage());
        }

        // 2. Préparer le HTML du devis (tu peux créer un fichier dédié : views/admin/pdf_template.php)
        ob_start();
        require __DIR__ . '/../views/admin/pdf_template.php';
        $html = ob_get_clean();

        // 3. Initialiser Dompdf
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 4. Forcer le téléchargement
        $dompdf->stream("Devis_" . $prospect['company_name'] . ".pdf");
    }
}