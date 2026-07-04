# Thème "Se faire plaisir"

Thème WordPress sur mesure pour dukanette.fr — même hébergement (Plesk),
même base de données, WordPress reste actif tel quel. Comptes, favoris et
commentaires utilisent le système natif de WordPress (aucun service externe
requis).

## Installation

1. Dans `wp-admin` : **Apparence → Thèmes → Ajouter → Téléverser un thème**.
2. Sélectionne le fichier `dukanette.zip`, installe, puis **Activer**.
3. Va dans **Réglages → Permaliens** et choisis la structure **"Nom de
   l'article"** (recommandé — plus lisible et meilleur pour le référencement
   que les liens `?p=123` actuels). Les liens du thème fonctionnent avec
   n'importe quelle structure, mais celle-ci est conseillée.

## Pages requises

Le thème utilise des templates automatiques basés sur le *slug* de page
(convention WordPress `page-{slug}.php`). Certaines pages existent déjà sur
le site (`login`, `register`, `lostpassword`, `resetpass`, `logout`,
`newsletter`) ; il manque probablement :

- Une page avec le slug **`favoris`** (titre libre, ex. "Mes favoris",
  contenu vide — le template s'occupe de tout).
- Une page avec le slug **`populaires`** (ex. "Les plus consultées",
  contenu vide) pour la page du palmarès des recettes les plus lues.

Si une page manque, WordPress affichera cette route avec le template par
défaut plutôt que le design du thème — crée-la simplement dans **Pages →
Ajouter**.

## Réglages à faire après activation

- **Réglages → Général → "Tout le monde peut s'inscrire"** : à cocher si tu
  veux permettre aux visiteurs de créer un compte (nécessaire pour les
  favoris). Sinon, seuls les comptes que tu crées toi-même peuvent se
  connecter.
- **Apparence → Personnaliser → Newsletter** : colle ta clé API Buttondown
  (créée sur buttondown.email → Réglages → Emails sortants → API Key) pour
  activer réellement les inscriptions à la newsletter.
- **Apparence → Personnaliser → Page d'accueil** :
  - Bandeau d'annonce (à activer + texte libre).
  - Emplacement publicitaire sur l'accueil (à activer + coller le code de ta
    régie, ex. le snippet `<ins class="adsbygoogle">` d'une unité AdSense).
  - L'identifiant client AdSense (`ca-pub-...`) est pré-rempli avec le tien ;
    le script AdSense est chargé automatiquement sur tout le site (requis
    par Google, avec ou sans emplacement manuel activé).
  - Astuces du jour : une astuce par ligne, affichée en rotation quotidienne
    dans le bloc "Aujourd'hui" — une liste de départ est déjà fournie.

## Ce que le thème gère

- Nav Recette / Salé / Sucré (catégories réelles ; les catégories/tags de
  spam détectés sur l'installation actuelle sont ignorés automatiquement).
- Recherche, archives de catégorie/tag paginées.
- Page recette : contenu nettoyé des résidus markdown (`**gras**`,
  `### titres`) laissés par la rédaction d'origine, commentaires natifs WP.
- Connexion / inscription / mot de passe oublié / réinitialisation, avec un
  design cohérent (pas l'écran wp-login.php par défaut).
- Favoris : cœur cliquable sur chaque recette, stocké en usermeta (pas de
  table supplémentaire), page "Mes favoris".
- Newsletter : formulaire AJAX côté thème, appel serveur vers Buttondown.
- Page d'accueil "vivante" : bandeau d'annonce, menu du jour (une recette
  sucrée + une salée tirées automatiquement, change chaque jour à minuit),
  astuce du jour, emplacement publicitaire, derniers articles.
- Compteur de lectures intégré (post meta, sans plugin ni table en plus ;
  les visites des comptes éditeurs/admins ne comptent pas) : section
  "Les plus consultées" sur l'accueil + page palmarès (`populaires`),
  nombre de lectures affiché sur chaque recette.
- Sur chaque recette : navigation "recette précédente / suivante" et bloc
  "Tu aimeras aussi" (3 recettes de la même catégorie).

## Point de sécurité (rappel)

L'installation WordPress actuelle contient des dizaines de catégories/tags
de spam (injection SEO probable via une extension obsolète) — à traiter
séparément d'un audit de sécurité, indépendamment de ce thème.
