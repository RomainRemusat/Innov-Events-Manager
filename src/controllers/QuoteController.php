<?php
/**
 * Contrôleur : QuoteController (Gestion des devis)
 *
 * Ce contrôleur orchestre les flux d'exécution liés aux demandes de devis.
 * Il fait le pont entre les requêtes HTTP de l'utilisateur, le modèle SQL Prospect
 * pour la persistance des données, et les vues publiques pour le rendu HTML.
 *
 * @package    InnovEventsManager
 * @subpackage Controllers
 * @author     Romain Remusat
 * @version    1.0.0
 */

require_once __DIR__ . '/../models/sql/Prospect.php';

class QuoteController
{
    /**
     * Point d'entrée pour l'affichage du formulaire de devis.
     * * Charge et injecte la vue contenant le formulaire HTML public.
     *
     * @return void
     */
    public function showForm(): void
    {
        require __DIR__ . '/../views/public/devis.php';
    }

    /**
     * Traite la soumission des données du formulaire de devis (Requête POST).
     * * Valide la présence des champs obligatoires côté serveur (sécurité fail-safe),
     * transmet les données nettoyées au modèle de persistance et génère un retour
     * utilisateur dynamique selon le succès ou l'échec de l'opération.
     *
     * @param array $data Tableau associatif contenant les variables $_POST soumises par l'utilisateur.
     * @return void
     */
    public function submitQuote(array $data): void
    {
        // Validation stricte côté serveur : Sécurité complémentaire si le 'required' HTML5 est contourné
        if (empty($data['company_name']) || empty($data['email']) || empty($data['contact_name'])) {
            die("Erreur : L'ensemble des éléments obligatoires du formulaire doivent être remplis.");
        }

        // Instanciation de la couche d'accès aux données (Modèle SQL)
        $prospectModel = new Prospect();

        // Tentative d'insertion du prospect en base de données
        $result = $prospectModel->create($data);

        // Aiguillage et rendu de l'interface selon le statut de la transaction
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