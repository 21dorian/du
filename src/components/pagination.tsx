import Link from "next/link";

export default function Pagination({
  currentPage,
  totalPages,
  basePath,
  queryParam = "page",
}: {
  currentPage: number;
  totalPages: number;
  basePath: string;
  queryParam?: string;
}) {
  if (totalPages <= 1) return null;

  const hasPrev = currentPage > 1;
  const hasNext = currentPage < totalPages;
  const separator = basePath.includes("?") ? "&" : "?";
  const pageHref = (page: number) =>
    page === 1 ? basePath : `${basePath}${separator}${queryParam}=${page}`;

  return (
    <nav className="mt-10 flex items-center justify-center gap-4" aria-label="Pagination">
      {hasPrev ? (
        <Link
          href={pageHref(currentPage - 1)}
          className="rounded-full border border-border px-4 py-2 text-sm font-medium hover:bg-accent-soft"
        >
          ← Précédent
        </Link>
      ) : (
        <span className="rounded-full border border-border px-4 py-2 text-sm text-muted opacity-50">← Précédent</span>
      )}
      <span className="text-sm text-muted">
        Page {currentPage} sur {totalPages}
      </span>
      {hasNext ? (
        <Link
          href={pageHref(currentPage + 1)}
          className="rounded-full border border-border px-4 py-2 text-sm font-medium hover:bg-accent-soft"
        >
          Suivant →
        </Link>
      ) : (
        <span className="rounded-full border border-border px-4 py-2 text-sm text-muted opacity-50">Suivant →</span>
      )}
    </nav>
  );
}
