<?php

declare(strict_types=1);

/**
 * Utilitaire : ImageHelper
 *
 * Gère le rendu sécurisé des visuels événementiels et la génération
 * d'un placeholder conforme aux critères d'accessibilité RGAA.
 *
 * @package    InnovEventsManager
 * @subpackage Utils
 * @author     Romain Remusat
 * @version    1.0.0
 */
class ImageHelper
{
    /**
     * Génère le balisage HTML du visuel ou son placeholder de substitution.
     *
     * @param string|null $imagePath      Chemin relatif stocké en base (ex: 'uploads/events/gala.webp').
     * @param string      $title          Titre de l'événement pour l'alternative textuelle (RGAA).
     * @param string      $height         Hauteur CSS (ex: '220px', '400px').
     * @param string      $additionalClass Classes CSS complémentaires.
     * @return string Balisage HTML sécurisé.
     */
    public static function renderThumbnail(?string $imagePath, string $title, string $height = '220px', string $additionalClass = ''): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        // Vérification de la présence et de l'existence physique du fichier dans public/
        if (!empty($imagePath)) {
            $publicFilePath = __DIR__ . '/../../public/' . ltrim($imagePath, '/');
            if (file_exists($publicFilePath)) {
                $safePath = htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8');
                return sprintf(
                    '<img src="%s" class="card-img-top object-fit-cover %s" alt="Photographie illustrant l\'événement : %s" style="height: %s;">',
                    $safePath,
                    $additionalClass,
                    $safeTitle,
                    $height
                );
            }
        }

        // Placeholder SVG / CSS élégant (Bootstrap 5) avec icône décorative ignorée par les lecteurs d'écran
        return sprintf(
            '<div class="bg-secondary-subtle d-flex flex-column align-items-center justify-content-center text-secondary border-bottom %s" style="height: %s;" aria-hidden="true">
                <i class="fa-regular fa-image fa-3x mb-2 text-muted"></i>
                <span class="small text-muted fw-semibold">Innov\'Events Portfolio</span>
            </div>',
            $additionalClass,
            $height
        );
    }
}