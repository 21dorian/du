import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { getCategoryBySlug, getPosts } from "@/lib/wp";
import PostCard from "@/components/post-card";
import Pagination from "@/components/pagination";

const PER_PAGE = 12;

type Props = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ page?: string }>;
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const category = await getCategoryBySlug(slug);
  if (!category) return {};
  return { title: category.name };
}

export default async function CategoryPage({ params, searchParams }: Props) {
  const { slug } = await params;
  const { page: pageParam } = await searchParams;
  const category = await getCategoryBySlug(slug);
  if (!category) notFound();

  const page = Math.max(1, Number(pageParam) || 1);
  const { posts, totalPages } = await getPosts({ page, perPage: PER_PAGE, category: category.id });

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <h1 className="mb-2 font-serif text-3xl font-semibold">{category.name}</h1>
      <p className="mb-8 text-sm text-muted">{category.count} recette{category.count > 1 ? "s" : ""}</p>

      {posts.length === 0 ? (
        <p className="text-muted">Aucun article dans cette catégorie pour le moment.</p>
      ) : (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {posts.map((post) => (
            <PostCard key={post.id} post={post} />
          ))}
        </div>
      )}

      <Pagination currentPage={page} totalPages={totalPages} basePath={`/categorie/${slug}`} />
    </div>
  );
}
