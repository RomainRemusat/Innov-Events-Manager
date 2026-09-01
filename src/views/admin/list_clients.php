<?php
/**
 * Vue : Liste globale des Clients (Back-Office)
 *
 * Ce composant (Vue) est responsable de l'affichage du portefeuille clients
 * de l'agence (les prospects ayant été convertis avec succès).
 * Il s'appuie sur la table `users` (filtrée par le rôle 'CLIENT').
 *
 * Normes ECF appliquées :
 * - Accessibilité (RGAA / AT1) : Utilisation des attributs scope="col", aria-label et aria-hidden.
 * - Sécurité (AT1) : Échappement systématique des données dynamiques (htmlspecialchars + ENT_QUOTES).
 * - Exigences Métier (AT2) : Préparation de l'interface pour la création, modification et suppression des clients.
 *
 * @package    InnovEventsManager
 * @subpackage Views/Admin
 * @author     Romain Remusat
 * @version    1.1.0
 *
 * @var array $clients Liste des utilisateurs récupérée depuis la base de données (rôle 'CLIENT').
 */
?>

<div class="container-fluid bg-light min-vh-100">
    <div class="row">

        <!-- Inclusion modulaire de la navigation latérale -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <!-- =============================================================== -->
            <!-- EN-TÊTE DE LA PAGE (Header)                                     -->
            <!-- =============================================================== -->
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        <!-- aria-hidden empêche la lecture redondante de l'icône par le lecteur d'écran -->
                        <i class="fa-solid fa-address-book text-primary me-2" aria-hidden="true"></i>Gestion des Clients
                    </h1>
                    <p class="text-muted small mb-0">
                        Retrouvez ici l'ensemble de vos clients (prospects convertis). Accédez à leurs fiches pour gérer leurs informations.
                    </p>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- TABLEAU DES DONNÉES (DataGrid)                                  -->
            <!-- =============================================================== -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" aria-label="Liste de vos clients">
                            <!-- Charte graphique Innov'Events : En-tête Slate Dark (#0F172A) -->
                            <thead style="background-color: #0F172A; color: white;">
                            <tr>
                                <!-- Norme RGAA : scope="col" aide les technologies d'assistance à structurer le tableau -->
                                <th scope="col" class="py-3 px-4">Nom / Prénom</th>
                                <th scope="col" class="py-3 px-4">Email de contact</th>
                                <th scope="col" class="py-3 px-4 text-center">Date d'inscription</th>
                                <th scope="col" class="py-3 px-4 text-center">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($clients)): ?>
                                <!-- État Vide (Empty State) : Optimisation UX si la table est vierge -->
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        Aucun client enregistré pour le moment. Convertissez un prospect pour commencer.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <!-- Itération sur le jeu de résultats SQL -->
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-dark">
                                            <!-- Sécurisation XSS stricte lors de l'affichage des identités -->
                                            <div class="fs-4"><?= htmlspecialchars($client['company_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="fst-italic"><?= htmlspecialchars($client['firstname'] . ' ' . $client['lastname'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-muted">
                                            <!-- Lien mailto sécurisé -->
                                            <a href="mailto:<?= htmlspecialchars($client['email'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none" aria-label="Envoyer un email à <?= htmlspecialchars($client['firstname'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($client['email'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 text-center small text-muted">
                                            <!-- Formatage de la date à la norme française -->
                                            <?= date('d/m/Y', strtotime($client['created_at'] ?? 'now')) ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">

                                            <!-- Bouton Consulter le dossier client (Devis & Événements) -->
                                            <a href="index.php?action=view_client&id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-outline-info me-1" title="Voir le dossier" aria-label="Consulter le dossier">
                                                <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                                            </a>

                                            <!--
                                              Bouton d'édition (Exigence AT2 : modification d'un client).
                                              RGAA : aria-label est obligatoire car le bouton ne contient pas de texte, juste une icône.
                                            -->
                                            <a href="index.php?action=edit_client&id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Éditer le client" aria-label="Éditer le profil de <?= htmlspecialchars($client['firstname'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                            </a>

                                            <!--
                                              Bouton de suppression (Exigence AT2 : suppression d'un client).
                                              Utilisation de la couleur danger (rouge) selon la charte d'interface.
                                            -->
                                            <form action="index.php?action=delete_client" method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement ce client ? Cette action est irréversible.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer le client" aria-label="Supprimer le client <?= htmlspecialchars($client['firstname'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>