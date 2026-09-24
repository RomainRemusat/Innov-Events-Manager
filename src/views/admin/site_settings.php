<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" id="main-content">
            <?php require __DIR__ . '/../partials/admin_messages.php'; ?>
            <div class="border-bottom mb-4 pb-3">
                <h1 class="h2 fw-bold">Contenus publics</h1>
                <p class="text-secondary mb-0">Personnalisez les textes affichés aux visiteurs.</p>
            </div>
            <form method="post" action="index.php?action=admin_update_site_settings" class="card border-0 shadow-sm p-4" style="max-width: 900px">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <label class="form-label fw-semibold" for="quote-thank-you">Message après une demande de devis</label>
                <textarea class="form-control" id="quote-thank-you" name="quote_thank_you_message" minlength="20" maxlength="1000" rows="6" required><?= htmlspecialchars($thankYouMessage, ENT_QUOTES, 'UTF-8') ?></textarea>
                <p class="form-text">Ce texte apparaît uniquement lorsque la demande a bien été enregistrée.</p>
                <div><button class="btn btn-primary" type="submit">Enregistrer</button></div>
            </form>
        </main>
    </div>
</div>
