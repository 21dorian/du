<?php
declare(strict_types=1);

/* Tiroir d'achat (sur la page de l'événement) : panier en direct, commande, paiement ; lien de connexion rapide ; noms des billets après l'achat. */

require_once APP . '/controllers/public.php';

function drawer_fmt(array $sum): array
{
    return ['lines' => array_map(fn($l) => ['name' => $l['name'], 'qty' => $l['qty'], 'kind' => $l['kind'], 'total' => money($l['qty'] * $l['unit_price'])], $sum['lines']),
        'subtotal' => money($sum['subtotal']), 'discount' => $sum['discount'] - (int)($sum['group']['amount'] ?? 0) > 0 ? money($sum['discount'] - (int)$sum['group']['amount']) : null,
        'group' => !empty($sum['group']['amount']) ? ['pct' => $sum['group']['pct'], 'amount' => money($sum['group']['amount'])] : null,
        'group_next' => !empty($sum['group']['next']) ? ['missing' => $sum['group']['next']['min'] - $sum['group']['count'], 'pct' => $sum['group']['next']['pct']] : null, 'fees' => $sum['fees'] ? money($sum['fees']) : null,
        'donation' => $sum['donation'] ? money($sum['donation']) : null, 'total' => money($sum['total']), 'total_cents' => $sum['total'], 'places' => $sum['places'],
        'promo' => $sum['promo']['code'] ?? null,
        'promo_note' => !empty($sum['promo']['min_qty']) && $sum['places'] < (int)$sum['promo']['min_qty'] ? 'Le code ' . $sum['promo']['code'] . ' s\'applique dès ' . (int)$sum['promo']['min_qty'] . ' billets.' : null];
}

/** Champs de l'étape « Toi » qui dépendent des billets choisis (questions de l'organisateur, noms facultatifs). */
function drawer_fields_html(array $ev, array $sum): string
{
    $fields = applicable_fields($ev, array_map(fn($l) => $l['rate_id'], array_filter($sum['lines'], fn($l) => $l['kind'] === 'rate')));
    $orderFields = array_values(array_filter($fields, fn($f) => !$f['per_ticket']));
    $ticketFields = array_values(array_filter($fields, fn($f) => $f['per_ticket']));
    $h = '';
    foreach ($orderFields as $f) $h .= custom_field_html($f, 'f[' . $f['id'] . ']', '');
    $blocks = ''; $required = (bool)array_filter($ticketFields, fn($f) => $f['required']);
    $total = 0;
    foreach ($sum['lines'] as $l) {
        if ($l['kind'] !== 'rate') continue;
        $tf = array_values(array_filter($ticketFields, fn($f) => !$f['only'] || in_array($l['rate_id'], $f['only'], true)));
        $n = $l['qty'] * $l['pack'];
        for ($i = 0; $i < $n; $i++) {
            $total++;
            $p = 'h[' . $l['rate_id'] . '][' . $i . ']';
            $blocks .= '<div class="dw-holder"><div class="dw-holder-title">' . e($l['name']) . ($n > 1 ? ' n°' . ($i + 1) : '') . '</div><div class="grid2">'
                . '<input name="' . $p . '[first_name]" placeholder="Prénom" autocomplete="off" aria-label="Prénom"><input name="' . $p . '[last_name]" placeholder="Nom" autocomplete="off" aria-label="Nom"></div>';
            foreach ($tf as $f) $blocks .= custom_field_html($f, $p . '[f][' . $f['id'] . ']', '');
            $blocks .= '</div>';
        }
    }
    $h .= Buyers::ageFieldsHtml(Buyers::requiredAge($ev, array_values(array_map(fn($l) => $l['rate_id'], array_filter($sum['lines'], fn($l) => $l['kind'] === 'rate')))));
    if ($total > 1 || $required) {
        $h .= '<details class="dw-names"' . ($required ? ' open' : '') . '><summary>' . ($required ? 'Infos pour chaque billet (demandées par l\'organisateur)' : 'Mettre les noms des participants maintenant <small>(facultatif)</small>') . '</summary>'
            . ($required ? '' : '<p class="muted">Sinon tes billets seront à ton nom : tu pourras les renommer après l\'achat, depuis l\'e-mail de confirmation.</p>') . $blocks . '</details>';
    }
    return $h;
}

