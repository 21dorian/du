<?php
declare(strict_types=1);

/* 2.1 : pages publiques des cartes cadeaux, abonnements & pass, boutique. */

require_once APP . '/controllers/public.php';

function store_org(string $slug): array
{
    $org = one("SELECT * FROM organizers WHERE slug = ? AND status = 'active'", [$slug]);
    if (!$org) abort(404);
    return $org;
}

function store_buyer(array &$errors): array
{
    $b = ['first_name' => trim((string)input('first_name')), 'last_name' => trim((string)input('last_name')), 'email' => strtolower(trim((string)input('email'))), 'phone' => trim((string)input('phone'))];
    if ($b['first_name'] === '' || $b['last_name'] === '') $errors[] = 'Indique ton prénom et ton nom.';
    if (!is_email($b['email'])) $errors[] = 'Ton adresse e-mail a l\'air incorrecte.';
    if (trim((string)input('website')) !== '') $errors[] = 'Requête refusée.';
    if (!$errors && ($blk = Risk::blocked($b['email']))) $errors[] = $blk;
    return $b;
}

function store_pay(array $order): never
{
    try { Risk::assess($order); } catch (Throwable $e) { log_line('fraude', $e->getMessage()); }
    $_SESSION['orders'][] = $order['ref'];
    redirect('/paiement/' . $order['ref'] . '?t=' . $order['token']);
}

// ---------- Cartes cadeaux ----------
function gift_amounts(array $org): array
{
    $a = array_values(array_filter(array_map(fn($v) => parse_money(trim($v)), explode(',', (string)($org['gift_amounts'] ?: '20,50,100')))));
    sort($a);
    return $a ?: [2000, 5000, 10000];
}

function gift_shop(string $slug): string
{
    $org = store_org($slug);
    if (!$org['gift_enabled']) abort(404, 'Les cartes cadeaux ne sont pas proposées par cet organisateur.');
    $errors = [];
    if (is_post()) {
        $b = store_buyer($errors);
        $amount = input('amount') === 'libre' ? parse_money(input('free_amount')) : (int)input('amount');
        if ($amount < 500 || $amount > 100000) $errors[] = 'Choisis un montant entre 5 € et 1 000 €.';
        $forOther = input('for') === 'other';
        $rEmail = strtolower(trim((string)input('recipient_email')));
        if ($forOther && !is_email($rEmail)) $errors[] = 'L\'adresse e-mail de la personne à qui tu l\'offres a l\'air incorrecte.';
        if (!$errors) {
            $o = Store::createOrder($org, $b, [['kind' => 'gift', 'name' => 'Carte cadeau ' . money($amount), 'qty' => 1, 'unit_price' => $amount]],
                ['channel' => 'cadeau', 'meta' => ['recipient_name' => $forOther ? mb_substr(trim((string)input('recipient_name')), 0, 190) : trim($b['first_name'] . ' ' . $b['last_name']),
                    'recipient_email' => $forOther ? $rEmail : null, 'message' => mb_substr(trim((string)input('message')), 0, 500) ?: null]]);
            store_pay($o);
        }
    }
    return pub('gift_shop', ['org' => $org, 'amounts' => gift_amounts($org), 'errors' => $errors], 'Carte cadeau · ' . $org['name']);
}

// ---------- Abonnements & pass ----------
function passes_shop(string $slug): string
{
    $org = store_org($slug);
    $types = all('SELECT * FROM pass_types WHERE organizer_id = ? AND active = 1 ORDER BY sort, price', [$org['id']]);
    if (!$types) abort(404, 'Aucun abonnement proposé pour l\'instant.');
    $errors = [];
    if (is_post()) {
        $b = store_buyer($errors);
        $t = one('SELECT * FROM pass_types WHERE id = ? AND organizer_id = ? AND active = 1', [(int)input('pass_type'), $org['id']]);
        if (!$t) $errors[] = 'Choisis ton abonnement.';
        elseif ((int)$t['quota'] > 0 && Store::passSold($t) >= (int)$t['quota']) $errors[] = 'Cet abonnement est complet.';
        if (!$errors) {
            $o = Store::createOrder($org, $b, [['kind' => 'pass', 'name' => $t['name'], 'qty' => 1, 'unit_price' => (int)$t['price'], 'item_ref' => (int)$t['id']]], ['channel' => 'abonnement']);
            store_pay($o);
        }
    }
    foreach ($types as &$t) { $t['events'] = Store::passTypeEvents($t); $t['left'] = (int)$t['quota'] > 0 ? max(0, (int)$t['quota'] - Store::passSold($t)) : null; }
    unset($t);
    return pub('passes_shop', ['org' => $org, 'types' => $types, 'errors' => $errors, 'sel' => (int)input('pass_type')], 'Abonnements · ' . $org['name']);
}

