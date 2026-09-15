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
        if (trim($libelle) === '' || !is_finite($montantHt) || $montantHt < 0) {
            return false;
        }
        return $this->modifyQuote($devisId, function () use ($devisId, $libelle, $montantHt): bool {
            $stmt = $this->db->prepare("INSERT INTO prestations (devis_id, libelle, montant_ht) VALUES (?, ?, ?)");
            return $stmt->execute([$devisId, $libelle, $montantHt]);
        });
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
        return $this->modifyQuote($devisId, function () use ($prestationId, $devisId): bool {
            $stmt = $this->db->prepare("DELETE FROM prestations WHERE id = ? AND devis_id = ?");
            $stmt->execute([$prestationId, $devisId]);
            return $stmt->rowCount() === 1;
        });
    }

    /**
     * Modifie les lignes et leurs totaux sous le même verrou que l'envoi et la décision.
     * Chaque modification retire la proposition de l'examen client et invalide sa version.
     * Le PDF stocké reste une ancienne copie, inaccessible au client pendant le brouillon ;
     * il sera régénéré avant le prochain envoi.
     *
     * @param int $devisId Identifiant du devis à verrouiller.
     * @param callable(): bool $mutation Écriture d'une prestation, dans la transaction courante.
     * @return bool False si le devis est accepté, absent ou si l'écriture échoue.
     */
    private function modifyQuote(int $devisId, callable $mutation): bool
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT status FROM devis WHERE id_devis = ? FOR UPDATE');
            $stmt->execute([$devisId]);
            $status = $stmt->fetchColumn();
            if ($status === false || $status === 'accepté' || !$mutation()) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare("UPDATE devis SET status = 'brouillon', revision = revision + 1,
                montant_ht = (SELECT COALESCE(SUM(montant_ht), 0) FROM prestations WHERE devis_id = ?),
                tva = ROUND(montant_ht * 0.20, 2) WHERE id_devis = ?");
            $stmt->execute([$devisId, $devisId]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Erreur modification devis : ' . $e->getMessage());
            return false;
        }
    }
}
