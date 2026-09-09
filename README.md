**Innov’Events Manager**

Projet en cours de développement pour l’ECF du titre professionnel Concepteur
Développeur d’Applications (Studi). L’objectif est de centraliser les prospects,
clients, événements et devis de l’agence Innov’Events.

État documenté au **9 septembre 2026** : l’application web et les scripts SQL
existent, mais des corrections fonctionnelles et de sécurité restent nécessaires.
L’application mobile et le déploiement en ligne ne sont pas encore livrés dans
ce dépôt.

**Technologies présentes**

| Élément | Implémentation actuelle |
| --- | --- |
| Serveur web | PHP 8.2 / Apache, image `php:8.2-apache` |
| Organisation | Point d’entrée `public/index.php`, contrôleurs, modèles, vues et services PHP |
| SQL | MySQL 8.0 : `companies`, `users`, `prospects`, `devis`, `events`, `prestations`, `notes` |
| NoSQL | MongoDB pour les journaux d’actions ; connexion et affichage des journaux vérifiés |
| Interface | HTML, CSS et Bootstrap 5 ; aucun SCSS trouvé dans le dépôt |
| PDF | Dompdf |
| Emails | PHPMailer vers MailHog pour une partie des envois ; certains appels `mail()` restent à corriger |
| Environnement local | Docker Compose : application, MySQL, MongoDB, MailHog et phpMyAdmin |

Les tâches et les avis ne disposent pas encore de tables ou de parcours complets.
Le stockage MongoDB ne garantit pas, à lui seul, l’immutabilité des journaux.

**Installation locale**

Prérequis : Git et Docker avec le moteur démarré et la commande `docker compose`
disponible. PHP et Composer sont fournis dans le conteneur applicatif. Python 3
est nécessaire uniquement pour les tests SQL.

1. Cloner le dépôt et sélectionner la branche à tester :

```bash
git clone https://github.com/RomainRemusat/Innov-Events-Manager.git
cd Innov-Events-Manager
git checkout feature/evenement
```

Cette documentation décrit la branche `feature/evenement` en cours de correction.
Les branches `dev` et `main` existent, mais ne contiennent pas nécessairement les
mêmes modifications. Les corrections doivent être intégrées après vérification.

2. Créer `.env` à partir du fichier réellement présent dans le dépôt, sans écraser
une configuration locale existante.

Sous Linux/macOS ou Git Bash :

```bash
cp .env.example.php .env
```

Sous PowerShell :

```powershell
Copy-Item .env.example.php .env
```

Malgré son extension `.php`, ce modèle contient des lignes `CLE=valeur`, pas du
code PHP. Pour l’installation actuelle, conserver les paramètres SQL suivants :

```dotenv
DB_HOST=db
DB_PORT=3306
DB_NAME=innovevents_db
DB_USER=root
DB_PASS=root_password
```

Compose utilise `.env` pour sa configuration. En revanche, les identifiants SQL
de `src/config/Database.php` et la connexion MailHog de `MailService.php` sont
encore codés en dur. Modifier uniquement `.env` ne reconfigure donc pas toute
l’application. Les variables MongoDB d’authentification du modèle ne sont pas
transmises au service MongoDB par le Compose actuel.

