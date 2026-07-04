import Link from "next/link";
import { getPosts } from "@/lib/wp";
import PostCard from "@/components/post-card";

export default async function HomePage() {
  const { posts } = await getPosts({ page: 1, perPage: 9 });
  const [featured, ...rest] = posts;

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <section className="mb-12 rounded-3xl bg-accent-soft px-6 py-14 text-center sm:px-12">
        <h1 className="font-serif text-4xl font-semibold text-foreground sm:text-5xl">
          Se faire plaisir, même au régime
        </h1>
        <p className="mx-auto mt-4 max-w-xl text-base text-muted sm:text-lg">
          Des recettes sucrées et salées gourmandes pour toutes les phases du
          régime Dukan, testées et approuvées par Choupette.
        </p>
        <div className="mt-6 flex flex-wrap justify-center gap-3">
          <Link
            href="/categorie/sucre"
            className="rounded-full bg-accent px-5 py-2.5 text-sm font-medium text-accent-foreground hover:opacity-90"
          >
            Recettes sucrées
          </Link>
          <Link
            href="/categorie/sale"
            className="rounded-full border border-accent px-5 py-2.5 text-sm font-medium text-accent hover:bg-accent hover:text-accent-foreground"
          >
            Recettes salées
          </Link>
        </div>
      </section>

      {featured && (
        <section className="mb-12">
          <h2 className="mb-4 font-serif text-2xl font-semibold">À la une</h2>
          <div className="grid gap-6 md:grid-cols-1">
            <PostCard post={featured} priority />
          </div>
        </section>
      )}

      <section>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="font-serif text-2xl font-semibold">Derniers articles</h2>
          <Link href="/categorie/recette" className="text-sm font-medium text-accent hover:underline">
            Voir tout →
          </Link>
        </div>
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {rest.map((post) => (
            <PostCard key={post.id} post={post} />
          ))}
        </div>
      </section>
    </div>
  );
}
