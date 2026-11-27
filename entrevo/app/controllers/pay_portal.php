<?php
declare(strict_types=1);

require_once APP . '/controllers/pos.php';

/* 3.0 : Entrevo Pay — portail public du festivalier : retrouver sa carte, carte virtuelle, recharge, blocage, transfert, remboursement. */

function portal_view(string $view, array $vars, string $title, ?array $ev = null): string
{
    $color = $ev ? (string)Pay::settings($ev)['brand_color'] : '#5e5ce6';
    return view('pay/layout_portal', ['title' => $title, 'content' => view('pay/' . $view, $vars), 'color' => $color, 'ev' => $ev]);
}

/** Retrouve un porte-monnaie à partir de ce que le festivalier connaît : code de carte, code billet, numéro de bracelet. */
function portal_find(string $ref, ?int $eventId = null): ?array
{
    $ref = trim($ref); if ($ref === '') return null;
    $code = strtoupper(trim(preg_replace('~^.*[/=]~', '', $ref)));
    $w = one('SELECT * FROM wallets WHERE code = ?' . ($eventId ? ' AND event_id = ' . (int)$eventId : ''), [$code]);
    if ($w) return $w;
    $t = one("SELECT * FROM tickets WHERE code = ? AND status = 'valid'" . ($eventId ? ' AND event_id = ' . (int)$eventId : ''), [$code]);
    if ($t) { $ev = one('SELECT cashless_enabled FROM events WHERE id = ?', [$t['event_id']]); return $ev && (int)$ev['cashless_enabled'] ? Cashless::forTicket($t) : null; }
    return one('SELECT w.* FROM pay_wristbands b JOIN wallets w ON w.id = b.wallet_id WHERE b.number = ? AND b.status = \'active\'' . ($eventId ? ' AND w.event_id = ' . (int)$eventId : '') . ' LIMIT 1', [$ref]);
}

