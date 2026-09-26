<?php
$mobileTitle = 'Événements à venir — Innov’Events Mobile';
require __DIR__ . '/_header.php';
?>
<section class="mobile-intro">
    <p class="mobile-eyebrow">Bonjour <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_firstname'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
    <h1>Événements à venir</h1>
    <p>Les informations essentielles pour préparer un rendez-vous ou contacter un client.</p>
</section>

<?php if (!$events): ?>
    <section class="mobile-empty">
        <h2>Aucun événement à venir</h2>
        <p>Les prochains projets apparaîtront ici.</p>
    </section>
<?php else: ?>
    <div class="mobile-event-list">
        <?php foreach ($events as $event): ?>
            <?php $start = new DateTimeImmutable((string)$event['start_date']); ?>
            <a class="mobile-event-card" href="index.php?action=mobile_event&amp;id=<?= (int)$event['id'] ?>">
                <time datetime="<?= $start->format('c') ?>" class="mobile-date">
                    <strong><?= $start->format('d') ?></strong>
                    <span><?= mb_strtoupper($start->format('M')) ?></span>
                </time>
                <span class="mobile-event-card__body">
                    <strong><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= htmlspecialchars($event['company_name'] ?: trim($event['firstname'] . ' ' . $event['lastname']), ENT_QUOTES, 'UTF-8') ?></span>
                    <span><?= htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
                <span aria-hidden="true">›</span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
