-- Ajoute les contenus publics administrables sans modifier les dossiers existants.
CREATE TABLE IF NOT EXISTS site_settings (
setting_key VARCHAR(100) PRIMARY KEY,
setting_value TEXT NOT NULL,
updated_by INT NULL,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_site_settings_user
FOREIGN KEY (updated_by) REFERENCES users(id)
ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
('quote_thank_you_message', 'Merci pour votre confiance. Votre demande est enregistrée et consultable par notre équipe, qui vous recontactera pour discuter de votre projet.');
