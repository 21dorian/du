<?php
declare(strict_types=1);

require_once APP . '/controllers/public.php';

function pro_page(string $title, string $view, array $vars, string $active, array $extra = []): string
{
    return view('layout_back', ['title' => $title, 'area' => 'pro', 'active' => $active, 'content' => view('pro/' . $view, $vars)] + $extra);
}

function ev_page(array $ev, string $tab, string $title, string $view, array $vars): string
{
    $content = view('pro/_event_head', ['ev' => $ev, 'tab' => $tab]) . view('pro/' . $view, $vars + ['ev' => $ev]);
    return view('layout_back', ['title' => $title, 'area' => 'pro', 'active' => 'events', 'content' => $content,
        'crumbs' => [['Événements', '/pro/evenements'], [$ev['name'], '/pro/evenements/' . $ev['id']]]]);
}

function sales_chart(string $where, array $params, int $days = 30): array
{
    $rows = all("SELECT DATE(paid_at) d, COUNT(*) n, SUM(total) t FROM orders WHERE status = 'paid' AND paid_at >= CURDATE() - INTERVAL $days DAY AND $where GROUP BY DATE(paid_at)", $params);
    $map = [];
    foreach ($rows as $r) $map[$r['d']] = (int)$r['t'];
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $out[$d] = $map[$d] ?? 0; }
    return $out;
}

// ---------- Tableau de bord ----------
function dashboard(): string
{
    $org = require_org();
    Tickets::expirePending();
    $k = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) total, COALESCE(SUM(CASE WHEN method='online' THEN organizer_amount ELSE total END),0) net
              FROM orders WHERE organizer_id = ? AND status = 'paid'", [$org['id']]);
    $tickets = (int)val("SELECT COUNT(*) FROM tickets t JOIN events e ON e.id = t.event_id WHERE e.organizer_id = ? AND t.status = 'valid'", [$org['id']]);
    $today = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM orders WHERE organizer_id = ? AND status = 'paid' AND DATE(paid_at) = CURDATE()", [$org['id']]);
    $upcoming = all("SELECT * FROM events WHERE organizer_id = ? AND COALESCE(ends_at, starts_at) >= NOW() - INTERVAL 6 HOUR ORDER BY starts_at LIMIT 6", [$org['id']]);
    foreach ($upcoming as &$ev) $ev['stats'] = Tickets::stats((int)$ev['id']);
    unset($ev);
    $recent = all("SELECT o.*, e.name ev_name FROM orders o JOIN events e ON e.id = o.event_id WHERE o.organizer_id = ? AND o.status IN ('paid','refunded','cancelled')
                   ORDER BY o.created_at DESC LIMIT 8", [$org['id']]);
    $chart = sales_chart('organizer_id = ?', [$org['id']]);
    $balance = Tickets::balance((int)$org['id']);
    $hasEvents = (bool)val('SELECT id FROM events WHERE organizer_id = ? LIMIT 1', [$org['id']]);
    return pro_page('Tableau de bord', 'dashboard', compact('org', 'k', 'tickets', 'today', 'upcoming', 'recent', 'chart', 'balance', 'hasEvents'), 'dash',
        ['actions' => '<a class="btn" href="' . url('/pro/evenements/nouveau') . '">+ Nouvel événement</a>']);
}

