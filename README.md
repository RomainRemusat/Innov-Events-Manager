```markdown
# 📅 Innov'Events Manager

[cite_start]**Innov'Events Manager** est une application web et mobile conçue pour centraliser et sécuriser la gestion événementielle de l'agence Innov'Events[cite: 369]. [cite_start]Ce projet vise à remplacer un système obsolète basé sur des fichiers Excel et Word par une **source unique de vérité** afin de garantir la fiabilité des données et d'automatiser les tâches répétitives[cite: 98, 110, 111].

---

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

2. **Lancer les conteneurs :**

```bash
docker-compose up -d --build

```

3. **Importer la base de données (Initialisation du jeu d'essai) :**
   Une fois les conteneurs démarrés, exécutez la commande suivante pour injecter la structure SQL et les données de test :

```bash
docker exec -i innoveventmanager-db-1 mysql -u root -proot_password innovevents_db < database.sql

```

4. **Accès aux services :**

* **Application Web :** `http://localhost:8081` *(Port 8081 configuré pour éviter les conflits locaux)*
* **Base de données SQL (MySQL) :** `localhost:3306`
* **Base NoSQL (MongoDB - Logs) :** `localhost:27017`

---

## 🛠️ Stack Technique

* **Conteneurisation :** Docker & Docker Compose.


* **Architecture Logicielle :** Architecture multicouche organisée en modèle MVC (Modèle-Vue-Contrôleur) en PHP POO.


* **Base de données relationnelle :** MySQL 8.0 pour les données métier (Clients, Événements, Devis).


* **Base de données NoSQL :** MongoDB pour la journalisation des actions sensibles (Logs de sécurité).



---

## 🌿 Gestion des versions (Workflow Git)

Ce projet respecte une stratégie de branches stricte pour garantir la stabilité du code:

* **main :** Branche de production contenant uniquement du code testé et validé.


* **dev :** Branche principale de développement. Toutes les fonctionnalités partent de cette branche.


* **Fusion :** Les modifications sont fusionnées de `dev` vers `main` uniquement après validation des tests.


* **Commits :** Chaque commit est fréquent et accompagné d'un message clair décrivant les changements.



---

## 🔐 Fonctionnalités Clés incluses (V1)

* **Architecture MVC & POO :** Séparation stricte de la logique métier (Contrôleurs), de l'accès aux données (Modèles PDO) et des interfaces (Vues).


* **Gestion des Prospects :** Interface publique responsive (Bootstrap) avec formulaire de demande de devis fonctionnel relié à la base de données.


* **Sécurisation des données :** Validation stricte des champs côté serveur, requêtes préparées avec PDO contre les injections SQL, et traitement contre les failles XSS.


* **Conformité :** Structure prête pour le respect du RGPD (anonymisation/IP) et du RGAA (accessibilité).



---

## 🧪 Tests

Pour garantir la qualité du code, les suites de tests suivantes sont intégrées:

* **Tests Unitaires :** Validation des composants métier.


* **Tests Fonctionnels & E2E :** Vérification des parcours utilisateurs critiques.


* **Couverture de code :** Un rapport de couverture sera disponible dans la documentation technique finale.

