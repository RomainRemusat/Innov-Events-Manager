<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/Database.php';

/**
 * Modèle de données : Devis (Couche d'Accès aux Données - DAL SQL)
 *
 * Encapsule et orchestre l'ensemble des interactions avec la table relationnelle `devis`.
 * Gère le cycle de vie commercial, l'extraction croisée avec les prospects
 * et le calcul de la charge financière (agrégation des prestations).
 *
 * @package    InnovEventsManager
 * @subpackage Models\SQL
 * @author     Romain Remusat
 * @version    2.4.0
 */
class Devis
{
    /**
     * Instance PDO partagée pour la communication avec le serveur MySQL/MariaDB.
     */
    private \PDO $db;

    /**
     * Initialise la couche d'accès aux données via le Singleton Database.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recherche un devis par sa clé primaire avec les attributs du prospect associé.
     *
     * Emploie des alias SQL explicites (`status`, `prospect_status`) afin d'empêcher
     * l'écrasement de la colonne d'état du devis par celle du prospect lors du mapping PDO::FETCH_ASSOC.
     *
     * @param int $devisId Identifiant unique du devis (`id_devis`).
     * @return array<string, mixed>|null Enregistrement associatif complet ou null si introuvable.
     */
    public function findWithProspect(int $devisId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT d.id_devis,
                       d.id_prospect,
                       d.reference_pdf,
                       d.montant_ht,
                       d.tva,
                       d.status AS status,
                       d.date_creation,
                       p.company_name,
                       p.contact_name,
                       p.email,
                       p.phone,
                       p.event_type,
                       p.event_date,
                       p.location,
                       p.estimated_participants,
                       p.budget,
                       p.description,
                       p.status AS prospect_status
                FROM devis d
                INNER JOIN prospects p ON d.id_prospect = p.id
                WHERE d.id_devis = :devis_id
                LIMIT 1
            ");
            $stmt->execute([':devis_id' => $devisId]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result !== false ? $result : null;
        } catch (\PDOException $e) {
            error_log(sprintf("[Devis::findWithProspect] Erreur SQL devis #%d : %s", $devisId, $e->getMessage()));
            return null;
        }
    }