// ---------- Événements ----------
function events(): string
{
    $org = require_org();
    $filter = (string)input('f', 'avenir');
    $where = $filter === 'passes' ? 'COALESCE(ends_at, starts_at) < NOW() - INTERVAL 6 HOUR' : 'COALESCE(ends_at, starts_at) >= NOW() - INTERVAL 6 HOUR';
    $list = all("SELECT e.*,
        (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid') sold,
        (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid' AND t.entries > 0) checked,
        (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.event_id = e.id AND o.status = 'paid') revenue
        FROM events e WHERE organizer_id = ? AND $where ORDER BY starts_at " . ($filter === 'passes' ? 'DESC' : 'ASC'), [$org['id']]);
    return pro_page('Événements', 'events', ['list' => $list, 'filter' => $filter], 'events',
        ['actions' => '<a class="btn" href="' . url('/pro/evenements/nouveau') . '">+ Nouvel événement</a>']);
}

function event_form(?string $id = null): string
{
    $org = require_org();
    $ev = $id ? org_event((int)$id) : null;
    $errors = [];
    if (is_post()) {
        $data = [
            'name' => trim((string)input('name')),
            'description' => trim((string)input('description')),
            'venue' => trim((string)input('venue')),
            'address' => trim((string)input('address')),
            'city' => trim((string)input('city')),
            'starts_at' => dt_from_input(input('starts_at')),
            'ends_at' => dt_from_input(input('ends_at')),
            'capacity' => max(0, (int)input('capacity')),
            'max_per_order' => max(0, (int)input('max_per_order')),
            'allow_reentry' => checked('allow_reentry'),
            'ticket_info' => trim((string)input('ticket_info')),
        ];
        if ($data['name'] === '') $errors[] = 'Il manque le nom de l\'événement.';
        if (!$data['starts_at']) $errors[] = 'Il manque la date de début.';
        if ($data['ends_at'] && $data['starts_at'] && $data['ends_at'] < $data['starts_at']) $errors[] = 'La fin avant le début ? Même Doc Brown n\'oserait pas 🕰️';
        try { $img = upload_image('image'); if ($img) $data['image'] = $img; } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        if ($ev && checked('remove_image')) $data['image'] = null;
        if (!$errors) {
            if ($ev) {
                update('events', $data, 'id = ?', [$ev['id']]);
                redirect('/pro/evenements/' . $ev['id'], ['success' => '✅ C\'est enregistré.']);
            }
            $data += ['organizer_id' => $org['id'], 'slug' => unique_slug('events', $data['name'] . ' ' . date('Y', strtotime($data['starts_at']))), 'status' => 'draft', 'created_at' => now()];
            $newId = insert('events', $data);
            $price = parse_money(input('first_price'));
            insert('rates', ['event_id' => $newId, 'name' => trim((string)input('first_rate')) ?: 'Entrée', 'price' => $price, 'quota' => 0, 'active' => 1, 'sort' => 0]);
            insert('access_points', ['event_id' => $newId, 'name' => 'Entrée principale', 'active' => 1]);
            redirect('/pro/evenements/' . $newId . '/tarifs', ['success' => '🎉 Événement créé (en brouillon). Vérifie tes tarifs, puis publie-le !']);
        }
        $ev = array_merge($ev ?? [], $data);
    }
    $title = $id ? 'Modifier l\'événement' : 'Nouvel événement';
    $content = view('pro/event_form', ['ev' => $ev, 'errors' => $errors, 'isNew' => !$id]);
    if ($id) return view('layout_back', ['title' => $title, 'area' => 'pro', 'active' => 'events', 'content' => view('pro/_event_head', ['ev' => org_event((int)$id), 'tab' => 'edit']) . $content,
        'crumbs' => [['Événements', '/pro/evenements'], [$ev['name'], '/pro/evenements/' . $id]]]);
    return view('layout_back', ['title' => $title, 'area' => 'pro', 'active' => 'events', 'content' => $content, 'crumbs' => [['Événements', '/pro/evenements']]]);
}

function event_overview(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $stats = Tickets::stats((int)$ev['id']);
    $chart = sales_chart('event_id = ?', [$ev['id']]);
    $recent = all("SELECT * FROM orders WHERE event_id = ? AND status IN ('paid','refunded','cancelled') ORDER BY created_at DESC LIMIT 6", [$ev['id']]);
    $rates = (int)val('SELECT COUNT(*) FROM rates WHERE event_id = ?', [$ev['id']]);
    return ev_page($ev, 'overview', $ev['name'], 'event_overview', compact('stats', 'chart', 'recent', 'rates'));
}

function event_status(string $id): never
{
    require_org();
    $ev = org_event((int)$id);
    $st = (string)input('status');
    if (!in_array($st, ['draft', 'published', 'closed'], true)) back();
    if ($st === 'published' && !val('SELECT id FROM rates WHERE event_id = ? AND active = 1', [$ev['id']])) back(['error' => 'Ajoute au moins un tarif actif avant de publier. Sans billets, ça marche moins bien 😅']);
    update('events', ['status' => $st], 'id = ?', [$ev['id']]);
    back(['success' => ['draft' => 'Retour en brouillon 🙈 Plus personne ne le voit.', 'published' => '🚀 C\'est en ligne ! La billetterie est ouverte.', 'closed' => '🔒 Ventes clôturées. Les billets vendus restent valables.'][$st]]);
}

function event_duplicate(string $id): never
{
    require_org();
    $ev = org_event((int)$id);
    $copy = $ev;
    unset($copy['id']);
    $copy['name'] = $ev['name'] . ' (copie)';
    $copy['slug'] = unique_slug('events', $copy['name']);
    $copy['status'] = 'draft';
    $copy['created_at'] = now();
    $newId = insert('events', $copy);
    $map = [];
    foreach (all('SELECT * FROM rates WHERE event_id = ?', [$ev['id']]) as $r) { $old = $r['id']; unset($r['id']); $r['event_id'] = $newId; $map[$old] = insert('rates', $r); }
    foreach (all('SELECT * FROM form_fields WHERE event_id = ?', [$ev['id']]) as $f) { unset($f['id']); $f['event_id'] = $newId; insert('form_fields', $f); }
    $remap = fn($ids) => $ids ? implode(',', array_filter(array_map(fn($i) => $map[(int)$i] ?? null, explode(',', $ids)))) : null;
    foreach (all('SELECT * FROM access_points WHERE event_id = ?', [$ev['id']]) as $a) { unset($a['id']); $a['event_id'] = $newId; $a['rate_ids'] = $remap($a['rate_ids']); insert('access_points', $a); }
    redirect('/pro/evenements/' . $newId . '/modifier', ['success' => '📑 Dupliqué ! Tarifs, formulaire et accès copiés. Pense à changer les dates.']);
}

function event_delete(string $id): never
{
    require_org();
    $ev = org_event((int)$id);
    if (val('SELECT id FROM orders WHERE event_id = ? LIMIT 1', [$ev['id']])) back(['error' => 'Cet événement a des commandes : impossible de le supprimer. Clôture-le plutôt.']);
    foreach (['rates', 'promos', 'form_fields', 'access_points', 'scans'] as $t) q("DELETE FROM $t WHERE event_id = ?", [$ev['id']]);
    q('DELETE FROM events WHERE id = ?', [$ev['id']]);
    redirect('/pro/evenements', ['success' => '🗑️ Événement supprimé.']);
}

// ---------- Tarifs ----------
function rates(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $action = (string)input('action');
        $rid = (int)input('rate_id');
        if ($action === 'delete') {
            if (val('SELECT id FROM order_items WHERE rate_id = ? LIMIT 1', [$rid])) back(['error' => 'Ce tarif a déjà été vendu : désactive-le plutôt que de le supprimer.']);
            q('DELETE FROM rates WHERE id = ? AND event_id = ?', [$rid, $ev['id']]);
            back(['success' => '🗑️ Tarif supprimé.']);
        }
        $data = [
            'name' => trim((string)input('name')),
            'description' => trim((string)input('description')) ?: null,
            'price' => parse_money(input('price')),
            'quota' => max(0, (int)input('quota')),
            'max_per_order' => max(0, (int)input('max_per_order')),
            'sale_start' => dt_from_input(input('sale_start')),
            'sale_end' => dt_from_input(input('sale_end')),
            'hidden' => checked('hidden'),
            'active' => checked('active'),
            'sort' => (int)input('sort'),
        ];
        if ($data['name'] === '') back(['error' => 'Donne un nom à ce tarif.']);
        if ($rid) update('rates', $data, 'id = ? AND event_id = ?', [$rid, $ev['id']]);
        else insert('rates', $data + ['event_id' => $ev['id']]);
        back(['success' => '✅ Tarif enregistré.']);
    }
    $org = current_org();
    $rates = all('SELECT r.*, (SELECT COUNT(*) FROM tickets t WHERE t.rate_id = r.id AND t.status = \'valid\') sold FROM rates r WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    return ev_page($ev, 'rates', 'Tarifs', 'rates', ['rates' => $rates, 'org' => $org]);
}

// ---------- Formulaire personnalisé ----------
function form_fields(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $fid = (int)input('field_id');
        if (input('action') === 'delete') { q('DELETE FROM form_fields WHERE id = ? AND event_id = ?', [$fid, $ev['id']]); back(['success' => '🗑️ Question supprimée.']); }
        $type = (string)input('type');
        $data = [
            'label' => trim((string)input('label')),
            'type' => in_array($type, ['text', 'textarea', 'select', 'checkbox', 'date', 'email', 'phone', 'number'], true) ? $type : 'text',
            'options' => trim((string)input('options')) ?: null,
            'required' => checked('required'),
            'per_ticket' => (int)input('per_ticket') ? 1 : 0,
            'sort' => (int)input('sort'),
        ];
        if ($data['label'] === '') back(['error' => 'Une question sans question, c\'est philosophique, mais non.']);
        if ($data['type'] === 'select' && !$data['options']) back(['error' => 'Indique les choix possibles (un par ligne).']);
        if ($fid) update('form_fields', $data, 'id = ? AND event_id = ?', [$fid, $ev['id']]);
        else insert('form_fields', $data + ['event_id' => $ev['id']]);
        back(['success' => '✅ Question enregistrée.']);
    }
    $fields = all('SELECT * FROM form_fields WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    return ev_page($ev, 'form', 'Formulaire d\'inscription', 'form_fields', ['fields' => $fields]);
}

// ---------- Codes promo ----------
function promos(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $rates = all('SELECT id, name, hidden FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    if (is_post()) {
        $pid = (int)input('promo_id');
        if (input('action') === 'delete') { q('DELETE FROM promos WHERE id = ? AND event_id = ?', [$pid, $ev['id']]); back(['success' => '🗑️ Code supprimé.']); }
        if (input('action') === 'toggle') { q('UPDATE promos SET active = 1 - active WHERE id = ? AND event_id = ?', [$pid, $ev['id']]); back(); }
        $kind = (string)input('kind');
        $code = strtoupper(preg_replace('~[^A-Za-z0-9_-]~', '', (string)input('code')));
        if (input('generate') || $code === '') $code = random_code(8);
        $rateIds = array_map('intval', (array)($_POST['rate_ids'] ?? []));
        $valid = array_column($rates, 'id');
        $rateIds = array_values(array_filter($rateIds, fn($r) => in_array($r, array_map('intval', $valid), true)));
        if (!in_array($kind, ['percent', 'amount', 'unlock'], true)) $kind = 'percent';
        $value = $kind === 'percent' ? max(1, min(100, (int)input('value'))) : ($kind === 'amount' ? parse_money(input('value')) : 0);
        if ($kind === 'unlock' && !$rateIds) back(['error' => 'Choisis le ou les tarifs secrets que ce code débloque.']);
        if (val('SELECT id FROM promos WHERE event_id = ? AND code = ?', [$ev['id'], $code])) back(['error' => 'Ce code existe déjà pour cet événement.']);
        insert('promos', ['event_id' => $ev['id'], 'code' => $code, 'kind' => $kind, 'value' => $value, 'rate_ids' => $rateIds ? implode(',', $rateIds) : null,
            'max_uses' => max(0, (int)input('max_uses')), 'valid_until' => dt_from_input(input('valid_until')), 'active' => 1]);
        back(['success' => '🏷️ Code « ' . $code . ' » créé. Fais-le tourner !']);
    }
    $promos = all('SELECT * FROM promos WHERE event_id = ? ORDER BY id DESC', [$ev['id']]);
    return ev_page($ev, 'promos', 'Codes promo & accès', 'promos', ['promos' => $promos, 'rates' => $rates]);
}

// ---------- Points d'accès ----------
function access_points(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $rates = all('SELECT id, name FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    if (is_post()) {
        $aid = (int)input('ap_id');
        if (input('action') === 'delete') { q('DELETE FROM access_points WHERE id = ? AND event_id = ?', [$aid, $ev['id']]); back(['success' => '🗑️ Point d\'accès supprimé.']); }
        if (input('action') === 'reentry') { update('events', ['allow_reentry' => checked('allow_reentry')], 'id = ?', [$ev['id']]); back(['success' => '✅ C\'est noté.']); }
        $valid = array_map('intval', array_column($rates, 'id'));
        $rateIds = array_values(array_filter(array_map('intval', (array)($_POST['rate_ids'] ?? [])), fn($r) => in_array($r, $valid, true)));
        $data = ['name' => trim((string)input('name')) ?: 'Entrée', 'rate_ids' => $rateIds ? implode(',', $rateIds) : null, 'active' => checked('active')];
        if ($aid) update('access_points', $data, 'id = ? AND event_id = ?', [$aid, $ev['id']]);
        else insert('access_points', $data + ['event_id' => $ev['id']]);
        back(['success' => '✅ Point d\'accès enregistré.']);
    }
    $aps = all("SELECT a.*, (SELECT COUNT(*) FROM scans s WHERE s.access_point_id = a.id AND s.result = 'ok' AND s.undone = 0) entries FROM access_points a WHERE event_id = ? ORDER BY id", [$ev['id']]);
    $scanners = all("SELECT name, email FROM users WHERE organizer_id = ? AND role = 'scanner' AND active = 1", [$ev['organizer_id']]);
    return ev_page($ev, 'access', 'Contrôle d\'accès', 'access_points', ['aps' => $aps, 'rates' => $rates, 'scanners' => $scanners]);
}

// ---------- Participants ----------
function attendees(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $q = trim((string)input('q'));
    $rate = (int)input('rate');
    $st = (string)input('st');
    $sql = 'SELECT t.*, o.ref, o.email, o.phone, o.method, o.answers AS order_answers FROM tickets t JOIN orders o ON o.id = t.order_id WHERE t.event_id = ?';
    $p = [$ev['id']];
    if ($q !== '') { $sql .= ' AND (t.first_name LIKE ? OR t.last_name LIKE ? OR t.code LIKE ? OR o.email LIKE ? OR o.ref LIKE ?)'; array_push($p, ...array_fill(0, 5, '%' . $q . '%')); }
    if ($rate) { $sql .= ' AND t.rate_id = ?'; $p[] = $rate; }
    if ($st === 'in') $sql .= " AND t.status = 'valid' AND t.entries > 0";
    elseif ($st === 'out') $sql .= " AND t.status = 'valid' AND t.entries = 0";
    elseif ($st === 'cancelled') $sql .= " AND t.status = 'cancelled'";
    else $sql .= " AND t.status = 'valid'";
    $rows = all($sql . ' ORDER BY t.last_name, t.first_name', $p);
    $fields = all('SELECT * FROM form_fields WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);

    if (input('csv')) {
        $head = ['Code billet', 'Prénom', 'Nom', 'Tarif', 'Prix', 'Statut', 'Entré', 'Première entrée', 'Commande', 'E-mail acheteur', 'Téléphone', 'Vente'];
        foreach ($fields as $f) $head[] = $f['label'];
        $out = [$head];
        foreach ($rows as $r) {
            $ta = json_decode((string)$r['answers'], true) ?: [];
            $oa = (json_decode((string)$r['order_answers'], true) ?: [])['order'] ?? [];
            $line = [$r['code'], $r['first_name'], $r['last_name'], $r['rate_name'], number_format($r['price'] / 100, 2, ',', ''),
                $r['status'] === 'valid' ? 'Valide' : 'Annulé', $r['entries'] > 0 ? 'Oui' : 'Non', $r['checked_in_at'] ?? '', $r['ref'], $r['email'], $r['phone'], method_label($r['method'])];
            foreach ($fields as $f) $line[] = ($f['per_ticket'] ? $ta : $oa)[$f['id']]['value'] ?? '';
            $out[] = $line;
        }
        csv_download('participants-' . $ev['slug'] . '-' . date('Ymd') . '.csv', $out);
    }
    $rates = all('SELECT id, name FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    return ev_page($ev, 'attendees', 'Participants', 'attendees', compact('rows', 'rates', 'q', 'rate', 'st', 'fields'));
}

// ---------- Guichet & invitations ----------
function box_office(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    $rates = all('SELECT * FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    $errors = [];
    if (is_post()) {
        $method = input('method') === 'invitation' ? 'invitation' : 'guichet';
        $q = [];
        foreach ((array)($_POST['qty'] ?? []) as $rid => $n) if ((int)$n > 0) $q[(int)$rid] = min(500, (int)$n);
        $buyer = ['first_name' => trim((string)input('first_name')), 'last_name' => trim((string)input('last_name')),
            'email' => trim((string)input('email')), 'phone' => trim((string)input('phone'))];
        if ($buyer['last_name'] === '') $errors[] = 'Il faut au moins un nom.';
        if ($buyer['email'] !== '' && !is_email($buyer['email'])) $errors[] = 'Cette adresse e-mail a l\'air bizarre 🤨';
        if (!$errors) {
            $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'quantities' => $q, 'buyer' => $buyer, 'method' => $method,
                'pay_method' => $method === 'guichet' ? (string)input('pay_method') : null, 'note' => (string)input('note'),
                'user_id' => user()['id'], 'force' => checked('force')]);
            if (!empty($r['errors'])) $errors = $r['errors'];
            else {
                $o = Tickets::markPaid((int)$r['order']['id']);
                $sent = checked('send') && $buyer['email'] ? Mailer::sendOrderSafe((int)$o['id']) : false;
                redirect('/pro/commandes/' . $o['id'], ['success' => ($method === 'invitation' ? '🎁 Invitation créée' : '💵 Vente enregistrée') . ($sent ? ', billets envoyés par e-mail 📬' : ' !')]);
            }
        }
    }
    foreach ($rates as &$r) $r['remaining'] = Tickets::remaining($r, $ev);
    unset($r);
    return ev_page($ev, 'box', 'Guichet & invitations', 'box_office', ['rates' => $rates, 'errors' => $errors]);
}

// ---------- Contrôle en direct ----------
function live_control(string $id): string
{
    require_org(['owner', 'manager']);
    $ev = org_event((int)$id);
    if (is_post() && input('action') === 'undo') {
        Tickets::undoScan((int)input('scan_id'), (int)$ev['id']) ? back(['success' => '↩️ Passage annulé.']) : back(['error' => 'Impossible d\'annuler ce passage.']);
    }
    if (is_post() && input('action') === 'checkin') {
        $t = one('SELECT * FROM tickets WHERE id = ? AND event_id = ?', [(int)input('ticket_id'), $ev['id']]);
        if ($t) { $r = Tickets::scan(['event' => $ev, 'code' => $t['code'], 'direction' => 'in', 'user' => user(), 'device' => 'admin']); back([$r['level'] === 'ok' ? 'success' : 'warning' => $r['label'] . ' : ' . ($r['ticket']['name'] ?? '')]); }
        back();
    }
    $stats = Tickets::stats((int)$ev['id']);
    $aps = all("SELECT a.name, SUM(s.result = 'ok' AND s.undone = 0) entries, SUM(s.result = 'exit' AND s.undone = 0) exits, SUM(s.result NOT IN ('ok','exit')) refused
                FROM access_points a LEFT JOIN scans s ON s.access_point_id = a.id WHERE a.event_id = ? GROUP BY a.id ORDER BY a.id", [$ev['id']]);
    $scans = all("SELECT s.*, t.first_name, t.last_name, t.rate_name, a.name ap_name, u.name user_name FROM scans s
                  LEFT JOIN tickets t ON t.id = s.ticket_id LEFT JOIN access_points a ON a.id = s.access_point_id LEFT JOIN users u ON u.id = s.user_id
                  WHERE s.event_id = ? ORDER BY s.scanned_at DESC, s.id DESC LIMIT 40", [$ev['id']]);
    $perHour = all("SELECT DATE_FORMAT(scanned_at, '%H:00') h, COUNT(*) n FROM scans WHERE event_id = ? AND result = 'ok' AND undone = 0 AND scanned_at >= NOW() - INTERVAL 12 HOUR GROUP BY h ORDER BY MIN(scanned_at)", [$ev['id']]);
    return ev_page($ev, 'live', 'Contrôle en direct', 'live_control', compact('stats', 'aps', 'scans', 'perHour'));
}

// ---------- Commandes ----------
function orders(): string
{
    $org = require_org();
    $q = trim((string)input('q'));
    $st = (string)input('st');
    $evId = (int)input('ev');
    $sql = 'SELECT o.*, e.name ev_name, (SELECT SUM(qty) FROM order_items i WHERE i.order_id = o.id) qty FROM orders o JOIN events e ON e.id = o.event_id WHERE o.organizer_id = ?';
    $p = [$org['id']];
    if ($q !== '') { $sql .= ' AND (o.ref LIKE ? OR o.email LIKE ? OR o.last_name LIKE ? OR o.first_name LIKE ?)'; array_push($p, ...array_fill(0, 4, '%' . $q . '%')); }
    if ($st !== '' && in_array($st, ['paid', 'pending', 'cancelled', 'refunded', 'expired'], true)) { $sql .= ' AND o.status = ?'; $p[] = $st; }
    else $sql .= " AND o.status IN ('paid','refunded','cancelled')";
    if ($evId) { $sql .= ' AND o.event_id = ?'; $p[] = $evId; }
    $list = all($sql . ' ORDER BY o.created_at DESC LIMIT 300', $p);
    if (input('csv')) {
        $rows = [['Référence', 'Date', 'Événement', 'Prénom', 'Nom', 'E-mail', 'Billets', 'Sous-total', 'Réduction', 'Frais', 'Total payé', 'Part organisateur', 'Vente', 'Paiement', 'Statut']];
        foreach ($list as $o) $rows[] = [$o['ref'], $o['created_at'], $o['ev_name'], $o['first_name'], $o['last_name'], $o['email'], $o['qty'],
            money_input((int)$o['subtotal']), money_input((int)$o['discount']), money_input((int)$o['fees']), money_input((int)$o['total']),
            money_input($o['method'] === 'online' ? (int)$o['organizer_amount'] : (int)$o['total']), method_label($o['method']), provider_label($o['provider'] ?: $o['pay_method']), strip_tags(badge($o['status']))];
        csv_download('commandes-' . date('Ymd') . '.csv', $rows);
    }
    $events = all('SELECT id, name FROM events WHERE organizer_id = ? ORDER BY starts_at DESC', [$org['id']]);
    return pro_page('Commandes', 'orders', compact('list', 'q', 'st', 'evId', 'events'), 'orders');
}

function order_detail(string $id): string
{
    $org = require_org();
    $o = one('SELECT * FROM orders WHERE id = ? AND organizer_id = ?', [(int)$id, $org['id']]);
    if (!$o) abort(404);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'resend') {
            if (input('email') && is_email((string)input('email'))) { update('orders', ['email' => trim((string)input('email'))], 'id = ?', [$o['id']]); }
            try { Mailer::sendOrder((int)$o['id']) ? back(['success' => '📬 Billets renvoyés !']) : back(['error' => 'Pas d\'e-mail valide ou pas de billet à envoyer.']); }
            catch (Throwable $e) { back(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
        }
        if ($a === 'holder') {
            $t = one('SELECT * FROM tickets WHERE id = ? AND order_id = ?', [(int)input('ticket_id'), $o['id']]);
            if ($t) update('tickets', ['first_name' => trim((string)input('first_name')), 'last_name' => trim((string)input('last_name')), 'updated_at' => now()], 'id = ?', [$t['id']]);
            back(['success' => '✅ Nom modifié.']);
        }
        if ($a === 'cancel_ticket') {
            q("UPDATE tickets SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND order_id = ?", [(int)input('ticket_id'), $o['id']]);
            back(['success' => 'Billet annulé (pas de remboursement automatique).']);
        }
        if (in_array($a, ['cancel', 'refund'], true) && $o['status'] === 'paid') {
            $msg = 'Commande annulée, billets désactivés.';
            if ($a === 'refund' && $o['method'] === 'online') {
                $amount = setting('refund_fees') === '1' || $o['fee_mode'] !== 'buyer' ? (int)$o['total'] : (int)$o['total'] - (int)$o['fees'];
                try { $msg = Payments::refund($o, $amount) . ' (' . money($amount) . '). Billets invalidés.'; }
                catch (Throwable $e) { back(['error' => 'Le remboursement a échoué : ' . $e->getMessage() . ' — la commande n\'a pas été annulée.']); }
            }
            Tickets::cancelOrder($o, $a === 'refund' ? 'refunded' : 'cancelled');
            back(['success' => $msg]);
        }
        back();
    }
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    $items = all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
    $tickets = all('SELECT * FROM tickets WHERE order_id = ? ORDER BY id', [$o['id']]);
    $answers = json_decode((string)$o['answers'], true) ?: [];
    $creator = $o['created_by'] ? val('SELECT name FROM users WHERE id = ?', [$o['created_by']]) : null;
    return view('layout_back', ['title' => 'Commande ' . $o['ref'], 'area' => 'pro', 'active' => 'orders',
        'crumbs' => [['Commandes', '/pro/commandes'], [$ev['name'], '/pro/evenements/' . $ev['id']]],
        'content' => view('pro/order_detail', compact('o', 'ev', 'items', 'tickets', 'answers', 'creator'))]);
}

// ---------- Équipe ----------
function team(): string
{
    $org = require_org(['owner']);
    $created = null;
    if (is_post()) {
        $a = (string)input('action');
        $uid = (int)input('user_id');
        $target = $uid ? one('SELECT * FROM users WHERE id = ? AND organizer_id = ?', [$uid, $org['id']]) : null;
        if ($a === 'add') {
            $email = strtolower(trim((string)input('email')));
            $role = in_array(input('role'), ['manager', 'scanner', 'owner'], true) ? (string)input('role') : 'scanner';
            if (!is_email($email) || trim((string)input('name')) === '') back(['error' => 'Il faut un nom et un e-mail valide.']);
            if (val('SELECT id FROM users WHERE email = ?', [$email])) back(['error' => 'Un compte existe déjà avec cet e-mail.']);
            $pass = (string)input('password') ?: strtolower(random_code(10));
            if (mb_strlen($pass) < 8) back(['error' => 'Mot de passe : 8 caractères minimum.']);
            insert('users', ['organizer_id' => $org['id'], 'email' => $email, 'name' => trim((string)input('name')), 'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                'role' => $role, 'active' => 1, 'created_at' => now()]);
            $_SESSION['team_created'] = ['email' => $email, 'password' => $pass, 'role' => $role];
            redirect('/pro/equipe');
        }
        if ($target && $target['id'] != user()['id']) {
            if ($a === 'toggle') update('users', ['active' => $target['active'] ? 0 : 1], 'id = ?', [$target['id']]);
            if ($a === 'role' && in_array(input('role'), ['manager', 'scanner', 'owner'], true)) update('users', ['role' => (string)input('role')], 'id = ?', [$target['id']]);
            if ($a === 'password') {
                $pass = strtolower(random_code(10));
                update('users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$target['id']]);
                $_SESSION['team_created'] = ['email' => $target['email'], 'password' => $pass, 'role' => $target['role']];
                redirect('/pro/equipe');
            }
            back(['success' => '✅ C\'est fait.']);
        }
        back();
    }
    $created = $_SESSION['team_created'] ?? null;
    unset($_SESSION['team_created']);
    $members = all('SELECT * FROM users WHERE organizer_id = ? ORDER BY role, name', [$org['id']]);
    return pro_page('Équipe', 'team', compact('members', 'created', 'org'), 'team');
}

// ---------- Organisation ----------
function organization(): string
{
    $org = require_org(['owner']);
    if (is_post()) {
        $data = [];
        foreach (['name', 'email', 'phone', 'address', 'legal_name', 'siret', 'vat_number', 'description', 'website'] as $k) $data[$k] = trim((string)input($k)) ?: null;
        $data['iban'] = strtoupper(preg_replace('~\s+~', '', (string)input('iban'))) ?: null;
        $data['bic'] = strtoupper(trim((string)input('bic'))) ?: null;
        $data['fee_mode'] = input('fee_mode') === 'organizer' ? 'organizer' : 'buyer';
        if (!$data['name'] || !is_email((string)$data['email'])) back(['error' => 'Nom et e-mail de contact obligatoires.']);
        update('organizers', $data, 'id = ?', [$org['id']]);
        redirect('/pro/organisation', ['success' => '✅ C\'est enregistré.']);
    }
    [$pct, $fixed] = Tickets::feeRates($org);
    return pro_page('Mon organisation', 'organization', compact('org', 'pct', 'fixed'), 'org');
}

// ---------- Finances ----------
function finances(): string
{
    $org = require_org(['owner']);
    $balance = Tickets::balance((int)$org['id']);
    $payouts = all('SELECT * FROM payouts WHERE organizer_id = ? ORDER BY created_at DESC', [$org['id']]);
    $byEvent = all("SELECT e.id, e.name, e.starts_at,
            COALESCE(SUM(CASE WHEN o.method='online' AND o.provider IN ('stripe','paypal','sumup','mollie') THEN o.total END),0) collected,
            COALESCE(SUM(CASE WHEN o.method='online' AND o.provider IN ('stripe','paypal','sumup','mollie') THEN o.commission END),0) commission,
            COALESCE(SUM(CASE WHEN o.method='online' AND o.provider IN ('stripe','paypal','sumup','mollie') THEN o.organizer_amount END),0) due,
            COALESCE(SUM(CASE WHEN o.method='guichet' THEN o.total END),0) box
        FROM events e LEFT JOIN orders o ON o.event_id = e.id AND o.status = 'paid'
        WHERE e.organizer_id = ? GROUP BY e.id ORDER BY e.starts_at DESC", [$org['id']]);
    $demo = (int)val("SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND status = 'paid' AND provider = 'demo'", [$org['id']]);
    [$pct, $fixed] = Tickets::feeRates($org);
    return pro_page('Finances', 'finances', compact('org', 'balance', 'payouts', 'byEvent', 'demo', 'pct', 'fixed'), 'finances');
}
