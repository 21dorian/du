<?php
declare(strict_types=1);

function pub(string $view, array $vars, string $title, array $extra = []): string
{
    return view('layout_public', ['title' => $title, 'content' => view('public/' . $view, $vars)] + $extra);
}

function public_event(string $slug, bool $gates = true): array
{
    $ev = one('SELECT * FROM events WHERE slug = ?', [$slug]);
    if (!$ev) abort(404, 'Cet événement n\'existe pas. Ou plus. Ou pas encore. 🤔');
    $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
    $u = user();
    $isOwner = $u && ($u['role'] === 'superadmin' || (int)$u['organizer_id'] === (int)$ev['organizer_id']);
    if (($ev['status'] === 'draft' || $org['status'] !== 'active') && !$isOwner) abort(404, 'Cet événement n\'est pas (ou plus) en ligne.');
    if ($gates && !$isOwner) {
        // événement privé protégé par mot de passe
        if ($ev['password'] && empty($_SESSION['evpass'][$ev['id']])) redirect('/e/' . $ev['slug'] . '/acces');
        // file d'attente virtuelle
        if ($ev['queue_enabled'] && !Queue::admitted($ev)) redirect('/file/' . $ev['slug']);
    }
    return [$ev, $org];
}

function event_card_data(array $ev): array
{
    $min = val('SELECT MIN(price) FROM rates WHERE event_id = ? AND active = 1 AND hidden = 0', [$ev['id']]);
    $sessions = (int)val('SELECT COUNT(*) FROM sessions WHERE event_id = ? AND active = 1 AND starts_at >= NOW() - INTERVAL 6 HOUR', [$ev['id']]);
    return $ev + ['min_price' => $min === null ? null : (int)$min, 'session_count' => $sessions];
}

function capture_utm(): void
{
    foreach (['source', 'medium', 'campaign'] as $k) if (!empty($_GET['utm_' . $k])) $_SESSION['utm'][$k] = mb_substr((string)$_GET['utm_' . $k], 0, 120);
    if (!empty($_GET['ref']) && empty($_SESSION['utm']['source'])) $_SESSION['utm']['source'] = mb_substr((string)$_GET['ref'], 0, 120);
}

