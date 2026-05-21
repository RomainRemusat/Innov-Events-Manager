<?php
/**
 * Vue : Formulaire de demande de devis public
 *
 * Ce fichier gère l'affichage de l'interface utilisateur permettant aux prospects
 * de soumettre une première demande de devis sur la plateforme Innov'Events Manager.
 * * Conformité d'accessibilité (RGAA) :
 * - Utilisation d'une structure sémantique propre.
 * - Association explicite de chaque étiquette (label) à son champ via l'attribut 'for'.
 * - Attributs 'required' pour la validation native de l'accessibilité au clavier.
 *
 * @package    InnovEventsManager
 * @subpackage Views
 * @author     Romain Remusat
 * @version    1.0.0
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande de Devis - Innov'Events</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Demander un devis</h2>
                    <p class="text-muted text-center mb-4">Parlez-nous de votre projet, Chloé vous recontactera rapidement.</p>

                    <form action="index.php?action=devis" method="POST">

                        <div class="mb-3">
                            <label for="company_name" class="form-label">Nom de l'entreprise *</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" required>
                        </div>

                        <div class="mb-3">
                            <label for="contact_name" class="form-label">Nom et Prénom *</label>
                            <input type="text" class="form-control" id="contact_name" name="contact_name" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Numéro de téléphone *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                            <div class="col-md-6">
                                <label for="event_type" class="form-label">Type d'événement *</label>
                                <select class="form-select" id="event_type" name="event_type" required>
                                    <option value="">Choisir...</option>
                                    <option value="Séminaire">Séminaire</option>
                                    <option value="Conférence">Conférence</option>
                                    <option value="Soirée d'entreprise">Soirée d'entreprise</option>
                                    <option value="Autre">Autre</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Envoyer ma demande</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>