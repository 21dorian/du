<?php
declare(strict_types=1);

/* Site public : pages « Bientôt disponible » (avec « préviens-moi ») et page vitrine pour les organisateurs. */

function public_soon(string $key): string
{
    $f = Features::getPublic($key) ?? (($g = Features::get($key)) && $g['status'] === 'soon' ? $g : null);
    if (!$f) abort(404);
    $count = (int)val('SELECT COUNT(*) FROM public_interest WHERE feature = ?', [$key]);
    if (is_post()) {
        $email = strtolower(trim((string)input('email')));
        if ($email !== '' && !is_email($email)) redirect('/bientot/' . $key, ['error' => 'Cet e-mail a l\'air bancal 🤔']);
        $ipHash = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d') . config('secret', '')), 0, 16);
        if ($email === '' && val('SELECT id FROM public_interest WHERE feature = ? AND email IS NULL AND ip_hash = ?', [$key, $ipHash])) redirect('/bientot/' . $key, ['info' => 'C\'est déjà noté 😉']);
        q('INSERT IGNORE INTO public_interest (feature, email, created_at, ip_hash) VALUES (?, ?, NOW(), ?)', [$key, $email ?: null, $ipHash]);
        redirect('/bientot/' . $key, ['success' => $email ? '📬 Promis, on t\'écrit dès que c\'est prêt (et seulement pour ça).' : '🙌 Merci, ton vote compte !']);
    }
    $others = array_slice(array_filter(Features::public(), fn($x, $k) => $k !== $key, ARRAY_FILTER_USE_BOTH), 0, 6, true);
    return view('layout_public', ['title' => $f['name'] . ' · bientôt disponible', 'content' => view('public/soon', compact('f', 'key', 'count', 'others'))]);
}

function organizers_page(): string
{
    [$pct, $fixed] = [(float)setting('fee_percent', 0), (int)setting('fee_fixed', 0)];
    $stats = ['events' => (int)val("SELECT COUNT(*) FROM events WHERE status IN ('published','closed')"), 'tickets' => (int)val("SELECT COUNT(*) FROM tickets WHERE status = 'valid'"),
        'orgs' => (int)val("SELECT COUNT(*) FROM organizers WHERE status = 'active'")];
    $all = Features::all();
    $live = array_filter($all, fn($f) => $f['status'] !== 'soon' && empty($f['extra']));
    $soon = array_filter($all, fn($f) => $f['status'] === 'soon' && empty($f['extra']));
    return view('layout_public', ['title' => 'Pour les organisateurs', 'description' => 'Billetterie en ligne et contrôle d\'accès pour tes événements : sans abonnement, paiement sécurisé, scan hors ligne.',
        'content' => view('public/organizers_landing', compact('pct', 'fixed', 'stats', 'live', 'soon'))]);
}
