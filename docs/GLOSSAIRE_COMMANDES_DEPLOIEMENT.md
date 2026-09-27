# Glossaire des commandes techniques

Ce glossaire regroupe les commandes principales utilisées pour construire,
tester, déployer et contrôler Innov'Events Manager. Les valeurs sensibles sont
remplacées par des variables ou des exemples génériques.

## Git et GitHub

| Commande | Rôle | Résultat attendu |
| --- | --- | --- |
| `git status --short` | Afficher les fichiers modifiés, ajoutés ou supprimés. | Une liste courte permettant de contrôler le contenu du prochain commit. |
| `git switch -c feature/deploiement-fly` | Créer une branche de fonctionnalité et s'y placer. | Le déploiement est préparé sans modifier directement `dev` ou `main`. |
| `git diff --check` | Rechercher les erreurs d'espaces et de fin de ligne dans les modifications. | Aucune erreur affichée. |
| `git add <fichier>` | Ajouter un fichier précis à la zone de préparation. | Le fichier apparaît dans « Changes to be committed ». |
| `git commit -m "type: description"` | Enregistrer un ensemble cohérent de modifications. | Un commit identifié par son empreinte Git. |
| `git push -u origin <branche>` | Publier une nouvelle branche sur GitHub. | La branche distante est créée et suivie localement. |
| `gh auth login --web` | Connecter GitHub CLI depuis le navigateur. | Le compte GitHub est reconnu par la commande `gh`. |
| `gh secret set FLY_API_TOKEN` | Enregistrer le jeton de déploiement dans les secrets GitHub. | Le workflow peut joindre Fly.io sans exposer le jeton dans le dépôt. |

## Docker et tests locaux

| Commande | Rôle | Résultat attendu |
| --- | --- | --- |
| `docker compose up -d --build` | Construire les images et démarrer les services locaux. | PHP, MySQL, MongoDB, MailHog et phpMyAdmin sont actifs. |
| `docker compose ps` | Afficher l'état des conteneurs. | Les services nécessaires sont indiqués comme démarrés. |
| `docker compose logs --no-color` | Consulter les journaux des services sans codes de couleur. | Les erreurs de démarrage ou de connexion sont lisibles dans la CI. |
| `docker compose exec -T app php -l <fichier.php>` | Vérifier la syntaxe d'un fichier PHP. | Le message `No syntax errors detected` est affiché. |
| `docker compose exec -T app php tests/password_policy.php` | Exécuter le test de politique des mots de passe. | Le scénario se termine sans erreur. |
| `python -B tests/<scenario>.py` | Exécuter un scénario fonctionnel Python sans créer de cache. | Le test confirme le comportement attendu ou retourne une erreur exploitable. |
| `docker compose down -v` | Arrêter l'environnement de test et supprimer ses volumes temporaires. | La prochaine CI repart d'une base vierge. |

## Fly.io

| Commande | Rôle | Résultat attendu |
| --- | --- | --- |
| `flyctl auth login` | Authentifier le poste auprès de Fly.io. | Les commandes suivantes accèdent à l'organisation personnelle. |
| `flyctl apps create <nom>` | Créer une application Fly vide. | Le nom apparaît dans le tableau de bord Fly.io. |
| `flyctl volumes create <volume> --app <app> --region cdg --size 1` | Créer un volume persistant de 1 Go à Paris. | Le volume est attachable à une machine de la même région. |
| `flyctl secrets set NOM="VALEUR" --app <app>` | Enregistrer un secret chiffré pour une application. | Le secret est injecté à l'exécution et n'apparaît pas dans Git. |
| `flyctl deploy --config fly.toml --remote-only` | Construire l'image à distance et déployer une nouvelle version. | La machine atteint l'état `started` et ses contrôles passent. |
| `flyctl status --app <app>` | Afficher la version, la région, la machine et les contrôles de santé. | La machine est `started` dans la région `cdg`. |
| `flyctl logs --app <app>` | Lire les journaux d'une application distante. | Les erreurs PHP, SQL ou MongoDB peuvent être diagnostiquées. |
| `flyctl ssh console --app <app> --command '<commande>'` | Exécuter un contrôle ponctuel dans une machine Fly. | Le résultat confirme l'état interne sans rendre le service public. |
| `flyctl ssh sftp put <local> <distant> --app <app>` | Transférer un fichier d'administration vers une machine. | Le fichier est envoyé par le tunnel privé Fly SSH. |
| `flyctl machine restart <id> --app <app>` | Redémarrer une machine pour vérifier son retour en service. | Le contrôle de santé repasse à l'état valide. |
| `flyctl tokens create deploy --app <app>` | Créer un jeton limité au déploiement d'une application. | Le jeton peut être enregistré dans `FLY_API_TOKEN` sur GitHub. |

## Base de données et persistance

| Commande | Rôle | Résultat attendu |
| --- | --- | --- |
| `mysqldump --no-tablespaces --single-transaction ...` | Exporter MySQL de manière cohérente sans bloquer les tables. | Un fichier SQL restaurable est produit avant une opération sensible. |
| `mysql --default-character-set=utf8mb4 ... < scripts/schema.sql` | Créer le schéma relationnel avec l'encodage attendu. | Les tables, contraintes et index sont créés. |
| `mysql --default-character-set=utf8mb4 ... < scripts/initialise.sql` | Charger le jeu de démonstration. | Les comptes, prospects, événements et devis fictifs sont disponibles. |
| `sha256sum <fichier>` | Calculer l'empreinte d'un fichier. | Deux empreintes identiques avant et après redémarrage prouvent la persistance. |

## Contrôles HTTP avec PowerShell

| Commande | Rôle | Résultat attendu |
| --- | --- | --- |
| `Invoke-WebRequest -Uri 'https://…' -UseBasicParsing` | Appeler une page HTTPS sans navigateur. | Le serveur répond avec le statut HTTP `200`. |
| `New-Object Microsoft.PowerShell.Commands.WebRequestSession` | Conserver les cookies entre plusieurs requêtes. | Un scénario de connexion peut être testé automatiquement. |
| `[regex]::Match($page.Content, '…')` | Extraire une valeur précise, par exemple le jeton CSRF. | Le formulaire peut être soumis avec sa protection CSRF active. |

## Lecture des résultats

Un code de sortie `0` indique généralement une commande réussie. Un autre code
doit être analysé avec le texte affiché : sous Windows, `flyctl ssh` peut par
exemple signaler `The handle is invalid` lors de la fermeture du terminal alors
que la commande distante a déjà confirmé son succès. La preuve retenue associe
donc le code de sortie, le message métier attendu, l'état du service et un
contrôle fonctionnel depuis l'application.

Les mots de passe, jetons API, URI contenant des identifiants et fichiers `.env`
ne doivent jamais figurer dans une capture, un commit ou une annexe.
