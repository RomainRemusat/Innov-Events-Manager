# Innov'Events Manager

Application de gestion événementielle réalisée pour l'ECF du titre professionnel
Concepteur développeur d'applications. Elle centralise les prospects, clients,
événements, devis, tâches, avis et journaux d'audit d'Innov'Events.

## Fonctionnalités principales

- site public : événements, avis, contact, demande de devis et pages légales ;
- inscription, authentification, mot de passe oublié et changement obligatoire ;
- espace client : suivi et réponse aux devis, profil, suppression du compte et avis ;
- espace employé : consultation des clients et événements, notes, tâches et modération ;
- espace administrateur : prospects, clients, événements, devis, comptes et journaux ;
- génération et envoi des devis PDF ;
- PWA réservée au personnel : événements à venir, contacts et notes rapides ;
- journalisation des actions sensibles dans MongoDB.

## Architecture et technologies

| Élément | Technologie |
| --- | --- |
| Application | PHP 8.2, Apache, architecture MVC et services métier |
| Interface | HTML5, CSS3 et Bootstrap 5 |
| Base relationnelle | MySQL 8 et PDO |
| Journalisation | MongoDB |
| Courriels locaux | PHPMailer et MailHog |
| PDF | Dompdf |
| Environnement | Docker Compose |

Le point d'entrée HTTP est `public/index.php`. Les devis générés sont conservés
hors du dossier public dans `storage/devis/` et servis après contrôle des droits.

## Installation locale

Prérequis : Git, Docker et la commande `docker compose`.

```bash
git clone https://github.com/RomainRemusat/Innov-Events-Manager.git
cd Innov-Events-Manager
git switch main
```

Créer la configuration locale :

Sous Linux, macOS ou Git Bash :

```bash
cp .env.example .env
```

Sous PowerShell :

```powershell
Copy-Item .env.example .env
```

Démarrer les services et installer les dépendances :

```bash
docker compose up -d --build
docker compose exec -T app composer install
docker compose ps
```

Attendre que MySQL soit disponible :

```bash
docker compose exec -T db mysql -uroot -proot_password -e 'SELECT 1'
```

Initialiser une base vide sous Linux, macOS ou Git Bash :

```bash
docker compose exec -T db mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < scripts/schema.sql
docker compose exec -T db mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < scripts/initialise.sql
```

Sous PowerShell :

```powershell
docker compose cp scripts/schema.sql db:/tmp/schema.sql
docker compose exec -T db sh -c 'mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < /tmp/schema.sql'
docker compose cp scripts/initialise.sql db:/tmp/initialise.sql
docker compose exec -T db sh -c 'mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < /tmp/initialise.sql'
```

Ces deux scripts servent uniquement à une installation vierge. Pour une base
existante, suivre le [guide des migrations](scripts/README.md).

## Services locaux

| Service | Adresse |
| --- | --- |
| Application | http://localhost:8081 |
| PWA du personnel | http://localhost:8081/index.php?action=mobile_dashboard |
| MailHog | http://localhost:8025 |
| phpMyAdmin | http://localhost:8082 |
| MySQL | `localhost:3306` |
| MongoDB | `localhost:27017` |

Depuis un téléphone connecté au même réseau, remplacer `localhost` par l'adresse
IPv4 de l'ordinateur. MailHog intercepte les messages : aucun email local n'est
distribué à une boîte réelle.

## Comptes de démonstration

Le jeu de données de `scripts/initialise.sql` crée les comptes suivants :

| Rôle | Identifiant | Mot de passe |
| --- | --- | --- |
| Administratrice | `chloe@innovevents.fr` | `Password123!` |
| Employé | `jose@innovevents.fr` | `Password123!` |
| Cliente | `client@luxe.com` | `Password123!` |
| Cliente | `a.legrand@nextgen.io` | `Password123!` |

Ces identifiants sont réservés à la démonstration et ne doivent pas être utilisés
sur un environnement public.

## Tests automatisés

Les conteneurs doivent être démarrés et la base locale initialisée. Les scénarios
qui créent des données utilisent des dossiers synthétiques et les suppriment à la
fin de leur exécution.

Contrôles principaux :

```bash
docker compose exec -T app php tests/password_policy.php
docker compose exec -T app php tests/conversion.php
python -B tests/sql_migrations.py
python -B tests/login_logging.py
python -B tests/commercial_workflow.py
python -B tests/mobile_app.py
python -B tests/public_pages.py
python -B tests/reviews.py
python -B tests/web_finishing.py
```

Les autres scénarios de sécurité, de droits, d'erreurs d'écriture et de cycle de
vie se trouvent dans [`tests/`](tests/). La recette humaine est décrite dans
[`docs/RECETTE_WEB_2026-09-24.md`](docs/RECETTE_WEB_2026-09-24.md).

La CI exécute les tests à chaque push sur `dev` et `main`, ainsi que pour les pull
requests qui ciblent ces branches. Une livraison ne doit pas être effectuée si la
CI échoue.

## PWA mobile

La PWA est destinée aux comptes `ADMIN` et `EMPLOYEE`. Elle présente les
événements à venir, les coordonnées du client et les actions téléphone, email,
itinéraire et ajout de note. Le navigateur conserve le contexte mobile pendant
sa session afin qu'un changement de compte du personnel revienne dans la PWA.

Pour bénéficier de l'installation complète et du service worker hors de
`localhost`, l'application doit être servie en HTTPS.

## Git

- `main` : version validée destinée à la production ;
- `dev` : intégration des fonctionnalités testées ;
- `feature/*` : développement isolé.

Une fonctionnalité est intégrée dans `dev` après validation, puis dans `main`
pour préparer une livraison. Les messages de commit utilisent principalement
les préfixes `feat`, `fix`, `test`, `docs`, `refactor` et `chore`.

## Déploiement

Le dépôt fournit actuellement un environnement Docker local reproductible. Aucun
hébergeur public ni déploiement automatique n'est encore configuré. Une mise en
ligne nécessite au minimum :

- un nom de domaine et HTTPS ;
- des secrets SQL, MongoDB et SMTP propres à l'environnement ;
- un serveur SMTP réel ;
- des volumes persistants et une stratégie de sauvegarde ;
- l'exclusion des outils d'administration et des ports de données de l'accès public.

Cette section devra contenir l'URL et la procédure reproductible après le premier
déploiement vérifié sur l'hébergeur choisi.
