import type { Metadata } from "next";
import { getPosts } from "@/lib/wp";
import PostCard from "@/components/post-card";
import Pagination from "@/components/pagination";
import SearchForm from "@/components/search-form";

export const metadata: Metadata = { title: "Recherche" };

const PER_PAGE = 12;

type Props = { searchParams: Promise<{ q?: string; page?: string }> };

export default async function SearchPage({ searchParams }: Props) {
  const { q, page: pageParam } = await searchParams;
  const query = q?.trim() ?? "";
  const page = Math.max(1, Number(pageParam) || 1);

  const { posts, total, totalPages } = query
    ? await getPosts({ search: query, page, perPage: PER_PAGE })
    : { posts: [], total: 0, totalPages: 0 };

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <h1 className="mb-6 font-serif text-3xl font-semibold">Rechercher une recette</h1>
      <SearchForm className="mb-10 max-w-xl" />

      {query && (
        <p className="mb-6 text-sm text-muted">
          {total} résultat{total > 1 ? "s" : ""} pour « {query} »
        </p>
      )}

      {query && posts.length === 0 && (
        <p className="text-muted">Aucun article ne correspond à cette recherche.</p>
      )}

      <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {posts.map((post) => (
          <PostCard key={post.id} post={post} />
        ))}
      </div>

      {query && (
        <Pagination
          currentPage={page}
          totalPages={totalPages}
          basePath={`/recherche?q=${encodeURIComponent(query)}`}
        />
      )}
    </div>
  );
}
