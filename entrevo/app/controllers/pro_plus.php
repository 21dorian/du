<?php
declare(strict_types=1);

// Onglets organisateur ajoutés en 1.1.0 : dates, options, plan de salle, invitations & partenaires,
// accréditations, liste d'attente & revente, messages, statistiques, caisse, documents PDF.
require_once APP . '/controllers/pro.php';

// ---------- Dates / séances ----------
function sessions_tab(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $a = (string)input('action');
        $sid = (int)input('session_id');
        if ($a === 'delete') {
            if (val("SELECT id FROM tickets WHERE session_id = ? AND status = 'valid' LIMIT 1", [$sid])) back(['error' => 'Des billets sont vendus pour cette date : désactive-la plutôt.']);
            q('DELETE FROM sessions WHERE id = ? AND event_id = ?', [$sid, $ev['id']]);
            Tickets::syncEventDates((int)$ev['id']);
            back(['success' => 'Date supprimée.']);
        }
        if ($a === 'toggle') { q('UPDATE sessions SET active = 1 - active WHERE id = ? AND event_id = ?', [$sid, $ev['id']]); Tickets::syncEventDates((int)$ev['id']); back(); }
        if ($a === 'generate') {
            // dates récurrentes : du … au …, certains jours, à certaines heures
            $from = strtotime((string)input('from'));
            $to = strtotime((string)input('to'));
            $days = array_map('intval', (array)($_POST['days'] ?? []));
            $times = array_filter(array_map('trim', explode(',', (string)input('times'))), fn($t) => preg_match('~^\d{1,2}[:h]\d{2}$~', $t));
            $dur = max(0, (int)input('duration'));
            $cap = max(0, (int)input('capacity'));
            if (!$from || !$to || $to < $from || !$days || !$times) back(['error' => 'Indique une période, au moins un jour et une heure (ex. 14:00, 20:30).']);
            if (($to - $from) / 86400 > 400) back(['error' => 'Période trop longue (13 mois max).']);
            $n = 0;
            for ($d = $from; $d <= $to; $d += 86400) {
                if (!in_array((int)date('N', $d), $days, true)) continue;
                foreach ($times as $t) {
                    $start = date('Y-m-d', $d) . ' ' . str_replace('h', ':', $t) . ':00';
                    if (val('SELECT id FROM sessions WHERE event_id = ? AND starts_at = ?', [$ev['id'], $start])) continue;
                    insert('sessions', ['event_id' => $ev['id'], 'label' => trim((string)input('label')) ?: null, 'starts_at' => $start,
                        'ends_at' => $dur ? date('Y-m-d H:i:s', strtotime($start) + $dur * 60) : null, 'capacity' => $cap, 'active' => 1]);
                    if (++$n >= 500) break 2;
                }
            }
            Tickets::syncEventDates((int)$ev['id']);
            back(['success' => '' . $n . ' date(s) ajoutée(s).']);
        }
        $data = ['label' => trim((string)input('label')) ?: null, 'starts_at' => dt_from_input(input('starts_at')), 'ends_at' => dt_from_input(input('ends_at')),
            'capacity' => max(0, (int)input('capacity')), 'active' => 1];
        if (!$data['starts_at']) back(['error' => 'Il manque la date de début.']);
        if ($sid) { unset($data['active']); update('sessions', $data, 'id = ? AND event_id = ?', [$sid, $ev['id']]); }
        else insert('sessions', $data + ['event_id' => $ev['id']]);
        Tickets::syncEventDates((int)$ev['id']);
        back(['success' => 'Date enregistrée.']);
    }
    $sessions = all("SELECT s.*, (SELECT COUNT(*) FROM tickets t WHERE t.session_id = s.id AND t.status = 'valid' AND t.type IN ('ticket','invitation')) sold,
                     (SELECT COUNT(*) FROM tickets t WHERE t.session_id = s.id AND t.status = 'valid' AND t.entries > 0) checked FROM sessions s WHERE event_id = ? ORDER BY starts_at", [$ev['id']]);
    return ev_page($ev, 'sessions', 'Dates & séances', 'sessions', ['sessions' => $sessions]);
}

