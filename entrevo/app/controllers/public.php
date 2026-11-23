<?php
declare(strict_types=1);

function pub(string $view, array $vars, string $title, array $extra = []): string
{
    return view('layout_public', ['title' => $title, 'content' => view('public/' . $view, $vars)] + $extra);
}

function public_event(string $slug): array
{
    $ev = one('SELECT * FROM events WHERE slug = ?', [$slug]);
    if (!$ev) abort(404, 'Cet événement n\'existe pas. Ou plus. Ou pas encore. 🤔');
    $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
    $u = user();
    $isOwner = $u && ($u['role'] === 'superadmin' || (int)$u['organizer_id'] === (int)$ev['organizer_id']);
    if (($ev['status'] === 'draft' || $org['status'] !== 'active') && !$isOwner) abort(404, 'Cet événement n\'est pas (ou plus) en ligne.');
    return [$ev, $org];
}

function event_card_data(array $ev): array
{
    $min = val('SELECT MIN(price) FROM rates WHERE event_id = ? AND active = 1 AND hidden = 0', [$ev['id']]);
    return $ev + ['min_price' => $min === null ? null : (int)$min];
}

function home(): string
{
    Tickets::expirePending();
    $q = trim((string)input('q'));
    $sql = "SELECT e.*, o.name AS org_name FROM events e JOIN organizers o ON o.id = e.organizer_id
            WHERE e.status = 'published' AND o.status = 'active' AND COALESCE(e.ends_at, e.starts_at) >= NOW() - INTERVAL 6 HOUR";
    $params = [];
    if ($q !== '') { $sql .= ' AND (e.name LIKE ? OR e.city LIKE ? OR e.venue LIKE ? OR o.name LIKE ?)'; $params = array_fill(0, 4, '%' . $q . '%'); }
    $events = array_map('event_card_data', all($sql . ' ORDER BY e.starts_at LIMIT 60', $params));
    return pub('home', ['events' => $events, 'q' => $q], '');
}

