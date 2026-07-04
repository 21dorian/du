import type { Metadata } from "next";
import NewsletterForm from "@/components/newsletter-form";

export const metadata: Metadata = { title: "Newsletter" };

export default function NewsletterPage() {
  return (
    <div className="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6">
      <h1 className="font-serif text-3xl font-semibold sm:text-4xl">
        Une recette par semaine, dans ta boîte mail
      </h1>
      <p className="mt-4 text-muted">
        Pas de spam, juste une nouvelle idée gourmande et compatible régime
        chaque semaine.
      </p>
      <div className="mt-8">
        <NewsletterForm />
      </div>
    </div>
  );
}
