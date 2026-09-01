<?php

require_once __DIR__ . '/../../config/Database.php';

/**
 * Modèle de données : Devis (SQL)
 *
 * Encapsule les accès BDD liés à la table `devis`.
 * Centralise les requêtes de jointure et d'agrégation financière.
 *
 * @package    InnovEventsManager
 * @subpackage Models\SQL
 * @author     Romain Remusat
 * @version    1.0.0
 */
class Devis
{
    /**
     * Instance PDO de connexion à la base de données.
     *
     * @var PDO
     */
    private PDO $db;

    /**
     * Initialise le modèle via le singleton de connexion.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère un devis par son ID avec les informations du prospect associé.
     *
     * @param  int $devisId Identifiant du devis (`id_devis`).
     * @return array|null Données du devis ou null si inexistant.
     */
    public function findWithProspect(int $devisId): ?array
    {
        try {
            $stmt = $this->db->prepare("
            SELECT d.*, 
                   p.*
            FROM devis d
            JOIN prospects p ON d.id_prospect = p.id
            WHERE d.id_devis = ?
            LIMIT 1
        ");
            $stmt->execute([$devisId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;

        } catch (\PDOException $e) {
            error_log("Erreur SQL (findWithProspect) : " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère l'ensemble des devis avec les totaux HT agrégés depuis les prestations.
     *
     * @return array Liste des devis ordonnés du plus récent au plus ancien.
     */
    public function findAllWithTotals(): array
    {
        $stmt = $this->db->prepare("
            SELECT d.*, 
                   p.company_name, 
                   p.contact_name, 
                   p.email, 
                   p.status AS prospect_status,
                   COALESCE(SUM(pr.montant_ht), 0) AS total_ht
            FROM devis d
            JOIN prospects p ON d.id_prospect = p.id
            LEFT JOIN prestations pr ON d.id_devis = pr.devis_id
            GROUP BY d.id_devis
            ORDER BY d.date_creation DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll();
    }


    public function findByStatus(string $status): array
    {
        $db = Database::getInstance();

        $sql = "SELECT d.id_devis, d.reference_pdf, d.montant_ht, d.date_creation, p.company_name, p.contact_name
                FROM devis d
                JOIN prospects p ON d.id_prospect = p.id
                WHERE d.status = :status
                ORDER BY d.date_creation DESC";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}