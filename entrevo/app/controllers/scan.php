<?php
declare(strict_types=1);

/** Événement accessible par l'utilisateur connecté (contrôleur, gestionnaire, responsable, super-admin en consultation). */
function scan_event(string $id): array
{
    $u = require_login(['owner', 'manager', 'scanner']);
    $org = current_org();
    $ev = $org ? one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [(int)$id, $org['id']]) : null;
    if (!$ev) json_out(['error' => 'Événement introuvable ou non autorisé.'], 404);
    if ($org['status'] === 'suspended' && $u['role'] !== 'superadmin') json_out(['error' => 'Compte organisateur suspendu.'], 403);
    return $ev;
}

/** Corps JSON de la requête. */
function json_body(): array
{
    $d = json_decode((string)file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}

function app(): string
{
    $u = user();
    return view('scan/app', ['u' => $u, 'org' => $u ? current_org() : null]);
}

function manifest(): never
{
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo json_encode([
        'name' => setting('platform_name') . ' Scan',
        'short_name' => 'Scan',
        'description' => 'Contrôle des billets à l\'entrée',
        'start_url' => url('/scan'),
        'scope' => url('/'),
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#0c0c0c',
        'theme_color' => '#0c0c0c',
        'icons' => [
            ['src' => url('assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => url('assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => url('assets/img/icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_me(): never
{
    $u = require_login(['owner', 'manager', 'scanner']);
    $org = current_org();
    if (!$org) json_out(['error' => 'Aucun organisateur sélectionné.'], 403);
    $events = all("SELECT id, name, starts_at, ends_at, venue, image, status FROM events
                   WHERE organizer_id = ? AND status <> 'draft' AND COALESCE(ends_at, starts_at) >= NOW() - INTERVAL 3 DAY
                   ORDER BY starts_at LIMIT 50", [$org['id']]);
    foreach ($events as &$e) {
        $e['id'] = (int)$e['id'];
        $e['date'] = fr_date($e['starts_at']);
        $e['sold'] = (int)val("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status = 'valid'", [$e['id']]);
        $e['image'] = $e['image'] ? url($e['image']) : null;
    }
    unset($e);
    json_out(['user' => ['id' => (int)$u['id'], 'name' => $u['name'], 'role' => $u['role']], 'org' => $org['name'],
        'csrf' => csrf_token(), 'events' => $events, 'server_time' => date('c')]);
}

/** Données de l'événement pour le mode hors ligne (complètes, ou seulement les changements depuis « since »). */
function api_data(string $id): never
{
    $ev = scan_event($id);
    $since = (string)input('since');
    $params = [$ev['id']];
    $sql = 'SELECT code, first_name, last_name, rate_id, rate_name, status, inside, entries, checked_in_at, updated_at, session_id, max_entries, per_day, population_id, type, seat, last_scan_at FROM tickets WHERE event_id = ?';
    if ($since !== '' && strtotime($since)) { $sql .= ' AND updated_at >= ?'; $params[] = date('Y-m-d H:i:s', strtotime($since) - 5); }
    $rows = all($sql, $params);
    // [code, nom, tarif, valide, sur place, entrées, 1re entrée, séance, entrées max, 1/jour, population, type, libellé tarif, siège, dernier passage]
    $tickets = array_map(fn($t) => [$t['code'], trim($t['first_name'] . ' ' . $t['last_name']), (int)$t['rate_id'], $t['status'] === 'valid' ? 1 : 0,
        (int)$t['inside'], (int)$t['entries'], $t['checked_in_at'], $t['session_id'] ? (int)$t['session_id'] : 0, (int)$t['max_entries'], (int)$t['per_day'],
        $t['population_id'] ? (int)$t['population_id'] : 0, $t['type'], $t['rate_name'], $t['seat'], $t['last_scan_at']], $rows);
    $pops = [];
    foreach (all('SELECT * FROM populations WHERE event_id = ?', [$ev['id']]) as $p) $pops[(int)$p['id']] = ['name' => $p['name'], 'zones' => array_values(array_filter(array_map('intval', explode(',', (string)$p['access_point_ids']))))];
    $sessions = array_map(fn($x) => ['id' => (int)$x['id'], 'label' => fr_date($x['starts_at']) . ($x['label'] ? ' · ' . $x['label'] : ''), 'start' => $x['starts_at']], Tickets::sessions((int)$ev['id']));
    $aps = array_map(fn($a) => ['id' => (int)$a['id'], 'name' => $a['name'], 'active' => (int)$a['active'], 'opens' => $a['opens_at'] ?? null, 'closes' => $a['closes_at'] ?? null,
        'rates' => array_values(array_filter(array_map('intval', explode(',', (string)$a['rate_ids']))))],
        all('SELECT * FROM access_points WHERE event_id = ? ORDER BY id', [$ev['id']]));
    $rates = []; $rules = [];
    foreach (all('SELECT id, name, scan_note, scan_color, min_age FROM rates WHERE event_id = ?', [$ev['id']]) as $r) {
        $rates[(int)$r['id']] = $r['name'];
        $age = max((int)($ev['min_age'] ?? 0), (int)($r['min_age'] ?? 0));
        $note = trim(($age ? '' . $age . ' ans et + : vérifier l\'âge. ' : '') . (string)$r['scan_note']);
        if ($note !== '' || $r['scan_color']) $rules[(int)$r['id']] = ['note' => $note, 'color' => (string)$r['scan_color']];
    }
    json_out([
        'event' => ['id' => (int)$ev['id'], 'name' => $ev['name'], 'date' => fr_date($ev['starts_at']), 'reentry' => (int)$ev['allow_reentry']],
        'access_points' => $aps, 'rates' => $rates, 'tickets' => $tickets, 'full' => $since === '', 'sessions' => $sessions, 'populations' => $pops,
        'server_time' => date('Y-m-d H:i:s'), 'stats' => scan_stats((int)$ev['id']), 'rules' => (object)$rules, 'message' => scan_last_message((int)$ev['id']),
    ]);
}

function scan_stats(int $eventId): array
{
    $r = one("SELECT COUNT(*) sold, SUM(entries > 0) checked, SUM(inside = 1) inside FROM tickets WHERE event_id = ? AND status = 'valid' AND type <> 'accreditation'", [$eventId]);
    return ['sold' => (int)$r['sold'], 'checked' => (int)$r['checked'], 'inside' => (int)$r['inside']];
}

function api_check(string $id): never
{
    $ev = scan_event($id);
    $b = json_body();
    $r = Tickets::scan(['event' => $ev, 'code' => (string)($b['code'] ?? ''), 'access_point_id' => $b['ap'] ?? null, 'session_id' => $b['session'] ?? null,
        'direction' => $b['direction'] ?? 'in', 'user' => user(), 'uuid' => (string)($b['uuid'] ?? ''), 'device' => (string)($b['device'] ?? '')]);
    $r['stats'] = scan_stats((int)$ev['id']);
    json_out($r);
}

/** Envoi groupé des passages enregistrés hors ligne. */
function api_sync(string $id): never
{
    $ev = scan_event($id);
    $b = json_body();
    $out = [];
    foreach (array_slice((array)($b['scans'] ?? []), 0, 500) as $s) {
        if (!is_array($s) || empty($s['uuid'])) continue;
        $r = Tickets::scan(['event' => $ev, 'code' => (string)($s['code'] ?? ''), 'access_point_id' => $s['ap'] ?? null, 'session_id' => $s['session'] ?? null,
            'direction' => $s['direction'] ?? 'in', 'user' => user(), 'uuid' => (string)$s['uuid'], 'device' => (string)($s['device'] ?? ''),
            'scanned_at' => $s['at'] ?? null, 'offline' => true]);
        $out[] = ['uuid' => $s['uuid'], 'result' => $r['result'], 'local' => $s['local'] ?? null];
    }
    json_out(['results' => $out, 'stats' => scan_stats((int)$ev['id'])]);
}

function api_stats(string $id): never
{
    $ev = scan_event($id);
    json_out(scan_stats((int)$ev['id']));
}

function api_undo(string $id): never
{
    $ev = scan_event($id);
    $b = json_body();
    $s = one('SELECT id FROM scans WHERE uuid = ? AND event_id = ?', [(string)($b['uuid'] ?? ''), $ev['id']]);
    $ok = $s && Tickets::undoScan((int)$s['id'], (int)$ev['id']);
    json_out(['ok' => $ok, 'stats' => scan_stats((int)$ev['id'])]);
}

/** Dernier message du PC (organisateur → contrôleurs), valable 3 heures. */
function scan_last_message(int $eventId): ?array
{
    $m = one('SELECT id, body, level, created_at FROM scan_messages WHERE event_id = ? AND created_at >= NOW() - INTERVAL 3 HOUR ORDER BY id DESC LIMIT 1', [$eventId]);
    return $m ? ['id' => (int)$m['id'], 'body' => $m['body'], 'level' => $m['level'], 'at' => substr($m['created_at'], 11, 5)] : null;
}

/** Signal de vie de l'appareil (toutes les 30 s) : état, file d'attente, batterie. Renvoie stats, message du PC et répartition. */
function api_ping(string $id): never
{
    $ev = scan_event($id);
    $b = json_body();
    $dev = preg_replace('~[^A-Za-z0-9_-]~', '', (string)($b['device'] ?? ''));
    if ($dev !== '') {
        q('INSERT INTO scan_devices (device, event_id, user_id, ap_name, direction, pending, battery, scans, last_seen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
           ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ap_name = VALUES(ap_name), direction = VALUES(direction), pending = VALUES(pending), battery = VALUES(battery), scans = VALUES(scans), last_seen = NOW()',
            [mb_substr($dev, 0, 40), $ev['id'], user()['id'], mb_substr((string)($b['ap'] ?? ''), 0, 120) ?: null, ($b['dir'] ?? '') === 'out' ? 'out' : 'in',
             max(0, (int)($b['pending'] ?? 0)), isset($b['battery']) && $b['battery'] !== null ? max(0, min(100, (int)$b['battery'])) : null, max(0, (int)($b['scans'] ?? 0))]);
    }
    $perAp = all("SELECT COALESCE(a.name, 'Sans porte') name, SUM(s.direction = 'in') n_in, SUM(s.direction = 'out') n_out FROM scans s LEFT JOIN access_points a ON a.id = s.access_point_id
        WHERE s.event_id = ? AND s.result IN ('ok','exit') AND s.undone = 0 AND s.scanned_at >= NOW() - INTERVAL 18 HOUR GROUP BY name ORDER BY n_in DESC", [$ev['id']]);
    $perHour = all("SELECT DATE_FORMAT(scanned_at, '%H') h, COUNT(*) n FROM scans WHERE event_id = ? AND result = 'ok' AND undone = 0 AND scanned_at >= NOW() - INTERVAL 6 HOUR GROUP BY h ORDER BY MIN(scanned_at)", [$ev['id']]);
    json_out(['stats' => scan_stats((int)$ev['id']), 'message' => scan_last_message((int)$ev['id']),
        'per_ap' => array_map(fn($r) => ['name' => $r['name'], 'in' => (int)$r['n_in'], 'out' => (int)$r['n_out']], $perAp),
        'per_hour' => array_map(fn($r) => ['h' => $r['h'] . 'h', 'n' => (int)$r['n']], $perHour)]);
}

function health(): never
{
    // utilisée après une mise à jour pour vérifier que l'application répond
    $ok = true;
    try { val('SELECT 1'); } catch (Throwable $e) { $ok = false; }
    json_out(['ok' => $ok, 'version' => app_version()], $ok ? 200 : 500);
}
