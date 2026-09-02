# 📅 Innov'Events Manager 🚀

Innov'Events Manager est une solution logicielle sécurisée (Web et Mobile) organisée en couches, conçue pour l'agence événementielle B2B Innov'Events. Ce projet remplace un système obsolète et dispersé (fichiers Word, classeurs Excel CRM non synchronisés) par une source unique de vérité centralisée, automatisant le tunnel commercial (prospects, devis PDF) et fiabilisant la gestion opérationnelle des événements.

Ce dépôt constitue le livrable technique d'évaluation en cours de formation (ECF) pour le titre professionnel **Concepteur Développeur d'Applications (CDA)** (Niveau 6 - École Studi).

---

## 🛠️ Stack Technique

* **Infrastructure & Conteneurisation :** Docker & Docker Compose v2 (isolation multi-conteneurs étanches, volumes de persistance).
* **Back-end :** PHP 8.2+ (Architecture multicouche MVC, POO stricte, Front Controller `index.php`).
* **Persistance Polyglotte :**
* **Base relationnelle (MySQL 8.0) :** Entités structurées (Users, Prospects, Devis, Prestations, Events, Notes, Tasks) conformes à la 3NF et à l'intégrité référentielle.
* **Base NoSQL orientée documents (MongoDB) :** Journalisation d'audit immuable (`logs`) pour la traçabilité des opérations sensibles.


* **Services Auxiliaires :** MailHog (capture locale des flux SMTP), Dompdf (compilation dynamique des propositions commerciales en PDF).
* **Front-end Web :** HTML5 sémantique (accessibilité RGAA), CSS3 / SCSS, Bootstrap 5 (Responsive Web Design).
* **Application Mobile :** Interface mobile conteneurisée sous Docker optimisée pour la consultation terrain et le déclenchement d'actions en un clic (Appels, Emails, Itinéraires).

---

## 🌐 Cartographie des Services et Ports Locaux

| Service | Rôle et Périmètre | Point d'Entrée / Port |
| --- | --- | --- |
| **Application Web** | Vitrine publique, Espace Client & Back-Office Staff | http://localhost:8081 |
| **Authentification** | Formulaire de connexion sécurisé multi-rôles | http://localhost:8081/index.php?action=login |
| **phpMyAdmin** | Administration visuelle de la base MySQL | http://localhost:8082 |
| **MailHog** | Capture et inspection des courriels sortants | http://localhost:8025 |
| **MySQL 8.0** | Serveur SQL relationnel | localhost:3306 |
| **MongoDB** | Serveur NoSQL documentaire (Audit logs) | localhost:27017 |

---

## 🚀 Installation et Démarrage en Local

### Prérequis Système

* Docker Desktop (moteur Docker v24+ avec Compose v2).
* Un client Git.

### Procédure de Déploiement Local

1. **Cloner le dépôt et basculer sur la branche de développement :**
```bash
git clone https://github.com/RomainRemusat/Innov-Events-Manager.git
cd Innov-Events-Manager
git checkout dev

```


2. **Créer le fichier de variables d'environnement :**
* *Linux / macOS / Git Bash :*
```bash
cp .env.example .env

```


* *PowerShell (Windows) :*
```powershell
Copy-Item .env.example .env

```




3. **Construire et lancer l'infrastructure Docker :**
```bash
docker compose up -d --build

```


4. **Installer les dépendances logicielles (Composer) :**
```bash
docker compose exec app composer install

```


