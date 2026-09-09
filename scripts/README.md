**Scripts SQL — MySQL 8.0**

Ces fichiers ciblent les sept tables actuellement utilisées par l'application.
Le schéma de référence est `schema.sql`. Les `update_*.sql` alignent la base
déjà structurée comme l'export `innovevents_db(6).sql` du 9 septembre 2026.
Ils ne constituent plus une chaîne d'installation des anciennes versions du projet.

| Situation | Scripts à utiliser |
| --- | --- |
| Base vide | `schema.sql`, puis `initialise.sql` pour les données de démonstration |
| Base correspondant à l'export du 09/09/2026 | Les cinq `update_*.sql` ci-dessous, après sauvegarde |
| Ancienne base avec `events.event_date` ou sans certaines tables/colonnes | Comparer son schéma et préparer une migration adaptée avant exécution |

`schema.sql` ne supprime aucune table et échoue si une table existe déjà.
Ne pas utiliser l'option MySQL `--force`, qui poursuivrait après les erreurs.
`initialise.sql` est un jeu de démonstration pour une base neuve, pas une sauvegarde
de la base de travail ni un script à rejouer sur des données existantes.

Le jeu d’essai contient uniquement Chloé (ADMIN), José (EMPLOYEE), Alice et
Amandine (CLIENT), tous avec `Password123!` (hashes bcrypt vérifiés). Ce mot de passe
respecte les cinq critères de complexité de l’ECF. Les deux clientes sont
rattachées à deux entreprises distinctes et disposent chacune de devis et
d’événements pour tester l’isolation de leurs accès. Les comptes issus des essais
manuels ont été retirés du script et leurs dossiers réattribués de façon cohérente.
Les identifiants exacts et les commandes de vérification de la connexion sont
indiqués dans [le README principal](../README.md).

**Installation vierge**

Depuis la racine du projet, après le démarrage du service `db` :

```bash
docker compose exec -T db mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < scripts/schema.sql
docker compose exec -T db mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < scripts/initialise.sql
```

Sous PowerShell, copier les fichiers évite les problèmes d'encodage d'un pipeline
texte et l'opérateur `<` non pris en charge. Les identifiants suivants sont ceux
de l'environnement local de démonstration ; adapter la base et le mot de passe
à la configuration réelle :

```powershell
docker compose cp scripts/schema.sql db:/tmp/innovevents-schema.sql
docker compose exec -T db sh -c 'exec mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < /tmp/innovevents-schema.sql'
# Continuer uniquement si l'import du schéma a réussi.
docker compose cp scripts/initialise.sql db:/tmp/innovevents-initialise.sql
docker compose exec -T db sh -c 'exec mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < /tmp/innovevents-initialise.sql'
```

**Mise à jour de la base exportée**

Conserver un export récent et vérifier sa restauration avant intervention.
Les cinq fichiers sont indépendants sur cette version de départ ; ordre conseillé :

1. `update_companies.sql` : largeur du nom de société alignée à 255 caractères ; liens existants conservés.
2. `update_users.sql` : rôle et indicateurs de compte obligatoires, avec leurs valeurs par défaut.
3. `update_prospects.sql` : statut obligatoire, défaut `à contacter`.
4. `update_devis.sql` : statut obligatoire et montants par défaut à zéro.
5. `update_event_note.sql` : statut événement obligatoire, notes globales autorisées.

Ces scripts peuvent être rejoués sur l'export indiqué et sur une base créée avec
`schema.sql`. Ils ne changent ni les montants, ni les statuts déjà renseignés,
ni les dates, ni les accords de publication. Ils ne créent aucun jeu d'essai.
Les noms des clés étrangères ne sont pas supposés : les contraintes existantes
sont conservées, y compris leurs cascades.

Importer chaque fichier dans phpMyAdmin en sélectionnant la bonne base, ou utiliser
le même mécanisme Docker que ci-dessus. Exemple PowerShell pour **un** fichier :

```powershell
docker compose cp scripts/update_users.sql db:/tmp/innovevents-update.sql
docker compose exec -T db sh -c 'exec mysql --default-character-set=utf8mb4 -uroot -proot_password innovevents_db < /tmp/innovevents-update.sql'
```

Remplacer `update_users.sql` par le fichier concerné et vérifier le résultat avant
de passer au suivant. Ne pas importer `schema.sql` ou `initialise.sql` pour mettre
à jour cette base.

Chaque mise à jour active `STRICT_ALL_TABLES` dans sa session : un NULL existant
dans un champ rendu obligatoire provoque une erreur, au lieu d'une conversion
silencieuse. Dans ce cas, qualifier la donnée concernée avant de reprendre.
MySQL effectue des validations implicites autour des ALTER TABLE : un
START TRANSACTION/ROLLBACK ne permet pas d'annuler une série entière de DDL.

`ConversionService` écrit désormais dans `events.start_date` ; le test
`tests/conversion.php` vérifie cette insertion sur le schéma courant.
`Prospect::create()` doit encore être corrigé pour ne plus imposer `en attente`.
Le défaut SQL ne remplace pas une valeur explicitement envoyée par l'application.

**Vérification automatisée**

Python 3 et Docker sont nécessaires. Le test utilise l'image `mysql:8.0`, un
conteneur sans réseau ni port exposé, et un stockage temporaire. Il ne se connecte
jamais au service `db` du projet. Le conteneur est supprimé en fin de test.

```powershell
python tests/sql_migrations.py
python tests/sql_migrations.py --export 'C:/Users/Romain/Downloads/innovevents_db(6).sql'
```

Le premier contrôle fonctionne sans sauvegarde personnelle. Le second remplace
la base simulée par l'export fourni. Ils vérifient la création vierge, le refus
d'une réinitialisation, deux passages des migrations, la conservation des lignes,
l'équivalence des colonnes/index/clés étrangères, le refus d'un rôle NULL,
l'unicité des emails, les valeurs par défaut et les notes globales.
