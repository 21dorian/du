<?php
declare(strict_types=1);

/* 1.8 : donner son billet, agenda, reprise d'un panier abandonné, avis. */

require_once APP . '/controllers/public.php';
require_once APP . '/controllers/drawer.php';

function give_input(): array
{
    $first = mb_substr(trim((string)input('to_first_name')), 0, 120);
    $last = mb_substr(trim((string)input('to_last_name')), 0, 120);
    $email = strtolower(trim((string)input('to_email')));
    $errors = [];
    if ($first === '' || $last === '') $errors[] = 'Indique le prénom et le nom de la personne.';
    if (!is_email($email)) $errors[] = 'Son adresse e-mail a l\'air bizarre';
    return [$first, $last, $email, $errors];
}

/** L'acheteur donne un billet de sa commande. */
function ticket_give(string $ref): never
{
    $o = order_by_ref($ref);
    $back = '/commande/' . $o['ref'] . '?t=' . $o['token'];
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $t = one('SELECT * FROM tickets WHERE id = ? AND order_id = ? AND holder_email IS NULL', [(int)input('ticket_id'), $o['id']]);
    if (!$t) redirect($back, ['error' => 'Billet introuvable.']);
    if ($why = Buyers::cannotTransfer($t, $o, $ev)) redirect($back, ['error' => $why]);
    [$first, $last, $email, $errors] = give_input();
    if (strtolower((string)$o['email']) === $email) $errors[] = 'C\'est ta propre adresse Pour changer juste le nom, utilise « Qui vient avec toi ? ».';
    if ($errors) redirect($back, ['error' => implode(' ', $errors)]);
    Buyers::transfer($t, $o, $ev, $first, $last, $email, trim($o['first_name'] . ' ' . $o['last_name']), (string)$o['email']);
    redirect($back, ['success' => 'Billet envoyé à ' . $first . ' ! Il ou elle le reçoit par e-mail, et ton ancien QR code ne passe plus.']);
}

