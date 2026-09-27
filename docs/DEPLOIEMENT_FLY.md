# Déploiement de production sur Fly.io

Cette procédure publie Innov'Events Manager dans la région de Paris (`cdg`).
L'application, MySQL et MongoDB s'exécutent dans trois machines distinctes sur
le réseau privé Fly.io. Les données SQL, NoSQL, les images et les devis PDF sont
conservés dans des volumes persistants.

Déploiement vérifié le 27 septembre 2026 :

- application : <https://innov-events-manager-romain.fly.dev> ;
- région : Paris (`cdg`) ;
- trois machines actives et trois volumes chiffrés de 1 Go ;
- HTTPS forcé et contrôle HTTP opérationnel ;
- connexion PHP vers MySQL et journalisation MongoDB validées ;
- persistance contrôlée par comparaison SHA-256 avant et après redémarrage.

## Prérequis

- un compte Fly.io avec un moyen de paiement enregistré ;
- `flyctl` installé et connecté avec `flyctl auth login` ;
- les tests de la branche `dev` validés avant fusion dans `main` ;
- un compte SMTP réel, par exemple Brevo, pour les courriels de production.

Les noms d'applications présents dans les fichiers `fly.toml` sont globaux. S'ils
sont déjà utilisés, les remplacer dans les trois configurations avant de lancer
les commandes suivantes.

## 1. Créer MySQL et son volume

```powershell
flyctl apps create innov-events-mysql-romain
flyctl volumes create mysql_data --app innov-events-mysql-romain --region cdg --size 1
flyctl secrets set MYSQL_ROOT_PASSWORD="MOT_DE_PASSE_ROOT" MYSQL_PASSWORD="MOT_DE_PASSE_APPLICATION" --app innov-events-mysql-romain
flyctl deploy --config deploy/mysql/fly.toml --remote-only
```

Les scripts `scripts/schema.sql` et `scripts/initialise.sql` sont exécutés lors
de la première initialisation du volume. Une reconstruction ultérieure de la
machine ne réinitialise donc pas les données existantes.

Chaque script déclare explicitement `SET NAMES utf8mb4` car le point d'entrée
MySQL les exécute dans des connexions distinctes. Après la détection du mauvais
encodage pendant la recette de production, la base contenant uniquement des
données fictives a été sauvegardée puis réinitialisée depuis ces scripts. Un
contrôle HTTP du devis a confirmé l'absence des marqueurs `Ã` et `Â`.

## 2. Créer MongoDB et son volume

```powershell
flyctl apps create innov-events-mongodb-romain
flyctl volumes create mongo_data --app innov-events-mongodb-romain --region cdg --size 1
flyctl secrets set MONGO_INITDB_ROOT_USERNAME="innovevents" MONGO_INITDB_ROOT_PASSWORD="MOT_DE_PASSE_MONGO" --app innov-events-mongodb-romain
flyctl deploy --config deploy/mongodb/fly.toml --remote-only
```

MongoDB n'expose aucun port public. L'application le joint par l'adresse privée
`innov-events-mongodb-romain.internal`.

## 3. Créer et configurer l'application

```powershell
flyctl apps create innov-events-manager-romain
flyctl volumes create app_data --app innov-events-manager-romain --region cdg --size 1
flyctl secrets set DB_PASS="MOT_DE_PASSE_APPLICATION" --app innov-events-manager-romain
flyctl secrets set MONGO_URI="mongodb://innovevents:MOT_DE_PASSE_MONGO@innov-events-mongodb-romain.internal:27017/innovevents_nosql?authSource=admin" --app innov-events-manager-romain
flyctl secrets set SMTP_HOST="smtp-relay.brevo.com" SMTP_PORT="587" SMTP_AUTH="1" SMTP_TLS="1" SMTP_USER="IDENTIFIANT_SMTP" SMTP_PASS="CLE_SMTP" SMTP_FROM="ADRESSE_VALIDEE" --app innov-events-manager-romain
flyctl deploy --config fly.toml --remote-only
```

L'application devient accessible à l'adresse :
<https://innov-events-manager-romain.fly.dev>.

## 4. Contrôler le déploiement

```powershell
flyctl status --app innov-events-manager-romain
flyctl status --app innov-events-mysql-romain
flyctl status --app innov-events-mongodb-romain
flyctl logs --app innov-events-manager-romain
```

Le contrôle final comprend une connexion administrateur, la consultation des
événements, la création d'une demande de devis, la génération d'un PDF et la
présence du journal correspondant dans MongoDB. Une image téléversée et un PDF
doivent toujours être présents après un nouveau déploiement de l'application.

## 5. Déploiement continu

Créer un jeton limité au déploiement puis le stocker dans le secret GitHub
`FLY_API_TOKEN` :

```powershell
flyctl tokens create deploy --app innov-events-manager-romain
```

À chaque push sur `main`, le workflow `.github/workflows/ci.yml` exécute d'abord
la suite de tests. Le job de déploiement ne démarre que si tous les tests
réussissent. Son historique dans GitHub Actions constitue la preuve horodatée du
pipeline CI/CD.

Le secret ne doit jamais être copié dans le dépôt, un fichier de documentation
ou une capture d'écran.

## Sauvegarde et maîtrise du coût

Fly.io réalise des instantanés quotidiens des volumes, mais une exportation SQL
et MongoDB reste nécessaire avant une modification importante. Après l'examen,
les machines peuvent être arrêtées ou supprimées. Les volumes continuent à être
facturés tant qu'ils existent ; ils doivent également être supprimés si les
données ne sont plus nécessaires.