function home(): string
{
    capture_utm();
    $q = trim((string)input('q'));
    $cat = trim((string)input('cat'));
    $sql = "SELECT e.*, o.name AS org_name FROM events e JOIN organizers o ON o.id = e.organizer_id
            WHERE e.status = 'published' AND e.password IS NULL AND o.status = 'active' AND COALESCE(e.ends_at, e.starts_at) >= NOW() - INTERVAL 6 HOUR";
    $params = [];
    if ($q !== '') { $sql .= ' AND (e.name LIKE ? OR e.city LIKE ? OR e.venue LIKE ? OR o.name LIKE ?)'; $params = array_fill(0, 4, '%' . $q . '%'); }
    if ($cat !== '') { $sql .= ' AND e.category = ?'; $params[] = $cat; }
    $events = array_map('event_card_data', all($sql . ' ORDER BY e.starts_at LIMIT 80', $params));
    $cats = q("SELECT DISTINCT category FROM events WHERE status = 'published' AND category IS NOT NULL AND category <> '' AND COALESCE(ends_at, starts_at) >= NOW() ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    return pub('home', ['events' => $events, 'q' => $q, 'cat' => $cat, 'cats' => $cats], '');
}

function organizer_page(string $slug): string
{
    $org = one("SELECT * FROM organizers WHERE slug = ? AND status = 'active'", [$slug]);
    if (!$org) abort(404);
    $events = array_map('event_card_data', all("SELECT e.*, ? AS org_name FROM events e WHERE organizer_id = ? AND status = 'published' AND password IS NULL
        AND COALESCE(ends_at, starts_at) >= NOW() - INTERVAL 6 HOUR ORDER BY starts_at", [$org['name'], $org['id']]));
    return pub('organizer', ['org' => $org, 'events' => $events], $org['name'], ['widget_ok' => true, 'org_tracking' => $org]);
}

function cart_get(int $eventId): array
{
    return ($_SESSION['cart'][$eventId] ?? []) + ['session' => null, 'q' => [], 'prices' => [], 'products' => [], 'donation' => 0, 'seats' => [],
        'promo' => '', 'unlocked' => [], 'partner' => null, 'waitlist' => null];
}
function cart_set(int $eventId, array $cart): void { $_SESSION['cart'][$eventId] = $cart; }

/** Tarifs visibles pour l'acheteur (dont les tarifs cachés débloqués par un code). */
function visible_rates(array $ev, array $cart, ?array $session): array
{
    $rates = all('SELECT * FROM rates WHERE event_id = ? AND active = 1 ORDER BY sort, id', [$ev['id']]);
    $unlocked = array_map('intval', $cart['unlocked'] ?? []);
    $out = [];
    foreach ($rates as $r) {
        if ($r['hidden'] && !in_array((int)$r['id'], $unlocked, true)) continue;
        if ($session && ($only = Tickets::rateSessions($r)) && !in_array((int)$session['id'], $only, true)) continue;
        $r['sale'] = Tickets::onSale($r, $ev, $session, (int)($cart['waitlist'] ?? 0));
        $out[] = $r;
    }
    return $out;
}

function cart_session(array $ev, array $cart): ?array
{
    if (empty($cart['session'])) return null;
    return one('SELECT * FROM sessions WHERE id = ? AND event_id = ? AND active = 1', [(int)$cart['session'], $ev['id']]);
}

function event_page(string $slug): string
{
    [$ev, $org] = public_event($slug);
    capture_utm();
    $cart = cart_get((int)$ev['id']);
    $sessions = Tickets::sessions((int)$ev['id']);
    $sessions = array_values(array_filter($sessions, fn($x) => strtotime((string)($x['ends_at'] ?: $x['starts_at'])) >= time()));
    if ($sessions) {
        $sid = (int)input('seance', $cart['session'] ?? 0);
        $ids = array_map(fn($x) => (int)$x['id'], $sessions);
        if (!in_array($sid, $ids, true)) $sid = count($sessions) === 1 ? $ids[0] : 0;
        if ((int)($cart['session'] ?? 0) !== $sid) { $cart['session'] = $sid ?: null; $cart['q'] = []; $cart['seats'] = []; cart_set((int)$ev['id'], $cart); }
        foreach ($sessions as &$x) {
            $cap = Tickets::capacity($ev, $x);
            $x['left'] = $cap > 0 ? max(0, $cap - Tickets::eventUsed((int)$ev['id'], (int)$x['id'])) : null;
        }
        unset($x);
    }
    $session = cart_session($ev, $cart);
    $rates = ($sessions && !$session) ? [] : visible_rates($ev, $cart, $session);
    $products = all('SELECT * FROM products WHERE event_id = ? AND active = 1 ORDER BY sort, id', [$ev['id']]);
    foreach ($products as &$pr) $pr['left'] = Tickets::productRemaining($pr);
    unset($pr);
    $promo = $cart['promo'] ? (Tickets::findPromo((int)$ev['id'], $cart['promo'])['promo'] ?? null) : null;
    $soldOut = $rates && !array_filter($rates, fn($r) => $r['sale']['ok']) && array_filter($rates, fn($r) => ($r['sale']['why'] ?? '') === 'Complet 🫠');
    $resales = $ev['resale_enabled'] ? all("SELECT x.*, t.rate_name, t.seat FROM resales x JOIN tickets t ON t.id = x.ticket_id
        WHERE x.event_id = ? AND x.status = 'listed'" . ($session ? ' AND t.session_id = ' . (int)$session['id'] : '') . ' ORDER BY x.price, x.id LIMIT 30', [$ev['id']]) : [];
    $seatmap = Seating::map($ev) ? true : false;
    $img = $ev['image'] ? abs_url($ev['image']) : null;
    return pub('event', compact('ev', 'org', 'rates', 'cart', 'promo', 'sessions', 'session', 'products', 'soldOut', 'resales', 'seatmap'), $ev['name'], [
        'description' => mb_substr(strip_tags((string)$ev['description']), 0, 160), 'og_image' => $img, 'widget_ok' => true, 'org_tracking' => $org,
    ]);
}

/** Plan de salle (JSON) pour le choix des places. */
function seat_data(string $slug): never
{
    [$ev] = public_event($slug, false);
    $cart = cart_get((int)$ev['id']);
    json_out(Seating::payload($ev, $cart['session'] ? (int)$cart['session'] : null) + ['mine' => array_merge(...array_values($cart['seats'] ?: [[]]))]);
}

function event_select(string $slug): never
{
    [$ev] = public_event($slug);
    $cart = cart_get((int)$ev['id']);
    $back = '/e/' . $slug . ($cart['session'] ? '?seance=' . (int)$cart['session'] : '');
    if (input('action') === 'code') {
        $code = strtoupper(trim((string)input('code')));
        $r = Tickets::findPromo((int)$ev['id'], $code);
        if (!empty($r['error']) || empty($r['promo'])) redirect($back, ['error' => ($r['error'] ?? 'Code inconnu.') . ' 🙅']);
        $p = $r['promo'];
        if ($p['kind'] === 'unlock') {
            $cart['unlocked'] = array_values(array_unique(array_merge($cart['unlocked'] ?? [], $p['rate_list'])));
            cart_set((int)$ev['id'], $cart);
            redirect($back, ['success' => '🤫 Code accepté : un tarif secret vient d\'apparaître.']);
        }
        $cart['promo'] = $p['code'];
        cart_set((int)$ev['id'], $cart);
        redirect($back, ['success' => '🎉 Code « ' . $p['code'] . ' » appliqué. Ton portefeuille te remercie.']);
    }
    if (input('action') === 'remove_code') { $cart['promo'] = ''; cart_set((int)$ev['id'], $cart); redirect($back); }
    fill_cart_from_input($ev, $cart, $_POST);
    if (!$cart['q']) redirect($back, ['error' => 'Prends au moins un billet, sinon ça va être compliqué d\'entrer 😅']);
    cart_set((int)$ev['id'], $cart);
    redirect('/e/' . $slug . '/infos');
}

/** Panier depuis le widget (lien GET ouvert dans un nouvel onglet : pas de cookie tiers nécessaire). */
function widget_cart(string $slug): never
{
    [$ev] = public_event($slug);
    $cart = cart_get((int)$ev['id']);
    $_SESSION['utm']['source'] = $_SESSION['utm']['source'] ?? 'widget';
    fill_cart_from_input($ev, $cart, $_GET);
    cart_set((int)$ev['id'], $cart);
    if (!$cart['q']) redirect('/e/' . $slug);
    if (Seating::map($ev)) redirect('/e/' . $slug . ($cart['session'] ? '?seance=' . (int)$cart['session'] : ''), ['info' => '💺 Choisis tes places sur le plan.']);
    redirect('/e/' . $slug . '/infos');
}

function fill_cart_from_input(array $ev, array &$cart, array $in): void
{
    if (!empty($in['seance'])) $cart['session'] = (int)$in['seance'];
    $cart['q'] = [];
    foreach ((array)($in['qty'] ?? []) as $rid => $n) if ((int)$n > 0) $cart['q'][(int)$rid] = min(50, (int)$n);
    $cart['prices'] = [];
    foreach ((array)($in['price'] ?? []) as $rid => $v) $cart['prices'][(int)$rid] = parse_money($v);
    $cart['products'] = [];
    foreach ((array)($in['product'] ?? []) as $pid => $n) if ((int)$n > 0) $cart['products'][(int)$pid] = min(50, (int)$n);
    $cart['donation'] = !empty($ev['donation_enabled']) ? parse_money($in['donation'] ?? 0) : 0;
    $cart['seats'] = [];
    foreach ((array)($in['seats'] ?? []) as $rid => $list) {
        $list = is_array($list) ? $list : explode(',', (string)$list);
        $cart['seats'][(int)$rid] = array_values(array_filter(array_map(fn($x) => preg_replace('~[^A-Za-z0-9-]~', '', (string)$x), $list)));
    }
}

/** Calcule le récapitulatif (sans créer de commande). */
function cart_summary(array $ev, array $org, array $cart): array
{
    $lines = [];
    foreach ($cart['q'] as $rid => $qty) {
        $r = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [$rid, $ev['id']]);
        if (!$r) continue;
        $price = (int)$r['price'];
        if ($r['price_mode'] === 'free') $price = max($price, (int)($cart['prices'][$rid] ?? $price));
        if (!empty($cart['partner'])) { $pa = one('SELECT * FROM partners WHERE id = ?', [$cart['partner']]); if ($pa && $pa['free']) $price = 0; }
        $lines[] = ['kind' => 'rate', 'rate_id' => (int)$r['id'], 'name' => $r['name'], 'qty' => (int)$qty, 'unit_price' => $price, 'pack' => max(1, (int)$r['pack_size']),
            'seats' => $cart['seats'][$rid] ?? []];
    }
    foreach ($cart['products'] as $pid => $qty) {
        $pr = one('SELECT * FROM products WHERE id = ? AND event_id = ?', [$pid, $ev['id']]);
        if ($pr) $lines[] = ['kind' => 'product', 'rate_id' => 0, 'name' => $pr['name'], 'qty' => (int)$qty, 'unit_price' => (int)$pr['price'], 'pack' => 0, 'seats' => []];
    }
    $promo = $cart['promo'] ? (Tickets::findPromo((int)$ev['id'], $cart['promo'])['promo'] ?? null) : null;
    $subtotal = array_sum(array_map(fn($l) => $l['qty'] * $l['unit_price'], $lines));
    $discount = Tickets::discount($promo, $lines);
    $paidUnits = array_sum(array_map(fn($l) => $l['kind'] === 'rate' && $l['unit_price'] > 0 ? $l['qty'] : 0, $lines));
    $fees = Tickets::computeFees($org, $subtotal - $discount, $paidUnits);
    $buyerFees = $org['fee_mode'] === 'buyer' ? $fees : 0;
    $donation = (int)($cart['donation'] ?? 0);
    return ['lines' => $lines, 'promo' => $promo, 'subtotal' => $subtotal, 'discount' => $discount, 'fees' => $buyerFees, 'donation' => $donation,
        'total' => $subtotal - $discount + $buyerFees + $donation, 'places' => array_sum(array_map(fn($l) => $l['qty'] * $l['pack'], $lines))];
}

/** Questions du formulaire applicables (toutes, ou seulement celles liées aux tarifs du panier). */
function applicable_fields(array $ev, array $rateIds): array
{
    $out = [];
    foreach (all('SELECT * FROM form_fields WHERE event_id = ? ORDER BY sort, id', [$ev['id']]) as $f) {
        $only = array_filter(array_map('intval', explode(',', (string)$f['rate_ids'])));
        $f['only'] = $only;
        if ($only && !array_intersect($only, $rateIds)) continue;
        $out[] = $f;
    }
    return $out;
}

function checkout(string $slug): string
{
    [$ev, $org] = public_event($slug);
    $cart = cart_get((int)$ev['id']);
    if (!$cart['q']) redirect('/e/' . $slug);
    $session = cart_session($ev, $cart);
    $sum = cart_summary($ev, $org, $cart);
    $fields = applicable_fields($ev, array_keys($cart['q']));
    $orderFields = array_values(array_filter($fields, fn($f) => !$f['per_ticket']));
    $ticketFields = array_values(array_filter($fields, fn($f) => $f['per_ticket']));
    $waitlist = $cart['waitlist'] ? one("SELECT * FROM waitlist WHERE id = ? AND status = 'offered' AND expires_at > NOW()", [$cart['waitlist']]) : null;
    $partner = $cart['partner'] ? one('SELECT * FROM partners WHERE id = ? AND active = 1', [$cart['partner']]) : null;
    $errors = [];
    $old = $_POST + ($waitlist && !is_post() ? ['email' => $waitlist['email'], 'email2' => $waitlist['email'], 'first_name' => $waitlist['first_name'], 'last_name' => $waitlist['last_name']] : []);

    if (is_post()) {
        $buyer = ['email' => trim((string)input('email')), 'first_name' => trim((string)input('first_name')),
            'last_name' => trim((string)input('last_name')), 'phone' => trim((string)input('phone'))];
        if ($buyer['first_name'] === '' || $buyer['last_name'] === '') $errors[] = 'On a besoin de ton prénom et de ton nom.';
        if (!is_email($buyer['email'])) $errors[] = 'Cette adresse e-mail a l\'air bizarre 🤨';
        if ($buyer['email'] !== trim((string)input('email2'))) $errors[] = 'Les deux e-mails ne sont pas pareils. Petite faute de frappe ?';
        if (trim((string)input('website')) !== '') $errors[] = 'Requête refusée.'; // piège à robots
        $orderAnswers = collect_answers($orderFields, (array)($_POST['f'] ?? []), $errors, '');
        $holders = [];
        foreach ($sum['lines'] as $l) {
            if ($l['kind'] !== 'rate') continue;
            $fieldsForRate = array_values(array_filter($ticketFields, fn($f) => !$f['only'] || in_array($l['rate_id'], $f['only'], true)));
            for ($i = 0; $i < $l['qty'] * $l['pack']; $i++) {
                $h = $_POST['h'][$l['rate_id']][$i] ?? [];
                $label = $l['name'] . ' n°' . ($i + 1);
                $fn = trim((string)($h['first_name'] ?? ''));
                $ln = trim((string)($h['last_name'] ?? ''));
                if ($fn === '' || $ln === '') $errors[] = 'Il manque le nom du participant (' . $label . ').';
                $holders[$l['rate_id']][$i] = ['first_name' => $fn, 'last_name' => $ln,
                    'answers' => collect_answers($fieldsForRate, (array)($h['f'] ?? []), $errors, ' (' . $label . ')')];
            }
        }
        if (setting('cgv') && empty($_POST['cgv'])) $errors[] = 'Coche les conditions de vente, on sait que tu les as lues 😉';
        if (!$errors) {
            $r = Tickets::createOrder([
                'event' => $ev, 'org' => $org, 'session' => $session, 'quantities' => $cart['q'], 'prices' => $cart['prices'], 'products' => $cart['products'],
                'donation' => $cart['donation'], 'seats' => $cart['seats'], 'buyer' => $buyer, 'holders' => $holders, 'order_answers' => $orderAnswers,
                'promo' => $cart['promo'], 'unlocked' => $cart['unlocked'] ?? [], 'method' => $partner ? 'partner' : 'online', 'partner' => $partner,
                'waitlist' => $waitlist, 'utm' => $_SESSION['utm'] ?? [], 'channel' => (($_SESSION['utm']['source'] ?? '') === 'widget') ? 'widget' : null,
            ]);
            if (!empty($r['errors'])) {
                $errors = $r['errors'];
            } else {
                $o = $r['order'];
                $_SESSION['orders'][] = $o['ref'];
                unset($_SESSION['cart'][$ev['id']]);
                if ((int)$o['total'] === 0) {
                    update('orders', ['provider' => 'free'], 'id = ?', [$o['id']]);
                    Tickets::confirm((int)$o['id']);
                    redirect('/commande/' . $o['ref'] . '?t=' . $o['token']);
                }
                redirect('/paiement/' . $o['ref'] . '?t=' . $o['token']);
            }
        }
    }
    $seatLabels = [];
    if ($map = Seating::map($ev)) foreach ($cart['seats'] as $list) foreach ($list as $k) $seatLabels[$k] = Seating::label($k, $map);
    return pub('checkout', compact('ev', 'org', 'sum', 'orderFields', 'ticketFields', 'errors', 'old', 'session', 'waitlist', 'partner', 'seatLabels'),
        'Tes infos — ' . $ev['name'], ['widget_ok' => true, 'org_tracking' => $org]);
}

function collect_answers(array $fields, array $input, array &$errors, string $suffix): array
{
    $out = [];
    foreach ($fields as $f) {
        $v = $input[$f['id']] ?? '';
        $v = is_array($v) ? '' : trim((string)$v);
        if ($f['type'] === 'checkbox') $v = $v !== '' ? 'Oui' : 'Non';
        if ($f['required'] && ($v === '' || ($f['type'] === 'checkbox' && $v === 'Non'))) $errors[] = 'Champ obligatoire : ' . $f['label'] . $suffix . '.';
        if ($f['type'] === 'select' && $v !== '' && !in_array($v, field_options($f), true)) $errors[] = 'Choix invalide : ' . $f['label'] . $suffix . '.';
        if ($f['type'] === 'email' && $v !== '' && !is_email($v)) $errors[] = 'E-mail invalide : ' . $f['label'] . $suffix . '.';
        $out[$f['id']] = ['label' => $f['label'], 'value' => mb_substr($v, 0, 1000)];
    }
    return $out;
}

function field_options(array $f): array
{
    return array_values(array_filter(array_map('trim', preg_split('~\r?\n~', (string)$f['options']))));
}

function order_by_ref(string $ref, bool $requireToken = true): array
{
    $o = one('SELECT * FROM orders WHERE ref = ?', [$ref]);
    if (!$o) abort(404, 'Commande introuvable 🕵️');
    $t = (string)input('t');
    $mine = in_array($o['ref'], $_SESSION['orders'] ?? [], true);
    $u = user();
    $staff = $u && ($u['role'] === 'superadmin' || ((int)$u['organizer_id'] === (int)$o['organizer_id'] && $u['role'] !== 'scanner'));
    if ($requireToken && !$mine && !$staff && !hash_equals($o['token'], $t)) abort(404, 'Commande introuvable.');
    return $o;
}

function payment_choice(string $ref): string
{
    $o = order_by_ref($ref);
    if ($o['status'] === 'paid') redirect('/commande/' . $o['ref'] . '?t=' . $o['token']);
    if ($o['status'] !== 'pending' || strtotime((string)$o['expires_at']) < time()) {
        return pub('message', ['title' => '⌛ Trop tard…', 'text' => 'Le chrono est fini, tes places sont reparties dans la nature. Rien de grave : recommence, il en reste peut-être.',
            'link' => url('/e/' . val('SELECT slug FROM events WHERE id = ?', [$o['event_id']])), 'link_label' => 'Je retente ma chance'], 'Commande expirée');
    }
    $providers = Payments::available();
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $error = null;
    if (is_post() || count($providers) === 1) {
        $p = is_post() ? (string)input('provider') : (string)array_key_first($providers);
        if (!isset($providers[$p])) $error = 'Choisis comment tu payes 💳';
        else {
            try { $to = Payments::start($p, $o, $ev); header('Location: ' . $to, true, 303); exit; }
            catch (Throwable $e) { $error = $e->getMessage(); log_line('paiements', $o['ref'] . ' : ' . $e->getMessage()); }
        }
    }
    $items = all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
    return pub('payment', compact('o', 'ev', 'providers', 'items', 'error'), 'Paiement');
}

function payment_demo(string $ref): string
{
    $o = order_by_ref($ref);
    if (setting('demo_enabled') !== '1' || $o['provider'] !== 'demo') abort(404);
    if (is_post()) {
        Tickets::confirm((int)$o['id'], ['provider_payment' => 'demo']);
        
        redirect('/commande/' . $o['ref'] . '?t=' . $o['token']);
    }
    return pub('payment_demo', ['o' => $o], 'Paiement de démonstration');
}

function payment_return(string $ref): never
{
    $o = order_by_ref($ref);
    if ($o['status'] !== 'paid') {
        try {
            if (Payments::verify($o)) { Tickets::confirm((int)$o['id']); }
        } catch (Throwable $e) { log_line('paiements', 'Vérification ' . $o['ref'] . ' : ' . $e->getMessage()); }
    }
    redirect('/commande/' . $o['ref'] . '?t=' . $o['token']);
}

function payment_cancel(string $ref): never
{
    $o = order_by_ref($ref);
    redirect('/paiement/' . $o['ref'] . '?t=' . $o['token'], ['warning' => 'Paiement annulé. Pas de souci, tes places t\'attendent encore un peu ⏱️']);
}

function payment_webhook(string $provider): never
{
    try { $res = Payments::webhook($provider); }
    catch (Throwable $e) { $res = 'erreur : ' . $e->getMessage(); log_line('paiements', 'Webhook ' . $provider . ' : ' . $e->getMessage()); }
    insert('webhook_log', ['provider' => mb_substr($provider, 0, 20), 'ref' => null, 'result' => mb_substr($res, 0, 190), 'created_at' => now()]);
    http_response_code(200);
    header('Content-Type: text/plain');
    exit('ok');
}

/** Tâches de fond (à appeler par une tâche cron cPanel toutes les 5 minutes, ou automatiquement par les visites). */
function cron(): never
{
    if (!hash_equals((string)setting('cron_key'), (string)input('cle'))) { http_response_code(403); exit('clé invalide'); }
    $r = Jobs::run(true);
    header('Content-Type: text/plain; charset=utf-8');
    exit('ok ' . json_encode($r));
}

function order_page(string $ref): string
{
    $o = order_by_ref($ref);
    // paiement en attente : on revérifie auprès du prestataire (au plus toutes les 10 s)
    if ($o['status'] === 'pending' && $o['provider'] && !in_array($o['provider'], ['demo', 'free'], true)) {
        $k = 'chk_' . $o['id'];
        if (time() - ($_SESSION[$k] ?? 0) > 10) {
            $_SESSION[$k] = time();
            try { if (Payments::verify($o)) { $o = Tickets::confirm((int)$o['id']); } }
            catch (Throwable $e) { log_line('paiements', $e->getMessage()); }
        }
    }
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
    $tickets = all('SELECT * FROM tickets WHERE order_id = ? ORDER BY id', [$o['id']]);
    $items = all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
    $session = $o['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$o['session_id']]) : null;
    $map = Seating::map($ev);
    $refundable = $ev['refund_request_enabled'] && $o['status'] === 'paid' && !$o['refund_requested_at'] && (int)$o['total'] > 0
        && (!$ev['refund_deadline'] || strtotime($ev['refund_deadline']) > time());
    return pub('order', compact('o', 'ev', 'org', 'tickets', 'items', 'session', 'map', 'refundable'), 'Commande ' . $o['ref']);
}

function order_pdf(string $ref): never
{
    $o = order_by_ref($ref);
    if ($o['status'] !== 'paid') abort(404, 'Tes billets arrivent dès que le paiement est confirmé. Encore un peu de patience ⏳');
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
    $sql = "SELECT * FROM tickets WHERE order_id = ? AND status = 'valid'";
    $params = [$o['id']];
    if (input('billet')) { $sql .= ' AND code = ?'; $params[] = (string)input('billet'); }
    $tickets = all($sql . ' ORDER BY id', $params);
    if (!$tickets) abort(404, 'Aucun billet valide.');
    $pdf = TicketPdf::build($o, $ev, $org, $tickets);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (input('dl') ? 'attachment' : 'inline') . '; filename="billets-' . $o['ref'] . (count($tickets) === 1 && input('billet') ? '-' . $tickets[0]['code'] : '') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function find_tickets(): string
{
    $sent = false;
    if (is_post()) {
        $email = trim((string)input('email'));
        if (is_email($email)) {
            $orders = all("SELECT o.*, e.name AS ev_name, e.starts_at FROM orders o JOIN events e ON e.id = o.event_id
                           WHERE o.email = ? AND o.status = 'paid' ORDER BY o.created_at DESC LIMIT 20", [$email]);
            if ($orders) {
                $list = '';
                foreach ($orders as $o) $list .= '<li style="margin-bottom:8px"><a href="' . e(abs_url('/commande/' . $o['ref'], ['t' => $o['token']])) . '">' . e($o['ev_name']) . '</a> — ' . e(fr_date($o['starts_at'], false)) . ' (commande ' . e($o['ref']) . ')</li>';
                try { Mailer::send($email, '🎟️ Tes billets ' . setting('platform_name'), '<p>Salut 👋</p><p>Tu les cherchais ? Les voilà :</p><ul>' . $list . '</ul><p>Bonne soirée (ou bon match, ou bon concert…) !</p>'); }
                catch (Throwable $e) { log_line('emails', 'Mes billets : ' . $e->getMessage()); }
            }
        }
        $sent = true; // même réponse que l'adresse existe ou non (confidentialité)
    }
    return pub('find_tickets', ['sent' => $sent], 'Retrouver mes billets');
}

function cgv(): string { return pub('text', ['title' => 'Conditions générales de vente', 'text' => (string)setting('cgv')], 'CGV'); }
function legal(): string { return pub('text', ['title' => 'Mentions légales', 'text' => (string)setting('legal')], 'Mentions légales'); }

// ====================== 1.1.0 : accès privé, file d'attente, liste d'attente, partenaires, revente, remboursement, widget ======================

function event_password(string $slug): string
{
    [$ev] = public_event($slug, false);
    $error = null;
    if (is_post()) {
        if ($ev['password'] && hash_equals((string)$ev['password'], trim((string)input('password')))) {
            $_SESSION['evpass'][$ev['id']] = 1;
            redirect('/e/' . $slug);
        }
        $error = 'Mauvais mot de passe 🔒 Demande-le à l\'organisateur.';
    }
    return pub('password', ['ev' => $ev, 'error' => $error], $ev['name'], ['widget_ok' => true]);
}

function queue_page(string $slug): string
{
    [$ev] = public_event($slug, false);
    if (!$ev['queue_enabled'] || Queue::admitted($ev)) {
        if (input('json')) json_out(['admitted' => true, 'url' => url('/e/' . $slug)]);
        redirect('/e/' . $slug);
    }
    $st = Queue::status($ev);
    if (input('json')) json_out($st);
    if (!empty($st['admitted'])) redirect('/e/' . $slug);
    return pub('queue', ['ev' => $ev, 'st' => $st], 'File d\'attente — ' . $ev['name'], ['widget_ok' => true]);
}

function waitlist_join(string $slug): never
{
    [$ev] = public_event($slug);
    $cart = cart_get((int)$ev['id']);
    $back = '/e/' . $slug . ($cart['session'] ? '?seance=' . (int)$cart['session'] : '');
    if (!$ev['waitlist_enabled']) redirect($back);
    $email = trim((string)input('email'));
    if (!is_email($email)) redirect($back, ['error' => 'Cette adresse e-mail a l\'air bizarre 🤨']);
    $qty = max(1, min(10, (int)input('qty', 1)));
    $exists = val("SELECT id FROM waitlist WHERE event_id = ? AND email = ? AND COALESCE(session_id,0) = ? AND status IN ('waiting','offered')", [$ev['id'], $email, (int)$cart['session']]);
    if (!$exists) insert('waitlist', ['event_id' => $ev['id'], 'session_id' => $cart['session'] ?: null, 'email' => $email,
        'first_name' => trim((string)input('first_name')) ?: null, 'last_name' => trim((string)input('last_name')) ?: null,
        'qty' => $qty, 'token' => token(20), 'created_at' => now()]);
    $pos = (int)val("SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND COALESCE(session_id,0) = ? AND status = 'waiting' AND id <= (SELECT MAX(id) FROM waitlist WHERE event_id = ? AND email = ?)",
        [$ev['id'], (int)$cart['session'], $ev['id'], $email]);
    redirect($back, ['success' => '⏳ C\'est noté ! Tu es n°' . max(1, $pos) . ' sur la liste d\'attente. Si une place se libère, on te l\'envoie par e-mail et on te la garde 24 h.']);
}

function waitlist_claim(string $token): never
{
    $w = one('SELECT * FROM waitlist WHERE token = ?', [$token]);
    if (!$w) abort(404, 'Lien inconnu.');
    $ev = one('SELECT * FROM events WHERE id = ?', [$w['event_id']]);
    if ($w['status'] !== 'offered' || strtotime((string)$w['expires_at']) < time()) {
        redirect('/e/' . $ev['slug'], ['warning' => '⌛ Cette offre a expiré ou a déjà été utilisée. Tu peux te réinscrire sur la liste d\'attente.']);
    }
    $cart = cart_get((int)$ev['id']);
    $cart['waitlist'] = (int)$w['id'];
    $cart['session'] = $w['session_id'] ? (int)$w['session_id'] : null;
    $_SESSION['evpass'][$ev['id']] = 1;
    Queue::admit($ev);
    cart_set((int)$ev['id'], $cart);
    redirect('/e/' . $ev['slug'] . ($cart['session'] ? '?seance=' . $cart['session'] : ''), ['success' => '🎉 Ta place t\'attend ! Elle est gardée pour toi jusqu\'au ' . fr_date($w['expires_at']) . ' (' . (int)$w['qty'] . ' billet' . ($w['qty'] > 1 ? 's' : '') . ').']);
}

function partner_page(string $token): string
{
    $pa = one('SELECT * FROM partners WHERE token = ? AND active = 1', [$token]);
    if (!$pa) abort(404, 'Ce lien partenaire n\'existe pas ou a été désactivé.');
    $ev = one('SELECT * FROM events WHERE id = ?', [$pa['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
    $rate = one('SELECT * FROM rates WHERE id = ?', [$pa['rate_id']]);
    $used = (int)val("SELECT COUNT(*) FROM tickets t JOIN orders o ON o.id = t.order_id WHERE o.partner_id = ? AND t.status = 'valid'", [$pa['id']]);
    $sessions = Tickets::sessions((int)$ev['id']);
    if (is_post()) {
        $qty = max(1, (int)input('qty'));
        $cart = cart_get((int)$ev['id']);
        $cart['partner'] = (int)$pa['id'];
        $cart['q'] = [(int)$rate['id'] => $qty];
        $cart['session'] = $sessions ? (int)input('seance') : null;
        $_SESSION['evpass'][$ev['id']] = 1;
        Queue::admit($ev);
        cart_set((int)$ev['id'], $cart);
        if (Seating::map($ev)) redirect('/e/' . $ev['slug'] . ($cart['session'] ? '?seance=' . $cart['session'] : ''), ['info' => '💺 Choisis tes places sur le plan.']);
        redirect('/e/' . $ev['slug'] . '/infos');
    }
    return pub('partner', compact('pa', 'ev', 'org', 'rate', 'used', 'sessions'), $ev['name'] . ' — ' . $pa['name']);
}

/** L'acheteur met un billet en revente (depuis sa page de commande). */
function resale_list(string $ref): never
{
    $o = order_by_ref($ref);
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $back = '/commande/' . $o['ref'] . '?t=' . $o['token'];
    $t = one("SELECT * FROM tickets WHERE id = ? AND order_id = ?", [(int)input('ticket_id'), $o['id']]);
    if (!$t) redirect($back);
    if (input('action') === 'cancel') {
        q("UPDATE resales SET status = 'cancelled' WHERE ticket_id = ? AND status = 'listed'", [$t['id']]);
        update('tickets', ['resale_status' => null, 'updated_at' => now()], 'id = ?', [$t['id']]);
        redirect($back, ['success' => 'Revente annulée, ton billet est de nouveau valable 🎟️']);
    }
    $err = Resale::canList($t, $o, $ev);
    if ($err) redirect($back, ['error' => $err]);
    Resale::list($t, $o, $ev);
    redirect($back, ['success' => '🔁 Ton billet est en vente sur la bourse officielle. Dès qu\'il est racheté, tu es remboursé automatiquement (moins ' . rtrim(rtrim((string)$ev['resale_fee_percent'], '0'), '.') . ' % de frais de revente). En attendant, il reste valable.']);
}

/** Un acheteur rachète un billet de la bourse. */
function resale_buy(string $id): string
{
    $x = one("SELECT x.*, t.rate_id, t.session_id, t.seat, t.rate_name FROM resales x JOIN tickets t ON t.id = x.ticket_id WHERE x.id = ? AND x.status = 'listed'", [(int)$id]);
    if (!$x) abort(404, 'Trop tard, ce billet a déjà trouvé preneur 🏃');
    if ((int)val('SELECT entries FROM tickets WHERE id = ?', [$x['ticket_id']]) > 0) {
        q("UPDATE resales SET status = 'cancelled' WHERE id = ?", [$x['id']]);
        abort(404, 'Ce billet n\'est plus disponible.');
    }
    $ev = one('SELECT * FROM events WHERE id = ?', [$x['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
    $errors = [];
    if (is_post()) {
        $buyer = ['email' => trim((string)input('email')), 'first_name' => trim((string)input('first_name')), 'last_name' => trim((string)input('last_name')), 'phone' => ''];
        if ($buyer['first_name'] === '' || $buyer['last_name'] === '' || !is_email($buyer['email'])) $errors[] = 'Nom, prénom et e-mail valides, s\'il te plaît.';
        if (setting('cgv') && empty($_POST['cgv'])) $errors[] = 'Coche les conditions de vente 😉';
        $busy = val("SELECT id FROM orders WHERE resale_id = ? AND status = 'pending' AND expires_at > NOW()", [$x['id']]);
        if ($busy) $errors[] = 'Quelqu\'un est en train de payer ce billet. Reviens dans quelques minutes 🤞';
        if (!$errors) {
            $session = $x['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$x['session_id']]) : null;
            $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'session' => $session, 'quantities' => [$x['rate_id'] => 1], 'buyer' => $buyer,
                'holders' => [$x['rate_id'] => [['first_name' => $buyer['first_name'], 'last_name' => $buyer['last_name']]]],
                'method' => 'online', 'resale' => ['id' => $x['id'], 'price' => $x['price'], 'seat' => $x['seat']], 'utm' => $_SESSION['utm'] ?? []]);
            if (!empty($r['errors'])) $errors = $r['errors'];
            else { $_SESSION['orders'][] = $r['order']['ref']; redirect('/paiement/' . $r['order']['ref'] . '?t=' . $r['order']['token']); }
        }
    }
    $fee = Tickets::unitFee($org, (int)$x['price']);
    return pub('resale_buy', compact('x', 'ev', 'org', 'fee', 'errors'), 'Bourse aux billets — ' . $ev['name']);
}

function refund_request(string $ref): never
{
    $o = order_by_ref($ref);
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $back = '/commande/' . $o['ref'] . '?t=' . $o['token'];
    if (!$ev['refund_request_enabled'] || $o['status'] !== 'paid' || $o['refund_requested_at']) redirect($back);
    if ($ev['refund_deadline'] && strtotime($ev['refund_deadline']) < time()) redirect($back, ['error' => 'La date limite pour demander un remboursement est passée.']);
    if (val("SELECT id FROM tickets WHERE order_id = ? AND entries > 0 LIMIT 1", [$o['id']])) redirect($back, ['error' => 'Un billet de cette commande a déjà servi, pas de remboursement possible.']);
    update('orders', ['refund_requested_at' => now(), 'refund_reason' => mb_substr(trim((string)input('reason')), 0, 500)], 'id = ?', [$o['id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
    try { if ($org['email']) Mailer::send($org['email'], '💸 Demande de remboursement ' . $o['ref'], '<p>' . e($o['first_name'] . ' ' . $o['last_name']) . ' demande le remboursement de la commande <b>' . e($o['ref']) . '</b> (' . e(money((int)$o['total'])) . ') pour « ' . e($ev['name']) . ' ».</p><p>Motif : ' . e((string)input('reason')) . '</p><p><a href="' . e(abs_url('/pro/commandes/' . $o['id'])) . '">Traiter la demande</a></p>'); }
    catch (Throwable $e) { log_line('emails', $e->getMessage()); }
    redirect($back, ['success' => '📨 Demande envoyée à l\'organisateur. Tu recevras un e-mail dès qu\'elle est traitée.']);
}

// ---------- Widget (à intégrer dans le site de l'organisateur) ----------
function widget_event(string $slug): string
{
    header_remove('X-Frame-Options');
    header('Content-Security-Policy: frame-ancestors *');
    [$ev, $org] = public_event($slug, false);
    $sessions = array_values(array_filter(Tickets::sessions((int)$ev['id']), fn($x) => strtotime((string)($x['ends_at'] ?: $x['starts_at'])) >= time()));
    $session = null;
    if ($sessions) {
        $sid = (int)input('seance');
        foreach ($sessions as $x) if ((int)$x['id'] === $sid) $session = $x;
    }
    $rates = ($sessions && !$session) ? [] : visible_rates($ev, cart_get((int)$ev['id']), $session);
    return view('widget/event', compact('ev', 'org', 'rates', 'sessions', 'session'));
}

function widget_org(string $slug): string
{
    header_remove('X-Frame-Options');
    header('Content-Security-Policy: frame-ancestors *');
    $org = one("SELECT * FROM organizers WHERE slug = ? AND status = 'active'", [$slug]);
    if (!$org) abort(404);
    $events = array_map('event_card_data', all("SELECT e.*, ? AS org_name FROM events e WHERE organizer_id = ? AND status = 'published' AND password IS NULL
        AND COALESCE(ends_at, starts_at) >= NOW() - INTERVAL 6 HOUR ORDER BY starts_at", [$org['name'], $org['id']]));
    return view('widget/org', compact('org', 'events'));
}

function receipt_pdf(string $ref): never
{
    $o = order_by_ref($ref);
    if (!in_array($o['status'], ['paid', 'refunded'], true) || (int)$o['total'] === 0) abort(404, 'Pas de reçu pour cette commande.');
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
    $pdf = Docs::receipt($o, $ev, $org, all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]));
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="recu-' . $o['ref'] . '.pdf"');
    echo $pdf;
    exit;
}
