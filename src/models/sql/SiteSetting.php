<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/Database.php';

/** Accès aux contenus publics modifiables depuis l'administration. */
class SiteSetting
{
    public const QUOTE_THANK_YOU = 'quote_thank_you_message';
    public const DEFAULT_QUOTE_THANK_YOU = 'Merci pour votre confiance. Votre demande est enregistrée et consultable par notre équipe, qui vous recontactera pour discuter de votre projet.';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    }

    public function set(string $key, string $value, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        );
        return $stmt->execute([$key, $value, $userId]);
    }
}
