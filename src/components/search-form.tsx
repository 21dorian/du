"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

export default function SearchForm({ className = "" }: { className?: string }) {
  const router = useRouter();
  const [value, setValue] = useState("");

  return (
    <form
      className={`flex items-center gap-2 ${className}`}
      onSubmit={(e) => {
        e.preventDefault();
        const q = value.trim();
        if (q) router.push(`/recherche?q=${encodeURIComponent(q)}`);
      }}
    >
      <input
        type="search"
        name="q"
        value={value}
        onChange={(e) => setValue(e.target.value)}
        placeholder="Rechercher une recette…"
        className="w-full rounded-full border border-border bg-surface px-4 py-2 text-sm outline-none focus:border-accent"
        aria-label="Rechercher une recette"
      />
      <button
        type="submit"
        className="shrink-0 rounded-full bg-accent px-4 py-2 text-sm font-medium text-accent-foreground transition hover:opacity-90"
      >
        Chercher
      </button>
    </form>
  );
}