function drawer_cart(string $slug): never
{
    [$ev, $org] = public_event($slug);
    $cart = cart_get((int)$ev['id']);
    $a = (string)input('action');
    $msg = null; $errors = [];
    if ($a === 'code') {
        $r = Tickets::findPromo((int)$ev['id'], strtoupper(trim((string)input('code'))));
        if (!empty($r['error']) || empty($r['promo'])) $errors[] = ($r['error'] ?? 'Code inconnu.') . '';
        elseif ($r['promo']['kind'] === 'unlock') { $cart['unlocked'] = array_values(array_unique(array_merge($cart['unlocked'] ?? [], $r['promo']['rate_list']))); $msg = 'unlock'; }
        else { $cart['promo'] = $r['promo']['code']; $msg = 'Code « ' . $r['promo']['code'] . ' » appliqué.'; }
    } elseif ($a === 'remove_code') {
        $cart['promo'] = '';
    } else {
        $keep = $cart;
        fill_cart_from_input($ev, $cart, $_POST);
        $cart['promo'] = $keep['promo']; $cart['unlocked'] = $keep['unlocked'] ?? [];
        foreach (['waitlist', 'partner'] as $k) if (isset($keep[$k])) $cart[$k] = $keep[$k];
        if (!$cart['q']) $errors[] = 'Prends au moins un billet';
        elseif ($a === 'start') Traffic::hit($ev, 'carts');
    }
    cart_set((int)$ev['id'], $cart);
    $sum = cart_summary($ev, $org, $cart);
    json_out(['ok' => !$errors, 'errors' => $errors, 'message' => $msg, 'summary' => drawer_fmt($sum), 'fields_html' => $cart['q'] ? drawer_fields_html($ev, $sum) : '']);
}

function drawer_order(string $slug): never
{
    [$ev, $org] = public_event($slug);
    $cart = cart_get((int)$ev['id']);
    $keep = $cart;
    fill_cart_from_input($ev, $cart, $_POST);
    $cart['promo'] = $keep['promo']; $cart['unlocked'] = $keep['unlocked'] ?? [];
    foreach (['waitlist', 'partner'] as $k) if (isset($keep[$k])) $cart[$k] = $keep[$k];
    cart_set((int)$ev['id'], $cart);
    if (!$cart['q']) json_out(['ok' => false, 'errors' => ['Ton panier est vide.']]);
    $session = cart_session($ev, $cart);
    $sum = cart_summary($ev, $org, $cart);
    $fields = applicable_fields($ev, array_keys($cart['q']));
    $waitlist = $cart['waitlist'] ? one("SELECT * FROM waitlist WHERE id = ? AND status = 'offered' AND expires_at > NOW()", [$cart['waitlist']]) : null;
    $partner = $cart['partner'] ? one('SELECT * FROM partners WHERE id = ? AND active = 1', [$cart['partner']]) : null;
    $r = place_order($ev, $org, $cart, $session, $sum, array_values(array_filter($fields, fn($f) => !$f['per_ticket'])), array_values(array_filter($fields, fn($f) => $f['per_ticket'])), $waitlist, $partner);
    if ($r['errors']) json_out(['ok' => false, 'errors' => $r['errors']]);
    $o = one('SELECT * FROM orders WHERE id = ?', [$r['order']['id']]);
    if (!empty($r['free'])) json_out(['ok' => true, 'free' => true, 'url' => url($r['url'])]);
    $providers = Payments::available();
    $items = all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
    json_out(['ok' => true, 'free' => false, 'url' => url($r['url']), 'expires_at' => date('c', strtotime((string)$o['expires_at'])),
        'pay_html' => view('public/_pay_embed', compact('o', 'ev', 'providers', 'items'))]);
}

// ---------- Connexion rapide (lien magique) ----------
function buyer_sig(string $email, int $exp): string { return substr(hash_hmac('sha256', 'acheteur|' . $email . '|' . $exp, (string)config('secret', 'entrevo')), 0, 24); }

