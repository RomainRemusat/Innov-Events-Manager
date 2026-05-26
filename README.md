# 📅 Innov'Events Manager

**Innov'Events Manager** est une application web et mobile conçue pour centraliser et sécuriser la gestion événementielle de l'agence Innov'Events. Ce projet vise à remplacer un système obsolète basé sur des fichiers Excel et Word par une **source unique de vérité** afin de garantir la fiabilité des données et d'automatiser les tâches répétitives.


## 🚀 Installation et exécution en local

Pour exécuter cette application sur votre machine, suivez les étapes ci-dessous :

### Prérequis

* **Docker** et **Docker Compose** installés sur votre système.
* Un client **Git**.

### Procédure de lancement

1. **Cloner le dépôt :**
   ```bash
   git clone [https://github.com/RomainRemusat/Innov-Events-Manager.git](https://github.com/RomainRemusat/Innov-Events-Manager.git)
   cd Innov-Events-Manager

   ```

2. **Lancer les conteneurs (Infrastructure persistante) :**
   ```bash
   docker-compose up -d --build
   
   ```


*(Note : L'infrastructure gère automatiquement la persistance des données via des volumes Docker configurés pour MySQL et MongoDB).*
3. **Importer la base de données (Initialisation du jeu d'essai) :**
   L'accès à l'interface d'administration de la base de données se fait via **phpMyAdmin** :
   * **URL :** `http://localhost:8082`
   * **Serveur :** `db`
   * **Utilisateur :** `root`
   * **Mot de passe :** `root_password`


   *(Veuillez exécuter le script SQL fourni dans l'onglet SQL de phpMyAdmin pour générer le schéma métier complet `innovevents_db` et le compte d'administration par défaut).*

4. **Accès aux services :**
   * **Application Web Publique :** `http://localhost:8081`
   * **Accès Back-Office (Sécurisé) :** `http://localhost:8081/index.php?action=login`
   * **Interface phpMyAdmin :** `http://localhost:8082`
   * **Base de données SQL (MySQL) :** `localhost:3306`
   * **Base NoSQL (MongoDB - Logs) :** `localhost:27017`

---

## 🛠️ Stack Technique

* **Conteneurisation :** Docker & Docker Compose (avec gestion des volumes de persistance).
* **Architecture Logicielle :** Architecture multicouche organisée en modèle MVC (Modèle-Vue-Contrôleur) en PHP POO.
* **Base de données relationnelle :** MySQL 8.0 pour les données métier (Clients, Événements, Devis).
* **Base de données NoSQL :** MongoDB pour la journalisation des actions sensibles (Logs de sécurité).
* **Front-end :** HTML5, CSS3, Bootstrap 5 (Approche Mobile-First et respect RGAA).

---

## 🌿 Gestion des versions (Workflow Git)

Ce projet respecte une stratégie de branches stricte (GitFlow) pour garantir la stabilité du code :

* **main :** Branche de production contenant uniquement du code testé et validé.
* **dev :** Branche principale de développement. Toutes les fonctionnalités partent de cette branche.
* **feature/* :** Branches isolées pour le développement atomique des fonctionnalités (ex: `feature/dashboard`).
* **Fusion :** Les modifications sont fusionnées via Pull Request après validation.
* **Commits :** Utilisation des conventions de nommage sémantiques (`Feat:`, `Fix:`, `Docs:`).

---

## 🔐 Fonctionnalités Clés incluses (MVP Sprint 1)

* **Architecture MVC & Front Controller :** Routage centralisé via `index.php` et séparation stricte des couches logiques.
* **Acquisition de Leads :** Interface publique avec formulaire de demande de devis dynamique, reliée à la base de données métier complète (Gestion des budgets, jauges et dates).
* **Authentification et Back-Office :** Espace d'administration sécurisé (`AuthController` & `DashboardController`) avec gestion des variables de sessions et protection des routes par *Guard Clauses*.
* **Sécurisation Globale (Defense in Depth) :** * Programmation défensive et typage strict.
* Requêtes préparées avec PDO (Protection Injection SQL).
* Nettoyage en entrée et échappement en sortie (`htmlspecialchars`) contre les failles XSS.

---

## 🧪 Tests

Pour garantir la qualité du code, les suites de tests suivantes sont intégrées :

* **Tests Unitaires :** Validation des composants métier.
* **Tests Fonctionnels & E2E :** Vérification des parcours utilisateurs critiques.
* **Couverture de code :** Un rapport de couverture sera disponible dans la documentation technique finale.


Note pour le jury : L'architecture a été optimisée sur la branche dev (Sprint 2). Pour tester cette branche, merci de lancer docker-compose up -d --build après le checkout.
