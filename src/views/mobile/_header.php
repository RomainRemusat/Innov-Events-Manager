<?php
$mobileTitle = $mobileTitle ?? 'Innov’Events Mobile';
$mobileBack = $mobileBack ?? null;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="description" content="Consultation mobile des événements et contacts Innov’Events.">
    <link rel="manifest" href="mobile-manifest.webmanifest">
    <link rel="icon" href="mobile/icon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="mobile/icon-192.png">
    <link rel="stylesheet" href="mobile/app.css">
    <title><?= htmlspecialchars($mobileTitle, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
<a class="mobile-skip" href="#mobile-main">Aller au contenu</a>
<header class="mobile-header">
    <div class="mobile-header__row">
        <?php if ($mobileBack): ?>
            <a class="mobile-icon-link" href="<?= htmlspecialchars($mobileBack, ENT_QUOTES, 'UTF-8') ?>" aria-label="Retour à la liste">←</a>
        <?php else: ?>
            <span class="mobile-brand-mark" aria-hidden="true">IE</span>
        <?php endif; ?>
        <div>
            <strong>Innov’Events</strong>
            <span>Équipe mobile</span>
        </div>
        <button id="install-app" class="mobile-install" type="button" hidden>Installer</button>
    </div>
</header>
<?php if (!empty($_SESSION['mobile_success'])): ?>
    <p class="mobile-alert mobile-alert--success" role="status"><?= htmlspecialchars($_SESSION['mobile_success'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php unset($_SESSION['mobile_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['mobile_error'])): ?>
    <p class="mobile-alert mobile-alert--error" role="alert"><?= htmlspecialchars($_SESSION['mobile_error'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php unset($_SESSION['mobile_error']); ?>
<?php endif; ?>
<main id="mobile-main" class="mobile-main">