function buyer_link_request(): never
{
    $email = strtolower(trim((string)input('email')));
    $back = (string)input('back');
    if (!preg_match('~^/[^/]~', $back)) $back = '/';
    if (is_email($email) && (val("SELECT id FROM orders WHERE LOWER(email) = ? AND status IN ('paid','refunded') LIMIT 1", [$email]) || val('SELECT id FROM tickets WHERE holder_email = ? LIMIT 1', [$email])) && throttle_ok('lien-' . $email)) {
        $exp = time() + 1800;
        $tok = rtrim(strtr(base64_encode($email . '|' . $exp . '|' . buyer_sig($email, $exp)), '+/', '-_'), '=');
        try { Mailer::send($email, 'Ton lien de connexion ' . setting('platform_name'), '<p>Clique ici pour retrouver tes infos préremplies (valable 30 minutes) :</p><p><a href="' . e(abs_url('/acheteur/' . $tok, ['r' => $back])) . '" style="display:inline-block;background:#0071e3;color:#fff;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:bold">Me connecter</a></p><p style="color:#777;font-size:13px">Pas toi ? Ignore cet e-mail.</p>'); }
        catch (Throwable $e) { log_line('emails', $e->getMessage()); }
    }
    json_out(['ok' => true, 'message' => 'Si tu as déjà acheté avec cette adresse, un lien vient de partir. Il est valable 30 minutes.']);
}

function throttle_ok(string $k): bool
{
    $key = 'thr_' . md5($k);
    $n = $_SESSION[$key] ?? 0;
    if ($n >= 3) return false;
    $_SESSION[$key] = $n + 1;
    return true;
}

function buyer_link(string $token): never
{
    $raw = base64_decode(strtr($token, '-_', '+/'), true) ?: '';
    [$email, $exp, $sig] = array_pad(explode('|', $raw), 3, '');
    $back = (string)input('r');
    if (!preg_match('~^/[^/]~', $back)) $back = '/';
    if (!is_email($email) || (int)$exp < time() || !hash_equals(buyer_sig($email, (int)$exp), $sig)) redirect($back, ['error' => 'Ce lien a expiré. Redemande-en un, ça prend 10 secondes.']);
    $o = one("SELECT first_name, last_name, email, phone FROM orders WHERE LOWER(email) = ? AND status IN ('paid','refunded') ORDER BY id DESC LIMIT 1", [$email]);
    $_SESSION['buyer'] = $o ?: ['email' => $email];
    $_SESSION['buyer_email'] = strtolower($email); // espace « Mes billets » ouvert
    if (str_starts_with($back, '/mes-billets')) redirect('/mes-billets', ['success' => 'Bienvenue dans ton espace' . (!empty($o['first_name']) ? ', ' . $o['first_name'] : '') . ' !']);
    redirect($back . (str_contains($back, '#') ? '' : '#acheter'), ['success' => 'Re-bonjour ' . ($o['first_name'] ?? '') . ' ! Tes infos sont préremplies.']);
}

// ---------- Noms des billets après l'achat ----------
function order_names(string $ref): never
{
    $o = order_by_ref($ref);
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $back = '/commande/' . $o['ref'] . '?t=' . $o['token'];
    if ($o['status'] !== 'paid' || strtotime((string)($ev['ends_at'] ?: $ev['starts_at'])) < time()) redirect($back, ['error' => 'Les noms ne peuvent plus être modifiés.']);
    $n = 0;
    foreach ((array)($_POST['t'] ?? []) as $tid => $h) {
        $t = one("SELECT * FROM tickets WHERE id = ? AND order_id = ? AND status = 'valid' AND entries = 0 AND holder_email IS NULL", [(int)$tid, $o['id']]);
        if (!$t) continue;
        $fn = mb_substr(trim((string)($h['first_name'] ?? '')), 0, 120); $ln = mb_substr(trim((string)($h['last_name'] ?? '')), 0, 120);
        if ($fn === '' || $ln === '' || ($fn === $t['first_name'] && $ln === $t['last_name'])) continue;
        update('tickets', ['first_name' => $fn, 'last_name' => $ln, 'updated_at' => now()], 'id = ?', [$t['id']]);
        $n++;
    }
    if ($n && input('send')) { try { Mailer::sendOrder((int)$o['id']); } catch (Throwable $e) { log_line('emails', $e->getMessage()); } }
    redirect($back, ['success' => $n ? '' . $n . ' billet(s) renommé(s). Les PDF sont à jour' . (input('send') ? ' et renvoyés par e-mail.' : '.') : 'Rien n\'a changé']);
}