// ---------- Options (ventes additionnelles) ----------
function products_tab(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $pid = (int)input('product_id');
        if (input('action') === 'delete') {
            if (val("SELECT i.id FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.product_id = ? AND o.status = 'paid' LIMIT 1", [$pid])) back(['error' => 'Cette option a déjà été vendue : désactive-la plutôt.']);
            q('DELETE FROM products WHERE id = ? AND event_id = ?', [$pid, $ev['id']]);
            back(['success' => 'Option supprimée.']);
        }
        $data = ['name' => trim((string)input('name')), 'description' => trim((string)input('description')) ?: null, 'price' => parse_money(input('price')),
            'stock' => max(0, (int)input('stock')), 'max_per_order' => max(0, (int)input('max_per_order')), 'active' => checked('active'), 'sort' => (int)input('sort'),
            'variants' => implode(', ', array_slice(array_values(array_unique(array_filter(array_map(fn($v) => mb_substr(trim($v), 0, 30), explode(',', (string)input('variants'))), fn($v) => $v !== ''))), 0, 20)) ?: null];
        if ($data['name'] === '') back(['error' => 'Donne un nom à cette option.']);
        if ($pid) update('products', $data, 'id = ? AND event_id = ?', [$pid, $ev['id']]); else insert('products', $data + ['event_id' => $ev['id']]);
        back(['success' => 'Option enregistrée.']);
    }
    $products = all("SELECT p.*, (SELECT COALESCE(SUM(i.qty),0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.product_id = p.id AND o.status = 'paid') sold,
                     (SELECT COALESCE(SUM(i.qty * i.unit_price),0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.product_id = p.id AND o.status = 'paid') revenue
                     FROM products p WHERE event_id = ? ORDER BY sort, id", [$ev['id']]);
    return ev_page($ev, 'products', 'Options & ventes additionnelles', 'products', ['products' => $products]);
}

// ---------- Plan de salle ----------
function seating_tab(string $id): string
{
    require_module('placement');
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        if (input('action') === 'remove') {
            if (val("SELECT id FROM tickets WHERE event_id = ? AND seat IS NOT NULL AND status = 'valid' LIMIT 1", [$ev['id']])) back(['error' => 'Des places numérotées sont déjà vendues : impossible de supprimer le plan.']);
            update('events', ['seatmap' => null], 'id = ?', [$ev['id']]);
            back(['success' => 'Plan supprimé : placement libre.']);
        }
        $raw = json_decode((string)input('seatmap'), true);
        if (!is_array($raw) || empty($raw['blocks'])) back(['error' => 'Ajoute au moins un bloc de places.']);
        $clean = ['stage' => mb_substr(trim((string)($raw['stage'] ?? 'Scène')), 0, 60) ?: 'Scène', 'categories' => [], 'blocks' => [], 'disabled' => []];
        foreach ((array)$raw['categories'] as $c) {
            $cid = preg_replace('~[^a-z0-9]~', '', strtolower((string)($c['id'] ?? '')));
            if ($cid === '') continue;
            $clean['categories'][] = ['id' => $cid, 'name' => mb_substr(trim((string)($c['name'] ?? $cid)), 0, 60), 'color' => preg_match('~^#[0-9a-fA-F]{6}$~', (string)($c['color'] ?? '')) ? $c['color'] : '#888888'];
        }
        $ids = [];
        foreach ((array)$raw['blocks'] as $b) {
            $bid = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', (string)($b['id'] ?? '')));
            if ($bid === '' || isset($ids[$bid])) continue;
            $ids[$bid] = 1;
            $clean['blocks'][] = ['id' => mb_substr($bid, 0, 8), 'name' => mb_substr(trim((string)($b['name'] ?? $bid)), 0, 60), 'rows' => max(1, min(60, (int)$b['rows'])),
                'cols' => max(1, min(80, (int)$b['cols'])), 'x' => max(0, min(200, (int)$b['x'])), 'y' => max(0, min(200, (int)$b['y'])),
                'row_start' => preg_replace('~[^A-Za-z0-9]~', '', (string)($b['row_start'] ?? 'A')) ?: 'A', 'num_start' => max(1, (int)($b['num_start'] ?? 1)),
                'cat' => preg_replace('~[^a-z0-9]~', '', strtolower((string)($b['cat'] ?? '')))];
        }
        $clean['disabled'] = array_values(array_slice(array_filter(array_map(fn($k) => preg_replace('~[^A-Za-z0-9-]~', '', (string)$k), (array)($raw['disabled'] ?? []))), 0, 5000));
        update('events', ['seatmap' => json_encode($clean, JSON_UNESCAPED_UNICODE)], 'id = ?', [$ev['id']]);
        $count = count(array_filter(Seating::seats($clean), fn($x) => !$x['off']));
        Audit::log('seatmap.saved', $count . ' places', (int)$ev['id']);
        back(['success' => 'Plan enregistré : ' . $count . ' places. Pense à mettre la capacité de l\'événement à jour.']);
    }
    $sold = (int)val("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND seat IS NOT NULL AND status = 'valid'", [$ev['id']]);
    return ev_page($ev, 'seating', 'Plan de salle', 'seating', ['map' => Seating::map($ev), 'sold' => $sold, 'payload' => Seating::payload($ev, null)]);
}