function organizer_page(string $slug): string
{
    $org = one("SELECT * FROM organizers WHERE slug = ? AND status = 'active'", [$slug]);
    if (!$org) abort(404);
    $events = array_map('event_card_data', all("SELECT e.*, ? AS org_name FROM events e WHERE organizer_id = ? AND status = 'published'
        AND COALESCE(ends_at, starts_at) >= NOW() - INTERVAL 6 HOUR ORDER BY starts_at", [$org['name'], $org['id']]));
    return pub('organizer', ['org' => $org, 'events' => $events], $org['name']);
}

function cart_get(int $eventId): array
{
    return $_SESSION['cart'][$eventId] ?? ['q' => [], 'promo' => '', 'unlocked' => []];
}
function cart_set(int $eventId, array $cart): void { $_SESSION['cart'][$eventId] = $cart; }

/** Tarifs visibles pour l'acheteur (dont les tarifs cachés débloqués par un code). */
function visible_rates(array $ev, array $cart): array
{
    $rates = all('SELECT * FROM rates WHERE event_id = ? AND active = 1 ORDER BY sort, id', [$ev['id']]);
    $unlocked = array_map('intval', $cart['unlocked'] ?? []);
    $out = [];
    foreach ($rates as $r) {
        if ($r['hidden'] && !in_array((int)$r['id'], $unlocked, true)) continue;
        $r['sale'] = Tickets::onSale($r, $ev);
        $out[] = $r;
    }
    return $out;
}

function event_page(string $slug): string
{
    [$ev, $org] = public_event($slug);
    Tickets::expirePending();
    $cart = cart_get((int)$ev['id']);
    $rates = visible_rates($ev, $cart);
    $promo = $cart['promo'] ? (Tickets::findPromo((int)$ev['id'], $cart['promo'])['promo'] ?? null) : null;
    $img = $ev['image'] ? abs_url($ev['image']) : null;
    return pub('event', compact('ev', 'org', 'rates', 'cart', 'promo'), $ev['name'], [
        'description' => mb_substr(strip_tags((string)$ev['description']), 0, 160), 'og_image' => $img,
    ]);
}

function event_select(string $slug): never
{
    [$ev] = public_event($slug);
    $cart = cart_get((int)$ev['id']);
    if (input('action') === 'code') {
        $code = strtoupper(trim((string)input('code')));
        $r = Tickets::findPromo((int)$ev['id'], $code);
        if (!empty($r['error']) || empty($r['promo'])) redirect('/e/' . $slug, ['error' => ($r['error'] ?? 'Code inconnu.') . ' 🙅']);
        $p = $r['promo'];
        if ($p['kind'] === 'unlock') {
            $cart['unlocked'] = array_values(array_unique(array_merge($cart['unlocked'] ?? [], $p['rate_list'])));
            cart_set((int)$ev['id'], $cart);
            redirect('/e/' . $slug, ['success' => '🤫 Code accepté : un tarif secret vient d\'apparaître.']);
        }
        $cart['promo'] = $p['code'];
        cart_set((int)$ev['id'], $cart);
        redirect('/e/' . $slug, ['success' => '🎉 Code « ' . $p['code'] . ' » appliqué. Ton portefeuille te remercie.']);
    }
    if (input('action') === 'remove_code') { $cart['promo'] = ''; cart_set((int)$ev['id'], $cart); redirect('/e/' . $slug); }

    $q = [];
    foreach ((array)($_POST['qty'] ?? []) as $rid => $n) if ((int)$n > 0) $q[(int)$rid] = min(50, (int)$n);
    if (!$q) redirect('/e/' . $slug, ['error' => 'Prends au moins un billet, sinon ça va être compliqué d\'entrer 😅']);
    $cart['q'] = $q;
    cart_set((int)$ev['id'], $cart);
    redirect('/e/' . $slug . '/infos');
}

/** Calcule le récapitulatif (sans créer de commande). */
function cart_summary(array $ev, array $org, array $cart): array
{
    $lines = [];
    foreach ($cart['q'] as $rid => $qty) {
        $r = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [$rid, $ev['id']]);
        if ($r) $lines[] = ['rate_id' => (int)$r['id'], 'name' => $r['name'], 'qty' => (int)$qty, 'unit_price' => (int)$r['price']];
    }
    $promo = $cart['promo'] ? (Tickets::findPromo((int)$ev['id'], $cart['promo'])['promo'] ?? null) : null;
    $subtotal = array_sum(array_map(fn($l) => $l['qty'] * $l['unit_price'], $lines));
    $discount = Tickets::discount($promo, $lines);
    $paidTickets = array_sum(array_map(fn($l) => $l['unit_price'] > 0 ? $l['qty'] : 0, $lines));
    $fees = Tickets::computeFees($org, $subtotal - $discount, $paidTickets);
    $buyerFees = $org['fee_mode'] === 'buyer' ? $fees : 0;
    return ['lines' => $lines, 'promo' => $promo, 'subtotal' => $subtotal, 'discount' => $discount, 'fees' => $buyerFees,
        'total' => $subtotal - $discount + $buyerFees, 'count' => array_sum(array_column($lines, 'qty'))];
}

function checkout(string $slug): string
{
    [$ev, $org] = public_event($slug);
    $cart = cart_get((int)$ev['id']);
    if (!$cart['q']) redirect('/e/' . $slug);
    $sum = cart_summary($ev, $org, $cart);
    $fields = all('SELECT * FROM form_fields WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    $orderFields = array_values(array_filter($fields, fn($f) => !$f['per_ticket']));
    $ticketFields = array_values(array_filter($fields, fn($f) => $f['per_ticket']));
    $errors = [];
    $old = $_POST;

    if (is_post()) {
        $buyer = ['email' => trim((string)input('email')), 'first_name' => trim((string)input('first_name')),
            'last_name' => trim((string)input('last_name')), 'phone' => trim((string)input('phone'))];
        if ($buyer['first_name'] === '' || $buyer['last_name'] === '') $errors[] = 'On a besoin de ton prénom et de ton nom.';
        if (!is_email($buyer['email'])) $errors[] = 'Cette adresse e-mail a l\'air bizarre 🤨';
        if ($buyer['email'] !== trim((string)input('email2'))) $errors[] = 'Les deux e-mails ne sont pas pareils. Petite faute de frappe ?';
        $orderAnswers = collect_answers($orderFields, (array)($_POST['f'] ?? []), $errors, '');
        $holders = [];
        foreach ($sum['lines'] as $l) {
            for ($i = 0; $i < $l['qty']; $i++) {
                $h = $_POST['h'][$l['rate_id']][$i] ?? [];
                $label = $l['name'] . ' n°' . ($i + 1);
                $fn = trim((string)($h['first_name'] ?? ''));
                $ln = trim((string)($h['last_name'] ?? ''));
                if ($fn === '' || $ln === '') $errors[] = 'Il manque le nom du participant (' . $label . ').';
                $holders[$l['rate_id']][$i] = ['first_name' => $fn, 'last_name' => $ln,
                    'answers' => collect_answers($ticketFields, (array)($h['f'] ?? []), $errors, ' (' . $label . ')')];
            }
        }
        if (setting('cgv') && empty($_POST['cgv'])) $errors[] = 'Coche les conditions de vente, on sait que tu les as lues 😉';
        if (!$errors) {
            $r = Tickets::createOrder([
                'event' => $ev, 'org' => $org, 'quantities' => $cart['q'], 'buyer' => $buyer, 'holders' => $holders,
                'order_answers' => $orderAnswers, 'promo' => $cart['promo'], 'unlocked' => $cart['unlocked'] ?? [], 'method' => 'online',
            ]);
            if (!empty($r['errors'])) {
                $errors = $r['errors'];
            } else {
                $o = $r['order'];
                $_SESSION['orders'][] = $o['ref'];
                unset($_SESSION['cart'][$ev['id']]);
                if ((int)$o['total'] === 0) {
                    update('orders', ['provider' => 'free'], 'id = ?', [$o['id']]);
                    Tickets::markPaid((int)$o['id']);
                    Mailer::sendOrderSafe((int)$o['id']);
                    redirect('/commande/' . $o['ref'] . '?t=' . $o['token']);
                }
                redirect('/paiement/' . $o['ref'] . '?t=' . $o['token']);
            }
        }
    }
    return pub('checkout', compact('ev', 'org', 'sum', 'orderFields', 'ticketFields', 'errors', 'old'), 'Vos informations — ' . $ev['name']);
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
        Tickets::markPaid((int)$o['id'], ['provider_payment' => 'demo']);
        Mailer::sendOrderSafe((int)$o['id']);
        redirect('/commande/' . $o['ref'] . '?t=' . $o['token']);
    }
    return pub('payment_demo', ['o' => $o], 'Paiement de démonstration');
}

function payment_return(string $ref): never
{
    $o = order_by_ref($ref);
    if ($o['status'] !== 'paid') {
        try {
            if (Payments::verify($o)) { Tickets::markPaid((int)$o['id']); Mailer::sendOrderSafe((int)$o['id']); }
        } catch (Throwable $e) { log_line('paiements', 'Vérification ' . $o['ref'] . ' : ' . $e->getMessage()); }
    }
    redirect('/commande/' . $o['ref'] . '?t=' . $o['token']);
}

function payment_cancel(string $ref): never
{
    $o = order_by_ref($ref);
    redirect('/paiement/' . $o['ref'] . '?t=' . $o['token'], ['warning' => 'Paiement annulé. Pas de souci, tes places t\'attendent encore un peu ⏱️']);
}

function webhook_mollie(): never
{
    $id = (string)($_POST['id'] ?? '');
    $o = $id ? one("SELECT * FROM orders WHERE provider = 'mollie' AND provider_ref = ?", [$id]) : null;
    if ($o && $o['status'] !== 'paid') {
        try { if (Payments::verify($o)) { Tickets::markPaid((int)$o['id']); Mailer::sendOrderSafe((int)$o['id']); } }
        catch (Throwable $e) { log_line('paiements', 'Webhook Mollie : ' . $e->getMessage()); }
    }
    http_response_code(200);
    exit('ok');
}

function order_page(string $ref): string
{
    $o = order_by_ref($ref);
    // paiement en attente : on revérifie auprès du prestataire (au plus toutes les 10 s)
    if ($o['status'] === 'pending' && $o['provider'] && !in_array($o['provider'], ['demo', 'free'], true)) {
        $k = 'chk_' . $o['id'];
        if (time() - ($_SESSION[$k] ?? 0) > 10) {
            $_SESSION[$k] = time();
            try { if (Payments::verify($o)) { $o = Tickets::markPaid((int)$o['id']); Mailer::sendOrderSafe((int)$o['id']); } }
            catch (Throwable $e) { log_line('paiements', $e->getMessage()); }
        }
    }
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
    $tickets = all('SELECT * FROM tickets WHERE order_id = ? ORDER BY id', [$o['id']]);
    $items = all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
    return pub('order', compact('o', 'ev', 'org', 'tickets', 'items'), 'Commande ' . $o['ref']);
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
