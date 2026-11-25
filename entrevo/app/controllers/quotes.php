<?php
declare(strict_types=1);

/* 2.3 : groupes & devis, factures acheteurs. */

require_once APP . '/controllers/public.php';

// ---------- Public ----------
function quote_request(string $slug): string
{
    [$ev, $org] = public_event($slug, false);
    if (!(int)($org['quotes_enabled'] ?? 1)) abort(404);
    $errors = []; $done = null;
    $v = ['company' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'qty' => (int)input('qty', 10), 'message' => ''];
    if (is_post()) {
        foreach ($v as $k => $_) $v[$k] = trim((string)input($k));
        $v['qty'] = (int)$v['qty'];
        if ($v['contact_name'] === '' || !is_email($v['email'])) $errors[] = 'Indique ton nom et une adresse e-mail valide.';
        if ($v['qty'] < (int)setting('quote_min_' . $org['id'], '10')) $errors[] = 'Les devis de groupe commencent à ' . (int)setting('quote_min_' . $org['id'], '10') . ' places. En dessous, réserve directement en ligne.';
        if (trim((string)input('website')) !== '' || !throttle_ok_pub('devis')) $errors[] = 'Requête refusée.';
        if (!$errors) {
            $q = Quotes::create($ev, $v + ['lines' => []]);
            Hooks::fire((int)$org['id'], 'quote.requested', ['ref' => $q['ref'], 'event_id' => (int)$ev['id'], 'company' => $q['company'], 'contact_name' => $q['contact_name'], 'email' => $q['email'], 'qty' => (int)$q['qty_wanted'], 'message' => $q['message']]);
            try { Mailer::send((string)$org['email'], 'Demande de devis groupe · ' . $ev['name'], '<p><b>' . e($v['company'] ?: $v['contact_name']) . '</b> demande un devis pour <b>' . (int)$v['qty'] . ' places</b>.</p>' . ($v['message'] ? '<blockquote>' . nl2br(e($v['message'])) . '</blockquote>' : '') . '<p><a href="' . e(abs_url('/pro/devis/' . $q['id'])) . '">Préparer le devis</a></p>', [], $v['email']); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            $done = $q;
        }
    }
    return pub('quote_request', compact('ev', 'org', 'v', 'errors', 'done'), 'Devis groupe · ' . $ev['name']);
}

function quote_view(string $token): string
{
    $q = one('SELECT * FROM quotes WHERE token = ?', [$token]);
    if (!$q || strlen($token) < 20 || $q['status'] === 'request') abort(404);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$q['organizer_id']]);
    $ev = one('SELECT * FROM events WHERE id = ?', [$q['event_id']]);
    $expired = $q['valid_until'] && strtotime($q['valid_until'] . ' 23:59:59') < time() && in_array($q['status'], ['sent'], true);
    if (is_post() && !$expired && in_array($q['status'], ['sent', 'accepted'], true)) {
        $a = (string)input('action');
        if ($a === 'decline') { update('quotes', ['status' => 'declined'], 'id = ?', [$q['id']]); redirect('/devis/' . $token, ['info' => 'C\'est noté, merci de ta réponse.']); }
        if ($a === 'card' && $q['pay_mode'] !== 'transfer') {
            try { $o = Quotes::order($q); } catch (Throwable $e) { redirect('/devis/' . $token, ['error' => 'Impossible de préparer la commande : ' . $e->getMessage()]); }
            update('quotes', ['status' => 'accepted', 'accepted_at' => now()], 'id = ?', [$q['id']]);
            $_SESSION['orders'][] = $o['ref'];
            redirect('/paiement/' . $o['ref'] . '?t=' . $o['token']);
        }
        if ($a === 'transfer' && $q['pay_mode'] !== 'card') {
            update('quotes', ['status' => 'accepted', 'accepted_at' => now()], 'id = ?', [$q['id']]);
            try { Mailer::send((string)$org['email'], 'Devis ' . $q['ref'] . ' accepté (virement)', '<p>' . e($q['company'] ?: $q['contact_name']) . ' a accepté le devis ' . e($q['ref']) . ' et va payer par virement. Dès réception, clique sur « Virement reçu » pour envoyer les billets.</p><p><a href="' . e(abs_url('/pro/devis/' . $q['id'])) . '">Ouvrir le devis</a></p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            redirect('/devis/' . $token, ['success' => 'Merci ! Fais le virement avec la référence ' . $q['ref'] . ' : tes billets partent dès sa réception.']);
        }
    }
    if ((string)input('pdf') === '1') { header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="devis-' . $q['ref'] . '.pdf"'); echo Quotes::pdf($q); exit; }
    $t = Quotes::totals($q, $org);
    $order = $q['order_id'] ? one('SELECT * FROM orders WHERE id = ?', [$q['order_id']]) : null;
    return pub('quote_view', compact('q', 'org', 'ev', 't', 'expired', 'order'), 'Devis ' . $q['ref']);
}

/** Facture acheteur : formulaire sur la page de commande, PDF ensuite. */
function order_invoice(string $ref): never
{
    $o = order_by_ref($ref);
    if (!in_array($o['status'], ['paid', 'refunded'], true) || (int)$o['total'] <= 0) abort(404);
    if (is_post() && !$o['invoice_number']) {
        update('orders', ['invoice_company' => mb_substr(trim((string)input('invoice_company')), 0, 190) ?: null, 'invoice_address' => mb_substr(trim((string)input('invoice_address')), 0, 500) ?: null,
            'invoice_vat' => mb_substr(trim((string)input('invoice_vat')), 0, 40) ?: null], 'id = ?', [$o['id']]);
        $o = one('SELECT * FROM orders WHERE id = ?', [$o['id']]);
    }
    $pdf = Quotes::invoice($o);
    $o = one('SELECT * FROM orders WHERE id = ?', [$o['id']]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (is_post() ? 'attachment' : 'inline') . '; filename="facture-' . $o['invoice_number'] . '.pdf"');
    echo $pdf; exit;
}

// ---------- Organisateur ----------
function pro_quotes(): string
{
    require_once APP . '/controllers/pro.php';
    $org = require_org();
    require_module('devis');
    if (is_post()) {
        if (input('action') === 'settings') {
            update('organizers', ['quotes_enabled' => checked('quotes_enabled')], 'id = ?', [$org['id']]);
            set_setting('quote_min_' . $org['id'], (string)max(2, (int)input('quote_min')));
            back(['success' => 'Réglages enregistrés.']);
        }
        $ev = one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [(int)input('event_id'), $org['id']]);
        if (!$ev) back(['error' => 'Choisis un événement.']);
        if (!is_email(strtolower(trim((string)input('email'))))) back(['error' => 'Indique l\'e-mail du client.']);
        $q = Quotes::create($ev, ['company' => trim((string)input('company')), 'contact_name' => trim((string)input('contact_name')) ?: trim((string)input('company')), 'email' => trim((string)input('email')), 'phone' => '', 'qty' => 0, 'message' => '', 'lines' => []]);
        redirect('/pro/devis/' . $q['id']);
    }
    $f = (string)input('f');
    $w = in_array($f, array_keys(Quotes::STATUS), true) ? ' AND q.status = ' . db()->quote($f) : '';
    $list = all("SELECT q.*, e.name ev_name FROM quotes q JOIN events e ON e.id = q.event_id WHERE q.organizer_id = ?$w ORDER BY q.id DESC LIMIT 200", [$org['id']]);
    foreach ($list as &$q) $q['t'] = Quotes::totals($q, $org);
    unset($q);
    $k = one("SELECT SUM(status = 'request') req, SUM(status IN ('sent','accepted')) open, SUM(status = 'paid') paid, COUNT(*) n FROM quotes WHERE organizer_id = ?", [$org['id']]);
    $won = (int)val("SELECT COALESCE(SUM(o.total), 0) FROM quotes q JOIN orders o ON o.id = q.order_id WHERE q.organizer_id = ? AND o.status = 'paid'", [$org['id']]);
    $events = all('SELECT id, name, starts_at FROM events WHERE organizer_id = ? AND starts_at >= NOW() ORDER BY starts_at', [$org['id']]);
    return pro_page('Groupes & devis', 'quotes/index', compact('org', 'list', 'k', 'won', 'events', 'f'), 'devis');
}

function pro_quote(string $id): string
{
    require_once APP . '/controllers/pro.php';
    $org = require_org();
    $q = one('SELECT * FROM quotes WHERE id = ? AND organizer_id = ?', [(int)$id, $org['id']]);
    if (!$q) abort(404);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'transfer_paid') {
            try { $o = Quotes::markTransferPaid($q); } catch (Throwable $e) { back(['error' => $e->getMessage()]); }
            Audit::log('quote.paid', 'Devis ' . $q['ref'] . ' payé par virement', (int)$q['event_id'], (int)$o['id']);
            back(['success' => 'Virement enregistré : commande ' . $o['ref'] . ' créée, billets envoyés à ' . $q['email'] . '.']);
        }
        if ($a === 'decline') { update('quotes', ['status' => 'declined'], 'id = ?', [$q['id']]); back(['success' => 'Devis classé comme refusé.']); }
        $lines = [];
        foreach ((array)($_POST['qty'] ?? []) as $rid => $n) if ((int)$n > 0) $lines[] = ['rate_id' => (int)$rid, 'qty' => min(5000, (int)$n)];
        update('quotes', ['items_json' => json_encode($lines), 'discount_pct' => max(0, min(90, (float)str_replace(',', '.', (string)input('discount_pct')))),
            'valid_until' => input('valid_until') ?: null, 'pay_mode' => in_array(input('pay_mode'), ['both', 'card', 'transfer'], true) ? (string)input('pay_mode') : 'both',
            'notes' => trim((string)input('notes')) ?: null, 'company' => trim((string)input('company')) ?: null, 'contact_name' => trim((string)input('contact_name')) ?: $q['contact_name'],
            'address' => trim((string)input('address')) ?: null, 'promo_code' => null, 'session_id' => (int)input('session_id') ?: null], 'id = ?', [$q['id']]);
        if ($q['promo_code']) q('DELETE FROM promos WHERE event_id = ? AND code = ? AND used = 0', [$q['event_id'], $q['promo_code']]);
        $q = one('SELECT * FROM quotes WHERE id = ?', [$q['id']]);
        if ($a === 'send') {
            if (!Quotes::lines($q)) back(['error' => 'Ajoute au moins une ligne avant d\'envoyer le devis.']);
            if (!$q['session_id'] && Tickets::hasSessions((int)$q['event_id'])) back(['error' => 'Choisis la date concernée par le devis.']);
            try { Quotes::send($q); } catch (Throwable $e) { back(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
            Audit::log('quote.sent', 'Devis ' . $q['ref'] . ' envoyé à ' . $q['email'], (int)$q['event_id']);
            back(['success' => 'Devis envoyé à ' . $q['email'] . ' avec le PDF.']);
        }
        back(['success' => 'Devis enregistré.']);
    }
    if (input('pdf')) { header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="devis-' . $q['ref'] . '.pdf"'); echo Quotes::pdf($q); exit; }
    $ev = one('SELECT * FROM events WHERE id = ?', [$q['event_id']]);
    $rates = all('SELECT r.*, (SELECT COUNT(*) FROM tickets t WHERE t.rate_id = r.id AND t.status = \'valid\') sold FROM rates r WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    $t = Quotes::totals($q, $org);
    $order = $q['order_id'] ? one('SELECT * FROM orders WHERE id = ?', [$q['order_id']]) : null;
    $sessions = Tickets::sessions((int)$ev['id'], false);
    return pro_page('Devis ' . $q['ref'], 'quotes/edit', compact('q', 'ev', 'rates', 't', 'org', 'order', 'sessions'), 'devis', ['crumbs' => [['Groupes & devis', '/pro/devis']]]);
}
