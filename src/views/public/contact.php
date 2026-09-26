<main class="container py-5">
    <div class="row g-5">
        <div class="col-lg-5">
            <h1 class="fw-bold">Contactez-nous</h1>
            <p class="text-secondary">Une question sur nos prestations ou un projet à préciser ? Écrivez-nous, notre équipe vous répondra dans les meilleurs délais.</p>
            <address class="mt-4 fst-normal">
                <p><strong>Innov'Events Agency</strong><br>15 rue de l’Innovation<br>75000 Paris</p>
                <p><a href="tel:0123456789">01 23 45 67 89</a><br><a href="mailto:contact@innovevents.fr">contact@innovevents.fr</a></p>
            </address>
        </div>
        <div class="col-lg-7">
            <?php if (!empty($_SESSION['contact_success'])): ?>
                <p class="alert alert-success" role="status"><?= htmlspecialchars($_SESSION['contact_success'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php unset($_SESSION['contact_success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['contact_error'])): ?>
                <p class="alert alert-danger" role="alert"><?= htmlspecialchars($_SESSION['contact_error'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php unset($_SESSION['contact_error']); ?>
            <?php endif; ?>

            <form method="post" action="index.php?action=contact" class="card border-0 shadow-sm p-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="contact-name">Nom et prénom *</label>
                        <input class="form-control" id="contact-name" name="name" maxlength="100" required value="<?= htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="contact-email">Adresse email *</label>
                        <input class="form-control" id="contact-email" type="email" name="email" maxlength="255" required value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="contact-subject">Objet *</label>
                        <input class="form-control" id="contact-subject" name="subject" minlength="3" maxlength="150" required value="<?= htmlspecialchars($old['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="contact-message">Message *</label>
                        <textarea class="form-control" id="contact-message" name="message" minlength="10" maxlength="5000" rows="7" required><?= htmlspecialchars($old['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="col-12 form-check ms-2">
                        <input class="form-check-input" id="contact-consent" type="checkbox" name="rgpd_consent" value="1" required>
                        <label class="form-check-label small" for="contact-consent">J’accepte que mes informations soient utilisées pour répondre à ma demande. Consultez notre <a href="index.php?action=politique_confidentialite">politique de confidentialité</a>.</label>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Envoyer le message</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>
