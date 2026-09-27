<?php
/**
 * @var array<string, mixed>             $event Événement et contact client associé.
 * @var array<int, array<string, mixed>> $notes Notes collaboratives de l'événement.
 */
$mobileTitle = $event['title'] . ' — Innov’Events Mobile';
$mobileBack = 'index.php?action=mobile_dashboard';
$start = new DateTimeImmutable((string)$event['start_date']);
$end = !empty($event['end_date']) ? new DateTimeImmutable((string)$event['end_date']) : null;
require __DIR__ . '/_header.php';
?>
<article>
    <section class="mobile-hero-card">
        <p class="mobile-eyebrow"><?= htmlspecialchars($event['event_type'] ?: 'Événement', ENT_QUOTES, 'UTF-8') ?></p>
        <h1><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <dl class="mobile-facts">
            <div><dt>Début</dt><dd><time datetime="<?= $start->format('c') ?>"><?= $start->format('d/m/Y à H:i') ?></time></dd></div>
            <?php if ($end): ?><div><dt>Fin</dt><dd><time datetime="<?= $end->format('c') ?>"><?= $end->format('d/m/Y à H:i') ?></time></dd></div><?php endif; ?>
            <div><dt>Lieu</dt><dd><?= htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>Statut</dt><dd><?= htmlspecialchars(Event::STATUS_LABELS[Event::normalizeStatus((string)$event['status'])] ?? (string)$event['status'], ENT_QUOTES, 'UTF-8') ?></dd></div>
        </dl>
        <a class="mobile-action mobile-action--secondary" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode((string)$event['location']) ?>" target="_blank" rel="noopener noreferrer">Itinéraire vers l’événement</a>
    </section>

    <section class="mobile-section" aria-labelledby="client-title">
        <p class="mobile-eyebrow">Contact</p>
        <h2 id="client-title"><?= htmlspecialchars(trim($event['firstname'] . ' ' . $event['lastname']), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($event['company_name'] ?: 'Compte individuel', ENT_QUOTES, 'UTF-8') ?></p>
        <a class="mobile-action" href="index.php?action=mobile_client&amp;event_id=<?= (int)$event['id'] ?>">Ouvrir la fiche client</a>
    </section>

    <section class="mobile-section" aria-labelledby="note-title">
        <p class="mobile-eyebrow">Compte rendu terrain</p>
        <h2 id="note-title">Ajouter une note rapide</h2>
        <form method="post" action="index.php?action=mobile_add_note">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
            <label for="mobile-note">Information à partager</label>
            <textarea id="mobile-note" name="content" rows="4" maxlength="10000" required placeholder="Ex. Le client confirme l’accès au lieu à 14 h."></textarea>
            <button class="mobile-submit" type="submit">Enregistrer la note</button>
        </form>
    </section>

    <section class="mobile-section" aria-labelledby="history-title">
        <h2 id="history-title">Dernières notes</h2>
        <?php if (!$notes): ?>
            <p>Aucune note pour cet événement.</p>
        <?php else: ?>
            <ul class="mobile-notes">
                <?php foreach (array_slice($notes, 0, 5) as $note): ?>
                    <li>
                        <p><?= nl2br(htmlspecialchars($note['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                        <small><?= htmlspecialchars(trim(($note['firstname'] ?? '') . ' ' . ($note['lastname'] ?? '')), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$note['created_at'], ENT_QUOTES, 'UTF-8') ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</article>

<?php require __DIR__ . '/_footer.php'; ?>
