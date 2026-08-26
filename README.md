# 📅 Innov'Events Manager 🚀

**Innov'Events Manager** est une solution web et mobile B2B de gestion événementielle conçue pour l'agence Innov'Events. Ce projet vise à remplacer un système obsolète basé sur des fichiers Excel et Word dispersés par une **source unique de vérité**, garantissant la fiabilité des données et l'automatisation du tunnel commercial (prospects, devis PDF, suivi de projets).

Ce dépôt constitue le projet d'**Évaluation en Cours de Formation (ECF)** pour le titre professionnel **Concepteur Développeur d'Applications (CDA)** (École Studi).

---

## 🛠️ Stack Technique

* **Infrastructure & Conteneurisation :** Docker & Docker Compose (Multi-conteneurs avec persistance par volumes).
* **Back-end :** PHP 8.2 (Architecture MVC POO maison, Front Controller `index.php`).
* **Bases de données :**
   * **Relationnelle (MySQL 8.0) :** Données métiers (Users, Prospects, Clients, Événements, Devis).
   * **NoSQL (MongoDB) :** Journalisation d'audit de sécurité et traçabilité immuable (Logs).
* **Services Auxiliaires :** MailHog (Serveur SMTP local d'interception d'e-mails), DomPDF (Génération dynamique de devis PDF).
* **Front-end :** HTML5, CSS3, Bootstrap 5 (Responsive Design, Bootstrap Icons).

---

## 🔐 Fonctionnalités Clés incluses (MVP Sprint 1)

* **Architecture MVC & Front Controller :** Routage centralisé via `index.php` et séparation stricte des couches logiques.
* **Acquisition de Leads :** Interface publique avec formulaire de demande de devis dynamique, reliée à la base de données métier complète (Gestion des budgets, jauges et dates).
* **Authentification et Back-Office :** Espace d'administration sécurisé (`AuthController` & `DashboardController`) avec gestion des variables de sessions et protection des routes par *Guard Clauses*.
* **Sécurisation Globale (Defense in Depth) :**
   * Programmation défensive et typage strict.
   * Requêtes préparées avec PDO (Protection contre les Injections SQL).
   * Nettoyage en entrée et échappement en sortie (`htmlspecialchars`) contre les failles XSS.

---

## 🚀 Installation et Exécution en Local

### Prérequis
* [Docker Desktop](https://www.docker.com/products/docker-desktop/) (avec Docker Compose v2).
* Un client [Git](https://git-scm.com/).

### Procédure de Lancement Pas à Pas

1. **Cloner le dépôt et basculer sur la branche de développement :**
   ```bash
   git clone https://github.com/RomainRemusat/Innov-Events-Manager.git
   cd Innov-Events-Manager
   git checkout dev
   ```

2. **Configurer l'environnement local :**
   Dupliquez le modèle de configuration pour générer votre fichier d'environnement local `.env` :
   * *Sous Bash / macOS / Linux :*
     ```bash
     cp .env.example.php
     ```
   * *Sous PowerShell (Windows) :*
     ```powershell
     Copy-Item .env.example.php
     ```

3. **Lancer les conteneurs Docker :**
   ```bash
   docker-compose up -d --build
   ```

4. **Installer les dépendances Composer (DomPDF, PHPMailer) :**
   ```bash
   docker-compose exec app composer install
   ```

5. **Initialiser le Schéma SQL et le Jeu d'Essai :**
   Accédez à **phpMyAdmin** sur `http://localhost:8082` (Serveur: `db`, Utilisateur: `root`, Mot de passe: `root_password`).
   Dans la base `innovevents_db`, importez le script d'initialisation situé dans :
   `scripts/test_data.sql`

---

## 🌐 Cartographie des Services et Ports

| Service | Rôle | URL / Port d'Accès |
| :--- | :--- | :--- |
| **Application Web** | Interface Publique & Back-Office | [http://localhost:8081](http://localhost:8081) |
| **Back-Office Login** | Authentification Sécurisée | [http://localhost:8081/index.php?action=login](http://localhost:8081/index.php?action=login) |
| **phpMyAdmin** | Gestionnaire BDD MySQL | [http://localhost:8082](http://localhost:8082) |
| **MailHog** | Capture d'emails locaux | [http://localhost:8025](http://localhost:8025) |
| **MySQL 8** | BDD Relationnelle | `localhost:3306` |
| **MongoDB** | BDD Logs NoSQL | `localhost:27017` |

---

## 🔐 Comptes de Démonstration (Jeu d'Essai)

Les comptes suivants sont pré-initialisés via le script `scripts/test_data.sql` pour tester les différents niveaux d'habilitation :

| Rôle | Adresse Email | Mot de Passe Local |
| :--- | :--- |:-------------------|
| **Administratrice (Chloé)** | `chloe@innovevents.fr` | `password`         |
| **Employé (José)** | `jose@innovevents.fr` | `password`    |
| **Client Test** | `client@luxe.com` | `Client@1234!`     |

---

## 🌿 Gestion des Versions (Workflow Git)

Ce projet applique les bonnes pratiques **GitFlow** :
* `main` : Branche de production stable.
* `dev` : Branche d'intégration continue des développements.
* `feature/*` : Branches isolées pour le développement atomique des fonctionnalités (ex: `feature/csrf-security`).
* **Commits Sémantiques :** Messages préfixés par `feat:`, `fix:`, `docs:`, `refactor:`.

---

## 🛡️ Sécurité et Conformité RGPD Implémentées

* **Protections Web :** Requêtes préparées PDO contre les injections SQL, échappement systématique (`htmlspecialchars`) contre les failles XSS, jetons Anti-CSRF sur les formulaires sensibles, et régénération de session (`session_regenerate_id`).
* **Hachage des Mots de Passe :** Utilisation de l'algorithme fort `BCRYPT` via `password_hash()`.
* **Audits & Traçabilité :** Journalisation des connexions et des actions critiques dans MongoDB avec respect du principe de minimisation des données (RGPD).

---

## 🧪 Tests et Qualité

* **Tests Unitaires & Fonctionnels :** Suites PHPUnit pour la validation des composants métiers.
* **Tests E2E :** Validation des scénarios critiques d'authentification et de création de devis.
* **Couverture de Code :** Génération des rapports dans le dossier `docs/coverage`.
