import Link from "next/link";
import { getCategories } from "@/lib/wp";
import SearchForm from "@/components/search-form";
import MobileNav from "@/components/mobile-nav";

export default async function Header() {
  const categories = await getCategories();

  return (
    <header className="relative border-b border-border bg-background/95 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <Link href="/" className="shrink-0 font-serif text-2xl font-semibold text-accent">
          Se faire plaisir
        </Link>

        <nav className="hidden items-center gap-1 md:flex">
          {categories.map((cat) => (
            <Link
              key={cat.id}
              href={`/categorie/${cat.slug}`}
              className="rounded-full px-4 py-2 text-sm font-medium text-foreground/80 transition hover:bg-accent-soft hover:text-foreground"
            >
              {cat.name}
            </Link>
          ))}
        </nav>

        <div className="hidden items-center gap-3 md:flex">
          <SearchForm className="w-64" />
          <Link
            href="/newsletter"
            className="shrink-0 rounded-full border border-accent px-4 py-2 text-sm font-medium text-accent transition hover:bg-accent hover:text-accent-foreground"
          >
            Newsletter
          </Link>
        </div>

        <MobileNav categories={categories} />
      </div>
    </header>
  );
}
