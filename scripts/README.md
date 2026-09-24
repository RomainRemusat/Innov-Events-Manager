**Scripts SQL — MySQL 8.0**

Ces fichiers ciblent les neuf tables actuellement utilisées par l'application.
Le schéma de référence est `schema.sql`. Les `update_*.sql` alignent la base
déjà structurée comme l'export `innovevents_db(6).sql` du 9 septembre 2026.
Ils ne constituent plus une chaîne d'installation des anciennes versions du projet.

| Situation | Scripts à utiliser |
| --- | --- |
| Base vide | `schema.sql`, puis `initialise.sql` pour les données de démonstration |
| Base correspondant à l'export du 09/09/2026 | Les scripts `update_*.sql` ci-dessous, après sauvegarde |
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
Les scripts sont indépendants sur cette version de départ ; ordre conseillé :

1. `update_companies.sql` : largeur du nom de société alignée à 255 caractères ; liens existants conservés.
2. `update_users.sql` : rôle et indicateurs de compte obligatoires, avec leurs valeurs par défaut.
3. `update_prospects.sql` : statut obligatoire, défaut `à contacter`.
4. `update_devis.sql` : statut obligatoire et montants par défaut à zéro.
5. `update_event_note.sql` : statut événement obligatoire, notes globales autorisées.
6. `update_tasks.sql` : tâches d'événement assignées aux employés et suivi de leur statut.
7. `update_reviews.sql` : avis uniques par événement, note, modération et publication.
8. `update_public_pages.sql` : contenu administrable du message de remerciement des demandes de devis.

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

**Lien devis/événement (base existante, y compris export du 15/09/2026)**

Après sauvegarde, importer `update_devis_event.sql` avant le déploiement du code
de conversion. Ce script ajoute `devis.event_id` nullable et sa clé étrangère ;
il peut être rejoué. `schema.sql` inclut déjà ce lien pour une installation neuve.
La suppression d'un événement détache le devis (`SET NULL`) sans le détruire.

Les devis historiques restent sans lien : ne pas les associer automatiquement
par client, date ou numéro. Après vérification des dossiers (ou des identifiants
`event_id` / `devis_id` d'un journal de conversion), renseigner explicitement
`devis.event_id` en vérifiant aussi que l'événement et le prospect ont le même client.
Plusieurs versions de devis peuvent viser un événement ; la fiche affiche la dernière.
Sans association, elle indique qu'aucun devis n'est rattaché.
Les PDF déjà émis ne sont pas réécrits par cette migration.

**Motif de non-faisabilité**

Importer `update_prospect_rejection.sql` après sauvegarde et avant de déployer
la qualification des prospects. La colonne nullable `prospects.rejection_reason`
conserve le dernier motif de refus, même après réouverture de la demande.
La migration est rejouable et ne reconstitue pas les motifs historiques perdus.
Le statut et le motif sont validés en SQL avant la tentative SMTP. En cas d'échec
d'envoi, l'écran conserve le motif et permet une nouvelle soumission explicite.
Une acceptation par le serveur SMTP ne garantit pas la livraison au destinataire.

`python -B tests/prospect_qualification.py` vérifie le contrôleur sur des bases
temporaires et des emails capturés par MailHog local. Il nettoie ses dossiers,
ses journaux et uniquement ses propres messages après exécution.

**Versions des propositions commerciales**

Importer `update_quote_revision.sql` après sauvegarde et avant le code du cycle
commercial. Les devis existants commencent à la version 1. Une modification des
prestations les remet en brouillon et invalide les anciens formulaires client.
Le PDF stocké reste inaccessible au client pendant cette préparation ; le prochain
envoi le remplace par un document à jour et publie une nouvelle version.
Un devis accepté ne peut être ni modifié ni renvoyé par l'action commerciale.
Les anciens formulaires ouverts avant déploiement doivent être rechargés.

Le verrou SQL par devis couvre la génération et l'envoi SMTP afin de sérialiser
les opérations commerciales. Il peut donc retarder une requête sur le même devis.
SQL et SMTP ne forment pas une transaction distribuée : en cas d'erreur après
acceptation du message par SMTP, vérifier l'état du devis et l'envoi avant de réessayer.

`python -B tests/quote_lifecycle.py` vérifie le cycle complet et une concurrence
modification/acceptation sur des bases temporaires, avec des emails capturés localement.

**Motif de modification des devis**

Importer `update_quote_change_reason.sql` avant le code qui enregistre les réponses
clients. La migration ajoute `devis.change_reason`, peut être rejouée et conserve
les données existantes. Le motif et le statut sont écrits dans la même requête SQL.
Les anciens motifs restent lus dans MongoDB lorsque la colonne est vide ; les
motifs historiques perdus ne peuvent pas être reconstitués.

Les échecs SMTP ne suppriment pas une demande ou une décision déjà enregistrée.
L'interface signale la notification échouée. Pour un compte créé par conversion,
le client peut demander de nouveaux accès avec « Mot de passe oublié ».
La réinitialisation conserve l'ancien mot de passe si l'envoi échoue. Le compte
reste verrouillé pendant la tentative SMTP, avec un délai de connexion de dix
secondes. Un message accepté par SMTP peut néanmoins précéder un échec du commit
SQL ; dans ce cas, les anciens identifiants restent valides et il faut réessayer.

`SMTP_HOST` et `SMTP_PORT` dans `$_ENV` permettent de choisir le serveur ; les
valeurs par défaut restent `mailhog:1025`. `python -B tests/write_failures.py`
simule des pannes SMTP, SQL et MongoDB sur une base temporaire, sans envoyer de mail.

**Exécution des tests SQL**

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