function held_ticket_load(string $token): array
{
    if (strlen($token) < 20) abort(404);
    $t = one('SELECT * FROM tickets WHERE holder_token = ?', [$token]);
    if (!$t) abort(404, 'Ce billet a changé de main : demande le nouveau lien à la personne qui l\'a reçu.');
    $o = one('SELECT * FROM orders WHERE id = ?', [$t['order_id']]);
    $ev = one('SELECT * FROM events WHERE id = ?', [$t['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
    return [$t, $o, $ev, $org];
}

/** Page du billet reçu (le destinataire n'a pas accès à la commande d'origine). */
function held_ticket(string $token): string
{
    [$t, $o, $ev, $org] = held_ticket_load($token);
    $session = $t['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$t['session_id']]) : ($o['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$o['session_id']]) : null);
    $map = Seating::map($ev);
    $why = Buyers::cannotTransfer($t, $o, $ev);
    return pub('held_ticket', compact('t', 'o', 'ev', 'org', 'session', 'map', 'token', 'why'), 'Ton billet · ' . $ev['name']);
}

function held_ticket_pdf(string $token): never
{
    [$t, $o, $ev, $org] = held_ticket_load($token);
    if ($t['status'] !== 'valid' || $o['status'] !== 'paid') abort(404, 'Ce billet n\'est plus valable.');
    $pdf = TicketPdf::build($o, $ev, $org, [$t]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (input('dl') ? 'attachment' : 'inline') . '; filename="billet-' . $t['code'] . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

/** Le destinataire peut à son tour donner le billet (dans la limite des transferts). */
function held_ticket_give(string $token): never
{
    [$t, $o, $ev] = held_ticket_load($token);
    $back = '/billet/' . $token;
    if ($why = Buyers::cannotTransfer($t, $o, $ev)) redirect($back, ['error' => $why]);
    [$first, $last, $email, $errors] = give_input();
    if ($email === $t['holder_email']) $errors[] = 'C\'est déjà ton adresse';
    if ($errors) redirect($back, ['error' => implode(' ', $errors)]);
    $from = trim($t['first_name'] . ' ' . $t['last_name']);
    Buyers::transfer($t, $o, $ev, $first, $last, $email, $from, (string)$t['holder_email']);
    redirect('/e/' . $ev['slug'], ['success' => 'Billet envoyé à ' . $first . ' ! Ton ancien QR code ne passe plus.']);
}

// ---------- Agenda ----------
function event_ics(string $slug): never
{
    $ev = one("SELECT * FROM events WHERE slug = ? AND status IN ('published','closed')", [$slug]);
    if (!$ev) abort(404);
    $sid = (int)input('seance');
    $session = $sid ? one('SELECT * FROM sessions WHERE id = ? AND event_id = ?', [$sid, $ev['id']]) : null;
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $ev['slug'] . '.ics"');
    echo Buyers::ics($ev, $session);
    exit;
}

// ---------- Panier abandonné ----------
function recover_cart(string $ref): never
{
    $o = order_by_ref($ref);
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    if ($o['status'] === 'paid') redirect('/commande/' . $o['ref'] . '?t=' . $o['token'], ['info' => 'Bonne nouvelle : cette commande est déjà payée.']);
    if ($o['status'] === 'pending' && $o['expires_at'] && strtotime((string)$o['expires_at']) > time()) redirect('/paiement/' . $o['ref'] . '?t=' . $o['token']);
    if (val("SELECT id FROM orders WHERE LOWER(email) = ? AND event_id = ? AND status = 'paid' AND id > ? LIMIT 1", [strtolower((string)$o['email']), $o['event_id'], $o['id']]))
        redirect('/e/' . $ev['slug'], ['info' => 'Tu as déjà pris tes billets pour cet événement depuis. Bonne fête !']);
    [$ev, $cart] = Buyers::restoreCart($o);
    $code = strtoupper(preg_replace('~[^A-Za-z0-9-]~', '', (string)input('code')));
    if ($code !== '' && empty(Tickets::findPromo((int)$ev['id'], $code)['error'])) $cart['promo'] = $code;
    cart_set((int)$ev['id'], $cart);
    $_SESSION['recover'][$ev['id']] = (int)$o['id'];
    $_SESSION['buyer'] = ['first_name' => $o['first_name'], 'last_name' => $o['last_name'], 'email' => $o['email'], 'phone' => $o['phone']];
    $hasMap = (bool)Seating::map($ev);
    redirect('/e/' . $ev['slug'] . ($cart['session'] ? '?seance=' . (int)$cart['session'] : '') . ($hasMap ? '' : '#acheter'), ['success' => 'Ton panier est de retour ! Vérifie les quantités et c\'est reparti.']);
}

// ---------- Avis ----------
function review_page(string $ref): string
{
    $o = order_by_ref($ref);
    if ($o['status'] !== 'paid') abort(404);
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
    $rev = one('SELECT * FROM reviews WHERE order_id = ?', [$o['id']]);
    $note = max(0, min(5, (int)input('note')));
    $done = false;
    if (is_post() || ($note && !$rev)) {
        $rating = max(1, min(5, (int)input('rating', $note)));
        $data = ['rating' => $rating, 'updated_at' => now()];
        if (is_post()) {
            $data['comment'] = mb_substr(trim((string)input('comment')), 0, 1000) ?: null;
            $data['author'] = input('anonymous') ? null : mb_substr(trim($o['first_name'] . ' ' . mb_substr((string)$o['last_name'], 0, 1) . '.'), 0, 120);
        }
        if ($rev) update('reviews', $data, 'id = ?', [$rev['id']]);
        else insert('reviews', $data + ['order_id' => $o['id'], 'event_id' => $ev['id'], 'organizer_id' => $org['id'], 'created_at' => now(), 'visible' => 1,
            'author' => $data['author'] ?? mb_substr(trim($o['first_name'] . ' ' . mb_substr((string)$o['last_name'], 0, 1) . '.'), 0, 120),
            'verified' => (int)(bool)val('SELECT 1 FROM tickets WHERE order_id = ? AND entries > 0 LIMIT 1', [$o['id']])]);
        $rev = one('SELECT * FROM reviews WHERE order_id = ?', [$o['id']]);
        $done = is_post();
    }
    return pub('review', compact('o', 'ev', 'org', 'rev', 'done'), 'Ton avis · ' . $ev['name']);
}

// ---------- 1.9 : prévente privée ----------
function presale_request(string $slug): never
{
    [$ev] = public_event($slug, false);
    $email = strtolower(trim((string)input('email')));
    $back = '/e/' . $ev['slug'] . '#billets';
    if (!Buyers::presaleActive($ev)) redirect($back);
    if (!is_email($email)) redirect($back, ['error' => 'Cette adresse e-mail a l\'air bizarre']);
    if (throttle_ok('prevente-' . $ev['id'] . '-' . $email) && ($why = Buyers::presaleAllowed($ev, $email))) {
        try { Buyers::sendPresaleLink($ev, $email, $why); } catch (Throwable $e) { log_line('emails', 'Prévente : ' . $e->getMessage()); }
    }
    redirect($back, ['info' => 'Si ton adresse fait partie de la prévente, ton lien d\'accès vient de partir. Pense à regarder tes spams.']);
}

function presale_enter(string $slug, string $token): never
{
    [$ev] = public_event($slug, false);
    $raw = base64_decode(strtr($token, '-_', '+/'), true) ?: '';
    [$email, $sig] = array_pad(explode('|', $raw), 2, '');
    if (!is_email($email) || !hash_equals(Buyers::presaleSig((int)$ev['id'], $email), $sig)) redirect('/e/' . $ev['slug'], ['error' => 'Ce lien de prévente n\'est pas valable.']);
    $_SESSION['presale'][$ev['id']] = strtolower($email);
    $_SESSION['buyer'] = ($_SESSION['buyer'] ?? []) + ['email' => strtolower($email)];
    redirect('/e/' . $ev['slug'] . '#acheter', ['success' => 'Bienvenue dans la prévente ! Les billets sont à toi avant tout le monde.']);
}

// ---------- 1.9 : changer de date ----------
function order_exchange(string $ref): never
{
    $o = order_by_ref($ref);
    $back = '/commande/' . $o['ref'] . '?t=' . $o['token'];
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $err = Buyers::exchange($o, $ev, (int)input('session_id'));
    if ($err) redirect($back, ['error' => $err]);
    $s = one('SELECT * FROM sessions WHERE id = ?', [(int)input('session_id')]);
    redirect($back, ['success' => 'C\'est changé ! Rendez-vous le ' . fr_date((string)$s['starts_at']) . '. Tes billets (mêmes QR codes) t\'ont été renvoyés par e-mail.']);
}
