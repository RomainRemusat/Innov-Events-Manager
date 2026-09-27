<?php
$mobileTitle = 'Fiche client — Innov’Events Mobile';
$mobileBack = 'index.php?action=mobile_event&id=' . (int)$client['id'];
$clientAddress = trim(implode(' ', array_filter([
    $client['address'] ?? null,
    $client['postal_code'] ?? null,
    $client['city'] ?? null,
])));
require __DIR__ . '/_header.php';
?>
<article>
    <section class="mobile-hero-card">
        <p class="mobile-eyebrow">Fiche client</p>
        <h1><?= htmlspecialchars(trim($client['firstname'] . ' ' . $client['lastname']), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($client['company_name'] ?: 'Compte individuel', ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($clientAddress !== ''): ?>
            <p><?= htmlspecialchars($clientAddress, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="mobile-actions">
            <?php if (!empty($client['phone'])): ?>
                <a class="mobile-action" href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', (string)$client['phone']), ENT_QUOTES, 'UTF-8') ?>">Appeler</a>
            <?php endif; ?>
            <a class="mobile-action" href="mailto:<?= htmlspecialchars($client['client_email'], ENT_QUOTES, 'UTF-8') ?>">Envoyer un email</a>
            <?php if ($clientAddress !== ''): ?>
                <a class="mobile-action" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($clientAddress) ?>" target="_blank" rel="noopener noreferrer">Itinéraire</a>
            <?php endif; ?>
        </div>
    </section>
</article>

<?php require __DIR__ . '/_footer.php'; ?>
