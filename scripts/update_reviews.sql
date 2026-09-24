-- Ajoute les avis clients modérés sans modifier les dossiers existants.
CREATE TABLE IF NOT EXISTS reviews (
id INT AUTO_INCREMENT PRIMARY KEY,
event_id INT NOT NULL UNIQUE,
rating TINYINT UNSIGNED NOT NULL,
comment TEXT NOT NULL,
status VARCHAR(50) NOT NULL DEFAULT 'en attente',
rejection_reason VARCHAR(500) NULL,
moderated_by INT NULL,
moderated_at DATETIME NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
CONSTRAINT chk_reviews_status CHECK (status IN ('en attente', 'validé', 'refusé')),
CONSTRAINT fk_reviews_event
FOREIGN KEY (event_id) REFERENCES events(id)
ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT fk_reviews_moderator
FOREIGN KEY (moderated_by) REFERENCES users(id)
ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
