# Innov'Events Manager

Innov'Events Manager est une application web de gestion événementielle destinée à
l'agence Innov'Events. Elle centralise les prospects, les demandes de devis, les
comptes utilisateurs et la journalisation des actions afin de remplacer les fichiers
Excel et Word dispersés par une source unique de vérité.

Le dépôt correspond actuellement à un **MVP en cours de développement** réalisé dans
le cadre de l'ECF du titre Concepteur développeur d'applications. Les fonctionnalités
disponibles et celles restant à réaliser sont détaillées plus bas.

## Stack technique

- PHP 8.2 et Apache
- Architecture MVC avec point d'entrée unique `public/index.php`
- HTML5, CSS3, Bootstrap 5 et Bootstrap Icons
- MySQL 8 pour les données métier
- MongoDB pour les journaux d'audit
- PHPMailer et MailHog pour les e-mails locaux
- Dompdf pour la génération de devis PDF
- Docker et Docker Compose
- Composer pour les dépendances PHP

## Prérequis

- Git
- Docker Desktop avec Docker Compose v2
- Les ports `8081`, `8082`, `8025`, `1025`, `3306` et `27017` disponibles

PHP, Composer, MySQL et MongoDB n'ont pas besoin d'être installés directement sur la
machine lorsque l'application est exécutée avec Docker.

## Installation locale

### 1. Cloner le dépôt

```bash
git clone https://github.com/RomainRemusat/Innov-Events-Manager.git
cd InnovEventManager
```

Le développement courant se trouve sur la branche `dev` :

```bash
git switch dev
```

### 2. Créer le fichier d'environnement

Sous PowerShell :

```powershell
Copy-Item .env.example.php .env
```

Sous Linux ou macOS :

```bash
cp .env.example.php .env
```

Le nom `.env.example.php` est conservé dans le dépôt pour le moment, mais son contenu
est bien un modèle de variables d'environnement et non du code PHP. Le fichier `.env`
contient la configuration locale et ne doit jamais être versionné.

Valeurs locales par défaut :

| Variable | Valeur |
|---|---|
| `BASE_URL` | `http://localhost:8081` |
| `DB_HOST` | `db` |
| `DB_PORT` | `3306` |
| `DB_NAME` | `innovevents_db` |
| `DB_USER` | `root` |
| `DB_PASS` | `root_password` |
| `MONGO_URI` | `mongodb://mongodb:27017` |
| `MAIL_HOST` | `mailhog` |
| `MAIL_PORT` | `1025` |

Ces identifiants sont exclusivement destinés au développement local. Ils doivent être
remplacés par des secrets robustes dans tout environnement en ligne.

### 3. Construire et démarrer les conteneurs

```bash
docker compose up -d --build
```

Vérifier leur état :

```bash
docker compose ps
```

Services démarrés :

| Service Docker | Rôle | Accès depuis la machine hôte |
|---|---|---|
| `app` | PHP 8.2 et Apache | http://localhost:8081 |
| `db` | MySQL 8 | `localhost:3306` |
| `mongodb` | MongoDB | `localhost:27017` |
| `phpmyadmin` | Administration MySQL | http://localhost:8082 |
| `mailhog` | Capture des e-mails locaux | http://localhost:8025 |

### 4. Installer les dépendances PHP

Le dossier du projet est monté dans le conteneur. Installer les dépendances avec :

```bash
docker compose exec app composer install
```

### 5. Initialiser la base MySQL

Ouvrir http://localhost:8082 puis se connecter avec :

- serveur : `db`
- utilisateur : `root`
- mot de passe : `root_password`
- base : `innovevents_db`

Dans l'onglet **Importer**, sélectionner le fichier `scripts/test_data.sql`.

> **Attention :** `scripts/test_data.sql` supprime puis recrée les tables métier. Il
> convient à une installation initiale ou à la réinitialisation volontaire des données
> de démonstration. Ne pas l'exécuter sur une base contenant des données à conserver.

Les fichiers `scripts/update_users.sql` et `scripts/update_prospects.sql` sont des
scripts d'évolution ciblés. Ils ne doivent pas être rejoués sans vérifier au préalable
l'état du schéma.

### 6. Vérifier l'installation

- accueil : http://localhost:8081
- connexion : http://localhost:8081/index.php?action=login
- demande de devis : http://localhost:8081/index.php?action=devis
- boîte de réception MailHog : http://localhost:8025
- phpMyAdmin : http://localhost:8082

## Comptes de démonstration

Le script `scripts/test_data.sql` crée les comptes suivants :

| Rôle | Identifiant | Mot de passe local |
|---|---|---|
| Administratrice | `chloe@innovevents.fr` | `password` |
| Employé | `jose@innovevents.fr` | `password` |
| Client | `client@luxe.com` | `password` |

Ces comptes et mots de passe sont uniquement des données d'essai. Ils ne doivent pas
être utilisés en production.

## Commandes Docker utiles

Afficher les journaux de l'application :

```bash
docker compose logs -f app
```

Ouvrir un shell dans le conteneur PHP :

```bash
docker compose exec app bash
```

Arrêter les services sans effacer les données :

```bash
docker compose down
```

Reconstruire l'image après une modification du Dockerfile :

```bash
docker compose up -d --build
```

La suppression des volumes Docker efface les bases locales persistantes ; elle ne fait
donc pas partie de la procédure normale d'arrêt.

## Fonctionnalités actuellement implémentées