3. Construire et démarrer les services, puis installer les dépendances :

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose ps
```

Attendre que MySQL accepte les connexions avant d’importer le SQL :

```bash
docker compose exec -T db mysql -uroot -proot_password -e 'SELECT 1'
```

Si la base n’est pas encore prête, attendre puis relancer cette commande.

4. Initialiser **uniquement une base vide**, avec le schéma puis le jeu d’essai.
Sous Linux/macOS ou Git Bash :

```bash
docker compose exec -T db mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < scripts/schema.sql
docker compose exec -T db mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < scripts/initialise.sql
```

Sous PowerShell :

```powershell
docker compose cp scripts/schema.sql db:/tmp/innovevents-schema.sql
docker compose exec -T db sh -c 'exec mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < /tmp/innovevents-schema.sql'
# Continuer uniquement si le schéma a été importé sans erreur.
docker compose cp scripts/initialise.sql db:/tmp/innovevents-initialise.sql
docker compose exec -T db sh -c 'exec mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < /tmp/innovevents-initialise.sql'
```

Pour une base existante, suivre [le guide des scripts SQL](scripts/README.md).
`schema.sql` refuse les tables déjà présentes ; `initialise.sql` ne doit pas être
rejoué sur les données de travail. Les volumes SQL et MongoDB persistent après
un arrêt normal des services.

**Services locaux**

Ports avec la configuration fournie :

| Service | Adresse |
| --- | --- |
| Application web | http://localhost:8081 |
| Connexion | http://localhost:8081/index.php?action=login |
| phpMyAdmin | http://localhost:8082 |
| Interface MailHog | http://localhost:8025 |
| SMTP MailHog | `localhost:1025` depuis l’hôte ; `mailhog:1025` depuis PHP |
| MySQL | `localhost:3306` depuis l’hôte ; `db:3306` depuis PHP |
| MongoDB | `localhost:27017` depuis l’hôte ; `mongodb:27017` depuis PHP |

MailHog capture les emails localement : ils ne sont pas distribués aux véritables
boîtes des destinataires. Cette configuration est destinée au développement.

**Comptes du jeu d’essai**

Les mots de passe ci-dessous ont été vérifiés avec `password_verify()` sur les
hashes de [scripts/initialise.sql](scripts/initialise.sql).

| Rôle | Email | Mot de passe du jeu d’essai |
| --- | --- | --- |
| Administratrice — Chloé | `chloe@innovevents.fr` | `Password123!` |
| Employé — José | `jose@innovevents.fr` | `Password123!` |
| Cliente — Alice | `client@luxe.com` | `Password123!` |
| Cliente — Amandine | `a.legrand@nextgen.io` | `Password123!` |

Ces identifiants concernent une base alimentée avec ce jeu d’essai. Une base
existante peut contenir des mots de passe modifiés. Le jeu d’essai contient
uniquement ces quatre comptes : un administrateur, un employé et deux clientes.
Alice et Amandine ont chacune leurs dossiers, pour tester notamment l’interdiction
d’accès aux devis d’une autre cliente.

`Password123!` respecte la règle de l’ECF (p. 5) : au moins 8 caractères,
une majuscule, une minuscule, un chiffre et un caractère spécial. Il est stocké
sous forme de hash bcrypt, avec un sel distinct pour chaque compte.

Ces quatre comptes sont préconfigurés pour la démonstration. Les mots de passe
temporaires issus d’un oubli (p. 6) ou de la création automatique d’un compte
client (p. 10) imposent un changement à la première connexion, via
`must_change_password = 1`. Le jeu d’essai utilise `0` pour les comptes déjà prêts.

**État des fonctionnalités et livrables**

| Domaine | État vérifié / travail restant |
| --- | --- |
| Docker local | Cinq services démarrés lors de l’audit ; build de production et configuration par environnement à finaliser |
| SQL | Création manuelle, données de démonstration et mises à jour alignées sur l’export du 09/09/2026 ; tests de migration réussis |
| Événements publics | Liste, détail, filtres dates/type/thème ; brouillons exclus et montants commerciaux non affichés |
| Inscription et connexion | Connexion des quatre comptes, session régénérée, redirection et journal MongoDB vérifiés ; inscription et mot de passe temporaire présents, contrôles de sécurité et retour à l’action initiale à compléter |
| Demande de devis | Formulaire et insertion présents, appel de journalisation corrigé ; le PHP force encore `en attente` et ignore certaines validations |
| Conversion | Colonne `start_date` corrigée ; conversion avec compte existant et rollback vérifiés sur bases temporaires. Parcours HTTP, nouveau compte et image à compléter |
| Devis et PDF | Génération réservée à ADMIN et au client propriétaire, testée sur Docker ; envois et téléchargement client restent à corriger |
| Réponse client | Acceptation/refus/modification présents ; motif non imposé côté serveur, transitions et notifications à fiabiliser |
| Clients, événements et notes | Listes, fiches, indicateurs et certaines mutations présents ; mutations structurelles réservées à ADMIN ; consultation et ajout de notes événement conservés pour EMPLOYEE. CRUD et profil à compléter |
| Journalisation et sécurité | Appels de logs et affichage alignés sur le modèle MongoDB ; couverture des actions à compléter, autres permissions, téléchargement des PDF stockés et protections CSRF à corriger |
| Mobile, tâches, avis, contact | Parcours dédiés non réalisés ; le dossier mobile est vide |
| Mentions légales, CGU et CGV | Pages manquantes ; les liens légaux existants ne suffisent pas |
| Accessibilité | Éléments sémantiques et responsive présents ; recette clavier/visuelle et corrections encore nécessaires, conformité RGAA non établie |
| Conception | Charte, trois wireframes et trois mockups web, MCD et diagrammes présents ; modèles à actualiser, maquettes mobile et schéma d’architecture complet à fournir |
| Tests applicatifs | Contrôles SQL, politique des mots de passe et connexion/journalisation disponibles ; couverture du parcours commercial aux trois niveaux et rapport de couverture encore à réaliser |
| CI/CD et production | Aucun pipeline ni déploiement en ligne documenté dans le dépôt ; hébergeur à choisir/configurer |
| Documentation utilisateur et veille | À compléter |

Les tests et la présence de mécanismes de sécurité ne constituent pas une
certification globale de conformité OWASP, RGPD ou RGAA.

**Vérifications disponibles**

Vérification des règles de mot de passe et des hashes du jeu d’essai (sans base) :

```bash
docker compose exec -T app php tests/password_policy.php
```

Vérification SQL :

```bash
python tests/sql_migrations.py
```

Ce contrôle utilise un MySQL Docker temporaire sans connexion à la base de travail.
Il vérifie l’installation vierge, le refus de réinitialisation, deux passages des
migrations, la conservation des données et les contraintes. Une option `--export`
permet de vérifier une sauvegarde locale ; voir [le guide SQL](scripts/README.md).
Ces tests ne calculent pas la couverture PHP et ne remplacent pas les tests E2E.

Vérification HTTP des quatre connexions et de leurs journaux MongoDB, sur le Docker
local démarré et les comptes de démonstration :

```bash
python tests/login_logging.py
```

Ce contrôle ouvre puis ferme ses sessions, vérifie les redirections et lit les
journaux dans MongoDB et l’interface admin. Il ne modifie pas les données SQL,
mais conserve les journaux de connexion normaux produits pendant le test.

Vérification du service de conversion sur des bases SQL et MongoDB temporaires :

```bash
docker compose exec -T app php tests/conversion.php
```

Ce test utilise les services Docker locaux et crée des bases au nom aléatoire,
supprimées en fin d’exécution. Il vérifie la date de début, les liens métier, le
devis, le journal et le rollback. Il utilise un client existant, sans envoyer de mail.

Vérification des accès directs aux routes prospects (réservées à ADMIN) :

```bash
python tests/prospect_access.py
```

Sur Docker local avec les quatre comptes de démonstration, ce test vérifie les
lectures et POST des visiteurs, clients, employé et administrateur. Il crée puis
supprime deux prospects synthétiques ; les dossiers existants restent inchangés.
Les journaux normaux produits par le test sont conservés. Aucun mail n’est envoyé.

Vérification des autorisations de génération PDF :

```bash
python -B tests/pdf_access.py
```

Le test utilise les quatre comptes de démonstration et un devis existant par cliente.
Il vérifie les PDF autorisés et le refus des accès directs aux devis d’autrui, ainsi
que les accès visiteur, employé et administrateur. Aucune écriture SQL, aucun mail
ni sauvegarde de PDF ; les journaux de connexion sont conservés.

Vérification des permissions employé et administrateur :

```bash
python -B tests/staff_permissions.py
```

Conversion, modification/suppression de client, prestations, envoi de devis,
statut et image d’événement sont réservés à ADMIN. EMPLOYEE conserve la consultation
des clients/événements et l’ajout de notes sur un événement. Les notes globales sont
réservées à ADMIN. Son dashboard redirige vers la liste des événements.

Le test crée des dossiers synthétiques sur le Docker local et contrôle les refus
par accès direct, les lectures et notes employé, puis les mutations administrateur,
y compris un téléversement PNG. Il nettoie les dossiers, le PNG et le PDF créés.
Les journaux et l’email de test envoyé à MailHog restent disponibles localement.
Les protections CSRF manquantes et les autres fonctionnalités ECF restent à compléter.

**Git et suivi du projet**

Les branches `main`, `dev` et `feature/*` sont présentes. Le workflow visé est de
partir de `dev`, développer et tester une fonctionnalité, puis intégrer vers `dev`
et ensuite `main`. La présence d’une branche `main` ne prouve pas un déploiement
ou une validation automatique : la CI/CD reste à mettre en place.

Conventions de messages utilisées : `feat`, `fix`, `refactor`, `docs` et `test`.
Des captures Trello existent dans `docs/element_graphique/`. Le lien partagé et
l’état actuel du Kanban restent à documenter. Les colonnes prévues sont Backlog,
Sprint, En cours, Terminé sur dev et, facultativement, Intégré à main.

**Documents**

- [Guide SQL : installation, migrations et tests](scripts/README.md)

Les documents Word et les visuels de conception se trouvent dans `docs/`.
Ils doivent être synchronisés avec la version finale de l’application.
