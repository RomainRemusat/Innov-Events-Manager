<?php
/** Affiche puis consomme les messages de résultat des actions d'administration. */
foreach (['flash_success' => 'success', 'flash_error' => 'danger', 'flash_warning' => 'warning'] as $key => $color): ?>
    <?php if (!empty($_SESSION[$key])): ?>
        <div class="alert alert-<?= $color ?>" role="alert"><?= htmlspecialchars($_SESSION[$key], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION[$key]); ?>
    <?php endif; ?>
<?php endforeach; ?>
