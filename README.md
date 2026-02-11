Voici ton fichier `README.md` mis en forme. Tu peux copier-coller ce bloc directement à la racine de ton projet sur la branche **dev**.

---

# 📅 Innov'Events Manager

**Innov'Events Manager** est une application web et mobile conçue pour centraliser et sécuriser la gestion événementielle de l'agence Innov'Events. Ce projet vise à remplacer un système obsolète basé sur des fichiers Excel et Word par une **source unique de vérité** afin de garantir la fiabilité des données et d'automatiser les tâches répétitives.

---

## 🚀 Installation et exécution en local

Pour exécuter cette application sur votre machine, suivez les étapes ci-dessous :

### Prérequis

*
**Docker** et **Docker Compose** installés sur votre système.


* Un client **Git**.



### Procédure de lancement

1. **Cloner le dépôt :**
```bash
git clone https://github.com/RomainRemusat/Innov-Events-Manager.git
cd Innov-Events-Manager

```


2. **Lancer les conteneurs :**
```bash
docker-compose up -d --build

```


3. **Accès aux services :**
*
**Application Web :** `http://localhost:8080`.


*
**Base de données SQL :** `localhost:3306`.


*
**Base NoSQL (Logs) :** `localhost:27017`.





---

## 🛠️ Stack Technique

*
**Conteneurisation :** Docker & Docker Compose.


*
**Base de données relationnelle :** SQL (MySQL/PostgreSQL) pour les données métier (Clients, Événements, Devis).


*
**Base de données NoSQL :** MongoDB pour la journalisation des actions sensibles (Logs).


*
**Langages suggérés :** PHP (PDO) / JS.



---

## 🌿 Gestion des versions (Workflow Git)

Ce projet respecte une stratégie de branches stricte pour garantir la stabilité du code:

*
**main :** Branche de production contenant uniquement du code testé et validé.


* **dev :** Branche principale de développement. Toutes les fonctionnalités partent de cette branche.


*
**Fusion :** Les modifications sont fusionnées de `dev` vers `main` uniquement après validation des tests.


*
**Commits :** Chaque commit est fréquent et accompagné d'un message clair décrivant les changements.



---

## 🔐 Fonctionnalités Clés

*
**Gestion des Prospects :** Formulaire de demande de devis et conversion automatique en client.


*
**Gestion Commerciale :** Création de prestations et génération automatique de devis en PDF.


*
**Espace Administrateur :** Dashboard avec indicateurs clés et gestion des employés.


*
**Journalisation NoSQL :** Traçabilité complète des actions sensibles (connexions, CRUD client, statuts).


*
**Conformité :** Respect du RGPD (données personnelles) et du RGAA (accessibilité).



---

## 🧪 Tests

Pour garantir la qualité du code, les suites de tests suivantes sont intégrées:

*
**Tests Unitaires :** Validation des composants métier.


*
**Tests Fonctionnels & E2E :** Vérification des parcours utilisateurs critiques.


*
**Couverture de code :** Un rapport de couverture est disponible dans la documentation technique.
