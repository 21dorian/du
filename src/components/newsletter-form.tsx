"use client";

import { useState } from "react";

export default function NewsletterForm() {
  const [email, setEmail] = useState("");
  const [status, setStatus] = useState<"idle" | "loading" | "done" | "error">("idle");
  const [error, setError] = useState("");

  return (
    <form
      className="flex flex-col gap-3 sm:flex-row"
      onSubmit={async (e) => {
        e.preventDefault();
        setStatus("loading");
        setError("");
        try {
          const res = await fetch("/api/newsletter", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email }),
          });
          if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            throw new Error(body.error || "Une erreur est survenue.");
          }
          setStatus("done");
        } catch (err) {
          setStatus("error");
          setError(err instanceof Error ? err.message : "Une erreur est survenue.");
        }
      }}
    >
      {status === "done" ? (
        <p className="rounded-xl bg-accent-soft px-4 py-3 text-sm text-accent">
          Merci ! Vérifie ta boîte mail pour confirmer ton inscription.
        </p>
      ) : (
        <>
          <input
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="ton@email.fr"
            className="flex-1 rounded-full border border-border bg-surface px-4 py-3 text-sm outline-none focus:border-accent"
          />
          <button
            type="submit"
            disabled={status === "loading"}
            className="shrink-0 rounded-full bg-accent px-6 py-3 text-sm font-medium text-accent-foreground transition hover:opacity-90 disabled:opacity-60"
          >
            {status === "loading" ? "Inscription…" : "Je m'inscris"}
          </button>
        </>
      )}
      {status === "error" && <p className="text-sm text-red-600">{error}</p>}
    </form>
  );
}
