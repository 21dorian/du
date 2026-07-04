import Link from "next/link";
import Image from "next/image";
import type { PostSummary } from "@/lib/wp";
import { formatDate, excerptText, cleanTitle } from "@/lib/format";

export default function PostCard({ post, priority = false }: { post: PostSummary; priority?: boolean }) {
  const mainCategory = post.categories[0];

  return (
    <article className="group flex flex-col overflow-hidden rounded-2xl border border-border bg-surface transition hover:shadow-md">
      <Link href={`/recette/${post.slug}`} className="block aspect-4/3 overflow-hidden bg-accent-soft">
        {post.image ? (
          <Image
            src={post.image.url}
            alt={post.image.alt}
            width={480}
            height={360}
            priority={priority}
            className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-4xl">🍰</div>
        )}
      </Link>
      <div className="flex flex-1 flex-col gap-2 p-4">
        {mainCategory && (
          <Link
            href={`/categorie/${mainCategory.slug}`}
            className="w-fit rounded-full bg-accent-soft px-3 py-1 text-xs font-medium text-accent"
          >
            {mainCategory.name}
          </Link>
        )}
        <h3 className="font-serif text-lg font-semibold leading-snug">
          <Link href={`/recette/${post.slug}`} className="hover:text-accent">
            {cleanTitle(post.title)}
          </Link>
        </h3>
        <p className="line-clamp-2 flex-1 text-sm text-muted">{excerptText(post.excerpt)}</p>
        <time dateTime={post.date} className="text-xs text-muted">
          {formatDate(post.date)}
        </time>
      </div>
    </article>
  );
}
