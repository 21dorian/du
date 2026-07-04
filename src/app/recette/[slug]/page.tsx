import Link from "next/link";
import Image from "next/image";
import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { getPostBySlug } from "@/lib/wp";
import { formatDate, excerptText, cleanTitle } from "@/lib/format";
import { sanitizeHtml } from "@/lib/sanitize";
import Comments from "@/components/comments";

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const post = await getPostBySlug(slug);
  if (!post) return {};
  return {
    title: cleanTitle(post.title.rendered),
    description: excerptText(post.excerpt.rendered, 160),
  };
}

export default async function PostPage({ params }: Props) {
  const { slug } = await params;
  const post = await getPostBySlug(slug);
  if (!post) notFound();

  const media = post._embedded?.["wp:featuredmedia"]?.[0];
  const terms = post._embedded?.["wp:term"]?.flat() ?? [];
  const categories = terms.filter((t) => t.taxonomy === "category");
  const tags = terms.filter((t) => t.taxonomy === "post_tag");

  return (
    <article className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
      <div className="mb-4 flex flex-wrap gap-2">
        {categories.map((cat) => (
          <Link
            key={cat.id}
            href={`/categorie/${cat.slug}`}
            className="rounded-full bg-accent-soft px-3 py-1 text-xs font-medium text-accent"
          >
            {cat.name}
          </Link>
        ))}
      </div>

      <h1 className="font-serif text-3xl font-semibold leading-tight sm:text-4xl">
        {cleanTitle(post.title.rendered)}
      </h1>
      <time dateTime={post.date} className="mt-3 block text-sm text-muted">
        {formatDate(post.date)}
      </time>

      {media && (
        <div className="relative mt-8 aspect-16/9 overflow-hidden rounded-2xl bg-accent-soft">
          <Image
            src={media.source_url}
            alt={media.alt_text || ""}
            fill
            priority
            className="object-cover"
          />
        </div>
      )}

      <div
        className="prose-recipe mt-8 max-w-none"
        dangerouslySetInnerHTML={{ __html: sanitizeHtml(post.content.rendered) }}
      />

      {tags.length > 0 && (
        <div className="mt-10 flex flex-wrap gap-2 border-t border-border pt-6">
          {tags.map((tag) => (
            <Link
              key={tag.id}
              href={`/tag/${tag.slug}`}
              className="rounded-full border border-border px-3 py-1 text-xs text-muted hover:border-accent hover:text-accent"
            >
              #{tag.name}
            </Link>
          ))}
        </div>
      )}

      <section className="mt-12 border-t border-border pt-8">
        <h2 className="mb-4 font-serif text-xl font-semibold">Commentaires</h2>
        <Comments />
      </section>
    </article>
  );
}
