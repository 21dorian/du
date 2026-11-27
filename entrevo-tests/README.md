# Tests de bout en bout d'Entrevo

Scénarios Playwright qui pilotent un vrai navigateur sur une installation de test, avec les données de démonstration
(organisateur `orga@demo.entrevo` / `demo1234`). Ils ne contiennent aucun identifiant de paiement.

## Préparer

- PHP 8.1+, MySQL avec une base `entrevo` installée **avec les données de démo**, Node 18+ et `npm i playwright`.
- Lancer le site : `cd entrevo && php -S 127.0.0.1:8080 ../entrevo-outils/routeur-dev.php`
- Pour `widget.js`, servir la page hôte : `php -S 127.0.0.1:8099 -t entrevo-tests/site`
- Variables : `BASE` (URL du site, défaut `http://127.0.0.1:8080`), `CAPTURES` (dossier des captures, défaut dossier temporaire).

## Lancer

`node entrevo-tests/smoke.js` : toutes les pages (aucun code d'erreur, aucune erreur JavaScript), puis un scénario par domaine :

| Fichier | Vérifie |
|---|---|
| `smoke.js` | Toutes les pages publiques, pro, super-admin et Pay répondent sans erreur |
| `accessibilite.js` | Chaque champ de formulaire a un nom accessible |
| `mobile-debordement.js` | Aucune page ne dépasse de l'écran sur mobile (390 px) |
| `tarifs-visibilite.js`, `modules-4.0.js` | Tarifs modulables, automatisations, PMR, vestiaire, contacts, rôles, borne |
| `widget.js`, `guichet.js`, `editeurs.js`, `social.js` | Widget de vente, guichet, éditeurs visuels, payer à plusieurs |
| `reservation.js`, `impressions.js`, `anglais.js`, `equipes-click-collect.js` | Nouveautés 4.2 |
| `maj-2.6-vers-4.2.js`, `maj-4.1-vers-4.2.js` | Mises à jour réelles sous Apache (adapter l'URL dans le fichier) |

Les scénarios modifient les données de démo (commandes de test, réglages) : à lancer sur une base de test, jamais en production.
