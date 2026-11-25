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
              FROM orders WHERE organizer_id = ? AND status = 'paid' AND (channel IS NULL OR channel <> 'systeme')", [$org['id']]);
    $tickets = (int)val("SELECT COUNT(*) FROM tickets t JOIN events e ON e.id = t.event_id WHERE e.organizer_id = ? AND t.status = 'valid'", [$org['id']]);
    $today = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM orders WHERE organizer_id = ? AND status = 'paid' AND DATE(paid_at) = CURDATE()", [$org['id']]);
    $upcoming = all("SELECT * FROM events WHERE organizer_id = ? AND COALESCE(ends_at, starts_at) >= NOW() - INTERVAL 6 HOUR ORDER BY starts_at LIMIT 6", [$org['id']]);
    foreach ($upcoming as &$ev) $ev['stats'] = Tickets::stats((int)$ev['id']);
    unset($ev);
    $recent = all("SELECT o.*, e.name ev_name FROM orders o JOIN events e ON e.id = o.event_id WHERE o.organizer_id = ? AND o.status IN ('paid','refunded','cancelled') AND (o.channel IS NULL OR o.channel <> 'systeme')
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
    $oid = (int)$org['id'];
    $tabs = ['avenir' => 'À venir', 'vente' => 'En vente', 'brouillons' => 'Brouillons', 'passes' => 'Passés', 'tous' => 'Tous'];
    $filter = array_key_exists((string)input('f'), $tabs) ? (string)input('f') : 'avenir';
    $conds = [
        'avenir' => 'COALESCE(e.ends_at, e.starts_at) >= NOW() - INTERVAL 6 HOUR',
        'vente' => "e.status = 'published' AND COALESCE(e.ends_at, e.starts_at) >= NOW() - INTERVAL 6 HOUR",
        'brouillons' => "e.status = 'draft'",
        'passes' => 'COALESCE(e.ends_at, e.starts_at) < NOW() - INTERVAL 6 HOUR',
        'tous' => '1',
    ];
    $counts = [];
    foreach ($conds as $k => $c) $counts[$k] = (int)val("SELECT COUNT(*) FROM events e WHERE e.organizer_id = ? AND $c", [$oid]);
    $q = trim((string)input('q'));
    $w = 'e.organizer_id = ? AND ' . $conds[$filter]; $p = [$oid];
    if ($q !== '') { $w .= ' AND (e.name LIKE ? OR e.venue LIKE ? OR e.city LIKE ?)'; array_push($p, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%'); }
    $sorts = ['date' => 'e.starts_at', 'ventes' => 'sold', 'recettes' => 'revenue', 'nom' => 'e.name'];
    $sort = array_key_exists((string)input('tri'), $sorts) ? (string)input('tri') : 'date';
    $dir = $sort === 'date' ? ($filter === 'passes' || $filter === 'tous' ? 'DESC' : 'ASC') : ($sort === 'nom' ? 'ASC' : 'DESC');
    $list = all("SELECT e.*,
        (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid') sold,
        (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid' AND t.entries > 0) checked,
        (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.event_id = e.id AND o.status = 'paid') revenue,
        (SELECT COUNT(*) FROM tickets t JOIN orders o ON o.id = t.order_id WHERE t.event_id = e.id AND t.status = 'valid' AND o.status = 'paid' AND o.paid_at >= NOW() - INTERVAL 7 DAY) week,
        (SELECT COALESCE(SUM(capacity),0) FROM sessions s WHERE s.event_id = e.id AND s.active = 1) sess_cap,
        (SELECT COUNT(*) FROM sessions s WHERE s.event_id = e.id AND s.active = 1) sess_n
        FROM events e WHERE $w ORDER BY " . $sorts[$sort] . " $dir LIMIT 300", $p);
    $view = (string)(input('vue') ?: ($_COOKIE['ev_view'] ?? 'cartes')) === 'liste' ? 'liste' : 'cartes';
    if (input('vue')) setcookie('ev_view', $view, ['expires' => time() + 31536000, 'path' => (base_path() ?: '') . '/', 'samesite' => 'Lax']);
    return pro_page('Événements', 'events', compact('list', 'filter', 'tabs', 'counts', 'q', 'sort', 'view'), 'events',
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
            'revenue_goal' => input('revenue_goal') !== '' ? max(0, parse_money((string)input('revenue_goal'))) ?: null : null,
            'max_per_order' => max(0, (int)input('max_per_order')),
            'allow_reentry' => checked('allow_reentry'),
            'ticket_info' => trim((string)input('ticket_info')),
            'category' => trim((string)input('category')) ?: null,
            'password' => trim((string)input('password')) ?: null,
            'publish_at' => dt_from_input(input('publish_at')),
            'resale_enabled' => checked('resale_enabled'),
            'transfer_enabled' => checked('transfer_enabled'),
            'resale_fee_percent' => max(0, min(30, (float)str_replace(',', '.', (string)input('resale_fee_percent', '5')))),
            'waitlist_enabled' => checked('waitlist_enabled'),
            'refund_request_enabled' => checked('refund_request_enabled'),
            'refund_deadline' => dt_from_input(input('refund_deadline')),
            'donation_enabled' => checked('donation_enabled'),
            'donation_label' => trim((string)input('donation_label')) ?: null,
            'queue_enabled' => checked('queue_enabled'),
            'queue_rate' => max(5, min(2000, (int)input('queue_rate', 50))),
            'ticket_color' => preg_match('~^#[0-9a-fA-F]{6}$~', (string)input('ticket_color')) ? (string)input('ticket_color') : null,
            'email_message' => trim((string)input('email_message')) ?: null,
            'exchange_enabled' => checked('exchange_enabled'),
            'reminder_message' => trim((string)input('reminder_message')) ?: null,
            'transfer_hours' => max(0, min(720, (int)input('transfer_hours'))),
            'exchange_hours' => max(0, min(720, (int)input('exchange_hours', 24))),
            'min_age' => in_array((int)input('min_age'), [12, 16, 18, 21], true) ? (int)input('min_age') : null,
            'presale_until' => dt_from_input(input('presale_until')),
            'presale_past' => checked('presale_past'),
            'presale_list' => implode("\n", array_values(array_unique(array_filter(array_map(fn($x) => strtolower(trim($x)), preg_split('~[\s,;]+~', (string)input('presale_list'))), 'is_email')))) ?: null,
            'group_tiers' => (function () {
                $t = [];
                foreach ((array)($_POST['tier_min'] ?? []) as $k => $m) { $m = (int)$m; $pc = (int)($_POST['tier_pct'][$k] ?? 0); if ($m >= 2 && $pc >= 1 && $pc <= 90) $t[$m] = ['min' => $m, 'pct' => $pc]; }
                ksort($t);
                return $t ? json_encode(array_values($t)) : null;
            })(),
        ];
        $newSlug = slugify((string)input('slug'));
        if ($ev && input('slug') !== null && trim((string)input('slug')) !== '' && $newSlug !== $ev['slug']) {
            if (val('SELECT id FROM events WHERE slug = ? AND id <> ?', [$newSlug, $ev['id']])) $errors[] = 'Cette adresse est déjà prise, choisis-en une autre.';
            else $data['slug'] = $newSlug;
        }
        try { $logo = upload_image('ticket_logo'); if ($logo) $data['ticket_logo'] = $logo; } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        if ($data['name'] === '') $errors[] = 'Il manque le nom de l\'événement.';
        if (!$data['starts_at']) $errors[] = 'Il manque la date de début.';
        if ($data['ends_at'] && $data['starts_at'] && $data['ends_at'] < $data['starts_at']) $errors[] = 'La fin avant le début ? Même Doc Brown n\'oserait pas';
        try { $img = upload_image('image'); if ($img) $data['image'] = $img; } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
        if ($ev && checked('remove_image')) $data['image'] = null;
        if (!$errors) {
            if ($ev) {
                update('events', $data, 'id = ?', [$ev['id']]);
                Audit::log('event.updated', $ev['name'], (int)$ev['id']);
                redirect('/pro/evenements/' . $ev['id'], ['success' => 'C\'est enregistré.']);
            }
            event_limit_check((int)$org['id']);
            $data += ['organizer_id' => $org['id'], 'slug' => unique_slug('events', $data['name'] . ' ' . date('Y', strtotime($data['starts_at']))), 'status' => 'draft', 'created_at' => now()];
            $newId = insert('events', $data);
            $price = parse_money(input('first_price'));
            insert('rates', ['event_id' => $newId, 'name' => trim((string)input('first_rate')) ?: 'Entrée', 'price' => $price, 'quota' => 0, 'active' => 1, 'sort' => 0]);
            insert('access_points', ['event_id' => $newId, 'name' => 'Entrée principale', 'active' => 1]);
            Audit::log('event.created', (string)input('name'), (int)$newId);
            redirect('/pro/evenements/' . $newId . '/tarifs', ['success' => 'Événement créé (en brouillon). Vérifie tes tarifs, puis publie-le !']);
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
    $recent = all("SELECT * FROM orders WHERE event_id = ? AND status IN ('paid','refunded','cancelled') AND (channel IS NULL OR channel <> 'systeme') ORDER BY created_at DESC LIMIT 6", [$ev['id']]);
    $rates = (int)val('SELECT COUNT(*) FROM rates WHERE event_id = ?', [$ev['id']]);
    return ev_page($ev, 'overview', $ev['name'], 'event_overview', compact('stats', 'chart', 'recent', 'rates'));
}

function event_status(string $id): never
{
    require_org();
    $ev = org_event((int)$id);
    $st = (string)input('status');
    if (!in_array($st, ['draft', 'published', 'closed'], true)) back();
    if ($st === 'published' && (current_org()['status'] ?? '') === 'pending') back(['warning' => 'Ton compte est en cours de validation par l\'équipe ' . setting('platform_name') . '. Tu pourras publier dès qu\'il est validé (on te prévient par e-mail).']);
    if ($st === 'published' && !val('SELECT id FROM rates WHERE event_id = ? AND active = 1', [$ev['id']])) back(['error' => 'Ajoute au moins un tarif actif avant de publier. Sans billets, ça marche moins bien']);
    update('events', ['status' => $st], 'id = ?', [$ev['id']]);
    if ($st === 'published' && $ev['status'] !== 'published' && class_exists('Hooks')) Hooks::fire((int)$ev['organizer_id'], 'event.published', ['id' => (int)$ev['id'], 'name' => $ev['name'], 'starts_at' => $ev['starts_at'], 'url' => abs_url('/e/' . $ev['slug'])]);
    Audit::log('event.status', $ev['name'] . ' → ' . $st, (int)$ev['id']);
    back(['success' => ['draft' => 'Retour en brouillon Plus personne ne le voit.', 'published' => 'C\'est en ligne ! La billetterie est ouverte.', 'closed' => 'Ventes clôturées. Les billets vendus restent valables.'][$st]]);
}

/** 2.2 : reprendre un événement — nouvelle date (tout est décalé d'autant) et choix de ce qui est copié. */
function event_duplicate(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    if (!is_post() || !input('confirm')) {
        $counts = ['rates' => (int)val('SELECT COUNT(*) FROM rates WHERE event_id = ?', [$ev['id']]), 'products' => (int)val('SELECT COUNT(*) FROM products WHERE event_id = ?', [$ev['id']]),
            'promos' => (int)val('SELECT COUNT(*) FROM promos WHERE event_id = ? AND batch IS NULL', [$ev['id']]), 'sessions' => (int)val('SELECT COUNT(*) FROM sessions WHERE event_id = ?', [$ev['id']]),
            'shifts' => (int)val('SELECT COUNT(*) FROM staff_shifts WHERE event_id = ?', [$ev['id']]), 'budget' => (int)val('SELECT COUNT(*) FROM budget_lines WHERE event_id = ?', [$ev['id']]),
            'tasks' => (int)val('SELECT COUNT(*) FROM tasks WHERE event_id = ?', [$ev['id']]), 'fields' => (int)val('SELECT COUNT(*) FROM form_fields WHERE event_id = ?', [$ev['id']]),
            'aps' => (int)val('SELECT COUNT(*) FROM access_points WHERE event_id = ?', [$ev['id']])];
        $suggest = date('Y-m-d\TH:i', strtotime($ev['starts_at'] . (strtotime($ev['starts_at']) < time() ? ' +1 year' : ' +7 days')));
        return ev_page($ev, 'edit', 'Reprendre l\'événement', 'event_duplicate', compact('counts', 'suggest'));
    }
    $start = dt_from_input(input('starts_at'));
    if (!$start) back(['error' => 'Choisis la date du nouvel événement.']);
    $delta = strtotime($start) - strtotime($ev['starts_at']);
    $shift = fn(?string $d) => $d ? date('Y-m-d H:i:s', strtotime($d) + $delta) : null;
    $shiftDay = fn(?string $d) => $d ? date('Y-m-d', strtotime($d) + $delta) : null;
    $want = fn(string $k) => in_array($k, (array)($_POST['copy'] ?? []), true);
    event_limit_check((int)$ev['organizer_id']);
    $copy = $ev;
    unset($copy['id']);
    $copy['name'] = mb_substr(trim((string)input('name')) ?: $ev['name'] . ' (copie)', 0, 190);
    $copy['slug'] = unique_slug('events', $copy['name'] . ' ' . date('Y', strtotime($start)));
    $copy['status'] = 'draft';
    $copy['created_at'] = now();
    foreach (['starts_at', 'ends_at', 'publish_at', 'refund_deadline', 'presale_until'] as $f) if (array_key_exists($f, $copy)) $copy[$f] = $shift($copy[$f]);
    if ($copy['publish_at'] && strtotime($copy['publish_at']) < time()) $copy['publish_at'] = null;
    $newId = insert('events', $copy);
    $map = []; $sessMap = []; $done = [];
    if ($want('sessions')) foreach (all('SELECT * FROM sessions WHERE event_id = ?', [$ev['id']]) as $x) { $old = $x['id']; unset($x['id']); $x['event_id'] = $newId; $x['starts_at'] = $shift($x['starts_at']); $x['ends_at'] = $shift($x['ends_at']); $sessMap[$old] = insert('sessions', $x); }
    foreach (all('SELECT * FROM rates WHERE event_id = ? ORDER BY id', [$ev['id']]) as $r) {
        $old = $r['id']; unset($r['id']); $r['event_id'] = $newId; $r['sale_start'] = $shift($r['sale_start']); $r['sale_end'] = $shift($r['sale_end']);
        $r['session_ids'] = $r['session_ids'] ? (implode(',', array_filter(array_map(fn($i) => $sessMap[(int)$i] ?? null, explode(',', (string)$r['session_ids'])))) ?: null) : null;
        $map[$old] = insert('rates', $r);
    }
    foreach ($map as $old => $nid) { $after = val('SELECT after_rate_id FROM rates WHERE id = ?', [$old]); if ($after) update('rates', ['after_rate_id' => $map[(int)$after] ?? null], 'id = ?', [$nid]); }
    $remap = fn($ids) => $ids ? (implode(',', array_filter(array_map(fn($i) => $map[(int)$i] ?? null, explode(',', (string)$ids)))) ?: null) : null;
    if ($want('fields')) foreach (all('SELECT * FROM form_fields WHERE event_id = ?', [$ev['id']]) as $f) { unset($f['id']); $f['event_id'] = $newId; if (array_key_exists('rate_ids', $f)) $f['rate_ids'] = $remap($f['rate_ids']); insert('form_fields', $f); }
    if ($want('aps')) foreach (all('SELECT * FROM access_points WHERE event_id = ?', [$ev['id']]) as $a) { unset($a['id']); $a['event_id'] = $newId; $a['rate_ids'] = $remap($a['rate_ids']); foreach (['opens', 'closes'] as $k) if (array_key_exists($k, $a)) $a[$k] = $shift($a[$k]); insert('access_points', $a); }
    if ($want('products')) foreach (all('SELECT * FROM products WHERE event_id = ?', [$ev['id']]) as $p) { unset($p['id']); $p['event_id'] = $newId; insert('products', $p); }
    if ($want('promos')) foreach (all('SELECT * FROM promos WHERE event_id = ? AND batch IS NULL', [$ev['id']]) as $p) { unset($p['id']); $p['event_id'] = $newId; $p['used'] = 0; $p['valid_from'] = $shift($p['valid_from'] ?? null); $p['valid_until'] = $shift($p['valid_until']); if (array_key_exists('rate_ids', $p)) $p['rate_ids'] = $remap($p['rate_ids']); insert('promos', $p); }
    if ($want('shifts')) foreach (all('SELECT * FROM staff_shifts WHERE event_id = ?', [$ev['id']]) as $x) { unset($x['id']); $x['event_id'] = $newId; $x['starts_at'] = $shift($x['starts_at']); $x['ends_at'] = $shift($x['ends_at']); $x['created_at'] = now(); insert('staff_shifts', $x); }
    if ($want('budget')) foreach (all('SELECT * FROM budget_lines WHERE event_id = ?', [$ev['id']]) as $x) { unset($x['id']); $x['event_id'] = $newId; foreach (['actual', 'paid'] as $k) if (array_key_exists($k, $x)) $x[$k] = is_numeric($x[$k]) ? 0 : null; $x['created_at'] = now(); insert('budget_lines', $x); }
    if ($want('tasks')) foreach (all('SELECT * FROM tasks WHERE event_id = ?', [$ev['id']]) as $x) { unset($x['id']); $x['event_id'] = $newId; $x['due_date'] = $shiftDay($x['due_date']); $x['done_at'] = null; if (array_key_exists('status', $x)) $x['status'] = 'todo'; $x['created_at'] = now(); insert('tasks', $x); }
    $days = (int)round($delta / 86400);
    Audit::log('event.created', 'Reprise de « ' . $ev['name'] . ' » (' . ($days >= 0 ? '+' : '') . $days . ' jours)', (int)$newId);
    redirect('/pro/evenements/' . $newId, ['success' => 'Nouvel événement créé en brouillon, toutes les dates décalées de ' . abs($days) . ' jour' . (abs($days) > 1 ? 's' : '') . '. Vérifie et publie.']);
}

function event_delete(string $id): never
{
    require_org();
    $ev = org_event((int)$id);
    if (val('SELECT id FROM orders WHERE event_id = ? LIMIT 1', [$ev['id']])) back(['error' => 'Cet événement a des commandes : impossible de le supprimer. Clôture-le plutôt.']);
    foreach (['rates', 'promos', 'form_fields', 'access_points', 'scans'] as $t) q("DELETE FROM $t WHERE event_id = ?", [$ev['id']]);
    q('DELETE FROM events WHERE id = ?', [$ev['id']]);
    Audit::log('event.deleted', $ev['name']);
    redirect('/pro/evenements', ['success' => 'Événement supprimé.']);
}

// ---------- Tarifs ----------
function rates(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $action = (string)input('action');
        $rid = (int)input('rate_id');
        // 2.2 : actions rapides depuis le tableau des tarifs
        if ($action === 'toggle') { q('UPDATE rates SET active = 1 - active WHERE id = ? AND event_id = ?', [$rid, $ev['id']]); Audit::log('rate.toggled', 'Tarif #' . $rid, (int)$ev['id']); back(); }
        if ($action === 'reorder') {
            foreach (array_values(array_map('intval', (array)($_POST['ids'] ?? []))) as $i => $x) q('UPDATE rates SET sort = ? WHERE id = ? AND event_id = ?', [($i + 1) * 10, $x, $ev['id']]);
            if (input('ajax')) json_out(['ok' => true]);
            back(['success' => 'Ordre enregistré.']);
        }
        if ($action === 'duplicate') {
            $r = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [$rid, $ev['id']]);
            if (!$r) back();
            unset($r['id']);
            $r['name'] = mb_substr($r['name'] . ' (copie)', 0, 190); $r['active'] = 0; $r['sort'] = (int)$r['sort'] + 1;
            $nid = insert('rates', $r);
            redirect('/pro/evenements/' . $ev['id'] . '/tarifs?edit=' . $nid . '#editeur', ['success' => 'Tarif dupliqué (en pause) : ajuste-le puis active-le.']);
        }
        if ($action === 'delete') {
            if (val('SELECT id FROM order_items WHERE rate_id = ? LIMIT 1', [$rid])) back(['error' => 'Ce tarif a déjà été vendu : désactive-le plutôt que de le supprimer.']);
            q('DELETE FROM rates WHERE id = ? AND event_id = ?', [$rid, $ev['id']]);
            Audit::log('rate.deleted', 'Tarif #' . (int)input('rate_id'), (int)$ev['id']);
            back(['success' => 'Tarif supprimé.']);
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
            'price_mode' => input('price_mode') === 'free' ? 'free' : 'fixed',
            'scan_note' => mb_substr(trim((string)input('scan_note')), 0, 160) ?: null,
            'vat_rate' => in_array((string)input('vat_rate'), ['0', '2.1', '5.5', '10', '20'], true) ? (string)input('vat_rate') : null,
            'scan_color' => preg_match('~^#[0-9a-fA-F]{6}$~', (string)input('scan_color')) ? strtolower((string)input('scan_color')) : null,
            'pack_size' => max(1, min(50, (int)input('pack_size', 1))),
            'max_entries' => max(1, min(100, (int)input('max_entries', 1))),
            'per_day' => checked('per_day'),
            'session_ids' => implode(',', array_map('intval', (array)($_POST['session_ids'] ?? []))) ?: null,
            'seat_category' => trim((string)input('seat_category')) ?: null,
            'min_age' => in_array((int)input('min_age'), [12, 16, 18, 21], true) ? (int)input('min_age') : null,
            'after_rate_id' => ((int)input('after_rate_id') && (int)input('after_rate_id') !== $rid && val('SELECT id FROM rates WHERE id = ? AND event_id = ?', [(int)input('after_rate_id'), $ev['id']])) ? (int)input('after_rate_id') : null,
        ];
        if ($data['name'] === '') back(['error' => 'Donne un nom à ce tarif.']);
        if ($data['after_rate_id'] && $rid && (int)val('SELECT after_rate_id FROM rates WHERE id = ?', [$data['after_rate_id']]) === $rid) back(['error' => 'Ces deux tarifs s\'attendent l\'un l\'autre : choisis un seul sens.']);
        if (!$rid && !(int)input('sort')) $data['sort'] = (int)val('SELECT COALESCE(MAX(sort), 0) + 10 FROM rates WHERE event_id = ?', [$ev['id']]);
        if ($rid) update('rates', $data, 'id = ? AND event_id = ?', [$rid, $ev['id']]);
        else $rid = insert('rates', $data + ['event_id' => $ev['id']]);
        Audit::log('rate.saved', (string)input('name'), (int)$ev['id']);
        redirect('/pro/evenements/' . $ev['id'] . '/tarifs', ['success' => 'Tarif « ' . $data['name'] . ' » enregistré.']);
    }
    $org = current_org();
    $rates = all("SELECT r.*, (SELECT COUNT(*) FROM tickets t WHERE t.rate_id = r.id AND t.status = 'valid') sold,
        (SELECT COUNT(*) FROM tickets t WHERE t.rate_id = r.id AND t.status = 'valid' AND t.created_at >= NOW() - INTERVAL 7 DAY) sold7,
        (SELECT COUNT(*) FROM tickets t WHERE t.rate_id = r.id AND t.status = 'valid' AND t.entries > 0) inside,
        (SELECT COALESCE(SUM(i.qty * i.unit_price), 0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.rate_id = r.id AND i.kind = 'rate' AND o.status = 'paid') revenue
        FROM rates r WHERE event_id = ? ORDER BY sort, id", [$ev['id']]);
    foreach ($rates as &$r) $r['state'] = Tickets::onSale($r, $ev);
    unset($r);
    $sessions = Tickets::sessions((int)$ev['id'], false);
    $map = Seating::map($ev);
    return ev_page($ev, 'rates', 'Tarifs', 'rates', ['rates' => $rates, 'org' => $org, 'sessions' => $sessions, 'seatCats' => $map['categories'] ?? []]);
}

// ---------- Formulaire personnalisé ----------
function form_fields(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $fid = (int)input('field_id');
        if (input('action') === 'delete') { q('DELETE FROM form_fields WHERE id = ? AND event_id = ?', [$fid, $ev['id']]); back(['success' => 'Question supprimée.']); }
        $type = (string)input('type');
        $data = [
            'label' => trim((string)input('label')),
            'type' => in_array($type, ['text', 'textarea', 'select', 'checkbox', 'date', 'email', 'phone', 'number'], true) ? $type : 'text',
            'options' => trim((string)input('options')) ?: null,
            'required' => checked('required'),
            'per_ticket' => (int)input('per_ticket') ? 1 : 0,
            'sort' => (int)input('sort'),
            'rate_ids' => implode(',', array_map('intval', (array)($_POST['rate_ids'] ?? []))) ?: null,
        ];
        if ($data['label'] === '') back(['error' => 'Une question sans question, c\'est philosophique, mais non.']);
        if ($data['type'] === 'select' && !$data['options']) back(['error' => 'Indique les choix possibles (un par ligne).']);
        if ($fid) update('form_fields', $data, 'id = ? AND event_id = ?', [$fid, $ev['id']]);
        else insert('form_fields', $data + ['event_id' => $ev['id']]);
        back(['success' => 'Question enregistrée.']);
    }
    $fields = all('SELECT * FROM form_fields WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    $rates = all('SELECT id, name FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    return ev_page($ev, 'form', 'Formulaire d\'inscription', 'form_fields', ['fields' => $fields, 'rates' => $rates]);
}

// ---------- Codes promo ----------
function promos(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $rates = all('SELECT id, name, hidden FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    if (input('csv')) {
        $batch = (string)input('batch');
        $rows = [['Code', 'Lot', 'Libellé', 'Effet', 'Utilisations', 'Max', 'Début', 'Fin', 'Actif']];
        foreach (all('SELECT * FROM promos WHERE event_id = ?' . ($batch !== '' ? ' AND batch = ?' : '') . ' ORDER BY id', $batch !== '' ? [$ev['id'], $batch] : [$ev['id']]) as $p)
            $rows[] = [$p['code'], $p['batch'], $p['label'], $p['kind'] === 'percent' ? $p['value'] . ' %' : ($p['kind'] === 'amount' ? money_input((int)$p['value']) . ' €' : 'accès'), $p['used'], $p['max_uses'] ?: '', $p['valid_from'], $p['valid_until'], $p['active'] ? 'oui' : 'non'];
        csv_download('codes-' . $ev['slug'] . ($batch !== '' ? '-' . $batch : '') . '.csv', $rows);
    }
    if (is_post()) {
        $pid = (int)input('promo_id');
        $a = (string)input('action');
        if ($a === 'delete') { q('DELETE FROM promos WHERE id = ? AND event_id = ? AND used = 0', [$pid, $ev['id']]) ; back(['success' => 'Code supprimé (un code déjà utilisé ne peut qu\'être désactivé).']); }
        if ($a === 'toggle') { q('UPDATE promos SET active = 1 - active WHERE id = ? AND event_id = ?', [$pid, $ev['id']]); back(); }
        if ($a === 'batch_off') { q('UPDATE promos SET active = 0 WHERE batch = ? AND event_id = ?', [(string)input('batch'), $ev['id']]); back(['success' => 'Lot désactivé.']); }
        $kind = (string)input('kind');
        if (!in_array($kind, ['percent', 'amount', 'unlock'], true)) $kind = 'percent';
        $rateIds = array_map('intval', (array)($_POST['rate_ids'] ?? []));
        $valid = array_map('intval', array_column($rates, 'id'));
        $rateIds = array_values(array_filter($rateIds, fn($r) => in_array($r, $valid, true)));
        $value = $kind === 'percent' ? max(1, min(100, (int)input('value'))) : ($kind === 'amount' ? parse_money(input('value')) : 0);
        if ($kind === 'unlock' && !$rateIds) back(['error' => 'Choisis le ou les tarifs secrets que ce code débloque.']);
        $base = ['event_id' => $ev['id'], 'kind' => $kind, 'value' => $value, 'rate_ids' => $rateIds ? implode(',', $rateIds) : null,
            'max_uses' => max(0, (int)input('max_uses')), 'valid_from' => dt_from_input(input('valid_from')), 'valid_until' => dt_from_input(input('valid_until')),
            'min_qty' => max(0, min(100, (int)input('min_qty'))), 'once_per_email' => checked('once_per_email'), 'label' => mb_substr(trim((string)input('label')), 0, 190) ?: null, 'active' => 1];
        if ($base['valid_from'] && $base['valid_until'] && $base['valid_until'] <= $base['valid_from']) back(['error' => 'La fin de validité doit être après le début.']);
        $count = max(1, min(1000, (int)input('count', 1)));
        if ($count > 1) {
            // lot de codes uniques (un par influenceur, partenaire, abonné…)
            $prefix = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', (string)input('prefix'))) ?: 'ENT';
            $batch = substr($prefix, 0, 12) . '-' . date('ymdHi');
            for ($k = 0; $k < $count; $k++) {
                do { $code = substr($prefix, 0, 8) . '-' . random_code(6); } while (val('SELECT id FROM promos WHERE event_id = ? AND code = ?', [$ev['id'], $code]));
                insert('promos', $base + ['code' => $code, 'batch' => $batch, 'max_uses' => $base['max_uses'] ?: 1]);
            }
            Audit::log('promo.batch', $count . ' codes ' . $batch, (int)$ev['id']);
            back(['success' => '' . $count . ' codes uniques créés (lot ' . $batch . '). Exporte-les en CSV pour les distribuer.']);
        }
        $code = strtoupper(preg_replace('~[^A-Za-z0-9_-]~', '', (string)input('code')));
        if (input('generate') || $code === '') $code = random_code(8);
        if (val('SELECT id FROM promos WHERE event_id = ? AND code = ?', [$ev['id'], $code])) back(['error' => 'Ce code existe déjà pour cet événement.']);
        insert('promos', $base + ['code' => $code]);
        back(['success' => 'Code « ' . $code . ' » créé. Fais-le tourner !']);
    }
    $stats = [];
    foreach (all("SELECT promo_code, COUNT(*) n, SUM(total) revenue, SUM(discount - group_discount) disc FROM orders WHERE event_id = ? AND status = 'paid' AND promo_code IS NOT NULL GROUP BY promo_code", [$ev['id']]) as $r) $stats[$r['promo_code']] = $r;
    $promos = all('SELECT * FROM promos WHERE event_id = ? AND batch IS NULL ORDER BY id DESC', [$ev['id']]);
    $batches = all('SELECT batch, MIN(label) label, MIN(kind) kind, MIN(value) value, COUNT(*) n, SUM(used > 0) used, SUM(active) active, MIN(created) created FROM (SELECT p.*, p.id created FROM promos p WHERE event_id = ? AND batch IS NOT NULL) x GROUP BY batch ORDER BY created DESC', [$ev['id']]);
    foreach ($batches as &$b) {
        $codes = array_column(all('SELECT code FROM promos WHERE event_id = ? AND batch = ?', [$ev['id'], $b['batch']]), 'code');
        $b['revenue'] = array_sum(array_map(fn($c) => (int)($stats[$c]['revenue'] ?? 0), $codes));
    }
    unset($b);
    return ev_page($ev, 'promos', 'Codes promo & accès', 'promos', ['promos' => $promos, 'rates' => $rates, 'stats' => $stats, 'batches' => $batches]);
}

// ---------- Points d'accès ----------
function access_points(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $rates = all('SELECT id, name FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    if (is_post()) {
        $aid = (int)input('ap_id');
        if (input('action') === 'delete') { q('DELETE FROM access_points WHERE id = ? AND event_id = ?', [$aid, $ev['id']]); back(['success' => 'Point d\'accès supprimé.']); }
        if (input('action') === 'reentry') { update('events', ['allow_reentry' => checked('allow_reentry')], 'id = ?', [$ev['id']]); back(['success' => 'C\'est noté.']); }
        $valid = array_map('intval', array_column($rates, 'id'));
        $rateIds = array_values(array_filter(array_map('intval', (array)($_POST['rate_ids'] ?? [])), fn($r) => in_array($r, $valid, true)));
        $data = ['name' => trim((string)input('name')) ?: 'Entrée', 'rate_ids' => $rateIds ? implode(',', $rateIds) : null, 'active' => checked('active'), 'capacity' => (int)input('capacity') > 0 ? (int)input('capacity') : null,
            'opens_at' => dt_from_input(input('opens_at')), 'closes_at' => dt_from_input(input('closes_at'))];
        if ($data['opens_at'] && $data['closes_at'] && $data['closes_at'] <= $data['opens_at']) back(['error' => 'La fermeture doit être après l\'ouverture.']);
        if ($aid) update('access_points', $data, 'id = ? AND event_id = ?', [$aid, $ev['id']]);
        else insert('access_points', $data + ['event_id' => $ev['id']]);
        back(['success' => 'Point d\'accès enregistré.']);
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
    require_module('guichet');
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
        if ($buyer['email'] !== '' && !is_email($buyer['email'])) $errors[] = 'Cette adresse e-mail a l\'air bizarre';
        if (!$errors) {
            $session = Tickets::hasSessions((int)$ev['id']) ? one('SELECT * FROM sessions WHERE id = ? AND event_id = ?', [(int)input('session_id'), $ev['id']]) : null;
            $seats = [];
            foreach ((array)($_POST['seats'] ?? []) as $rid => $list) $seats[(int)$rid] = array_values(array_filter(array_map(fn($x) => strtoupper(preg_replace('~[^A-Za-z0-9-]~', '', $x)), preg_split('~[\s,;]+~', (string)$list))));
            $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'session' => $session, 'seats' => $seats, 'quantities' => $q, 'buyer' => $buyer, 'method' => $method, 'channel' => $method,
                'pay_method' => $method === 'guichet' ? (string)input('pay_method') : null, 'note' => (string)input('note'),
                'user_id' => user()['id'], 'force' => checked('force')]);
            if (!empty($r['errors'])) $errors = $r['errors'];
            else {
                $send = checked('send') && $buyer['email'];
                $o = Tickets::confirm((int)$r['order']['id'], [], $send);
                $sent = $send && val('SELECT email_sent_at FROM orders WHERE id = ?', [$o['id']]);
                Audit::log('box.sale', $o['ref'] . ' · ' . money((int)$o['total']), (int)$ev['id'], (int)$o['id']);
                redirect('/pro/commandes/' . $o['id'], ['success' => ($method === 'invitation' ? 'Invitation créée' : 'Vente enregistrée') . ($sent ? ', billets envoyés par e-mail' : ' !')]);
            }
        }
    }
    $sessions = Tickets::sessions((int)$ev['id']);
    $sel = $sessions ? (one('SELECT * FROM sessions WHERE id = ?', [(int)input('session_id')]) ?: $sessions[0]) : null;
    foreach ($rates as &$r) $r['remaining'] = Tickets::remaining($r, $ev, $sel);
    unset($r);
    return ev_page($ev, 'box', 'Guichet & invitations', 'box_office', ['rates' => $rates, 'errors' => $errors, 'sessions' => $sessions, 'sel' => $sel, 'seatmap' => (bool)Seating::map($ev)]);
}

// ---------- Contrôle en direct ----------
function live_control(string $id): string
{
    require_org(['owner', 'manager']);
    $ev = org_event((int)$id);
    if (is_post() && input('action') === 'undo') {
        Tickets::undoScan((int)input('scan_id'), (int)$ev['id']) ? back(['success' => 'Passage annulé.']) : back(['error' => 'Impossible d\'annuler ce passage.']);
    }
    if (is_post() && input('action') === 'checkin') {
        $t = one('SELECT * FROM tickets WHERE id = ? AND event_id = ?', [(int)input('ticket_id'), $ev['id']]);
        if ($t) { $r = Tickets::scan(['event' => $ev, 'code' => $t['code'], 'direction' => 'in', 'user' => user(), 'device' => 'admin']); back([$r['level'] === 'ok' ? 'success' : 'warning' => $r['label'] . ' : ' . ($r['ticket']['name'] ?? '')]); }
        back();
    }
    if (is_post() && input('action') === 'scan_message') {
        $body = mb_substr(trim((string)input('body')), 0, 280);
        if ($body === '') back(['error' => 'Le message est vide.']);
        insert('scan_messages', ['event_id' => $ev['id'], 'body' => $body, 'level' => in_array(input('level'), ['info', 'warn', 'alert'], true) ? (string)input('level') : 'info', 'user_id' => user()['id'], 'created_at' => now()]);
        Audit::log('message.sent', 'Message aux contrôleurs : ' . $body, (int)$ev['id']);
        back(['success' => 'Message envoyé aux téléphones de contrôle (il s\'affiche d\'ici 30 secondes).']);
    }
    $devices = all('SELECT d.*, u.name user_name FROM scan_devices d LEFT JOIN users u ON u.id = d.user_id WHERE d.event_id = ? AND d.last_seen >= NOW() - INTERVAL 12 HOUR ORDER BY d.last_seen DESC', [$ev['id']]);
    $messages = all('SELECT m.*, u.name user_name FROM scan_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.event_id = ? ORDER BY m.id DESC LIMIT 5', [$ev['id']]);
    $stats = Tickets::stats((int)$ev['id']);
    $aps = all("SELECT a.name, SUM(s.result = 'ok' AND s.undone = 0) entries, SUM(s.result = 'exit' AND s.undone = 0) exits, SUM(s.result NOT IN ('ok','exit')) refused
                FROM access_points a LEFT JOIN scans s ON s.access_point_id = a.id WHERE a.event_id = ? GROUP BY a.id ORDER BY a.id", [$ev['id']]);
    $scans = all("SELECT s.*, t.first_name, t.last_name, t.rate_name, a.name ap_name, u.name user_name FROM scans s
                  LEFT JOIN tickets t ON t.id = s.ticket_id LEFT JOIN access_points a ON a.id = s.access_point_id LEFT JOIN users u ON u.id = s.user_id
                  WHERE s.event_id = ? ORDER BY s.scanned_at DESC, s.id DESC LIMIT 40", [$ev['id']]);
    $perHour = all("SELECT DATE_FORMAT(scanned_at, '%H:00') h, COUNT(*) n FROM scans WHERE event_id = ? AND result = 'ok' AND undone = 0 AND scanned_at >= NOW() - INTERVAL 12 HOUR GROUP BY h ORDER BY MIN(scanned_at)", [$ev['id']]);
    // 2.2 : lecture des arrivées
    $flow = [];
    $flow['last15'] = (int)val("SELECT COUNT(*) FROM scans WHERE event_id = ? AND result = 'ok' AND undone = 0 AND scanned_at >= NOW() - INTERVAL 15 MINUTE", [$ev['id']]);
    $flow['rate'] = $flow['last15'] / 15;
    $flow['remaining'] = max(0, (int)$stats['sold'] - (int)$stats['checked']);
    $flow['eta'] = $flow['rate'] > 0 ? time() + (int)round($flow['remaining'] / $flow['rate'] * 60) : null;
    $flow['started'] = strtotime($ev['starts_at']) < time();
    $flow['noshow'] = $flow['started'] && strtotime($ev['starts_at']) < time() - 3600 && $stats['sold'] ? round($flow['remaining'] / (int)$stats['sold'] * 100) : null;
    $tot = one("SELECT COUNT(*) n, SUM(result NOT IN ('ok','exit')) refused FROM scans WHERE event_id = ? AND undone = 0", [$ev['id']]);
    $flow['refuse_rate'] = (int)$tot['n'] ? round((int)$tot['refused'] / (int)$tot['n'] * 100, 1) : 0;
    $flow['reasons'] = all("SELECT result, COUNT(*) n FROM scans WHERE event_id = ? AND result NOT IN ('ok','exit') AND undone = 0 GROUP BY result ORDER BY n DESC LIMIT 4", [$ev['id']]);
    $flow['peak'] = one("SELECT DATE_FORMAT(MIN(scanned_at), '%H:%i') h, COUNT(*) n FROM scans WHERE event_id = ? AND result = 'ok' AND undone = 0 GROUP BY FLOOR(UNIX_TIMESTAMP(scanned_at) / 900) ORDER BY n DESC LIMIT 1", [$ev['id']]);
    $flow['doors'] = all("SELECT a.name, SUM(s.result = 'ok' AND s.undone = 0 AND s.scanned_at >= NOW() - INTERVAL 15 MINUTE) n15 FROM access_points a LEFT JOIN scans s ON s.access_point_id = a.id WHERE a.event_id = ? GROUP BY a.id ORDER BY a.id", [$ev['id']]);
    $q = []; $from = strtotime(date('Y-m-d H:00:00', time() - 5 * 3600));
    for ($t = $from; $t <= time(); $t += 900) $q[date('H:i', $t)] = [0, 0];
    foreach (all("SELECT FLOOR(UNIX_TIMESTAMP(scanned_at) / 900) * 900 b, SUM(result = 'ok') i, SUM(result = 'exit') x FROM scans WHERE event_id = ? AND undone = 0 AND scanned_at >= ? GROUP BY b", [$ev['id'], date('Y-m-d H:i:s', $from)]) as $r) {
        $k = date('H:i', (int)$r['b']); if (isset($q[$k])) $q[$k] = [(int)$r['i'], (int)$r['x']];
    }
    $flow['quarters'] = $q;
    return ev_page($ev, 'live', 'Contrôle en direct', 'live_control', compact('stats', 'aps', 'scans', 'perHour', 'devices', 'messages', 'flow'));
}

// Commandes : voir app/controllers/orders.php

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
            Audit::log('team.changed', (string)input('action'));
            back(['success' => 'C\'est fait.']);
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
        foreach (['ga4_id', 'gtm_id', 'fb_pixel'] as $k) $data[$k] = preg_replace('~[^A-Za-z0-9-]~', '', (string)input($k)) ?: null;
        $hook = trim((string)input('webhook_url'));
        $data['webhook_url'] = $hook !== '' && preg_match('~^https://~', $hook) ? mb_substr($hook, 0, 255) : null;
        if ($data['webhook_url'] && !$org['webhook_secret']) $data['webhook_secret'] = token(24);
        $data['recap'] = in_array(input('recap'), ['daily', 'weekly'], true) ? (string)input('recap') : null;
        $data['vat_rate'] = in_array((string)input('vat_rate'), ['0', '2.1', '5.5', '10', '20'], true) ? (string)input('vat_rate') : null;
        if (input('action') === 'recap_test') {
            update('organizers', ['recap' => $data['recap']], 'id = ?', [$org['id']]);
            try { $ok = Recap::send(['recap' => $data['recap'] ?: 'daily'] + $org, user()['email']); } catch (Throwable $e) { back(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
            back([$ok ? 'success' : 'warning' => $ok ? 'Exemple envoyé à ' . user()['email'] . '.' : 'Rien à raconter pour l\'instant : publie un événement d\'abord']);
        }
        if (input('action') === 'api_key') { update('organizers', ['api_key' => 'ev_' . token(28)], 'id = ?', [$org['id']]); redirect('/pro/organisation', ['success' => 'Nouvelle clé API générée (l\'ancienne ne marche plus).']); }
        try { $logo = upload_image('logo'); if ($logo) $data['logo'] = $logo; } catch (RuntimeException $e) { back(['error' => $e->getMessage()]); }
        if (input('page_settings') !== null) {
            try { $cover = upload_image('cover'); if ($cover) $data['cover'] = $cover; } catch (RuntimeException $e) { back(['error' => $e->getMessage()]); }
            if (input('remove_cover')) $data['cover'] = null;
            $data['tagline'] = mb_substr(trim((string)input('tagline')), 0, 190) ?: null;
            $data['show_past'] = checked('show_past');
            $soc = [];
            foreach (['instagram', 'facebook', 'tiktok', 'youtube', 'spotify', 'linkedin'] as $k) { $v = trim((string)input('soc_' . $k)); if ($v !== '' && preg_match('~^https?://~i', $v)) $soc[$k] = mb_substr($v, 0, 255); }
            $data['socials'] = $soc ? json_encode($soc) : null;
        }
        if (!$data['name'] || !is_email((string)$data['email'])) back(['error' => 'Nom et e-mail de contact obligatoires.']);
        update('organizers', $data, 'id = ?', [$org['id']]);
        Audit::log('org.updated', 'Profil de l\'organisation');
        redirect('/pro/organisation', ['success' => 'C\'est enregistré.']);
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
    $invoices = all('SELECT * FROM commission_invoices WHERE organizer_id = ? ORDER BY period DESC LIMIT 24', [$org['id']]);
    // TVA collectée par mois (12 derniers mois)
    $vatMonths = [];
    foreach (Vat::orders($org, date('Y-m-01 00:00:00', strtotime('-11 months')), date('Y-m-d 23:59:59')) as $o) {
        $m = substr((string)($o['paid_at'] ?: $o['created_at']), 0, 7);
        foreach ($o['vat'] as $k => $v) { if ($k === 'don') continue; $vatMonths[$m][$k]['ttc'] = ($vatMonths[$m][$k]['ttc'] ?? 0) + $v['ttc']; $vatMonths[$m][$k]['vat'] = ($vatMonths[$m][$k]['vat'] ?? 0) + $v['vat']; $vatMonths[$m][$k]['ht'] = ($vatMonths[$m][$k]['ht'] ?? 0) + $v['ht']; }
    }
    krsort($vatMonths);
    $terms = Billing::terms($org);
    $charges = all('SELECT * FROM org_charges WHERE organizer_id = ? ORDER BY created_at DESC LIMIT 100', [$org['id']]);
    return pro_page('Finances', 'finances', compact('org', 'balance', 'payouts', 'byEvent', 'demo', 'pct', 'fixed', 'invoices', 'vatMonths', 'terms', 'charges'), 'finances');
}

/** Export comptable : détail des ventes (CSV) ou écritures au format FEC. */
function finances_export(): never
{
    $org = require_org(['owner']);
    $from = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('du')) ? (string)input('du') : date('Y-01-01');
    $to = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('au')) ? (string)input('au') : date('Y-m-d');
    $orders = Vat::orders($org, $from . ' 00:00:00', $to . ' 23:59:59');
    $amt = fn(int $c) => number_format($c / 100, 2, ',', '');
    if (input('format') === 'fec') {
        // Format FEC (article A47 A-1 du LPF), séparateur tabulation. Comptes par défaut, à adapter par ton comptable.
        $head = ['JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate', 'CompteNum', 'CompteLib', 'CompAuxNum', 'CompAuxLib', 'PieceRef', 'PieceDate', 'EcritureLib', 'Debit', 'Credit', 'EcritureLet', 'DateLet', 'ValidDate', 'Montantdevise', 'Idevise'];
        $rows = [$head]; $n = 0;
        $add = function (string $num, string $date, string $acc, string $accLib, string $ref, string $lib, int $debit, int $credit) use (&$rows, $amt) {
            $rows[] = ['VT', 'Ventes billetterie', $num, $date, $acc, $accLib, '', '', $ref, $date, mb_substr($lib, 0, 100), $amt($debit), $amt($credit), '', '', $date, '', ''];
        };
        foreach ($orders as $o) {
            $n++; $num = 'VT' . str_pad((string)$n, 6, '0', STR_PAD_LEFT); $d = date('Ymd', strtotime((string)($o['paid_at'] ?: $o['created_at'])));
            $online = in_array($o['method'], ['online', 'partner'], true);
            $comm = $online && $o['fee_mode'] !== 'buyer' ? (int)$o['commission'] : 0;
            $gross = array_sum(array_column($o['vat'], 'ttc'));
            $lib = 'Commande ' . $o['ref'] . ' - ' . $o['ev_name'];
            $add($num, $d, $online ? '467000' : '530000', $online ? 'Plateforme de billetterie' : 'Caisse', $o['ref'], $lib, $gross - $comm, 0);
            if ($comm) $add($num, $d, '622600', 'Commissions billetterie', $o['ref'], $lib, $comm, 0);
            foreach ($o['vat'] as $k => $v) {
                if ($k === 'don') { $add($num, $d, '754000', 'Dons', $o['ref'], $lib, 0, $v['ttc']); continue; }
                $add($num, $d, '706000', 'Ventes billetterie' . ($k === 'none' ? '' : ' ' . Vat::label((float)$k)), $o['ref'], $lib, 0, $v['ht']);
                if ($v['vat']) $add($num, $d, '445710', 'TVA collectée ' . Vat::label((float)$k), $o['ref'], $lib, 0, $v['vat']);
            }
            if ((int)$o['refunded_amount']) { $n++; $rn = 'VT' . str_pad((string)$n, 6, '0', STR_PAD_LEFT); $add($rn, $d, '709000', 'Remboursements', $o['ref'], 'Remboursement ' . $o['ref'], (int)$o['refunded_amount'], 0); $add($rn, $d, $online ? '467000' : '530000', $online ? 'Plateforme de billetterie' : 'Caisse', $o['ref'], 'Remboursement ' . $o['ref'], 0, (int)$o['refunded_amount']); }
        }
        foreach (all('SELECT * FROM payouts WHERE organizer_id = ? AND created_at BETWEEN ? AND ? ORDER BY created_at', [$org['id'], $from . ' 00:00:00', $to . ' 23:59:59']) as $p) {
            $n++; $num = 'BQ' . str_pad((string)$n, 6, '0', STR_PAD_LEFT); $d = date('Ymd', strtotime($p['created_at']));
            $rows[] = ['BQ', 'Banque', $num, $d, '512000', 'Banque', '', '', (string)($p['reference'] ?: $p['id']), $d, 'Reversement plateforme', $amt((int)$p['amount']), '0,00', '', '', $d, '', ''];
            $rows[] = ['BQ', 'Banque', $num, $d, '467000', 'Plateforme de billetterie', '', '', (string)($p['reference'] ?: $p['id']), $d, 'Reversement plateforme', '0,00', $amt((int)$p['amount']), '', '', $d, '', ''];
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('~\D~', '', (string)$org['siret']) . 'FEC' . str_replace('-', '', $to) . '.txt"');
        foreach ($rows as $r) echo implode("\t", array_map(fn($x) => str_replace(["\t", "\n", "\r"], ' ', (string)$x), $r)) . "\r\n";
        exit;
    }
    $rows = [['Date', 'Commande', 'Événement', 'Acheteur', 'Vente', 'Paiement', 'Taux TVA', 'HT', 'TVA', 'TTC', 'Frais acheteur', 'Commission', 'Remboursé', 'Statut']];
    foreach ($orders as $o) foreach ($o['vat'] as $k => $v) $rows[] = [substr((string)($o['paid_at'] ?: $o['created_at']), 0, 16), $o['ref'], $o['ev_name'], trim($o['first_name'] . ' ' . $o['last_name']), method_label($o['method']),
        provider_label($o['provider'] ?: $o['pay_method']), $k === 'don' ? 'Don' : ($k === 'none' ? 'Non assujetti' : Vat::label((float)$k)), $amt($v['ht']), $amt($v['vat']), $amt($v['ttc']),
        $o['fee_mode'] === 'buyer' ? $amt((int)$o['fees']) : '0,00', $amt((int)$o['commission']), $amt((int)$o['refunded_amount']), $o['status'] === 'paid' ? 'Payée' : 'Remboursée'];
    csv_download('ventes-' . $from . '-' . $to . '.csv', $rows);
}

/** 2.2 : module réservé à certaines formules → page explicative au lieu de l'écran. */
function require_module(string $key): void
{
    $org = current_org();
    if (!$org || !class_exists('Billing') || Billing::allows($org, $key)) return;
    $plan = Billing::plan($org);
    echo pro_page(Billing::MODULES[$key] ?? 'Module', 'upsell', ['key' => $key, 'plan' => $plan, 'plans' => Billing::plansWith($key)], $key);
    exit;
}

/** 2.2 : limite d'événements actifs par organisateur (Super-admin › Réglages › Plateforme). */
function event_limit_check(int $orgId): void
{
    $max = (int)setting('max_events', '0');
    if ($max > 0 && !is_super() && (int)val("SELECT COUNT(*) FROM events WHERE organizer_id = ? AND COALESCE(ends_at, starts_at) >= NOW()", [$orgId]) >= $max)
        back(['error' => 'Tu as atteint la limite de ' . $max . ' événements à venir. Écris-nous depuis « Aide & support » pour l\'augmenter.']);
}