- page d'accueil responsive avec appels à l'action ;
- demande de devis publique et création d'un prospect en base ;
- message de confirmation et notification locale par e-mail ;
- inscription d'un client avec contrôle de la complexité du mot de passe ;
- connexion, déconnexion et gestion des rôles ;
- procédure de mot de passe oublié par e-mail ;
- changement obligatoire du mot de passe temporaire ;
- tableau de bord administrateur ;
- consultation de la liste et du détail des prospects ;
- modification du statut d'un prospect ;
- génération d'un devis PDF ;
- consultation et réponse à certains devis depuis l'espace client ;
- demande de suppression du compte client ;
- consultation des journaux MongoDB par l'administratrice ;
- jeu de données SQL comprenant utilisateurs, prospects, événements, devis,
  prestations et notes.

## Fonctionnalités restant à finaliser

Le MVP ne couvre pas encore l'intégralité du cahier des charges de l'ECF. Restent
notamment à réaliser ou à compléter :

- catalogue public et détail des événements avec filtres ;
- pages Avis, Contact, Mentions légales, CGU et CGV ;
- conversion complète d'un prospect en client et en événement ;
- gestion commerciale complète des prestations et contre-propositions ;
- envoi direct et téléchargement sécurisé des PDF ;
- CRUD des clients, événements, employés, tâches, notes et avis ;
- widgets complets des espaces client, employé et administrateur ;
- contrôles CSRF et durcissement des autorisations sur toutes les actions sensibles ;
- application mobile ;
- tests unitaires, fonctionnels, end-to-end et rapport de couverture ;
- pipeline CI/CD et déploiement automatique en production ;
- déploiement public et documentation utilisateur finale.

Cette liste évite de présenter comme terminées des fonctionnalités encore en cours de
développement.

## Architecture du dépôt

```text
InnovEventManager/
|-- public/                 Point d'entrée web, styles et futurs fichiers publics
|   `-- index.php           Routeur central de l'application
|-- src/
|   |-- config/             Connexions aux bases de données
|   |-- controllers/        Orchestration et règles applicatives
|   |-- models/
|   |   |-- sql/            Accès aux données métier MySQL
|   |   `-- nosql/          Accès aux journaux MongoDB
|   |-- services/           Services transverses, notamment les e-mails
|   `-- views/              Interfaces publiques, clientes et administratives
|-- scripts/                Création, données d'essai et évolutions SQL
|-- docs/                   Documentation, diagrammes et éléments graphiques
|-- mobile/                 Emplacement réservé à l'application mobile
|-- Dockerfile              Image PHP/Apache de l'application
|-- docker-compose.yml      Orchestration des services locaux
`-- composer.json           Dépendances PHP
```

## Données et sécurité

- Les requêtes SQL utilisent PDO et des requêtes préparées.
- Les mots de passe sont stockés sous forme de hash Bcrypt.
- Les sorties dynamiques doivent être échappées avec `htmlspecialchars`.
- Les actions d'audit sont enregistrées dans MongoDB.
- Les fichiers `.env`, les PDF générés et les fichiers téléversés sont ignorés par Git.
- MailHog intercepte les e-mails en local : aucun e-mail de test n'est remis à un vrai
  destinataire.

Ces protections constituent une base et ne remplacent pas l'audit de sécurité restant à
effectuer avant une mise en production, notamment pour les jetons CSRF, les permissions
par ressource, la limitation des tentatives de connexion et la conformité RGPD des IP.

## Tests et contrôles qualité

Il n'existe pas encore de suite PHPUnit ou end-to-end intégrée au dépôt. Ne pas annoncer
de pourcentage de couverture tant qu'un rapport reproductible n'a pas été généré.

Contrôle syntaxique manuel de tous les fichiers PHP, depuis PowerShell :

```powershell
$files = rg --files -g '*.php' -g '!vendor/**'
foreach ($file in $files) { php -l $file }
```

Ce contrôle valide uniquement la syntaxe ; il ne remplace pas les tests unitaires,
fonctionnels, de sécurité et end-to-end demandés par l'ECF.

## Gestion des versions

Le projet suit une organisation inspirée de GitFlow :

- `main` : version stable destinée à la production ;
- `dev` : branche d'intégration des développements ;
- `feature/*` : développement isolé d'une fonctionnalité.

Workflow attendu :

1. créer une branche `feature/nom-fonctionnalite` depuis `dev` ;
2. réaliser des commits fréquents avec des messages explicites ;
3. tester la fonctionnalité ;
4. fusionner la branche de fonctionnalité dans `dev` ;
5. après validation globale, fusionner `dev` dans `main`.

Exemples de messages : `feat(auth): ajoute la réinitialisation du mot de passe`,
`fix(pdf): corrige le montant du devis` ou `docs(readme): précise l'installation`.

## Documentation du projet

Le dossier `docs/` contient actuellement :

- la documentation technique ;
- la charte graphique et UX/UI ;
- le MCD ;
- un diagramme de cas d'utilisation ;
- un diagramme de séquence ;
- des wireframes et captures relatives à Docker, Git/Kanban et aux e-mails.

Ces documents doivent rester synchronisés avec le code et le schéma SQL au fil du
développement.

## Dépannage

### Un port est déjà utilisé

Modifier uniquement le port situé à gauche dans `docker-compose.yml`, par exemple
`8083:80`, puis adapter `BASE_URL` dans `.env`.

### L'application ne se connecte pas à MySQL

Vérifier que `DB_HOST=db` dans `.env` et que le conteneur est démarré :

```bash
docker compose ps
docker compose logs db
```

### Les e-mails n'apparaissent pas

Vérifier que MailHog fonctionne, puis consulter http://localhost:8025 :

```bash
docker compose logs mailhog
```

### Une classe PHP est introuvable

Réinstaller les dépendances :

```bash
docker compose exec app composer install
```

## Licence et contexte

Projet pédagogique réalisé pour une évaluation en cours de formation. Aucun fichier de
licence de diffusion n'est actuellement fourni ; le code ne doit donc pas être considéré
comme librement réutilisable sans l'autorisation de son auteur.
