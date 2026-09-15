<?php

require_once __DIR__ . '/../models/sql/User.php';
require_once __DIR__ . '/../models/nosql/Log.php';

class AccountDeletionService
{
    public function delete(int $userId): bool
    {
        $db = Database::getInstance();
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("SELECT email FROM users WHERE id = ? AND role = 'CLIENT' FOR UPDATE");
            $stmt->execute([$userId]);
            $email = $stmt->fetchColumn();
            if ($email === false) {
                throw new RuntimeException('Compte client introuvable.');
            }

            $stmt = $db->prepare('SELECT id FROM prospects WHERE user_id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $prospectIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $stmt = $db->prepare('SELECT d.id_devis, d.reference_pdf FROM devis d
                JOIN prospects p ON p.id = d.id_prospect WHERE p.user_id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $quotes = $stmt->fetchAll();
            $stmt = $db->prepare('SELECT id, image_path FROM events WHERE client_id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $events = $stmt->fetchAll();

            // Les dépendances SQL sont supprimées en cascade, pas la société potentiellement partagée.
            if (!(new User())->deleteAccount($userId)) {
                throw new RuntimeException('Suppression SQL impossible.');
            }

            $root = dirname(__DIR__, 2);
            $files = [];
            foreach ($quotes as $quote) {
                if (empty($quote['reference_pdf'])) {
                    continue;
                }
                $name = basename($quote['reference_pdf']);
                $stmt = $db->prepare("SELECT COUNT(*) FROM devis WHERE SUBSTRING_INDEX(reference_pdf, '/', -1) = ?");
                $stmt->execute([$name]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $files[] = [$root . '/storage/devis', $name];
                }
            }
            foreach ($events as $event) {
                if (empty($event['image_path'])) {
                    continue;
                }
                $path = $event['image_path'];
                if ($path !== 'uploads/events/' . basename($path)) {
                    throw new RuntimeException('Chemin image non pris en charge.');
                }
                $stmt = $db->prepare('SELECT COUNT(*) FROM events WHERE image_path = ?');
                $stmt->execute([$path]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $files[] = [$root . '/public/uploads/events', basename($path)];
                }
            }

            $paths = [];
            foreach ($files as [$directory, $name]) {
                $path = $directory . '/' . $name;
                if (!file_exists($path) && !is_link($path)) {
                    continue; // Un fichier absent est déjà effacé (permet aussi une nouvelle tentative).
                }
                if (is_link($path) || !is_file($path) || dirname(realpath($path)) !== realpath($directory)
                    || !is_writable($directory)) {
                    throw new RuntimeException('Fichier non supprimable dans le répertoire autorisé.');
                }
                $paths[] = $path;
            }

            if (!(new Log())->deleteClientData($userId, $email, $prospectIds,
                array_column($quotes, 'id_devis'), array_column($events, 'id'))) {
                throw new RuntimeException('Nettoyage MongoDB impossible.');
            }
            foreach (array_unique($paths) as $path) {
                if (!unlink($path)) {
                    throw new RuntimeException('Suppression du fichier impossible.');
                }
            }
            $db->commit();
            return true;
        } catch (Throwable $e) {
            // MySQL ne peut pas annuler les effacements MongoDB/fichiers : conserver le compte
            // et ses références en cas d'échec permet de reprendre le nettoyage sans faux succès.
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[AccountDeletionService] ' . $e->getMessage());
            return false;
        }
    }
}
