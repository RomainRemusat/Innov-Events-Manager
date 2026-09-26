<?php

require_once __DIR__ . '/../models/sql/Event.php';
require_once __DIR__ . '/../models/nosql/Log.php';
require_once __DIR__ . '/FileUploadService.php';

/** Enregistre les projets et coordonne leurs images avec les transactions SQL. */
class EventManagementService
{
    /**
     * Crée ou modifie un événement ; son propriétaire reste inchangé après création.
     * Les coordonnées commerciales des devis déjà émis ne sont pas réécrites.
     *
     * @param int|null $id Identifiant existant, ou null pour une création.
     * @param array $data Champs du formulaire d'événement.
     * @param array|null $file Image reçue via $_FILES.
     * @param int $actorId Administrateur exécutant la modification.
     * @param bool $imageOnly Réservé à l'action de remplacement de l'illustration.
     * @return array{id: int, image_cleanup_failed: bool} Résultat après validation SQL.
     * @throws Throwable Si la validation ou la transaction échoue.
     */
    public function save(?int $id, array $data, ?array $file, int $actorId, bool $imageOnly = false): array
    {
        $db = Database::getInstance();
        $model = new Event();
        $newImage = null;
        $committed = false;
        $db->beginTransaction();
        try {
            $old = null;
            if ($id !== null) {
                $query = $db->prepare('SELECT * FROM events WHERE id = ? FOR UPDATE');
                $query->execute([$id]);
                $old = $query->fetch(PDO::FETCH_ASSOC);
                if (!$old) throw new InvalidArgumentException('Événement introuvable.');
            }
            if ($imageOnly) {
                if (!$old) throw new InvalidArgumentException('Événement introuvable.');
                $data = $old;
                $data['start_date'] = str_replace(' ', 'T', $old['start_date']);
                $data['end_date'] = str_replace(' ', 'T', $old['end_date'] ?? '');
                $data['publish'] = (string)$old['is_published'];
            }
            foreach ($data as $value) {
                if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException('Champ de formulaire invalide.');
            }
            $clientId = filter_var($data['client_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$clientId || ($old && $clientId !== (int)$old['client_id'])) {
                throw new InvalidArgumentException('Le client du projet est invalide ou a été modifié.');
            }
            $clientQuery = $db->prepare("SELECT company_id, is_deleted FROM users WHERE id = ? AND role = 'CLIENT' FOR UPDATE");
            $clientQuery->execute([$clientId]);
            $client = $clientQuery->fetch(PDO::FETCH_ASSOC);
            if (!$client || (!$old && (int)$client['is_deleted'])) throw new InvalidArgumentException('Sélectionnez un client actif.');
            $values = [];
            foreach (['title' => 255, 'location' => 255, 'event_type' => 100, 'theme' => 100] as $field => $max) {
                $values[$field] = trim((string)($data[$field] ?? ''));
                if (mb_strlen($values[$field]) > $max || ($field !== 'theme' && $values[$field] === '')) {
                    throw new InvalidArgumentException("Le champ $field est obligatoire et limité à $max caractères.");
                }
            }
            $values['description'] = trim((string)($data['description'] ?? ''));
            if (strlen($values['description']) > 65535) throw new InvalidArgumentException('Description trop longue.');
            $values['start_date'] = $this->date((string)($data['start_date'] ?? ''));
            $values['end_date'] = empty($data['end_date']) ? null : $this->date((string)$data['end_date']);
            if ($values['end_date'] !== null && $values['end_date'] <= $values['start_date']) {
                throw new InvalidArgumentException('La date de fin doit être postérieure au début.');
            }
            $participants = $data['estimated_participants'] ?? '';
            $values['estimated_participants'] = $participants === '' || $participants === null ? null
                : filter_var($participants, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            if ($values['estimated_participants'] === false) throw new InvalidArgumentException('Le nombre de participants doit être un entier positif.');
            $status = Event::normalizeStatus((string)($data['status'] ?? 'brouillon'));
            if (!isset(Event::STATUS_LABELS[$status])) throw new InvalidArgumentException('Statut inconnu.');
            $publish = ($data['publish'] ?? '') === '1';
            $confirmed = ($data['publication_consent'] ?? '') === '1';
            if ($publish && empty($old['publication_consent_at']) && !$confirmed) {
                throw new InvalidArgumentException('Confirmez l’accord client avant la publication.');
            }
            $imagePath = $old['image_path'] ?? null;
            if ($file !== null && ($file['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
                $newImage = (new FileUploadService())->uploadEventImage($file);
                if ($newImage === null) throw new RuntimeException('L’image n’a pas pu être enregistrée.');
                $imagePath = $newImage;
            } elseif (($data['remove_image'] ?? '') === '1') {
                $imagePath = null;
            }
            $values['image_path'] = $imagePath;
            if ($old) {
                $query = $db->prepare('UPDATE events SET title=:title, location=:location, event_type=:event_type,
                    theme=:theme, description=:description, start_date=:start_date, end_date=:end_date,
                    estimated_participants=:estimated_participants, image_path=:image_path WHERE id=:id');
                $query->execute($values + ['id' => $id]);
            } else {
                $query = $db->prepare("INSERT INTO events (client_id,company_id,title,location,event_type,theme,description,
                    start_date,end_date,estimated_participants,image_path,status,is_published)
                    VALUES (:client_id,:company_id,:title,:location,:event_type,:theme,:description,
                    :start_date,:end_date,:estimated_participants,:image_path,'brouillon',0)");
                $query->execute($values + ['client_id' => $clientId, 'company_id' => $client['company_id']]);
                $id = (int)$db->lastInsertId();
            }
            $oldStatus = $old['status'] ?? 'brouillon';
            if ($status !== $oldStatus && !$model->updateStatus($id, $status, $oldStatus)) {
                throw new InvalidArgumentException('Statut non modifié : le passage en cours nécessite un devis accepté.');
            }
            $publicationChanged = $publish !== (bool)($old['is_published'] ?? false)
                || ($publish && empty($old['publication_consent_at']));
            if ($publicationChanged && !$model->setPublication($id, $publish, $confirmed, $actorId)) {
                throw new InvalidArgumentException('L’accord de publication n’a pas pu être enregistré.');
            }
            $db->commit();
            $committed = true;
            (new Log())->addLog($old ? 'MODIFICATION_EVENEMENT' : 'CREATION_EVENEMENT', $actorId, [
                'event_id' => $id, 'title' => $values['title'], 'old_status' => $oldStatus, 'new_status' => $status,
                'old_image' => $old['image_path'] ?? null, 'image_path' => $imagePath,
            ]);
            if ($status !== $oldStatus) {
                (new Log())->addLog('MODIFICATION_STATUT_EVENEMENT', $actorId,
                    ['event_id' => $id, 'old_status' => $oldStatus, 'new_status' => $status]);
            }
            $cleaned = $imagePath === ($old['image_path'] ?? null) || $this->cleanImage($old['image_path'] ?? null);
            return ['id' => $id, 'image_cleanup_failed' => !$cleaned];
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            if (!$committed && $newImage !== null) $this->cleanImage($newImage);
            throw $error;
        }
    }

    /**
     * Supprime les notes par cascade et détache les devis, qui restent consultables.
     * L'image non partagée est nettoyée après le commit ; un échec est signalé à l'appelant.
     * @param int $id Événement à supprimer.
     * @param int $actorId Administrateur authentifié.
     * @return bool Vrai si le nettoyage du fichier est également terminé.
     * @throws Throwable En cas de dossier introuvable ou d'échec SQL.
     */
    public function delete(int $id, int $actorId): bool
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT title, image_path FROM events WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $event = $query->fetch(PDO::FETCH_ASSOC);
            if (!$event) throw new InvalidArgumentException('Événement introuvable.');
            $query = $db->prepare('DELETE FROM events WHERE id = ?');
            $query->execute([$id]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
        (new Log())->addLog('SUPPRESSION_EVENEMENT', $actorId, ['event_id' => $id] + $event);
        return $this->cleanImage($event['image_path']);
    }

    /**
     * Efface uniquement une image locale qui n'est plus référencée par aucun événement.
     * @param string|null $path Chemin relatif issu de la base ou du service d'upload.
     * @return bool Faux si le fichier nécessite un nettoyage manuel.
     */
    private function cleanImage(?string $path): bool
    {
        if (!$path) return true;
        try {
            if ($path !== 'uploads/events/' . basename($path)) throw new RuntimeException('Chemin image non autorisé.');
            $query = Database::getInstance()->prepare('SELECT COUNT(*) FROM events WHERE image_path = ?');
            $query->execute([$path]);
            if ((int)$query->fetchColumn() > 0) return true;
            $directory = dirname(__DIR__, 2) . '/public/uploads/events';
            $absolute = $directory . '/' . basename($path);
            if (!file_exists($absolute) && !is_link($absolute)) return true;
            if (is_link($absolute) || !is_file($absolute) || dirname(realpath($absolute)) !== realpath($directory)
                || !unlink($absolute)) throw new RuntimeException('Image non supprimable.');
            return true;
        } catch (Throwable $error) {
            error_log('[EventManagementService::cleanImage] ' . $path . ' : ' . $error->getMessage());
            return false;
        }
    }

    /**
     * Convertit une date locale du formulaire sans corriger silencieusement le calendrier.
     * @param string $value Date et heure avec secondes facultatives.
     * @return string Valeur DATETIME pour MySQL.
     * @throws InvalidArgumentException En cas de date invalide.
     */
    private function date(string $value): string
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date && $date->format($format) === $value && (int)$date->format('Y') >= 1000) return $date->format('Y-m-d H:i:s');
        }
        throw new InvalidArgumentException('La date ou l’heure est invalide.');
    }
}
