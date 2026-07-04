"use client";

import Giscus from "@giscus/react";

export default function Comments() {
  const repo = process.env.NEXT_PUBLIC_GISCUS_REPO;
  const repoId = process.env.NEXT_PUBLIC_GISCUS_REPO_ID;
  const category = process.env.NEXT_PUBLIC_GISCUS_CATEGORY;
  const categoryId = process.env.NEXT_PUBLIC_GISCUS_CATEGORY_ID;

  if (!repo || !repoId || !categoryId) {
    return (
      <p className="rounded-xl border border-dashed border-border p-4 text-sm text-muted">
        Les commentaires seront activés dès que Giscus sera configuré
        (variables NEXT_PUBLIC_GISCUS_*).
      </p>
    );
  }

  return (
    <Giscus
      repo={repo as `${string}/${string}`}
      repoId={repoId}
      category={category}
      categoryId={categoryId}
      mapping="pathname"
      strict="0"
      reactionsEnabled="1"
      emitMetadata="0"
      inputPosition="top"
      theme="preferred_color_scheme"
      lang="fr"
      loading="lazy"
    />
  );
}
