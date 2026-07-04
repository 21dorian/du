import Link from "next/link";

export default function Footer() {
  return (
    <footer className="border-t border-border bg-surface">
      <div className="mx-auto max-w-6xl px-4 py-10 text-sm text-muted sm:px-6">
        <div className="flex flex-col gap-6 sm:flex-row sm:justify-between">
          <div>
            <p className="font-serif text-lg text-foreground">Se faire plaisir</p>
            <p className="mt-1 max-w-sm">
              Le blog Dukan de Choupette : des recettes sucrées et salées pour
              toutes les phases du régime, sans jamais se priver.
            </p>
          </div>
          <div className="flex gap-10">
            <div>
              <p className="mb-2 font-medium text-foreground">Explorer</p>
              <ul className="space-y-1">
                <li><Link href="/categorie/sucre" className="hover:text-accent">Sucré</Link></li>
                <li><Link href="/categorie/sale" className="hover:text-accent">Salé</Link></li>
                <li><Link href="/categorie/recette" className="hover:text-accent">Recette</Link></li>
                <li><Link href="/recherche" className="hover:text-accent">Recherche</Link></li>
              </ul>
            </div>
            <div>
              <p className="mb-2 font-medium text-foreground">Suivre</p>
              <ul className="space-y-1">
                <li><Link href="/newsletter" className="hover:text-accent">Newsletter</Link></li>
                <li><a href="/feed.xml" className="hover:text-accent">Flux RSS</a></li>
              </ul>
            </div>
          </div>
        </div>
        <p className="mt-8 text-xs">
          © {new Date().getFullYear()} Se faire plaisir. Tous droits réservés.
        </p>
      </div>
    </footer>
  );
}