// ---------- Invitations (envoi en masse) & partenaires ----------
function invites_tab(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    $rates = all('SELECT * FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    $sessions = Tickets::sessions((int)$ev['id']);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'partner') {
            $rate = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [(int)input('rate_id'), $ev['id']]);
            if (!$rate || trim((string)input('name')) === '') back(['error' => 'Nom du partenaire et tarif obligatoires.']);
            insert('partners', ['event_id' => $ev['id'], 'name' => trim((string)input('name')), 'email' => trim((string)input('email')) ?: null, 'token' => token(16),
                'rate_id' => $rate['id'], 'quota' => max(0, (int)input('quota')), 'free' => checked('free'), 'active' => 1, 'created_at' => now()]);
            back(['success' => 'Partenaire créé. Envoie-lui son lien !']);
        }
        if ($a === 'partner_toggle') { q('UPDATE partners SET active = 1 - active WHERE id = ? AND event_id = ?', [(int)input('partner_id'), $ev['id']]); back(); }
        if ($a === 'invite') {
            // une ligne par invité : prénom ; nom ; e-mail [; nombre]
            $rate = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [(int)input('rate_id'), $ev['id']]);
            $session = $sessions ? one('SELECT * FROM sessions WHERE id = ? AND event_id = ?', [(int)input('session_id'), $ev['id']]) : null;
            if (!$rate) back(['error' => 'Choisis un tarif.']);
            if ($sessions && !$session) back(['error' => 'Choisis une date.']);
            $lines = preg_split('~\R~', trim((string)input('guests')));
            if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) $lines = array_merge($lines, file($_FILES['csv']['tmp_name'], FILE_IGNORE_NEW_LINES) ?: []);
            $ok = 0; $bad = [];
            foreach ($lines as $i => $line) {
                $line = trim((string)preg_replace('~^\xEF\xBB\xBF~', '', (string)$line));
                if ($line === '') continue;
                $c = array_map('trim', str_getcsv($line, str_contains($line, ';') ? ';' : ',', '"', '\\'));
                [$fn, $ln, $mail, $nb] = array_pad($c, 4, '');
                if (strtolower($fn) === 'prénom' || strtolower($fn) === 'prenom') continue;
                $nb = max(1, min(20, (int)$nb ?: 1));
                if ($ln === '' && $fn === '') { $bad[] = 'ligne ' . ($i + 1); continue; }
                if ($mail !== '' && !is_email($mail)) { $bad[] = $mail; continue; }
                $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'session' => $session, 'quantities' => [$rate['id'] => $nb], 'method' => 'invitation', 'force' => checked('force'),
                    'buyer' => ['first_name' => $fn, 'last_name' => $ln, 'email' => $mail], 'note' => trim((string)input('note')), 'user_id' => user()['id'], 'channel' => 'invitation']);
                if (!empty($r['errors'])) { $bad[] = $fn . ' ' . $ln . ' (' . implode(' ', $r['errors']) . ')'; continue; }
                Tickets::confirm((int)$r['order']['id'], [], $mail !== '' && checked('send'));
                $ok++;
                if ($ok >= 1000) break;
            }
            if ($ok) Audit::log('invites.sent', $ok . ' invitation(s)', (int)$ev['id']);
            back([$ok ? 'success' : 'error' => '' . $ok . ' invitation(s) créée(s)' . ($bad ? '. Ignorées : ' . implode(', ', array_slice($bad, 0, 10)) . (count($bad) > 10 ? '…' : '') : '.')]);
        }
        back();
    }
    $partners = all("SELECT p.*, r.name rate_name, (SELECT COUNT(*) FROM tickets t JOIN orders o ON o.id = t.order_id WHERE o.partner_id = p.id AND t.status = 'valid') used
                     FROM partners p JOIN rates r ON r.id = p.rate_id WHERE p.event_id = ? ORDER BY p.id DESC", [$ev['id']]);
    $invites = all("SELECT o.*, (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id) n FROM orders o WHERE o.event_id = ? AND o.method = 'invitation' AND o.status = 'paid' ORDER BY o.id DESC LIMIT 100", [$ev['id']]);
    return ev_page($ev, 'invites', 'Invitations & partenaires', 'invites', compact('rates', 'sessions', 'partners', 'invites'));
}

