import DOMPurify from "isomorphic-dompurify";

/**
 * A chunk of the recipe content on the source WordPress site was authored as
 * raw markdown that WP never converts (it only wraps text in <p>/<br>), so
 * "**bold**" and "### heading" show up as literal characters. Convert the
 * common patterns to real HTML before sanitizing.
 */
function headingLevel(hashes: string) {
  return Math.min(6, hashes.length + 2);
}

export function fixLegacyMarkdown(html: string) {
  return html
    // WP sometimes already turned "# Title" into a real heading tag but left the "#" inside it
    .replace(/(<h[1-6]>)\s*#{1,6}\s*/g, "$1")
    // a paragraph that is only a heading: <p>### Title</p>
    .replace(/<p>\s*(#{1,6})\s*([^<]+?)\s*<\/p>/g, (_, hashes: string, text: string) => {
      const level = headingLevel(hashes);
      return `<h${level}>${text}</h${level}>`;
    })
    // a heading followed by more content in the same paragraph: <p>### Title<br />...
    .replace(/<p>(#{1,6})\s*([^<\n]+?)\s*(<br\s*\/?>)/g, (_, hashes: string, text: string) => {
      const level = headingLevel(hashes);
      return `<h${level}>${text}</h${level}><p>`;
    })
    .replace(/\*\*([^*<]+)\*\*/g, "<strong>$1</strong>");
}

export function sanitizeHtml(html: string) {
  return DOMPurify.sanitize(fixLegacyMarkdown(html), {
    ADD_ATTR: ["target", "loading"],
  });
}
