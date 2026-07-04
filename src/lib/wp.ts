const WP_API_URL = process.env.WP_API_URL ?? "https://dukanette.fr";

// The site doesn't use pretty permalinks for the REST API, so we go through
// the ?rest_route= fallback instead of /wp-json/.
function restUrl(path: string, params: Record<string, string | number | undefined> = {}) {
  const query = new URLSearchParams({ rest_route: path });
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined) query.set(key, String(value));
  }
  return `${WP_API_URL}/?${query.toString()}`;
}

async function wpFetch<T>(
  path: string,
  params?: Record<string, string | number | undefined>,
  revalidate = 300,
): Promise<{ data: T; total: number; totalPages: number }> {
  const res = await fetch(restUrl(path, params), { next: { revalidate } });
  if (!res.ok) {
    throw new Error(`WordPress API error ${res.status} for ${path}`);
  }
  const data = (await res.json()) as T;
  const total = Number(res.headers.get("x-wp-total") ?? 0);
  const totalPages = Number(res.headers.get("x-wp-totalpages") ?? 0);
  return { data, total, totalPages };
}

export interface WPRendered {
  rendered: string;
}

export interface WPMedia {
  id: number;
  source_url: string;
  alt_text: string;
  media_details?: {
    sizes?: Record<string, { source_url: string; width: number; height: number }>;
  };
}

export interface WPTerm {
  id: number;
  name: string;
  slug: string;
  count: number;
  taxonomy: "category" | "post_tag";
}

export interface WPPost {
  id: number;
  date: string;
  slug: string;
  title: WPRendered;
  excerpt: WPRendered;
  content: WPRendered;
  categories: number[];
  tags: number[];
  featured_media: number;
  _embedded?: {
    "wp:featuredmedia"?: WPMedia[];
    "wp:term"?: WPTerm[][];
  };
}

export interface WPPage {
  id: number;
  slug: string;
  title: WPRendered;
  content: WPRendered;
}

export interface PostSummary {
  id: number;
  slug: string;
  title: string;
  excerpt: string;
  date: string;
  image: { url: string; alt: string } | null;
  categories: WPTerm[];
  tags: WPTerm[];
}

function toPostSummary(post: WPPost): PostSummary {
  const media = post._embedded?.["wp:featuredmedia"]?.[0];
  const terms = post._embedded?.["wp:term"]?.flat() ?? [];
  return {
    id: post.id,
    slug: post.slug,
    title: post.title.rendered,
    excerpt: post.excerpt.rendered,
    date: post.date,
    image: media
      ? { url: media.source_url, alt: media.alt_text || post.title.rendered }
      : null,
    categories: terms.filter((t) => t.taxonomy === "category"),
    tags: terms.filter((t) => t.taxonomy === "post_tag"),
  };
}

/** The WP install has spam categories/tags injected with 0 posts; filter those out. */
function isRealTerm(term: WPTerm) {
  return term.count > 0;
}

export async function getPosts(options: {
  page?: number;
  perPage?: number;
  category?: number;
  tag?: number;
  search?: string;
} = {}) {
  const { page = 1, perPage = 12, category, tag, search } = options;
  const { data, total, totalPages } = await wpFetch<WPPost[]>("/wp/v2/posts", {
    page,
    per_page: perPage,
    categories: category,
    tags: tag,
    search,
    _embed: "wp:featuredmedia,wp:term",
    orderby: "date",
    order: "desc",
  });
  return { posts: data.map(toPostSummary), total, totalPages };
}

export async function getPostBySlug(slug: string) {
  const { data } = await wpFetch<WPPost[]>("/wp/v2/posts", {
    slug,
    _embed: "wp:featuredmedia,wp:term",
  });
  return data[0] ?? null;
}

// The three structural categories of the site; everything else in WP is
// stray/misc terms (duplicate "Non classé", a couple of misfiled posts...).
const MAIN_CATEGORY_SLUGS = ["recette", "sale", "sucre"];

export async function getCategories() {
  const { data } = await wpFetch<WPTerm[]>("/wp/v2/categories", { per_page: 100 });
  const bySlug = new Map(data.filter(isRealTerm).map((c) => [c.slug, c]));
  return MAIN_CATEGORY_SLUGS.map((slug) => bySlug.get(slug)).filter((c): c is WPTerm => Boolean(c));
}

export async function getCategoryBySlug(slug: string) {
  const { data } = await wpFetch<WPTerm[]>("/wp/v2/categories", { slug });
  return data[0] ?? null;
}

export async function getTagBySlug(slug: string) {
  const { data } = await wpFetch<WPTerm[]>("/wp/v2/tags", { slug });
  return data[0] ?? null;
}

export async function getTags(minCount = 1) {
  const { data } = await wpFetch<WPTerm[]>("/wp/v2/tags", { per_page: 100, orderby: "count", order: "desc" });
  return data.filter((t) => t.count >= minCount);
}

export async function getPageBySlug(slug: string) {
  const { data } = await wpFetch<WPPage[]>("/wp/v2/pages", { slug });
  return data[0] ?? null;
}