// ---------- Accréditations (staff, presse, artistes, bénévoles…) ----------
function accred_tab(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $aps = all('SELECT * FROM access_points WHERE event_id = ? ORDER BY id', [$ev['id']]);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'population') {
            $pid = (int)input('population_id');
            $data = ['name' => trim((string)input('name')) ?: 'Staff', 'color' => preg_match('~^#[0-9a-fA-F]{6}$~', (string)input('color')) ? (string)input('color') : '#0032fa',
                'access_point_ids' => implode(',', array_map('intval', (array)($_POST['zones'] ?? []))) ?: null];
            if ($pid) update('populations', $data, 'id = ? AND event_id = ?', [$pid, $ev['id']]); else insert('populations', $data + ['event_id' => $ev['id']]);
            back(['success' => 'Population enregistrée.']);
        }
        if ($a === 'add') {
            $pop = one('SELECT * FROM populations WHERE id = ? AND event_id = ?', [(int)input('population_id'), $ev['id']]);
            if (!$pop) back(['error' => 'Crée d\'abord une population (ex. Bénévoles, Presse).']);
            $lines = preg_split('~\R~', trim((string)input('people')));
            $n = 0;
            $orderId = accred_order($ev);
            foreach ($lines as $line) {
                $c = array_map('trim', str_getcsv(trim((string)$line), str_contains((string)$line, ';') ? ';' : ',', '"', '\\'));
                [$fn, $ln, $company, $mail] = array_pad($c, 4, '');
                if ($fn === '' && $ln === '') continue;
                insert('tickets', ['order_id' => $orderId, 'event_id' => $ev['id'], 'rate_id' => 0, 'rate_name' => $pop['name'], 'code' => Tickets::newCode(),
                    'first_name' => mb_substr($fn, 0, 120), 'last_name' => mb_substr($ln, 0, 120), 'company' => mb_substr($company, 0, 190) ?: null,
                    'answers' => $mail && is_email($mail) ? json_encode(['email' => ['label' => 'E-mail', 'value' => $mail]]) : null,
                    'type' => 'accreditation', 'population_id' => $pop['id'], 'max_entries' => 1, 'per_day' => 0, 'price' => 0, 'status' => 'valid',
                    'updated_at' => now(), 'created_at' => now()]);
                $n++;
            }
            Audit::log('accred.created', $n . ' accréditation(s)', (int)$ev['id']);
            back(['success' => '' . $n . ' accréditation(s) créée(s). Imprime les badges !']);
        }
        if ($a === 'revoke') { q("UPDATE tickets SET status = IF(status = 'valid', 'cancelled', 'valid'), updated_at = NOW() WHERE id = ? AND event_id = ? AND type = 'accreditation'", [(int)input('ticket_id'), $ev['id']]); back(); }
        if ($a === 'import') {
            // billets vendus par un autre distributeur : une ligne par code (code ; prénom ; nom ; tarif)
            $orderId = import_order($ev);
            $n = 0; $dup = 0;
            $lines = preg_split('~\R~', trim((string)input('codes')));
            if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) $lines = array_merge($lines, file($_FILES['csv']['tmp_name'], FILE_IGNORE_NEW_LINES) ?: []);
            foreach ($lines as $line) {
                $c = array_map('trim', str_getcsv(trim((string)$line), str_contains((string)$line, ';') ? ';' : ',', '"', '\\'));
                $code = Tickets::normalize((string)($c[0] ?? ''));
                if (strlen($code) < 4) continue;
                if (val('SELECT id FROM tickets WHERE code = ?', [$code])) { $dup++; continue; }
                insert('tickets', ['order_id' => $orderId, 'event_id' => $ev['id'], 'rate_id' => 0, 'rate_name' => mb_substr($c[3] ?? '', 0, 190) ?: 'Billet externe', 'code' => $code,
                    'first_name' => mb_substr($c[1] ?? '', 0, 120), 'last_name' => mb_substr($c[2] ?? '', 0, 120), 'type' => 'external', 'max_entries' => 1, 'price' => 0,
                    'status' => 'valid', 'updated_at' => now(), 'created_at' => now()]);
                if (++$n >= 50000) break;
            }
            back(['success' => '' . $n . ' billet(s) externe(s) importé(s)' . ($dup ? ', ' . $dup . ' doublon(s) ignoré(s)' : '') . '. Ils passent au scanner comme les autres.']);
        }
        back();
    }
    $pops = all("SELECT p.*, (SELECT COUNT(*) FROM tickets t WHERE t.population_id = p.id AND t.status = 'valid') n FROM populations p WHERE event_id = ? ORDER BY id", [$ev['id']]);
    $people = all("SELECT t.*, p.name pop_name, p.color FROM tickets t JOIN populations p ON p.id = t.population_id WHERE t.event_id = ? AND t.type = 'accreditation' ORDER BY p.name, t.last_name", [$ev['id']]);
    $external = (int)val("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND type = 'external'", [$ev['id']]);
    return ev_page($ev, 'accred', 'Accréditations', 'accreditations', compact('aps', 'pops', 'people', 'external'));
}

