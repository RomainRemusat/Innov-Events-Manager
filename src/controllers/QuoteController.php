<?php
// Fichier : src/controllers/QuoteController.php
require_once __DIR__ . '/../models/sql/Prospect.php';

class QuoteController {

    public function showForm() {
        require __DIR__ . '/../views/public/devis.php';
    }

    public function submitQuote($data) {
        if (empty($data['company_name']) || empty($data['email']) || empty($data['contact_name'])) {
            die("Erreur : L'ensemble des éléments obligatoires du formulaire doivent être remplis.");
        }

        $prospectModel = new Prospect();
        $result = $prospectModel->create($data);

        if ($result) {
            echo "<div class='container mt-5'><div class='alert alert-success text-center'>";
            echo "Merci pour votre demande. Chloé vous recontactera dans les plus brefs délais pour discuter de votre projet.";
            echo "<br><a href='index.php' class='btn btn-primary mt-3'>Retour à l'accueil</a>";
            echo "</div></div>";
        } else {
            echo "<div class='container mt-5'><div class='alert alert-danger text-center'>Une erreur est survenue lors de l'enregistrement.</div></div>";
        }
    }
}