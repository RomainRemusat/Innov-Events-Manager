<?php
/**
 * Formulaire de création et de modification des informations logistiques.
 * @var array|null $event Événement existant ; null en création.
 * @var array $form Valeurs initiales ou dernières saisies après erreur.
 * @var array $clients Clients actifs proposés pour un nouveau projet.
 */
?>
<div class="container-fluid"><div class="row">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="col-md-9 col-lg-10 p-4">
        <a href="index.php?action=admin_events" class="btn btn-outline-secondary btn-sm mb-3">Retour aux événements</a>
        <h1 class="h3 mb-4"><?= $event ? 'Modifier l’événement' : 'Créer un événement' ?></h1>
        <?php require __DIR__ . '/../partials/admin_messages.php'; ?>
        <form action="index.php?action=admin_save_event" method="post" enctype="multipart/form-data" class="card card-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="event_id" value="<?= (int)($event['id'] ?? 0) ?>">
            <?php if ($event): ?>
                <p>Client : <strong><?= htmlspecialchars($event['firstname'] . ' ' . $event['lastname'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                <input type="hidden" name="client_id" value="<?= (int)$event['client_id'] ?>">
            <?php else: ?>
                <label for="client_id" class="form-label">Client *</label>
                <select name="client_id" id="client_id" class="form-select mb-3" required>
                    <option value="">Sélectionnez un client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int)$client['id'] ?>" <?= (int)($form['client_id'] ?? 0) === (int)$client['id'] ? 'selected' : '' ?>><?= htmlspecialchars($client['firstname'] . ' ' . $client['lastname'] . ' — ' . ($client['company_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <div class="row g-3">
                <?php foreach (['title' => ['Nom de l’événement', 255], 'location' => ['Lieu', 255], 'theme' => ['Thème', 100]] as $field => [$label, $max]): ?>
                    <div class="col-md-6"><label for="<?= $field ?>" class="form-label"><?= $label ?><?= $field !== 'theme' ? ' *' : '' ?></label>
                        <input class="form-control" id="<?= $field ?>" name="<?= $field ?>" maxlength="<?= $max ?>" <?= $field !== 'theme' ? 'required' : '' ?> value="<?= htmlspecialchars((string)($form[$field] ?? ($field === 'event_type' ? 'Autre' : '')), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                <?php endforeach; ?>
                <div class="col-md-6">
                    <label for="event_type" class="form-label">Type d’événement *</label>
                    <select id="event_type" name="event_type" class="form-select" required>
                        <?php
                        // Conserver les anciens types personnalisés lors d'une modification.
                        $selectedType = (string)($form['event_type'] ?? 'Autre');
                        $eventTypes = ['Séminaire', 'Soirée de Gala', 'Lancement de produit', 'Team Building', 'Autre'];
                        if ($selectedType !== '' && !in_array($selectedType, $eventTypes, true)) $eventTypes[] = $selectedType;
                        ?>
                        <?php foreach ($eventTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedType === $type ? 'selected' : '' ?>><?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php foreach (['start_date' => 'Début *', 'end_date' => 'Fin'] as $field => $label): ?>
                    <div class="col-md-6"><label for="<?= $field ?>" class="form-label"><?= $label ?></label>
                        <input type="datetime-local" step="1" class="form-control" id="<?= $field ?>" name="<?= $field ?>" <?= $field === 'start_date' ? 'required' : '' ?> value="<?= htmlspecialchars((string)($form[$field] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                <?php endforeach; ?>
                <div class="col-md-6"><label for="estimated_participants" class="form-label">Participants prévus</label><input type="number" min="1" max="2147483647" class="form-control" id="estimated_participants" name="estimated_participants" value="<?= htmlspecialchars((string)($form['estimated_participants'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-6"><label for="status" class="form-label">Statut</label><select class="form-select" id="status" name="status">
                    <?php foreach (Event::STATUS_LABELS as $value => $label): ?>
                        <option value="<?= $value ?>" <?= Event::normalizeStatus((string)($form['status'] ?? 'brouillon')) === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select><p class="form-text">Le passage en cours nécessite un devis associé accepté.</p></div>
                <div class="col-12"><label for="description" class="form-label">Description</label><textarea id="description" name="description" rows="4" class="form-control"><?= htmlspecialchars((string)($form['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></div>
                <div class="col-12"><label for="event_image" class="form-label">Illustration (JPEG, PNG ou WebP, 5 Mo maximum)</label><input type="file" class="form-control" id="event_image" name="event_image" accept="image/jpeg,image/png,image/webp">
                    <?php if (!empty($event['image_path'])): ?><label class="mt-2"><input type="checkbox" name="remove_image" value="1" <?= ($form['remove_image'] ?? '') === '1' ? 'checked' : '' ?>> Retirer l’illustration actuelle</label><?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="d-block"><input type="checkbox" name="publish" value="1" <?= ($form['publish'] ?? '') === '1' ? 'checked' : '' ?>> Publier dans le catalogue public (hors brouillon)</label>
                    <label class="d-block mt-2"><input type="checkbox" name="publication_consent" value="1"> Je confirme avoir recueilli l’accord client pour publier cet événement et son illustration.</label>
                    <p class="form-text"><?= !empty($event['publication_consent_at']) ? 'Un accord est déjà enregistré. Le retrait de publication efface cet accord actif.' : 'La confirmation est obligatoire pour activer la publication.' ?></p>
                </div>
                <div class="col-12"><button type="submit" class="btn btn-primary">Enregistrer l’événement</button></div>
            </div>
        </form>
    </main>
</div></div>