/** Commande technique qui regroupe les accréditations / imports d'un événement. */
function accred_order(array $ev): int { return system_order($ev, 'accreditation', 'Accréditations'); }
function import_order(array $ev): int { return system_order($ev, 'import', 'Import billets externes'); }
function system_order(array $ev, string $method, string $label): int
{
    $id = val("SELECT id FROM orders WHERE event_id = ? AND method = ? AND channel = 'systeme' LIMIT 1", [$ev['id'], $method]);
    if ($id) return (int)$id;
    return insert('orders', ['ref' => 'S' . random_code(7), 'token' => token(24), 'event_id' => $ev['id'], 'organizer_id' => $ev['organizer_id'], 'first_name' => $label, 'last_name' => '',
        'method' => $method, 'channel' => 'systeme', 'status' => 'paid', 'paid_at' => now(), 'created_at' => now(), 'answers' => '{}']);
}

function badges_pdf(string $id): never
{
    require_org();
    $ev = org_event((int)$id);
    $sql = "SELECT t.*, p.name pop_name, p.color, p.access_point_ids FROM tickets t JOIN populations p ON p.id = t.population_id WHERE t.event_id = ? AND t.type = 'accreditation' AND t.status = 'valid'";
    $params = [$ev['id']];
    if (input('t')) { $sql .= ' AND t.id = ?'; $params[] = (int)input('t'); }
    if (input('pop')) { $sql .= ' AND t.population_id = ?'; $params[] = (int)input('pop'); }
    $people = all($sql . ' ORDER BY p.name, t.last_name', $params);
    if (!$people) abort(404, 'Aucune accréditation à imprimer.');
    $aps = [];
    foreach (all('SELECT id, name FROM access_points WHERE event_id = ?', [$ev['id']]) as $a) $aps[(int)$a['id']] = $a['name'];
    $pdf = Docs::badges($ev, $people, $aps);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="badges-' . $ev['slug'] . '.pdf"');
    echo $pdf;
    exit;
}

// ---------- Liste d'attente & revente ----------
function waitlist_tab(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $wid = (int)input('waitlist_id');
        if (input('action') === 'cancel') q("UPDATE waitlist SET status = 'cancelled' WHERE id = ? AND event_id = ?", [$wid, $ev['id']]);
        if (input('action') === 'offer' && ($w = one("SELECT * FROM waitlist WHERE id = ? AND event_id = ? AND status IN ('waiting','expired')", [$wid, $ev['id']]))) {
            try { Waitlist::offer($w, $ev); } catch (Throwable $e) { log_line('emails', 'Liste d\'attente : ' . $e->getMessage()); }
            back(['success' => 'Place proposée à ' . $w['email'] . ' (gardée ' . Waitlist::hours($ev) . ' h).']);
        }
        if (input('action') === 'settings') {
            update('events', ['waitlist_hours' => max(1, min(168, (int)input('waitlist_hours', 24))), 'hold_minutes' => (int)input('hold_minutes') ? max(5, min(60, (int)input('hold_minutes'))) : null], 'id = ?', [$ev['id']]);
            back(['success' => 'Délais enregistrés.']);
        }
        if (input('action') === 'process') { $n = Waitlist::process(); back(['success' => '' . $n . ' offre(s) envoyée(s).']); }
        back(['success' => 'C\'est fait.']);
    }
    $list = all('SELECT w.*, s.starts_at s_start FROM waitlist w LEFT JOIN sessions s ON s.id = w.session_id WHERE w.event_id = ? ORDER BY FIELD(w.status, \'offered\', \'waiting\', \'converted\', \'expired\', \'cancelled\'), w.id', [$ev['id']]);
    $resales = all("SELECT x.*, t.code, t.first_name, t.last_name, t.rate_name, so.ref seller_ref, bo.ref buyer_ref FROM resales x JOIN tickets t ON t.id = x.ticket_id
                    JOIN orders so ON so.id = x.seller_order_id LEFT JOIN orders bo ON bo.id = x.buyer_order_id WHERE x.event_id = ? ORDER BY x.id DESC", [$ev['id']]);
    return ev_page($ev, 'waitlist', 'Liste d\'attente & revente', 'waitlist', compact('list', 'resales'));
}