function portal_home(): string
{
    if (is_post()) {
        if (input('action') === 'mail') {
            $email = strtolower(trim((string)input('email')));
            if (is_email($email) && throttle_pos('portal-mail-' . $email)) {
                $ws = all("SELECT w.*, e.name ev FROM wallets w JOIN events e ON e.id = w.event_id LEFT JOIN tickets t ON t.id = w.ticket_id LEFT JOIN orders o ON o.id = t.order_id
                    WHERE (w.email = ? OR t.holder_email = ? OR o.email = ?) AND e.cashless_enabled = 1 ORDER BY w.id DESC LIMIT 20", [$email, $email, $email]);
                if ($ws) { try { Mailer::send($email, 'Tes cartes cashless', '<p>Voici tes cartes cashless :</p><ul>' . implode('', array_map(fn($w) => '<li><a href="' . e(Pay::absUrl('/w/' . $w['token'])) . '">' . e($w['ev']) . ' · solde ' . e(money((int)$w['balance'])) . '</a></li>', $ws)) . '</ul>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); } }
            }
            redirect(Pay::path(''), ['success' => 'Si une carte est liée à cette adresse, tu vas recevoir un e-mail avec le lien.']);
        }
        if (!throttle_pos('portal-find')) redirect(Pay::path(''), ['error' => 'Trop d\'essais : patiente une minute.']);
        $w = portal_find((string)input('ref'));
        if (!$w) redirect(Pay::path(''), ['error' => 'Carte, billet ou bracelet introuvable. Vérifie le code (il est sous le QR code de ton billet ou imprimé sur ton bracelet).']);
        redirect(Pay::path('/w/') . $w['token']);
    }
    $events = all("SELECT e.id, e.name, e.slug, e.starts_at, e.city, g.name org FROM events e JOIN organizers g ON g.id = e.organizer_id WHERE e.cashless_enabled = 1 AND e.status = 'published' AND COALESCE(e.ends_at, e.starts_at) >= NOW() - INTERVAL 2 DAY ORDER BY e.starts_at LIMIT 12");
    return portal_view('portal_home', compact('events'), 'Mon cashless');
}

function portal_event(string $slug): string
{
    $ev = one('SELECT * FROM events WHERE slug = ? AND cashless_enabled = 1', [$slug]);
    if (!$ev) abort(404, 'Le cashless n\'est pas actif pour cet événement.');
    $s = Pay::settings($ev);
    if (is_post()) {
        if (input('action') === 'virtual' && $s['virtual']) {
            $email = strtolower(trim((string)input('email')));
            if (!is_email($email)) redirect(Pay::path('/e/') . $slug, ['error' => 'Indique un e-mail valide : il sert à retrouver ta carte.']);
            if (!throttle_pos('portal-virtual')) redirect(Pay::path('/e/') . $slug, ['error' => 'Trop d\'essais : patiente une minute.']);
            $w = Pay::virtualCard($ev, $email, trim((string)input('name')));
            Pay::sendWalletLink($w, $ev);
            redirect(Pay::path('/w/') . $w['token'], ['success' => 'Ta carte est prête ! Recharge-la, puis présente son QR code au bar. Le lien t\'a aussi été envoyé par e-mail.']);
        }
        $w = portal_find((string)input('ref'), (int)$ev['id']);
        if (!$w) redirect(Pay::path('/e/') . $slug, ['error' => 'Introuvable pour cet événement. Vérifie le code.']);
        redirect(Pay::path('/w/') . $w['token']);
    }
    $org = one('SELECT name, logo FROM organizers WHERE id = ?', [$ev['organizer_id']]);
    return portal_view('portal_event', compact('ev', 's', 'org'), 'Cashless · ' . $ev['name'], $ev);
}

function portal_wallet(string $token): string
{
    $w = one('SELECT * FROM wallets WHERE token = ?', [$token]);
    if (!$w || strlen($token) < 20) abort(404);
    $ev = one('SELECT * FROM events WHERE id = ?', [$w['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
    $s = Pay::settings($ev);
    $ended = strtotime((string)($ev['ends_at'] ?: $ev['starts_at'])) + 6 * 3600 < time();
    $canRefund = $ended && $ev['cashless_refund_until'] && date('Y-m-d') <= $ev['cashless_refund_until'] && (int)$w['balance'] > 0 && in_array($w['status'], ['active', 'blocked'], true);
    $here = Pay::path('/w/') . $token;
    if (is_post()) {
        $a = (string)input('action');
        try {
            if ($a === 'topup') {
                if ($ended || !(int)$ev['cashless_enabled']) throw new RuntimeException('Les recharges sont fermées pour cet événement.');
                if ($w['status'] !== 'active') throw new RuntimeException('Cette carte est bloquée : la recharge est impossible.');
                $amount = input('amount') === 'libre' ? parse_money((string)input('free')) : (int)input('amount');
                if ($amount < (int)$ev['cashless_min_topup'] || $amount > 30000) throw new RuntimeException('Recharge entre ' . money((int)$ev['cashless_min_topup']) . ' et 300 €.');
                if ((int)$w['balance'] + $amount > (int)$s['max_balance']) throw new RuntimeException('Le solde ne peut pas dépasser ' . money((int)$s['max_balance']) . '.');
                $t = $w['ticket_id'] ? one('SELECT * FROM tickets WHERE id = ?', [$w['ticket_id']]) : null;
                $buyer = one('SELECT email, first_name, last_name, phone FROM orders WHERE id = ?', [$t['order_id'] ?? 0]) ?: ['email' => $w['email'] ?: (string)input('email'), 'first_name' => Pay::holder($w) ?: 'Festivalier', 'last_name' => '', 'phone' => ''];
                if (!is_email((string)$buyer['email'])) throw new RuntimeException('Indique ton e-mail pour recevoir le reçu de recharge.');
                if (!$w['email']) update('wallets', ['email' => strtolower((string)$buyer['email'])], 'id = ?', [$w['id']]);
                $o = Store::createOrder($org, $buyer, [['kind' => 'topup', 'name' => 'Recharge cashless · ' . $ev['name'], 'qty' => 1, 'unit_price' => $amount, 'item_ref' => (int)$w['id']]], ['channel' => 'cashless']);
                $_SESSION['orders'][] = $o['ref'];
                redirect('/paiement/' . $o['ref'] . '?t=' . $o['token']);
            }
            if ($a === 'block' && $s['self_block'] && $w['status'] === 'active') {
                Pay::block($w, 'Déclaré perdu par le festivalier');
                redirect($here, ['success' => 'Carte et bracelet bloqués : ton solde de ' . money((int)$w['balance']) . ' est protégé. Passe au point info pour le reporter sur un nouveau bracelet.']);
            }
            if ($a === 'transfer' && $s['transfer']) {
                if (!throttle_pos('portal-xfer-' . $w['id'])) throw new RuntimeException('Trop d\'essais : patiente une minute.');
                $to = Pay::transfer($w, (string)input('to'), parse_money((string)input('amount')));
                redirect($here, ['success' => 'Envoyé ! ' . money(parse_money((string)input('amount'))) . ' ont été transférés sur la carte ' . substr($to['code'], 0, 4) . '…']);
            }
            if ($a === 'bracelet') {
                $ref = trim((string)input('ref'));
                $b = one("SELECT b.* FROM pay_wristbands b WHERE b.organizer_id = ? AND b.number = ? AND b.status = 'stock'", [$ev['organizer_id'], $ref]);
                if (!$b) throw new RuntimeException('Bracelet introuvable ou déjà utilisé : vérifie le numéro imprimé, ou passe au point info.');
                Pay::attachBracelet($w, $ref, (int)$ev['organizer_id']);
                redirect($here, ['success' => 'Bracelet associé : tu peux payer en l\'approchant du terminal.']);
            }
            if ($a === 'collect') { // 4.2 : click & collect
                if ($ended) throw new RuntimeException('Les commandes sont fermées.');
                $sale = Crew::collectOrder($w, $ev, (int)input('bar_id'), (array)($_POST['q'] ?? []));
                redirect($here, ['success' => 'Commande n° ' . $sale['order_no'] . ' envoyée au bar (' . money((int)$sale['amount']) . ' débités). Récupère-la au comptoir quand ton numéro s\'affiche.']);
            }
            if ($a === 'refund' && $canRefund) {
                $iban = strtoupper(preg_replace('~\s+~', '', (string)input('iban')));
                if (!iban_valid($iban)) throw new RuntimeException('Cet IBAN n\'est pas valide.');
                update('wallets', ['status' => 'refund_req', 'refund_iban' => $iban, 'email' => $w['email'] ?: (is_email((string)input('email')) ? strtolower((string)input('email')) : null)], 'id = ?', [$w['id']]);
                $fee = min((int)$w['balance'], (int)$s['refund_fee']);
                redirect($here, ['success' => 'Demande envoyée : ' . money((int)$w['balance'] - $fee) . ' te seront remboursés par virement.']);
            }
        } catch (RuntimeException $e) { redirect($here, ['error' => $e->getMessage()]); }
        redirect($here);
    }
    $tx = all('SELECT t.*, b.name bar FROM wallet_tx t LEFT JOIN bars b ON b.id = t.bar_id WHERE t.wallet_id = ? AND t.undone = 0 ORDER BY t.id DESC LIMIT 60', [$w['id']]);
    $hasBracelet = (bool)$w['nfc_uid'];
    $cBars = !$ended && (int)$ev['cashless_enabled'] ? Crew::collectBars($ev) : [];
    $cMenu = $cBars ? Crew::menu($ev, (int)$cBars[0]['id']) : [];
    $cOrders = Crew::myOrders($w);
    return portal_view('portal_wallet', compact('w', 'ev', 'org', 's', 'tx', 'ended', 'canRefund', 'hasBracelet', 'cBars', 'cMenu', 'cOrders'), 'Ma carte · ' . $ev['name'], $ev);
}

function portal_qr(string $token): never
{
    $w = one('SELECT code FROM wallets WHERE token = ?', [$token]);
    if (!$w || strlen($token) < 20) abort(404);
    header('Content-Type: image/svg+xml'); header('Cache-Control: private, max-age=86400');
    echo Qr::svg($w['code'], 240); exit;
}
