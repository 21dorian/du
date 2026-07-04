"use client";

import Link from "next/link";
import { useState } from "react";
import SearchForm from "@/components/search-form";
import type { WPTerm } from "@/lib/wp";

export default function MobileNav({ categories }: { categories: WPTerm[] }) {
  const [open, setOpen] = useState(false);

  return (
    <div className="md:hidden">
      <button
        onClick={() => setOpen((o) => !o)}
        aria-label={open ? "Fermer le menu" : "Ouvrir le menu"}
        aria-expanded={open}
        className="flex h-10 w-10 items-center justify-center rounded-full border border-border"
      >
        <span className="sr-only">Menu</span>
        {open ? "✕" : "☰"}
      </button>
      {open && (
        <div className="absolute inset-x-0 top-full z-40 border-b border-border bg-background px-4 pb-6 pt-4 shadow-lg">
          <SearchForm className="mb-4" />
          <nav className="flex flex-col gap-1">
            {categories.map((cat) => (
              <Link
                key={cat.id}
                href={`/categorie/${cat.slug}`}
                onClick={() => setOpen(false)}
                className="rounded-lg px-3 py-2 text-base font-medium hover:bg-accent-soft"
              >
                {cat.name}
              </Link>
            ))}
            <Link
              href="/newsletter"
              onClick={() => setOpen(false)}
              className="rounded-lg px-3 py-2 text-base font-medium hover:bg-accent-soft"
            >
              Newsletter
            </Link>
          </nav>
        </div>
      )}
    </div>
  );
}