/** Espace de l'abonné : événements compris, réservation en un clic. */
function pass_page(string $token): string
{
    $p = one('SELECT * FROM passes WHERE token = ?', [$token]);
    if (!$p || strlen($token) < 20) abort(404);
    $type = one('SELECT * FROM pass_types WHERE id = ?', [$p['pass_type_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$p['organizer_id']]);
    if (is_post()) {
        $ev = one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [(int)input('event_id'), $org['id']]);
        $r = $ev ? Store::passBook($p, $ev) : ['error' => 'Événement introuvable.'];
        if (!empty($r['error'])) redirect('/pass/' . $token, ['error' => $r['error']]);
        redirect('/commande/' . $r['order']['ref'] . '?t=' . $r['order']['token'], ['success' => 'Ta place est réservée avec ton abonnement. Ton billet est parti par e-mail.']);
    }
    $events = Store::passTypeEvents($type);
    $booked = array_column(all("SELECT event_id, ref, token FROM orders WHERE pass_id = ? AND status = 'paid'", [$p['id']]), null, 'event_id');
    return pub('pass_page', compact('p', 'type', 'org', 'events', 'booked'), $type['name'] . ' · ' . $org['name']);
}

// ---------- Boutique ----------
function shop_page(string $slug): string
{
    $org = store_org($slug);
    if (!$org['shop_enabled']) abort(404, 'La boutique n\'est pas ouverte.');
    $products = all('SELECT * FROM shop_products WHERE organizer_id = ? AND active = 1 ORDER BY sort, id', [$org['id']]);
    $errors = [];
    if (is_post()) {
        $b = store_buyer($errors);
        $items = []; $canShip = true; $canPickup = true; $ship = 0;
        foreach ($products as $p) {
            $q = max(0, min(20, (int)($_POST['q'][$p['id']] ?? 0)));
            if (!$q) continue;
            if ($q > Store::shopRemaining($p)) { $errors[] = $p['name'] . ' : plus assez de stock.'; continue; }
            $vars = array_filter(array_map('trim', explode(',', (string)$p['variants'])));
            $v = $vars ? (string)($_POST['v'][$p['id']] ?? '') : '';
            if ($vars && !in_array($v, $vars, true)) $v = reset($vars);
            $items[] = ['kind' => 'shop', 'name' => $p['name'] . ($v !== '' ? ' (' . $v . ')' : ''), 'qty' => $q, 'unit_price' => (int)$p['price'], 'item_ref' => (int)$p['id'], 'variant' => $v ?: null];
            if ($p['delivery'] === 'pickup') $canShip = false;
            if ($p['delivery'] === 'ship') $canPickup = false;
            $ship = max($ship, (int)$p['ship_price']);
        }
        if (!$items && !$errors) $errors[] = 'Ton panier est vide.';
        $delivery = input('delivery') === 'ship' ? 'ship' : 'pickup';
        if ($delivery === 'ship' && !$canShip) $errors[] = 'Un des articles est seulement à retirer sur place.';
        if ($delivery === 'pickup' && !$canPickup) $delivery = 'ship';
        $addr = trim((string)input('address'));
        if ($delivery === 'ship' && mb_strlen($addr) < 10) $errors[] = 'Indique ton adresse de livraison complète.';
        if (!$errors) {
            $o = Store::createOrder($org, $b, $items, ['channel' => 'boutique', 'delivery' => $delivery, 'ship_address' => $delivery === 'ship' ? mb_substr($addr, 0, 500) : null, 'ship_price' => $delivery === 'ship' ? $ship : 0]);
            store_pay($o);
        }
    }
    foreach ($products as &$p) $p['left'] = Store::shopRemaining($p);
    unset($p);
    return pub('shop', ['org' => $org, 'products' => $products, 'errors' => $errors], 'Boutique · ' . $org['name']);
}

/** Page de commande d'une vente hors événement (appelée par order_page). */
function store_order_page(array $o, array $org): string
{
    $items = all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
    $cards = all('SELECT * FROM gift_cards WHERE order_id = ?', [$o['id']]);
    $passes = all('SELECT p.*, t.name type_name FROM passes p JOIN pass_types t ON t.id = p.pass_type_id WHERE p.order_id = ?', [$o['id']]);
    return pub('store_order', compact('o', 'org', 'items', 'cards', 'passes'), 'Commande ' . $o['ref']);
}

function gift_card_pdf(string $ref): never
{
    $o = order_by_ref($ref);
    $c = one('SELECT * FROM gift_cards WHERE order_id = ? AND code = ?', [$o['id'], (string)input('code')]);
    if (!$c || $o['status'] !== 'paid') abort(404);
    $pdf = Store::giftPdf($c, one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]));
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="carte-cadeau-' . $c['code'] . '.pdf"');
    echo $pdf;
    exit;
}

/** Solde d'une carte cadeau (formulaire public). */
function gift_balance(string $slug): string
{
    $org = store_org($slug);
    $card = null; $err = null;
    if (is_post() && throttle_ok_pub('solde-' . session_id())) {
        [$card, $err] = Store::giftFind((int)$org['id'], (string)input('code'));
        if (!$card && !$err) $err = 'Code inconnu.';
    }
    return pub('gift_balance', compact('org', 'card', 'err'), 'Solde de carte cadeau');
}
