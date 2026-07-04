# Se faire plaisir — nouveau frontend

Frontend Next.js "headless" pour [dukanette.fr](https://dukanette.fr). WordPress
reste le CMS (articles, catégories, tags, pages) et ce site consomme son API
REST publique en lecture seule ; il n'y a pas de base de données ni
d'authentification côté frontend.

## Développement

```bash
npm install
npm run dev
```

Variables d'environnement (voir `.env.local.example`) :

- `WP_API_URL` — URL du WordPress source (par défaut `https://dukanette.fr`).
- `NEXT_PUBLIC_GISCUS_*` — configuration des commentaires via
  [Giscus](https://giscus.app) (GitHub Discussions). Sans ces variables, la
  zone commentaires affiche un message d'attente.
- `NEWSLETTER_API_KEY` — clé API du provider newsletter (Buttondown par
  défaut, voir `src/app/api/newsletter/route.ts`).

## Structure

- `src/lib/wp.ts` — client de l'API REST WordPress (posts, catégories, tags,
  pages, recherche).
- `src/lib/sanitize.ts` — sanitisation du contenu HTML + correction des
  résidus de markdown non convertis par WordPress (`**gras**`, `### titres`).
- `src/app` — pages : accueil, `/categorie/[slug]`, `/tag/[slug]`,
  `/recette/[slug]`, `/recherche`, `/newsletter`.

## Points de vigilance connus

- Le WordPress source contient des dizaines de catégories/tags de spam
  (injection SEO) avec 0 article ; `getCategories()` ne garde que Sucré, Salé
  et Recette. Un audit de sécurité de l'installation WordPress est recommandé
  séparément.
