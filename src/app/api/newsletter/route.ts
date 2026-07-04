import { NextResponse } from "next/server";

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export async function POST(request: Request) {
  const { email } = await request.json().catch(() => ({ email: undefined }));

  if (typeof email !== "string" || !EMAIL_RE.test(email)) {
    return NextResponse.json({ error: "Adresse email invalide." }, { status: 400 });
  }

  const apiKey = process.env.NEWSLETTER_API_KEY;
  if (!apiKey) {
    console.warn("NEWSLETTER_API_KEY absente : inscription non transmise à un provider.");
    return NextResponse.json({ error: "Newsletter pas encore configurée." }, { status: 503 });
  }

  // Buttondown API — swap this block if a different provider is chosen.
  const res = await fetch("https://api.buttondown.email/v1/subscribers", {
    method: "POST",
    headers: {
      Authorization: `Token ${apiKey}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ email }),
  });

  if (!res.ok && res.status !== 409) {
    return NextResponse.json({ error: "Inscription impossible pour le moment." }, { status: 502 });
  }

  return NextResponse.json({ ok: true });
}