5. **Initialiser les bases de données (Script manuel & Jeu d'essai) :**
```bash
docker compose exec -T db mysql -u root -proot_password innovevents_db < scripts/schema.sql
docker compose exec -T db mysql -u root -proot_password innovevents_db < scripts/test_data.sql

```



---

## 🔐 Comptes de Démonstration (Jeu d'Essai)

| Rôle Métier | Identifiant (Email) | Mot de Passe Local | Périmètre Applicatif |
| --- | --- | --- | --- |
| **Administratrice (Chloé)** | chloe@innovevents.fr | Password123! | Pilotage global, conversion prospects, génération devis, logs NoSQL, gestion d'équipe |
| **Employé (José)** | jose@innovevents.fr | Password123! | Suivi des projets, gestion des tâches opérationnelles, notes de terrain |
| **Client B2B** | client@luxe.com | Password123! | Espace client, arbitrage des devis (acceptation, refus, demande de modification) |

---

## 🌿 Gouvernance Git & Gestion de Projet (AT1)

### Modèle de Branches GitFlow

* `main` : Version de production stable et testée.
* `dev` : Branche d'intégration continue des développements.
* `feature/*` : Branches de travail isolées pour chaque fonctionnalité (ex: `feature/events-filters`, `feature/notes-system`).

### Norme de Commits (Conventional Commits)

* `feat(scope): description` : Nouvelle fonctionnalité.
* `fix(scope): description` : Correction d'anomalie.
* `refactor(scope): description` : Réorganisation du code à comportement constant.
* `docs(scope): description` : Rédaction ou mise à jour documentaire.
* `test(scope): description` : Ajout ou révision de tests automatisés.

### Pilotage Kanban

Le projet suit un tableau Kanban partagé en 5 colonnes :

1. Fonctionnalités prévues (ordonnées par priorité).
2. Fonctionnalités prévues dans le sprint en cours.
3. En cours de développement.
4. Terminées et testées sur la branche `dev`.
5. Mergées dans la branche `main` (Production).

---

## 📋 Matrice de Couverture des Exigences ECF

### AT1 - Développer une Application Sécurisée

* [x] Conteneurisation Docker multi-services et gestion de configuration par environnement.
* [x] Gestion de version GitFlow (`main`, `dev`, `feature/*`) avec commits sémantiques.
* [x] Authentification sécurisée (Bcrypt, sessions régénérées, mot de passe oublié temporaire).
* [x] Formulaire public de devis avec insertion en table `prospects` (statut `à contacter`) et notification mail.
* [x] Back-Office Chloé : badge dynamique d'indicateurs de devis en attente et édition des prestations.
* [x] Espace Client B2B : arbitrage des devis (`accepté`, `refusé`, `modification` avec motif obligatoire).
* [ ] Vitrine publique des événements avec filtres multicritères (dates, type, thème) sans affichage des prix.
* [ ] Application Mobile Dockerisée pour Chloé et José (fiches concises, appels/mails/itinéraires en un clic, notes rapides).
* [ ] Pages légales : Mentions légales, CGU et CGV.
* [ ] Respect des règles d'accessibilité RGAA (navigation clavier, sémantique HTML, attributs ARIA).

### AT2 - Concevoir et Développer une Application Sécurisée Organisée en Couches

* [x] Modélisation relationnelle 3NF découpant Prospects, Devis, Prestations, Événements et Notes.
* [x] Journalisation NoSQL MongoDB (`logs`) des opérations sensibles (connexions, CRUD client, statut devis).
* [x] Prévention des vulnérabilités OWASP Top 10 (requêtes préparées PDO anti-injections SQL, assainissement XSS, CSRF tokens).
* [ ] Rédaction du script SQL de création des tables écrit manuellement (non exporté de phpMyAdmin).
* [ ] Modèle Conceptuel de Données (MCD textuel et graphique).
* [ ] Dossier d'architecture multicouche avec schémas techniques détaillant chaque brique logicielle.
* [ ] Diagrammes UML : Diagramme de Cas d'Utilisation global et Diagramme de Séquence du tunnel commercial.
* [ ] 3 Wireframes et 3 Mockups pour le Web, 3 Wireframes et 3 Mockups pour le Mobile avec charte graphique.

### AT3 - Préparer le Déploiement d'une Application Sécurisée

* [ ] Suite de tests automatisés (Tests unitaires, fonctionnels et E2E sur le parcours commercial).
* [ ] Rapport de couverture de code chiffré (`docs/coverage`).
* [ ] Pipeline CI/CD automatisé (GitHub Actions ou GitLab CI) validant le code avant merge.
* [ ] Procédure de déploiement continu automatisée vers l'hébergeur en ligne (Fly.io).
* [ ] Rédaction des guides d'installation détaillés, reproductibles et de la documentation utilisateur finale.
