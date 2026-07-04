export function formatDate(iso: string) {
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(new Date(iso));
}

const NAMED_ENTITIES: Record<string, string> = {
  amp: "&",
  lt: "<",
  gt: ">",
  quot: '"',
  apos: "'",
  nbsp: " ",
  hellip: "…",
};

export function decodeEntities(str: string) {
  return str
    .replace(/&#x([0-9a-fA-F]+);/g, (_, hex) => String.fromCodePoint(parseInt(hex, 16)))
    .replace(/&#(\d+);/g, (_, dec) => String.fromCodePoint(parseInt(dec, 10)))
    .replace(/&([a-zA-Z]+);/g, (match, name) => NAMED_ENTITIES[name] ?? match);
}

export function stripTags(html: string) {
  return decodeEntities(html.replace(/<[^>]*>/g, " "))
    .replace(/\s+/g, " ")
    .trim();
}

/** Recipe titles sometimes carry literal markdown bold markers from the source content. */
export function cleanTitle(html: string) {
  return stripTags(html).replace(/\*\*/g, "").trim();
}

export function excerptText(html: string, maxLength = 140) {
  const text = stripTags(html);
  if (text.length <= maxLength) return text;
  return text.slice(0, maxLength).replace(/\s+\S*$/, "") + "…";
}