    /**
     * Recherche un devis par son identifiant unique strict (sans jointure).
     *
     * @param int $id Identifiant unique du devis (`id_devis`).
     * @return array<string, mixed>|null Données brutes du devis ou null si non trouvé.
     */
    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id_devis, id_prospect, reference_pdf, montant_ht, tva, status, date_creation
                FROM devis
                WHERE id_devis = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result !== false ? $result : null;
        } catch (\PDOException $e) {
            error_log(sprintf("[Devis::findById] Erreur SQL devis #%d : %s", $id, $e->getMessage()));
            return null;
        }
    }

    /**
     * Extrait l'intégralité du portefeuille de devis avec consolidation financière des prestations.
     *
     * Effectue une jointure externe (LEFT JOIN) sur la table `prestations` afin de calculer
     * le montant HT consolidé en temps réel via la fonction d'agrégation `COALESCE(SUM(), 0)`.
     *
     * @return array<int, array<string, mixed>> Liste chronologique décroissante des devis.
     */
    public function findAllWithTotals(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT d.id_devis,
                       d.id_prospect,
                       d.reference_pdf,
                       d.montant_ht,
                       d.tva,
                       d.status AS status,
                       d.date_creation,
                       p.company_name,
                       p.contact_name,
                       p.email,
                       p.status AS prospect_status,
                       COALESCE(SUM(pr.montant_ht), 0) AS total_ht
                FROM devis d
                INNER JOIN prospects p ON d.id_prospect = p.id
                LEFT JOIN prestations pr ON d.id_devis = pr.devis_id
                GROUP BY d.id_devis, d.id_prospect, d.reference_pdf, d.montant_ht, d.tva, d.status, d.date_creation, p.company_name, p.contact_name, p.email, p.status
                ORDER BY d.date_creation DESC
            ");
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("[Devis::findAllWithTotals] Défaillance SQL lors de l'extraction : " . $e->getMessage());
            return [];
        }
    }

    /**
     * Filtre les devis selon un statut commercial spécifique.
     *
     * Utilisé pour segmenter les flux du tableau de bord (ex: devis à relancer ou à modifier).
     *
     * @param string $status État cible ('brouillon', 'étude côté client', 'accepté', 'refusé', 'modification').
     * @return array<int, array<string, mixed>> Liste des propositions correspondantes.
     */
    public function findByStatus(string $status): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT d.id_devis, 
                       d.id_prospect, 
                       d.reference_pdf, 
                       d.montant_ht, 
                       d.tva, 
                       d.status AS status, 
                       d.date_creation, 
                       p.company_name, 
                       p.contact_name
                FROM devis d
                INNER JOIN prospects p ON d.id_prospect = p.id
                WHERE d.status = :status
                ORDER BY d.date_creation DESC
            ");
            $stmt->execute([':status' => $status]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log(sprintf("[Devis::findByStatus] Erreur SQL statut '%s' : %s", $status, $e->getMessage()));
            return [];
        }
    }

    /**
     * Recalcule et persiste les montants HT et TVA (taux légal à 20 %) d'un devis.
     *
     * Synchronise la table `devis` à partir de la somme exacte de ses lignes de `prestations`.
     *
     * @param int $devisId Identifiant du devis à synchroniser.
     * @return bool True si la mise à jour transactionnelle a réussi, false sinon.
     */
    public function recalculateTotals(int $devisId): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE devis d
                SET d.montant_ht = (
                    SELECT COALESCE(SUM(p.montant_ht), 0) 
                    FROM prestations p 
                    WHERE p.devis_id = d.id_devis
                ),
                d.tva = (
                    SELECT COALESCE(SUM(p.montant_ht), 0) * 0.20 
                    FROM prestations p 
                    WHERE p.devis_id = d.id_devis
                )
                WHERE d.id_devis = :devis_id
            ");
            return $stmt->execute([':devis_id' => $devisId]);
        } catch (\PDOException $e) {
            error_log(sprintf("[Devis::recalculateTotals] Échec du recalcul pour le devis #%d : %s", $devisId, $e->getMessage()));
            return false;
        }
    }

    /**
     * Met à jour le statut du cycle de vie commercial d'un devis.
     *
     * Intègre une clause de garde (liste blanche) garantissant l'intégrité des données
     * face aux valeurs non prévues par le cahier des charges.
     *
     * @param int    $devisId Identifiant unique du devis concerné.
     * @param string $status  Nouvel état commercial à appliquer.
     * @return bool True en cas de succès, false si le statut est rejeté ou sur incident SQL.
     */
    public function updateStatus(int $devisId, string $status): bool
    {
        $allowedStatuses = ['brouillon', 'étude côté client', 'accepté', 'refusé', 'modification'];

        if (!in_array($status, $allowedStatuses, true)) {
            error_log(sprintf("[Devis::updateStatus] Rejet métier : statut '%s' non autorisé.", $status));
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE devis 
                SET status = :status 
                WHERE id_devis = :devis_id
            ");
            return $stmt->execute([
                ':status'   => $status,
                ':devis_id' => $devisId
            ]);
        } catch (\PDOException $e) {
            error_log(sprintf("[Devis::updateStatus] Erreur SQL devis #%d : %s", $devisId, $e->getMessage()));
            return false;
        }
    }

    /**
     * Initialise un devis vierge au statut 'brouillon' lors de la conversion d'un prospect.
     *
     * @param int    $prospectId Identifiant du prospect qualifié rattaché.
     * @param string $pdfRef     Nom normalisé du document PDF généré.
     * @return int|false Identifiant du devis inséré (`id_devis`), ou false en cas d'anomalie.
     */
    public function createDraft(int $prospectId, string $pdfRef): int|false
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO devis (id_prospect, reference_pdf, montant_ht, tva, status, date_creation)
                VALUES (:id_prospect, :reference_pdf, 0.00, 0.00, 'brouillon', NOW())
            ");
            $stmt->execute([
                ':id_prospect'   => $prospectId,
                ':reference_pdf' => $pdfRef
            ]);

            return (int)$this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log(sprintf("[Devis::createDraft] Échec création brouillon prospect #%d : %s", $prospectId, $e->getMessage()));
            return false;
        }
    }

    /**
     * Calcule le volume global de devis au statut 'modification' (Indicateur clé / Badge navbar).
     *
     * Utilisé pour alerter Chloé sur les contre-propositions commerciales requises.
     *
     * @return int Nombre total de dossiers en attente d'arbitrage.
     */
    public function countPendingModifications(): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) AS total 
                FROM devis 
                WHERE status = 'modification'
            ");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return (int)($result['total'] ?? 0);
        } catch (\PDOException $e) {
            error_log("[Devis::countPendingModifications] Erreur SQL décompte : " . $e->getMessage());
            return 0;
        }
    }
}