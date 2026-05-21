<?php
/**
 * Vue : Formulaire d'Authentification (Login)
 *
 * Cette vue présente l'interface utilisateur permettant aux administrateurs,
 * employés et clients de s'authentifier. Elle est chargée par l'AuthController
 * et transmet de manière sécurisée (POST) les identifiants saisis au routeur.
 *
 * @package    InnovEventsManager
 * @subpackage Views\Public
 * @author     Romain Remusat
 * @version    1.1.0
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Innov'Events Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">

            <div class="card shadow-sm mt-5 border-0">
                <div class="card-body p-4">

                    <h2 class="card-title text-center mb-3 text-primary">Connexion</h2>
                    <p class="text-muted text-center mb-4">Accédez à votre espace sécurisé Innov'Events Manager.</p>

                    <form action="index.php?action=login" method="POST">

                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">Adresse Email</label>
                            <input type="email" class="form-control form-control-lg" id="email" name="email" required placeholder="exemple@innovevents.fr" autocomplete="email">
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-bold">Mot de passe</label>
                            <input type="password" class="form-control form-control-lg" id="password" name="password" required placeholder="••••••••" autocomplete="current-password">
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Se connecter</button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <a href="index.php" class="text-decoration-none text-secondary">← Retour à l'accueil</a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>