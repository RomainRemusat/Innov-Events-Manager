# Guide utilisateur — Innov’Events Manager

## Accès et comptes de démonstration

L’application locale est accessible sur `http://localhost:8081`. La production est accessible sur `https://innov-events-manager-romain.fly.dev`.

| Rôle | Identifiant de démonstration | Mot de passe |
| --- | --- | --- |
| Administratrice | `chloe@innovevents.fr` | `Password123!` |
| Employé | `jose@innovevents.fr` | `Password123!` |
| Cliente | `client@luxe.com` | `Password123!` |
| Cliente | `a.legrand@nextgen.io` | `Password123!` |

Ces comptes et données sont fictifs. Ils ne doivent pas être réutilisés pour une exploitation réelle.

## Visiteur

Depuis le menu public, le visiteur peut consulter l’accueil, les événements publiés, les avis validés, le contact et les pages légales.

Pour demander un devis :

1. ouvrir **Demande de devis** ;
2. compléter tous les champs obligatoires ;
3. lire et cocher le consentement ;
4. envoyer le formulaire ;
5. vérifier le message de confirmation.

Une erreur affichée dans le formulaire doit être corrigée avant un nouvel envoi. Le visiteur peut aussi créer un compte avec une adresse unique, un pseudo unique et un mot de passe conforme aux règles indiquées.

## Client

Après connexion, le client retrouve ses événements et demandes depuis son tableau de bord. Il peut ouvrir un devis disponible, télécharger son PDF et répondre à la proposition.

- **Accepter** confirme la version affichée et verrouille les prestations concernées.
- **Demander une modification** exige un motif transmis à l’équipe.
- **Refuser** change le statut de la proposition.

Le client peut modifier son profil, déposer un avis après un événement terminé et demander la suppression de son compte. La suppression nécessite une confirmation explicite et entraîne l’effacement coordonné des données personnelles prévues.

## Employé

L’employé consulte les clients et les événements. Sur une fiche événement, il peut ajouter, modifier ou supprimer ses notes collaboratives. Il peut mettre à jour le statut des tâches qui lui sont affectées et participer à la modération des avis.

Il ne dispose pas des fonctions structurelles réservées à l’administrateur, comme la gestion des comptes ou la suppression globale d’un événement.

## Administrateur

Le tableau de bord présente les événements proches, les notes récentes et les indicateurs utiles. Le menu permet de traiter les prospects, clients, événements, devis, avis, comptes, réglages publics et journaux.

### Traiter une demande de devis

1. ouvrir **Prospects** ;
2. consulter la demande ;
3. qualifier le besoin ou enregistrer un échec motivé ;
4. convertir le prospect en client et événement ;
5. ouvrir le devis créé ;
6. ajouter les prestations ;
7. générer ou envoyer le PDF ;
8. suivre la décision du client.

### Gérer un événement

L’administrateur peut créer ou modifier l’événement, changer son statut, associer une image, gérer les notes et tâches et contrôler sa publication. La publication publique exige le consentement prévu et exclut les brouillons.

### Consulter les journaux

L’écran des journaux affiche les actions sensibles enregistrées dans MongoDB. Ces journaux servent au diagnostic et à la traçabilité applicative ; ils ne constituent pas une preuve inviolable.

## PWA du personnel

La PWA est réservée aux administrateurs et employés. Elle est accessible avec l’action `mobile_dashboard` et peut être installée depuis un navigateur compatible lorsque l’application est servie en HTTPS ou sur `localhost`.

1. consulter les événements à venir ;
2. ouvrir une fiche événement ;
3. lancer un itinéraire vers le lieu ;
4. ouvrir la fiche du client ;
5. appeler, écrire un courriel ou lancer l’itinéraire vers son adresse ;
6. enregistrer une note rapide.

Le lien **Espace complet** ouvre l’interface web sans perdre la préférence mobile de la session.

## Mot de passe oublié

Depuis la connexion, utiliser **Mot de passe oublié** et saisir l’adresse du compte. En environnement local, le message est visible dans MailHog sur `http://localhost:8025`. Le mot de passe temporaire doit être remplacé lors de la connexion suivante.

## Conseils et erreurs courantes

- vérifier que les champs obligatoires et consentements sont complétés ;
- actualiser la page si un jeton de formulaire a expiré ;
- utiliser un fichier JPG, PNG ou WebP de 5 Mo maximum ;
- ne pas partager un lien de PDF : l’accès dépend de la session et du propriétaire ;
- contacter l’administrateur si un courriel n’apparaît pas ou si un compte est suspendu ;
- sur téléphone local, remplacer `localhost` par l’adresse IPv4 de l’ordinateur et autoriser le port 8081 dans le pare-feu.

## Déconnexion

Utiliser toujours le lien **Déconnexion** après une session sur un poste partagé. Fermer simplement l’onglet ne remplace pas la déconnexion applicative.
