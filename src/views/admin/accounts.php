<?php
/**
 * Gestion des accès clients et employés, réservée à l'administration.
 * @var array $accounts Comptes administrables, sans mots de passe.
 * @var array $companies Entreprises disponibles.
 * @var array $inputs Dernières coordonnées soumises en cas d'erreur.
 */
?>
<div class="container-fluid"><div class="row">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="col-md-9 col-lg-10 p-4">
        <h1 class="h3 mb-4">Comptes clients et employés</h1>
        <?php require __DIR__ . '/../partials/admin_messages.php'; ?>
        <section class="card mb-4" aria-labelledby="create-account-heading"><div class="card-body">
            <h2 id="create-account-heading" class="h5">Créer un compte</h2>
            <form action="index.php?action=admin_manage_account" method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="operation" value="create">
                <?php foreach (['firstname' => 'Prénom', 'lastname' => 'Nom', 'email' => 'Email'] as $field => $label): ?>
                    <div class="col-md-4">
                        <label class="form-label" for="<?= $field ?>"><?= $label ?> *</label>
                        <input class="form-control" id="<?= $field ?>" name="<?= $field ?>" type="<?= $field === 'email' ? 'email' : 'text' ?>" maxlength="<?= $field === 'email' ? 255 : 100 ?>" required value="<?= htmlspecialchars((string)($inputs[$field] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                <?php endforeach; ?>
                <div class="col-md-4">
                    <label class="form-label" for="role">Rôle *</label>
                    <select class="form-select" id="role" name="role" required>
                        <?php foreach (['CLIENT' => 'Client', 'EMPLOYEE' => 'Employé'] as $role => $label): ?>
                            <option value="<?= $role ?>" <?= ($inputs['role'] ?? '') === $role ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="company_id">Entreprise du client (facultatif)</label>
                    <select class="form-select" id="company_id" name="company_id" aria-describedby="company-help">
                        <option value="">Sans entreprise</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= (int)$company['id'] ?>" <?= (string)($inputs['company_id'] ?? '') === (string)$company['id'] ? 'selected' : '' ?>><?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p id="company-help" class="form-text">Le rattachement à une entreprise concerne uniquement les comptes clients.</p>
                </div>
                <div class="col-12"><p class="text-muted">Un mot de passe temporaire sera envoyé par email et devra être remplacé à la première connexion.</p><button class="btn btn-primary" type="submit">Créer le compte</button></div>
            </form>
        </div></section>
        <section aria-labelledby="accounts-heading">
            <h2 id="accounts-heading" class="h5">Comptes existants</h2>
            <p class="text-muted">La suspension bloque l’accès et conserve les dossiers. La suppression d’un client efface ses demandes, devis, événements et fichiers non partagés. La suppression d’un employé efface aussi ses notes.</p>
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Identité</th><th scope="col">Rôle</th><th scope="col">État</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><?= htmlspecialchars($account['firstname'] . ' ' . $account['lastname'], ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars($account['email'], ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><?= $account['role'] === 'CLIENT' ? 'Client' : 'Employé' ?></td>
                        <td><?= $account['is_deleted'] ? 'Suspendu' : 'Actif' ?></td>
                        <td>
                            <form method="post" action="index.php?action=admin_manage_account" class="mb-2">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                                <button type="submit" name="operation" value="<?= $account['is_deleted'] ? 'restore' : 'suspend' ?>" class="btn btn-outline-secondary btn-sm"><?= $account['is_deleted'] ? 'Réactiver' : 'Suspendre' ?></button>
                            </form>
                            <details><summary class="text-danger">Supprimer définitivement</summary>
                                <form method="post" action="index.php?action=admin_manage_account" class="mt-2">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                                    <label class="d-block"><input type="checkbox" name="confirm_delete" value="1" required> Je confirme la suppression définitive de ce compte et des données décrites ci-dessus.</label>
                                    <button type="submit" name="operation" value="delete" class="btn btn-danger btn-sm mt-2">Supprimer le compte</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$accounts): ?><tr><td colspan="4">Aucun compte client ou employé.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </section>
    </main>
</div></div>
