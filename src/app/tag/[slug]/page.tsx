import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { getTagBySlug, getPosts } from "@/lib/wp";
import PostCard from "@/components/post-card";
import Pagination from "@/components/pagination";

const PER_PAGE = 12;

type Props = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ page?: string }>;
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const tag = await getTagBySlug(slug);
  if (!tag) return {};
  return { title: `#${tag.name}` };
}

export default async function TagPage({ params, searchParams }: Props) {
  const { slug } = await params;
  const { page: pageParam } = await searchParams;
  const tag = await getTagBySlug(slug);
  if (!tag) notFound();

  const page = Math.max(1, Number(pageParam) || 1);
  const { posts, totalPages } = await getPosts({ page, perPage: PER_PAGE, tag: tag.id });

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <h1 className="mb-2 font-serif text-3xl font-semibold">#{tag.name}</h1>
      <p className="mb-8 text-sm text-muted">{tag.count} recette{tag.count > 1 ? "s" : ""}</p>

      {posts.length === 0 ? (
        <p className="text-muted">Aucun article avec ce tag pour le moment.</p>
      ) : (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {posts.map((post) => (
            <PostCard key={post.id} post={post} />
          ))}
        </div>
      )}

      <Pagination currentPage={page} totalPages={totalPages} basePath={`/tag/${slug}`} />
    </div>
  );
}
