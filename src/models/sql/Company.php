<?php

require_once __DIR__ . '/../../config/Database.php';

/**
 * Modèle : Company (Entité Morale B2B - Norme 3NF)
 *
 * Encapsule les requêtes SQL liées à la table `companies`.
 *
 * @package    InnovEventsManager
 * @subpackage Models\SQL
 * @author     Romain Remusat
 * @version    1.0.0
 */
class Company
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recherche ou crée une entreprise par son nom, et enrichit ses informations légales si fournies.
     *
     * @param string      $name       Raison sociale
     * @param string|null $siren      Numéro SIREN (9 chiffres)
     * @param string|null $address    Adresse postale
     * @param string|null $postalCode Code postal
     * @param string|null $city       Ville
     * @return int Identifiant unique de l'entreprise
     */
    public function findOrCreateAndEnrich(
        string $name,
        ?string $siren = null,
        ?string $address = null,
        ?string $postalCode = null,
        ?string $city = null
    ): int {
        // 1. Vérification si l'entreprise existe déjà
        $stmt = $this->db->prepare("SELECT id FROM companies WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $companyId = (int)$existing['id'];

            // Mise à jour si des informations légales complémentaires sont renseignées
            $stmtUpdate = $this->db->prepare("
                UPDATE companies 
                SET siren = COALESCE(?, siren),
                    address = COALESCE(?, address),
                    postal_code = COALESCE(?, postal_code),
                    city = COALESCE(?, city)
                WHERE id = ?
            ");
            $stmtUpdate->execute([$siren, $address, $postalCode, $city, $companyId]);

            return $companyId;
        }

        // 2. Création d'une nouvelle structure
        $stmtInsert = $this->db->prepare("
            INSERT INTO companies (name, siren, address, postal_code, city) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([$name, $siren, $address, $postalCode, $city]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Récupère les données complètes d'une société par son ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM companies WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }
}