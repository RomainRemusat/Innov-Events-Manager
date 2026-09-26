# Recette des finitions web — 24 septembre 2026

## Résultats

| Contrôle | Résultat | Preuve |
| --- | --- | --- |
| Pseudo d’inscription | Conforme | Colonne SQL unique, migration rejouable, persistance et doublon testés |
| Retour après connexion | Conforme | Une route GET interne protégée est mémorisée puis restaurée après authentification |
| Journalisation sensible | Conforme sur l’inventaire courant | Comptes, prospects, événements, notes, tâches, avis, contenus, prestations, devis et téléchargements PDF couverts |
| Navigation clavier | Conforme sur les contrôles structurels | Lien d’évitement, ordre DOM natif et focus visible global |
| Responsive | Conforme sur les vues contrôlées | Captures réelles à 575 × 900 et 1440 × 1000 ; formulaire empilé sur mobile et navigation repliée |
| Contrastes corrigés | Conforme pour les couleurs personnalisées | Bouton bleu/blanc : 5,17:1 ; texte secondaire du pied de page : 12,02:1 |
| HTML et formulaires publics | Conforme | Un `h1`, identifiants uniques, alternatives d’image, labels et CSRF POST vérifiés automatiquement |
| Consentements serveur | Conforme | Inscription, demande de devis, contact, publication et suppressions définitives contrôlés côté serveur |

## Scénarios exécutés

- Inscription refusée sans consentement, puis acceptée avec un pseudo persisté et unique.
- Accès anonyme à une page d’administration, connexion, puis retour exact vers cette page.
- Demande de devis refusée sans consentement sans création de prospect.
- Suppression de compte refusée sans confirmation explicite et protégée par POST/CSRF.
- Deux passages de toutes les migrations sur bases vierge et migrée, sans divergence.
- Relecture automatisée des onze routes publiques et inspection visuelle du formulaire d’inscription aux deux largeurs.
- Écritures MongoDB vérifiées après ajout et suppression d’une prestation, ainsi qu’après trois téléchargements autorisés de devis.

Commande principale :

```bash
python -B tests/web_finishing.py
python -B tests/csrf_protection.py
python -B tests/quote_lifecycle.py
```

Cette recette ne constitue pas une certification RGAA. Une validation finale avec technologies d’assistance réelles reste nécessaire avant une mise en production publique.
