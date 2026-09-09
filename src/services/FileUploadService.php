<?php

declare(strict_types=1);

/**
 * Service transverse : FileUploadService
 *
 * Gère la validation stricte et le stockage sécurisé des téléversements de médias.
 * Conforme aux exigences de sécurité OWASP (CWE-434) et aux critères AT1 / AT2.
 *
 * @package    InnovEventsManager
 * @subpackage Services
 * @author     Romain Remusat
 * @version    1.2.0
 */
class FileUploadService
{
    private int $maxSize;
    private array $allowedMimes;

    public function __construct(
        int $maxSize = 5242880, // 5 Mo par défaut
        array $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ]
    ) {
        $this->maxSize = $maxSize;
        $this->allowedMimes = $allowedMimes;
    }

    /**
     * Valide et téléverse un fichier image de façon sécurisée.
     *
     * @param array  $file            Tableau $_FILES['nom_du_champ'].
     * @param string $targetDirectory Dossier physique absolu de destination.
     * @param string $prefix          Préfixe de sécurisation aléatoire.
     * @return string|null Nom du fichier généré sur le disque.
     * @throws \InvalidArgumentException En cas d'anomalie de conformité.
     */
    public function uploadImage(array $file, string $targetDirectory, string $prefix = 'img_'): ?string
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \InvalidArgumentException("Structure de fichier invalide.");
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new \InvalidArgumentException("Aucun fichier n'a été sélectionné.");
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new \InvalidArgumentException("Le fichier dépasse la taille maximale autorisée par le serveur.");
            default:
                throw new \InvalidArgumentException("Erreur lors de la réception du flux de données.");
        }

        if ($file['size'] > $this->maxSize) {
            throw new \InvalidArgumentException("Le fichier dépasse la taille maximale autorisée (5 Mo).");
        }

        // Vérification du type MIME réel via Fileinfo (octets magiques)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mimeType, $this->allowedMimes)) {
            throw new \InvalidArgumentException("Format non autorisé : seuls JPG, PNG et WEBP sont acceptés.");
        }

        // Vérification complémentaire : s'assurer que c'est un flux image valide
        if (@getimagesize($file['tmp_name']) === false) {
            throw new \InvalidArgumentException("Le fichier transmis n'est pas une image valide.");
        }

        $extension = $this->allowedMimes[$mimeType];
        // Nommage sécurisé par génération cryptographique aléatoire (CWE-338)
        $newFilename = $prefix . bin2hex(random_bytes(10)) . '.' . $extension;

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $destination = rtrim($targetDirectory, '/') . '/' . $newFilename;

        if (move_uploaded_file($file['tmp_name'], $destination) || (php_sapi_name() === 'cli' && copy($file['tmp_name'], $destination))) {
            return $newFilename;
        }

        return null;
    }

    /**
     * Supprime physiquement un ancien fichier sur le disque.
     */
    public function deleteFile(string $absolutePath): void
    {
        if (file_exists($absolutePath) && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
