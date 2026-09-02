<?php
require_once __DIR__ . '/../../config/Database.php';

class Prestation
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Insère une nouvelle prestation commerciale.
     */
    public function create(int $devisId, string $libelle, float $montantHt): bool
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO prestations (devis_id, libelle, montant_ht) VALUES (?, ?, ?)");
            return $stmt->execute([$devisId, $libelle, $montantHt]);
        } catch (\PDOException $e) {
            error_log("Erreur création prestation : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère toutes les prestations associées à un devis spécifique.
     *
     * @param int $devisId L'identifiant du devis
     * @return array La liste des prestations
     */
    public function findByDevisId(int $devisId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM prestations WHERE devis_id = ? ORDER BY id ASC");
            $stmt->execute([$devisId]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Erreur lecture prestations : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Supprime une prestation d'un devis.
     * La double condition (id + devis_id) empêche la faille IDOR (Insecure Direct Object Reference).
     *
     * @param int $prestationId L'identifiant de la prestation
     * @param int $devisId L'identifiant du devis associé
     * @return bool Succès ou échec
     */
    public function delete(int $prestationId, int $devisId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM prestations WHERE id = ? AND devis_id = ?");
            return $stmt->execute([$prestationId, $devisId]);
        } catch (\PDOException $e) {
            error_log("Erreur suppression prestation : " . $e->getMessage());
            return false;
        }
    }
}