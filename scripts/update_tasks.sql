-- Ajoute le suivi des tâches sans modifier les événements ni les comptes existants.
CREATE TABLE IF NOT EXISTS tasks (
id INT AUTO_INCREMENT PRIMARY KEY,
event_id INT NOT NULL,
assigned_user_id INT NOT NULL,
created_by INT NOT NULL,
title VARCHAR(255) NOT NULL,
status VARCHAR(50) NOT NULL DEFAULT 'à faire',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_tasks_event
FOREIGN KEY (event_id) REFERENCES events(id)
ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT fk_tasks_assigned_user
FOREIGN KEY (assigned_user_id) REFERENCES users(id)
ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT fk_tasks_creator
FOREIGN KEY (created_by) REFERENCES users(id)
ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
