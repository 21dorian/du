<?php
declare(strict_types=1);

function pub(string $view, array $vars, string $title, array $extra = []): string
{
    return view('layout_public', ['title' => $title, 'content' => view('public/' . $view, $vars)] + $extra);
}

function public_event(string $slug, bool $gates = true): array
{
    $ev = one('SELECT * FROM events WHERE slug = ?', [$slug]);
    if (!$ev) abort(404, 'Cet événement n\'existe pas. Ou plus. Ou pas encore.');
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
    // prévente privée : achat réservé aux personnes autorisées jusqu'à l'ouverture au public
    if (Buyers::presaleActive($ev) && !$isOwner && empty($_SESSION['presale'][$ev['id']])) $ev['_presale_locked'] = true;
    // lien de suivi (canal ou ambassadeur) : clic compté une fois par visite, code promo associé appliqué
    if (!empty($_GET['l']) && preg_match('~^[A-Za-z0-9-]{2,24}$~', (string)$_GET['l'])) {
        $lk = one('SELECT * FROM tracking_links WHERE event_id = ? AND code = ? AND active = 1', [$ev['id'], strtoupper((string)$_GET['l'])]);
        if ($lk) {
            if (($_SESSION['link'][$ev['id']] ?? 0) !== (int)$lk['id']) q('UPDATE tracking_links SET clicks = clicks + 1 WHERE id = ?', [$lk['id']]);
            $_SESSION['link'][$ev['id']] = (int)$lk['id'];
            if ($lk['promo_code']) { $c = cart_get((int)$ev['id']); if (empty($c['promo'])) { $c['promo'] = $lk['promo_code']; cart_set((int)$ev['id'], $c); } }
        }
    }
    // invitation d'un ami : on retient qui a partagé le lien
    if (!empty($_GET['ami']) && preg_match('~^E[A-Z0-9]{7}$~', (string)$_GET['ami'])) {
        $rid = val("SELECT id FROM orders WHERE ref = ? AND event_id = ? AND status = 'paid'", [(string)$_GET['ami'], $ev['id']]);
        if ($rid) $_SESSION['ami'][$ev['id']] = (int)$rid;
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
    $when = (string)input('quand');
    $filters = home_filters();
    $counts = [];
    foreach ($filters as $k => $f) $counts[$k] = (int)val("SELECT COUNT(*) FROM ($sql AND {$f['sql']}) x", array_merge($params, $f['p']));
    if (isset($filters[$when])) { $sql .= ' AND ' . $filters[$when]['sql']; $params = array_merge($params, $filters[$when]['p']); } else $when = '';
    $events = array_map('event_card_data', all($sql . ' ORDER BY e.starts_at LIMIT 80', $params));
    $cats = q("SELECT DISTINCT category FROM events WHERE status = 'published' AND category IS NOT NULL AND category <> '' AND COALESCE(ends_at, starts_at) >= NOW() ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    return pub('home', ['events' => $events, 'q' => $q, 'cat' => $cat, 'cats' => $cats, 'when' => $when, 'filters' => $filters, 'fcounts' => $counts], '');
}

/** Filtres rapides de l'accueil : ce soir, ce week-end, cette semaine, gratuit. */
function home_filters(): array
{
    $overlap = fn(string $a, string $b) => ['sql' => "((e.starts_at <= ? AND COALESCE(e.ends_at, e.starts_at) >= ?) OR EXISTS (SELECT 1 FROM sessions s WHERE s.event_id = e.id AND s.active = 1 AND s.starts_at BETWEEN ? AND ?))", 'p' => [$b, $a, $a, $b]];
    $now = date('Y-m-d H:i:s');
    $tonightEnd = date('Y-m-d 05:00:00', strtotime('+1 day'));
    $w = (int)date('N'); // 1 = lundi … 7 = dimanche
    $wkStart = $w >= 5 ? $now : date('Y-m-d 18:00:00', strtotime('friday this week'));
    $wkEnd = date('Y-m-d 23:59:59', strtotime($w === 7 ? 'today' : 'sunday this week'));
    $weekEnd = date('Y-m-d 23:59:59', strtotime('+7 days'));
    return [
        'ce-soir' => ['label' => 'Ce soir'] + $overlap($now, $tonightEnd),
        'week-end' => ['label' => 'Ce week-end'] + $overlap($wkStart, $wkEnd),
        'semaine' => ['label' => 'Cette semaine'] + $overlap($now, $weekEnd),
        'gratuit' => ['label' => 'Gratuit', 'sql' => "EXISTS (SELECT 1 FROM rates r WHERE r.event_id = e.id AND r.active = 1 AND r.hidden = 0) AND NOT EXISTS (SELECT 1 FROM rates r WHERE r.event_id = e.id AND r.active = 1 AND r.hidden = 0 AND r.price > 0)", 'p' => []],
    ];
}

function organizer_page(string $slug): string
{
    $org = one("SELECT * FROM organizers WHERE slug = ? AND status = 'active'", [$slug]);
    if (!$org) abort(404);
    $events = array_map('event_card_data', all("SELECT e.*, ? AS org_name FROM events e WHERE organizer_id = ? AND status = 'published' AND password IS NULL
        AND COALESCE(ends_at, starts_at) >= NOW() - INTERVAL 6 HOUR ORDER BY starts_at", [$org['name'], $org['id']]));
    $rating = Buyers::orgRating((int)$org['id']);
    $reviews = all("SELECT r.*, e.name ev_name FROM reviews r JOIN events e ON e.id = r.event_id WHERE r.organizer_id = ? AND r.visible = 1 AND r.comment IS NOT NULL ORDER BY r.rating >= 4 DESC, r.created_at DESC LIMIT 6", [$org['id']]);
    $past = !empty($org['show_past']) ? array_map('event_card_data', all("SELECT e.*, ? AS org_name FROM events e WHERE organizer_id = ? AND status IN ('published','closed') AND password IS NULL
        AND COALESCE(ends_at, starts_at) < NOW() - INTERVAL 6 HOUR ORDER BY starts_at DESC LIMIT 8", [$org['name'], $org['id']])) : [];
    $figures = one("SELECT COUNT(DISTINCT e.id) events, (SELECT COUNT(*) FROM tickets t JOIN events e2 ON e2.id = t.event_id WHERE e2.organizer_id = ? AND t.status = 'valid') tickets FROM events e WHERE e.organizer_id = ? AND e.status IN ('published','closed')", [$org['id'], $org['id']]);
    $socials = json_decode((string)($org['socials'] ?? ''), true) ?: [];
    return pub('organizer', ['org' => $org, 'events' => $events, 'rating' => $rating, 'reviews' => $reviews, 'past' => $past, 'figures' => $figures, 'socials' => $socials], $org['name'],
        ['widget_ok' => true, 'org_tracking' => $org, 'description' => $org['tagline'] ?: mb_substr((string)$org['description'], 0, 160), 'og_image' => !empty($org['cover']) ? abs_url('/' . ltrim($org['cover'], '/')) : null]);
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
    $soldOut = $rates && !array_filter($rates, fn($r) => $r['sale']['ok']) && array_filter($rates, fn($r) => ($r['sale']['why'] ?? '') === 'Complet');
    $resales = $ev['resale_enabled'] ? all("SELECT x.*, t.rate_name, t.seat FROM resales x JOIN tickets t ON t.id = x.ticket_id
        WHERE x.event_id = ? AND x.status = 'listed'" . ($session ? ' AND t.session_id = ' . (int)$session['id'] : '') . ' ORDER BY x.price, x.id LIMIT 30', [$ev['id']]) : [];
    $seatmap = Seating::map($ev) ? true : false;
    $img = $ev['image'] ? abs_url($ev['image']) : null;
    Traffic::hit($ev, 'views');
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
        if (!empty($r['error']) || empty($r['promo'])) {
            [$gc, $gErr] = Store::giftFind((int)$ev['organizer_id'], $code);
            if ($gc) { $cart['gift'] = $gc['code']; cart_set((int)$ev['id'], $cart); redirect($back, ['success' => 'Carte cadeau ajoutée : ' . money((int)$gc['balance']) . ' disponibles.']); }
            redirect($back, ['error' => $gErr ?: ($r['error'] ?? 'Code inconnu.')]);
        }
        $p = $r['promo'];
        if ($p['kind'] === 'unlock') {
            $cart['unlocked'] = array_values(array_unique(array_merge($cart['unlocked'] ?? [], $p['rate_list'])));
            cart_set((int)$ev['id'], $cart);
            redirect($back, ['success' => 'Code accepté : un tarif secret vient d\'apparaître.']);
        }
        $cart['promo'] = $p['code'];
        cart_set((int)$ev['id'], $cart);
        redirect($back, ['success' => 'Code « ' . $p['code'] . ' » appliqué. Ton portefeuille te remercie.']);
    }
    if (input('action') === 'remove_code') { $cart['promo'] = ''; cart_set((int)$ev['id'], $cart); redirect($back); }
    fill_cart_from_input($ev, $cart, $_POST);
    if (!$cart['q']) redirect($back, ['error' => 'Prends au moins un billet, sinon ça va être compliqué d\'entrer']);
    Traffic::hit($ev, 'carts');
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
    if (Seating::map($ev)) redirect('/e/' . $slug . ($cart['session'] ? '?seance=' . (int)$cart['session'] : ''), ['info' => 'Choisis tes places sur le plan.']);
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
    $cart['variants'] = [];
    foreach ((array)($in['variant'] ?? []) as $pid => $v) if (isset($cart['products'][(int)$pid])) $cart['variants'][(int)$pid] = mb_substr(trim((string)$v), 0, 30);
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
        if ($pr) $lines[] = ['kind' => 'product', 'rate_id' => 0, 'name' => $pr['name'] . (!empty($cart['variants'][$pid]) ? ' (' . $cart['variants'][$pid] . ')' : ''), 'qty' => (int)$qty, 'unit_price' => (int)$pr['price'], 'pack' => 0, 'seats' => []];
    }
    $promo = $cart['promo'] ? (Tickets::findPromo((int)$ev['id'], $cart['promo'])['promo'] ?? null) : null;
    $subtotal = array_sum(array_map(fn($l) => $l['qty'] * $l['unit_price'], $lines));
    $discount = Tickets::discount($promo, $lines);
    $group = empty($cart['partner']) ? Tickets::groupDiscount($ev, $lines) : ['amount' => 0, 'pct' => 0, 'count' => 0, 'next' => null];
    $discount = min($subtotal, $discount + $group['amount']);
    $paidUnits = array_sum(array_map(fn($l) => $l['kind'] === 'rate' && $l['unit_price'] > 0 ? $l['qty'] : 0, $lines));
    $fees = Tickets::computeFees($org, $subtotal - $discount, $paidUnits);
    $buyerFees = $org['fee_mode'] === 'buyer' ? $fees : 0;
    $donation = (int)($cart['donation'] ?? 0);
    $gross = $subtotal - $discount + $buyerFees + $donation;
    $giftAmt = 0; $giftCode = null;
    if (!empty($cart['gift']) && $gross > 0) { [$card] = Store::giftFind((int)$ev['organizer_id'], (string)$cart['gift']); if ($card) { $giftAmt = min((int)$card['balance'], $gross); $giftCode = $card['code']; } }
    return ['gift' => $giftAmt ? ['code' => $giftCode, 'amount' => $giftAmt] : null, 'gross' => $gross,
        'lines' => $lines, 'promo' => $promo, 'subtotal' => $subtotal, 'discount' => $discount, 'group' => $group, 'fees' => $buyerFees, 'donation' => $donation,
        'total' => $gross - $giftAmt, 'places' => array_sum(array_map(fn($l) => $l['qty'] * $l['pack'], $lines))];
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
        $r = place_order($ev, $org, $cart, $session, $sum, $orderFields, $ticketFields, $waitlist, $partner);
        if ($r['errors']) $errors = $r['errors'];
        else redirect($r['url']);
    }
    $seatLabels = [];
    if ($map = Seating::map($ev)) foreach ($cart['seats'] as $list) foreach ($list as $k) $seatLabels[$k] = Seating::label($k, $map);
    return pub('checkout', compact('ev', 'org', 'sum', 'orderFields', 'ticketFields', 'errors', 'old', 'session', 'waitlist', 'partner', 'seatLabels'),
        'Tes infos — ' . $ev['name'], ['widget_ok' => true, 'org_tracking' => $org]);
}

/**
 * Crée la commande à partir du panier et des infos postées (page « Infos » classique et tiroir d'achat).
 * Les noms des participants sont facultatifs : à défaut, le billet est au nom de l'acheteur et peut être renommé après l'achat.
 * @return array{errors: array, order?: array, url?: string}
 */
function place_order(array $ev, array $org, array $cart, ?array $session, array $sum, array $orderFields, array $ticketFields, ?array $waitlist, ?array $partner): array
{
    $errors = [];
    $buyer = ['email' => trim((string)input('email')), 'first_name' => trim((string)input('first_name')),
        'last_name' => trim((string)input('last_name')), 'phone' => trim((string)input('phone'))];
    if ($buyer['first_name'] === '' || $buyer['last_name'] === '') $errors[] = 'On a besoin de ton prénom et de ton nom.';
    if (!is_email($buyer['email'])) $errors[] = 'Cette adresse e-mail a l\'air bizarre';
    if (isset($_POST['email2']) && $buyer['email'] !== trim((string)input('email2'))) $errors[] = 'Les deux e-mails ne sont pas pareils. Petite faute de frappe ?';
    if (trim((string)input('website')) !== '') $errors[] = 'Requête refusée.'; // piège à robots
    $orderAnswers = collect_answers($orderFields, (array)($_POST['f'] ?? []), $errors, '');
    $holders = [];
    foreach ($sum['lines'] as $l) {
        if ($l['kind'] !== 'rate') continue;
        $fieldsForRate = array_values(array_filter($ticketFields, fn($f) => !$f['only'] || in_array($l['rate_id'], $f['only'], true)));
        for ($i = 0; $i < $l['qty'] * $l['pack']; $i++) {
            $h = $_POST['h'][$l['rate_id']][$i] ?? [];
            $label = $l['name'] . ' n°' . ($i + 1);
            $holders[$l['rate_id']][$i] = ['first_name' => trim((string)($h['first_name'] ?? '')), 'last_name' => trim((string)($h['last_name'] ?? '')),
                'answers' => $fieldsForRate ? collect_answers($fieldsForRate, (array)($h['f'] ?? []), $errors, ' (' . $label . ')') : []];
        }
    }
    if (setting('cgv') && empty($_POST['cgv'])) $errors[] = 'Coche les conditions de vente, on sait que tu les as lues';
    $ageErr = Buyers::checkAge($ev, $session, array_keys($cart['q']), (string)input('birth_date') ?: null, !empty($_POST['age_ok']));
    if ($ageErr) $errors[] = $ageErr;
    if (!$errors && ($blk = Risk::blocked((string)$buyer['email']))) $errors[] = $blk;
    if ($errors) return ['errors' => $errors];
    $r = Tickets::createOrder([
        'event' => $ev, 'org' => $org, 'session' => $session, 'quantities' => $cart['q'], 'prices' => $cart['prices'], 'products' => $cart['products'],
        'donation' => $cart['donation'], 'seats' => $cart['seats'], 'buyer' => $buyer, 'holders' => $holders, 'order_answers' => $orderAnswers, 'gift' => $cart['gift'] ?? '',
        'promo' => $cart['promo'], 'unlocked' => $cart['unlocked'] ?? [], 'variants' => $cart['variants'] ?? [], 'method' => $partner ? 'partner' : 'online', 'partner' => $partner,
        'waitlist' => $waitlist, 'utm' => $_SESSION['utm'] ?? [], 'channel' => (($_SESSION['utm']['source'] ?? '') === 'widget') ? 'widget' : null,
    ]);
    if (!empty($r['errors'])) return ['errors' => $r['errors']];
    $o = $r['order'];
    if ((int)$o['total'] > 0) { try { Risk::assess($o); } catch (Throwable $e) { log_line('fraude', $e->getMessage()); } }
    if (input('birth_date') && Buyers::requiredAge($ev, array_keys($cart['q']))) update('orders', ['birth_date' => (string)input('birth_date')], 'id = ?', [$o['id']]);
    if (!empty($_POST['optin'])) { update('orders', ['marketing_optin' => 1], 'id = ?', [$o['id']]); q('DELETE FROM email_unsubscribes WHERE organizer_id = ? AND email = ?', [$org['id'], strtolower((string)$o['email'])]); }
    if (!empty($_SESSION['ami'][$ev['id']]) && strtolower((string)val('SELECT email FROM orders WHERE id = ?', [$_SESSION['ami'][$ev['id']]])) !== strtolower((string)$o['email'])) update('orders', ['referrer_order_id' => (int)$_SESSION['ami'][$ev['id']]], 'id = ?', [$o['id']]);
    if (!empty($_SESSION['link'][$ev['id']])) update('orders', ['link_id' => (int)$_SESSION['link'][$ev['id']]], 'id = ?', [$o['id']]);
    if (!empty($_SESSION['presale'][$ev['id']])) q('UPDATE presale_access SET used_at = COALESCE(used_at, NOW()) WHERE event_id = ? AND email = ?', [$ev['id'], $_SESSION['presale'][$ev['id']]]);
    if (!empty($_SESSION['recover'][$ev['id']])) { update('orders', ['recovered_from' => (int)$_SESSION['recover'][$ev['id']]], 'id = ?', [$o['id']]); unset($_SESSION['recover'][$ev['id']]); }
    $_SESSION['orders'][] = $o['ref'];
    $_SESSION['buyer'] = $buyer;
    unset($_SESSION['cart'][$ev['id']]);
    Traffic::hit($ev, 'checkouts');
    if ((int)$o['total'] === 0) {
        update('orders', ['provider' => 'free'], 'id = ?', [$o['id']]);
        Tickets::confirm((int)$o['id']);
        return ['errors' => [], 'order' => $o, 'url' => '/commande/' . $o['ref'] . '?t=' . $o['token'], 'free' => true];
    }
    return ['errors' => [], 'order' => $o, 'url' => '/paiement/' . $o['ref'] . '?t=' . $o['token'], 'free' => false];
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
    if (!$o) abort(404, 'Commande introuvable');
    $t = (string)input('t');
    $mine = in_array($o['ref'], $_SESSION['orders'] ?? [], true) || (!empty($_SESSION['buyer_email']) && strtolower((string)$o['email']) === $_SESSION['buyer_email']);
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
        return pub('message', ['title' => 'Trop tard…', 'text' => 'Le chrono est fini, tes places sont reparties dans la nature. Rien de grave : recommence, il en reste peut-être.',
            'link' => $o['event_id'] ? url('/e/' . val('SELECT slug FROM events WHERE id = ?', [$o['event_id']])) : url('/o/' . val('SELECT slug FROM organizers WHERE id = ?', [$o['organizer_id']])), 'link_label' => 'Je retente ma chance'], 'Commande expirée');
    }
    $providers = Payments::available();
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]) ?: Store::pseudoEvent($o);
    $error = null;
    if (is_post() || count($providers) === 1) {
        $p = is_post() ? (string)input('provider') : (string)array_key_first($providers);
        if (!isset($providers[$p])) $error = 'Choisis comment tu payes';
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
    redirect('/paiement/' . $o['ref'] . '?t=' . $o['token'], ['warning' => 'Paiement annulé. Pas de souci, tes places t\'attendent encore un peu']);
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
    if (!$ev) { require_once APP . '/controllers/store.php'; return store_order_page($o, $org); }
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
    if ($o['status'] !== 'paid') abort(404, 'Tes billets arrivent dès que le paiement est confirmé. Encore un peu de patience');
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
    $sql = "SELECT * FROM tickets WHERE order_id = ? AND status = 'valid' AND holder_email IS NULL";
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
    if (input('sortir')) { unset($_SESSION['buyer_email']); redirect('/mes-billets', ['info' => 'Déconnecté de ton espace.']); }
    if (is_post()) {
        $email = strtolower(trim((string)input('email')));
        if (is_email($email) && throttle_ok_pub('mes-billets-' . $email)) {
            $orders = all("SELECT o.*, e.name AS ev_name, e.starts_at FROM orders o JOIN events e ON e.id = o.event_id WHERE LOWER(o.email) = ? AND o.status = 'paid' ORDER BY o.created_at DESC LIMIT 20", [$email]);
            $held = (int)val('SELECT COUNT(*) FROM tickets WHERE holder_email = ?', [$email]);
            if ($orders || $held) {
                $exp = time() + 1800;
                require_once APP . '/controllers/drawer.php';
                $tok = rtrim(strtr(base64_encode($email . '|' . $exp . '|' . buyer_sig($email, $exp)), '+/', '-_'), '=');
                $list = '';
                foreach ($orders as $o) $list .= '<li style="margin-bottom:8px"><a href="' . e(abs_url('/commande/' . $o['ref'], ['t' => $o['token']])) . '">' . e($o['ev_name']) . '</a> — ' . e(fr_date($o['starts_at'], false)) . ' (commande ' . e($o['ref']) . ')</li>';
                try { Mailer::send($email, 'Ton espace billets ' . setting('platform_name'), '<p>Salut</p><p>Voici ton accès à <b>tous tes billets</b> au même endroit (lien valable 30 minutes) :</p>'
                    . '<p><a href="' . e(abs_url('/acheteur/' . $tok, ['r' => '/mes-billets'])) . '" style="display:inline-block;background:' . e(setting('primary_color', '#0071e3')) . ';color:#fff;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:bold">Ouvrir mon espace</a></p>'
                    . ($list ? '<p>Ou directement :</p><ul>' . $list . '</ul>' : '') . '<p style="color:#777;font-size:13px">Pas toi ? Ignore cet e-mail.</p>'); }
                catch (Throwable $e) { log_line('emails', 'Mes billets : ' . $e->getMessage()); }
            }
        }
        $sent = true; // même réponse que l'adresse existe ou non (confidentialité)
    }
    $me = $_SESSION['buyer_email'] ?? null;
    if ($me) {
        $orders = all("SELECT o.*, e.name ev_name, e.slug, e.image, e.venue, e.city, COALESCE(s.starts_at, e.starts_at) start_at, COALESCE(s.ends_at, e.ends_at, e.starts_at) end_at,
                (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id AND t.status = 'valid' AND t.holder_email IS NULL) n_tickets,
                (SELECT id FROM reviews r WHERE r.order_id = o.id) review_id
            FROM orders o JOIN events e ON e.id = o.event_id LEFT JOIN sessions s ON s.id = o.session_id
            WHERE LOWER(o.email) = ? AND o.status IN ('paid','refunded') ORDER BY start_at DESC LIMIT 200", [$me]);
        $held = all("SELECT t.*, e.name ev_name, e.slug, e.image, e.starts_at start_at FROM tickets t JOIN events e ON e.id = t.event_id WHERE t.holder_email = ? AND t.status = 'valid' ORDER BY e.starts_at DESC", [$me]);
        $upcoming = array_values(array_filter($orders, fn($o) => strtotime((string)$o['end_at']) + 6 * 3600 >= time()));
        $past = array_values(array_filter($orders, fn($o) => strtotime((string)$o['end_at']) + 6 * 3600 < time()));
        $stats = ['events' => count(array_unique(array_column($orders, 'event_id'))), 'tickets' => array_sum(array_map('intval', array_column($orders, 'n_tickets')))];
        return pub('my_tickets', compact('me', 'upcoming', 'past', 'held', 'stats'), 'Mes billets');
    }
    return pub('find_tickets', ['sent' => $sent], 'Mes billets');
}

function throttle_ok_pub(string $k): bool
{
    $key = 'thr_' . md5($k);
    $n = $_SESSION[$key] ?? 0;
    if ($n >= 3) return false;
    $_SESSION[$key] = $n + 1;
    return true;
}

function cgv(): string { return pub('page', ['p' => ['title' => 'Conditions générales de vente', 'body' => (string)setting('cgv'), 'updated_at' => null]], 'CGV'); }
function legal(): string { return pub('page', ['p' => ['title' => 'Mentions légales', 'body' => (string)setting('legal'), 'updated_at' => null]], 'Mentions légales'); }

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
        $error = 'Mauvais mot de passe Demande-le à l\'organisateur.';
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
    if (!is_email($email)) redirect($back, ['error' => 'Cette adresse e-mail a l\'air bizarre']);
    $qty = max(1, min(10, (int)input('qty', 1)));
    $exists = val("SELECT id FROM waitlist WHERE event_id = ? AND email = ? AND COALESCE(session_id,0) = ? AND status IN ('waiting','offered')", [$ev['id'], $email, (int)$cart['session']]);
    if (!$exists) insert('waitlist', ['event_id' => $ev['id'], 'session_id' => $cart['session'] ?: null, 'email' => $email,
        'first_name' => trim((string)input('first_name')) ?: null, 'last_name' => trim((string)input('last_name')) ?: null,
        'qty' => $qty, 'token' => token(20), 'created_at' => now()]);
    $pos = (int)val("SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND COALESCE(session_id,0) = ? AND status = 'waiting' AND id <= (SELECT MAX(id) FROM waitlist WHERE event_id = ? AND email = ?)",
        [$ev['id'], (int)$cart['session'], $ev['id'], $email]);
    redirect($back, ['success' => 'C\'est noté ! Tu es n°' . max(1, $pos) . ' sur la liste d\'attente. Si une place se libère, on te l\'envoie par e-mail et on te la garde ' . Waitlist::hours($ev) . ' h.']);
}

function waitlist_claim(string $token): never
{
    $w = one('SELECT * FROM waitlist WHERE token = ?', [$token]);
    if (!$w) abort(404, 'Lien inconnu.');
    $ev = one('SELECT * FROM events WHERE id = ?', [$w['event_id']]);
    if ($w['status'] !== 'offered' || strtotime((string)$w['expires_at']) < time()) {
        redirect('/e/' . $ev['slug'], ['warning' => 'Cette offre a expiré ou a déjà été utilisée. Tu peux te réinscrire sur la liste d\'attente.']);
    }
    $cart = cart_get((int)$ev['id']);
    $cart['waitlist'] = (int)$w['id'];
    $cart['session'] = $w['session_id'] ? (int)$w['session_id'] : null;
    $_SESSION['evpass'][$ev['id']] = 1;
    Queue::admit($ev);
    cart_set((int)$ev['id'], $cart);
    redirect('/e/' . $ev['slug'] . ($cart['session'] ? '?seance=' . $cart['session'] : ''), ['success' => 'Ta place t\'attend ! Elle est gardée pour toi jusqu\'au ' . fr_date($w['expires_at']) . ' (' . (int)$w['qty'] . ' billet' . ($w['qty'] > 1 ? 's' : '') . ').']);
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
        if (Seating::map($ev)) redirect('/e/' . $ev['slug'] . ($cart['session'] ? '?seance=' . $cart['session'] : ''), ['info' => 'Choisis tes places sur le plan.']);
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
        redirect($back, ['success' => 'Revente annulée, ton billet est de nouveau valable']);
    }
    $err = Resale::canList($t, $o, $ev);
    if ($err) redirect($back, ['error' => $err]);
    Resale::list($t, $o, $ev);
    redirect($back, ['success' => 'Ton billet est en vente sur la bourse officielle. Dès qu\'il est racheté, tu es remboursé automatiquement (moins ' . rtrim(rtrim((string)$ev['resale_fee_percent'], '0'), '.') . ' % de frais de revente). En attendant, il reste valable.']);
}

/** Un acheteur rachète un billet de la bourse. */
function resale_buy(string $id): string
{
    $x = one("SELECT x.*, t.rate_id, t.session_id, t.seat, t.rate_name FROM resales x JOIN tickets t ON t.id = x.ticket_id WHERE x.id = ? AND x.status = 'listed'", [(int)$id]);
    if (!$x) abort(404, 'Trop tard, ce billet a déjà trouvé preneur');
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
        if (setting('cgv') && empty($_POST['cgv'])) $errors[] = 'Coche les conditions de vente';
        $busy = val("SELECT id FROM orders WHERE resale_id = ? AND status = 'pending' AND expires_at > NOW()", [$x['id']]);
        if ($busy) $errors[] = 'Quelqu\'un est en train de payer ce billet. Reviens dans quelques minutes';
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
    try { if ($org['email']) Mailer::send($org['email'], 'Demande de remboursement ' . $o['ref'], '<p>' . e($o['first_name'] . ' ' . $o['last_name']) . ' demande le remboursement de la commande <b>' . e($o['ref']) . '</b> (' . e(money((int)$o['total'])) . ') pour « ' . e($ev['name']) . ' ».</p><p>Motif : ' . e((string)input('reason')) . '</p><p><a href="' . e(abs_url('/pro/commandes/' . $o['id'])) . '">Traiter la demande</a></p>'); }
    catch (Throwable $e) { log_line('emails', $e->getMessage()); }
    redirect($back, ['success' => 'Demande envoyée à l\'organisateur. Tu recevras un e-mail dès qu\'elle est traitée.']);
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