// ---------- Messages aux participants ----------
function messages_tab(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $rates = all('SELECT id, name FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    $sessions = Tickets::sessions((int)$ev['id'], false);
    if (is_post()) {
        $subject = trim((string)input('subject'));
        $body = trim((string)input('body'));
        if ($subject === '' || $body === '') back(['error' => 'Objet et message obligatoires.']);
        $f = ['rate' => (int)input('rate'), 'session' => (int)input('session'), 'who' => (string)input('who', 'all')];
        $emails = message_recipients($ev, $f);
        if (input('action') === 'test') {
            try { Mailer::send(user()['email'], '[TEST] ' . $subject, message_html($ev, $body, 'Camille')); back(['success' => 'Test envoyé à ' . user()['email'] . '.']); }
            catch (Throwable $e) { back(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
        }
        if (!$emails) back(['error' => 'Personne ne correspond à ces critères.']);
        @set_time_limit(600);
        $sent = 0; $failed = 0;
        foreach ($emails as $mail => $first) {
            try { Mailer::send($mail, $subject, message_html($ev, $body, $first)); $sent++; } catch (Throwable $e) { $failed++; log_line('emails', 'Message : ' . $e->getMessage()); }
        }
        insert('messages', ['event_id' => $ev['id'], 'subject' => mb_substr($subject, 0, 190), 'body' => $body, 'filters' => json_encode($f), 'recipients' => count($emails),
            'sent' => $sent, 'failed' => $failed, 'user_id' => user()['id'], 'created_at' => now()]);
        Audit::log('message.sent', $sent . ' destinataire(s) · ' . mb_substr((string)$subject, 0, 120), (int)$ev['id']);
        back([$failed ? 'warning' : 'success' => 'Message envoyé à ' . $sent . ' personne(s)' . ($failed ? ', ' . $failed . ' échec(s)' : '') . '.']);
    }
    $history = all('SELECT m.*, u.name user_name FROM messages m LEFT JOIN users u ON u.id = m.user_id WHERE event_id = ? ORDER BY id DESC', [$ev['id']]);
    $preview = count(message_recipients($ev, ['rate' => (int)input('rate'), 'session' => (int)input('session'), 'who' => (string)input('who', 'all')]));
    return ev_page($ev, 'messages', 'Messages aux participants', 'messages', compact('rates', 'sessions', 'history', 'preview'));
}

/** Destinataires uniques (e-mail => prénom) : acheteurs et participants des billets valides. */
function message_recipients(array $ev, array $f): array
{
    $sql = "SELECT o.email, o.first_name FROM tickets t JOIN orders o ON o.id = t.order_id WHERE t.event_id = ? AND t.status = 'valid' AND t.type IN ('ticket','invitation') AND o.email <> ''";
    $p = [$ev['id']];
    if (!empty($f['rate'])) { $sql .= ' AND t.rate_id = ?'; $p[] = (int)$f['rate']; }
    if (!empty($f['session'])) { $sql .= ' AND t.session_id = ?'; $p[] = (int)$f['session']; }
    if (($f['who'] ?? '') === 'in') $sql .= ' AND t.entries > 0';
    if (($f['who'] ?? '') === 'out') $sql .= ' AND t.entries = 0';
    $out = [];
    foreach (all($sql, $p) as $r) if (is_email((string)$r['email'])) $out[strtolower($r['email'])] = $r['first_name'];
    return $out;
}

function message_html(array $ev, string $body, ?string $first): string
{
    return '<p>Salut ' . e($first ?: '') . '</p><div style="white-space:pre-line">' . e(str_replace('{prenom}', (string)$first, $body)) . '</div>'
        . '<p style="color:#777;font-size:13px;margin-top:22px">Message de l\'organisateur de « ' . e($ev['name']) . ' ». Tu le reçois car tu as un billet pour cet événement.</p>';
}

// ---------- Statistiques ----------
function stats_tab(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $sid = (int)input('seance') ?: null;
    $ss = $sid ? ' AND session_id = ' . $sid : '';
    $st = Tickets::stats((int)$ev['id'], $sid);
    $paid = "event_id = ? AND status = 'paid' AND (channel IS NULL OR channel <> 'systeme')$ss";
    $byDay = all("SELECT DATE(paid_at) d, COUNT(*) n, SUM(total) t, (SELECT COUNT(*) FROM tickets tt WHERE tt.order_id IN (SELECT id FROM orders o2 WHERE DATE(o2.paid_at) = DATE(o.paid_at) AND o2.event_id = o.event_id AND o2.status = 'paid')) q
                  FROM orders o WHERE $paid GROUP BY DATE(paid_at) ORDER BY d", [$ev['id']]);
    $byHour = all("SELECT HOUR(paid_at) h, COUNT(*) n FROM orders WHERE $paid GROUP BY HOUR(paid_at)", [$ev['id']]);
    $byWeekday = all("SELECT DAYOFWEEK(paid_at) d, COUNT(*) n FROM orders WHERE $paid GROUP BY DAYOFWEEK(paid_at)", [$ev['id']]);
    $byChannel = all("SELECT CASE WHEN COALESCE(NULLIF(channel,''), method) IN ('online','web') THEN 'web' ELSE COALESCE(NULLIF(channel,''), method) END c, COUNT(*) n, SUM(total) t FROM orders WHERE $paid GROUP BY c ORDER BY n DESC", [$ev['id']]);
    $bySource = all("SELECT COALESCE(NULLIF(utm_source,''), 'direct') s, COALESCE(utm_campaign,'') c, COUNT(*) n, SUM(total) t FROM orders WHERE $paid GROUP BY s, c ORDER BY n DESC LIMIT 20", [$ev['id']]);
    $byPromo = all("SELECT promo_code, COUNT(*) n, SUM(discount) d, SUM(total) t FROM orders WHERE $paid AND promo_code IS NOT NULL GROUP BY promo_code ORDER BY n DESC", [$ev['id']]);
    $byProvider = all("SELECT COALESCE(provider, pay_method, method) p, COUNT(*) n, SUM(total) t FROM orders WHERE $paid GROUP BY p ORDER BY t DESC", [$ev['id']]);
    $byCity = [];
    $products = all("SELECT i.name, SUM(i.qty) q, SUM(i.qty * i.unit_price) t FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.event_id = ? AND o.status = 'paid' AND i.kind = 'product'" . ($sid ? ' AND o.session_id = ' . $sid : '') . ' GROUP BY i.name', [$ev['id']]);
    $sessions = Tickets::sessions((int)$ev['id'], false);
    $bySession = $sessions ? all("SELECT s.id, s.starts_at, s.capacity, SUM(t.status = 'valid' AND t.type IN ('ticket','invitation')) sold, SUM(t.status = 'valid' AND t.entries > 0) checked
                                  FROM sessions s LEFT JOIN tickets t ON t.session_id = s.id WHERE s.event_id = ? GROUP BY s.id ORDER BY s.starts_at", [$ev['id']]) : [];
    $avg = $st['orders'] ? intdiv($st['revenue'], $st['orders']) : 0;
    $donations = (int)val("SELECT COALESCE(SUM(donation),0) FROM orders WHERE $paid", [$ev['id']]);
    $refunds = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM orders WHERE event_id = ? AND status = 'refunded'$ss", [$ev['id']]);
    $cap = Tickets::capacity($ev, $sid ? one('SELECT * FROM sessions WHERE id = ?', [$sid]) : null);
    if (input('csv')) {
        $rows = [['Date', 'Commandes', 'Billets', 'Montant']];
        foreach ($byDay as $d) $rows[] = [$d['d'], $d['n'], $d['q'], money_input((int)$d['t'])];
        csv_download('ventes-par-jour-' . $ev['slug'] . '.csv', $rows);
    }
    return ev_page($ev, 'stats', 'Statistiques', 'stats', compact('st', 'byDay', 'byHour', 'byWeekday', 'byChannel', 'bySource', 'byPromo', 'byProvider', 'products', 'sessions', 'bySession', 'avg', 'donations', 'refunds', 'cap', 'sid'));
}

// ---------- Liste de contrôle papier ----------
function control_list_pdf(string $id): never
{
    require_org();
    $ev = org_event((int)$id);
    $sid = (int)input('seance') ?: null;
    $rows = all("SELECT t.*, p.name pop_name FROM tickets t LEFT JOIN populations p ON p.id = t.population_id WHERE t.event_id = ? AND t.status = 'valid'"
        . ($sid ? ' AND t.session_id = ' . $sid : '') . ' ORDER BY t.last_name, t.first_name', [$ev['id']]);
    $pdf = Docs::controlList($ev, $rows, $sid ? one('SELECT * FROM sessions WHERE id = ?', [$sid]) : null);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="liste-controle-' . $ev['slug'] . '.pdf"');
    echo $pdf;
    exit;
}

// ---------- Caisse (guichet) : journal et clôture ----------
function cash_tab(string $id): string
{
    require_module('guichet');
    require_org();
    $ev = org_event((int)$id);
    $from = dt_from_input(input('from')) ?: ((string)val('SELECT MAX(period_end) FROM cash_closures WHERE event_id = ?', [$ev['id']]) ?: date('Y-m-d 00:00:00'));
    $to = dt_from_input(input('to')) ?: now();
    $journal = all("SELECT o.*, u.name seller, (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id) n FROM orders o LEFT JOIN users u ON u.id = o.created_by
                    WHERE o.event_id = ? AND o.method = 'guichet' AND o.created_at BETWEEN ? AND ? ORDER BY o.created_at", [$ev['id'], $from, $to]);
    $totals = ['count' => 0, 'tickets' => 0, 'total' => 0, 'by_method' => [], 'by_seller' => [], 'cancelled' => 0];
    foreach ($journal as $o) {
        if ($o['status'] !== 'paid') { $totals['cancelled'] += (int)$o['total']; continue; }
        $totals['count']++; $totals['tickets'] += (int)$o['n']; $totals['total'] += (int)$o['total'];
        $m = $o['pay_method'] ?: 'autre';
        $totals['by_method'][$m] = ($totals['by_method'][$m] ?? 0) + (int)$o['total'];
        $s = $o['seller'] ?: '—';
        $totals['by_seller'][$s] = ($totals['by_seller'][$s] ?? 0) + (int)$o['total'];
    }
    if (is_post() && input('action') === 'close') {
        $counted = input('counted') !== '' ? parse_money(input('counted')) : null;
        insert('cash_closures', ['event_id' => $ev['id'], 'user_id' => user()['id'], 'period_start' => $from, 'period_end' => $to, 'totals' => json_encode($totals, JSON_UNESCAPED_UNICODE),
            'counted_cash' => $counted, 'note' => mb_substr(trim((string)input('note')), 0, 500), 'created_at' => now()]);
        $diff = $counted !== null ? $counted - (int)($totals['by_method']['especes'] ?? 0) : null;
        Audit::log('cash.closed', money($totals['total']), (int)$ev['id']);
        back(['success' => 'Caisse clôturée : ' . money($totals['total']) . ($diff !== null ? ' · écart espèces ' . ($diff >= 0 ? '+' : '') . money($diff) : '') . '.']);
    }
    if (input('csv')) {
        $rows = [['Date', 'Commande', 'Vendeur', 'Client', 'Billets', 'Paiement', 'Montant', 'Statut']];
        foreach ($journal as $o) $rows[] = [$o['created_at'], $o['ref'], $o['seller'], trim($o['first_name'] . ' ' . $o['last_name']), $o['n'], $o['pay_method'], money_input((int)$o['total']), $o['status']];
        csv_download('journal-caisse-' . $ev['slug'] . '-' . date('Ymd-Hi') . '.csv', $rows);
    }
    $closures = all('SELECT c.*, u.name user_name FROM cash_closures c LEFT JOIN users u ON u.id = c.user_id WHERE event_id = ? ORDER BY id DESC LIMIT 20', [$ev['id']]);
    return ev_page($ev, 'box', 'Journal de caisse', 'cash', compact('journal', 'totals', 'from', 'to', 'closures'));
}

function thermal_pdf(string $id): never
{
    $org = require_org();
    $o = one('SELECT * FROM orders WHERE id = ? AND organizer_id = ?', [(int)$id, $org['id']]);
    if (!$o) abort(404);
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $tickets = all("SELECT * FROM tickets WHERE order_id = ? AND status = 'valid' ORDER BY id", [$o['id']]);
    if (!$tickets) abort(404, 'Aucun billet valide.');
    $pdf = Docs::thermal($o, $ev, $org, $tickets);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="ticket-' . $o['ref'] . '.pdf"');
    echo $pdf;
    exit;
}

function statement_pdf(): never
{
    $org = require_org(['owner']);
    $month = preg_match('~^\d{4}-\d{2}$~', (string)input('mois')) ? (string)input('mois') : date('Y-m', strtotime('first day of last month'));
    $pdf = Docs::statement($org, $month);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="releve-' . $org['slug'] . '-' . $month . '.pdf"');
    echo $pdf;
    exit;
}
