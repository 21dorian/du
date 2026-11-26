<?php
declare(strict_types=1);

// API REST v1 pour les organisateurs. Authentification : en-tête « Authorization: Bearer <clé API> ».

function api_org(): array
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('getallheaders')) { foreach (getallheaders() as $k => $v) if (strcasecmp($k, 'Authorization') === 0) $h = $v; }
    $key = preg_match('~^Bearer\s+(\S+)$~i', trim($h), $m) ? $m[1] : (string)($_GET['api_key'] ?? '');
    $org = $key !== '' ? one("SELECT * FROM organizers WHERE api_key = ? AND status = 'active'", [$key]) : null;
    if (!$org) json_out(['error' => 'Clé API invalide ou manquante.'], 401);
    return $org;
}

function api_event(array $org, int $id): array
{
    $ev = one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [$id, $org['id']]);
    if (!$ev) json_out(['error' => 'Événement introuvable.'], 404);
    return $ev;
}

function api_page(): array
{
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
    $page = max(1, (int)($_GET['page'] ?? 1));
    return [$limit, ($page - 1) * $limit, $page];
}

function index(string $resource): never
{
    $org = api_org();
    [$limit, $offset, $page] = api_page();
    $since = !empty($_GET['since']) && strtotime((string)$_GET['since']) ? date('Y-m-d H:i:s', strtotime((string)$_GET['since'])) : null;
    switch ($resource) {
        case 'events':
            $rows = all("SELECT id, slug, name, status, starts_at, ends_at, venue, city, capacity, category FROM events WHERE organizer_id = ? ORDER BY starts_at DESC LIMIT $limit OFFSET $offset", [$org['id']]);
            foreach ($rows as &$r) { $r['url'] = abs_url('/e/' . $r['slug']); $st = Tickets::stats((int)$r['id']); $r['sold'] = $st['sold']; $r['revenue'] = $st['revenue']; $r['checked_in'] = $st['checked']; }
            json_out(['data' => $rows, 'page' => $page]);
        case 'orders':
            $sql = 'SELECT * FROM orders WHERE organizer_id = ? AND (channel IS NULL OR channel <> \'systeme\')';
            $p = [$org['id']];
            if (!empty($_GET['event'])) { $sql .= ' AND event_id = ?'; $p[] = (int)$_GET['event']; }
            if (!empty($_GET['status'])) { $sql .= ' AND status = ?'; $p[] = (string)$_GET['status']; }
            if ($since) { $sql .= ' AND created_at >= ?'; $p[] = $since; }
            $rows = array_map(fn($o) => Hooks::orderPayload($o), all($sql . " ORDER BY id DESC LIMIT $limit OFFSET $offset", $p));
            json_out(['data' => $rows, 'page' => $page]);
        case 'tickets':
            if (empty($_GET['event'])) json_out(['error' => 'Paramètre « event » obligatoire.'], 400);
            $ev = api_event($org, (int)$_GET['event']);
            $sql = 'SELECT t.code, t.type, t.status, t.first_name, t.last_name, t.company, t.rate_name, t.price, t.seat, t.session_id, t.entries, t.checked_in_at, t.answers, o.ref order_ref, o.email
                    FROM tickets t JOIN orders o ON o.id = t.order_id WHERE t.event_id = ?';
            $p = [$ev['id']];
            if ($since) { $sql .= ' AND t.updated_at >= ?'; $p[] = $since; }
            $rows = all($sql . " ORDER BY t.id LIMIT $limit OFFSET $offset", $p);
            foreach ($rows as &$r) $r['answers'] = json_decode((string)$r['answers'], true) ?: [];
            json_out(['data' => $rows, 'page' => $page]);
    }
    json_out(['error' => 'Ressource inconnue.'], 404);
}

function show(string $resource, string $id): never
{
    $org = api_org();
    if ($resource === 'samples') { // 4.0 : exemple de données pour le mappage dans Zapier / Make
        $o = one("SELECT * FROM orders WHERE organizer_id = ? AND status = 'paid' ORDER BY id DESC LIMIT 1", [$org['id']]);
        json_out([$o ? Hooks::orderPayload($o) : ['ref' => 'EXEMPLE1', 'status' => 'paid', 'email' => 'exemple@exemple.fr', 'total' => 2500, 'tickets' => []]]);
    }
    if ($resource === 'events') {
        $ev = api_event($org, (int)$id);
        json_out(['data' => [
            'id' => (int)$ev['id'], 'name' => $ev['name'], 'status' => $ev['status'], 'starts_at' => $ev['starts_at'], 'ends_at' => $ev['ends_at'], 'url' => abs_url('/e/' . $ev['slug']),
            'rates' => all('SELECT id, name, price, quota, price_mode, pack_size, active FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]),
            'sessions' => all('SELECT id, label, starts_at, ends_at, capacity, active FROM sessions WHERE event_id = ? ORDER BY starts_at', [$ev['id']]),
            'stats' => Tickets::stats((int)$ev['id']),
        ]]);
    }
    if ($resource === 'orders') {
        $o = one('SELECT * FROM orders WHERE ref = ? AND organizer_id = ?', [$id, $org['id']]);
        if (!$o) json_out(['error' => 'Commande introuvable.'], 404);
        json_out(['data' => Hooks::orderPayload($o)]);
    }
    json_out(['error' => 'Ressource inconnue.'], 404);
}

/** POST /api/v1/invitations : {event_id, rate_id, session_id?, first_name, last_name, email, qty?, send?} */
function create(string $resource): never
{
    $org = api_org();
    $b = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
    // 4.0 : abonnements REST (Zapier, Make, n8n) : un webhook créé et supprimé par l'outil lui-même
    if ($resource === 'hooks') {
        $url = trim((string)($b['url'] ?? $b['target_url'] ?? $b['hookUrl'] ?? ''));
        $events = array_values(array_intersect((array)($b['events'] ?? [$b['event'] ?? 'order.paid']), array_keys(Hooks::EVENTS)));
        if (!preg_match('~^https://~i', $url) || !$events) json_out(['error' => 'Il faut une adresse https et un événement parmi : ' . implode(', ', array_keys(Hooks::EVENTS))], 422);
        $src = preg_match('~zapier~i', $url) ? 'Zapier' : (preg_match('~make\.com|integromat~i', $url) ? 'Make' : (preg_match('~n8n~i', $url) ? 'n8n' : 'REST'));
        $id = insert('webhook_endpoints', ['organizer_id' => $org['id'], 'url' => mb_substr($url, 0, 500), 'secret' => bin2hex(random_bytes(16)), 'events' => implode(',', $events), 'label' => $src . ' (automatique)', 'active' => 1, 'created_at' => now()]);
        json_out(['id' => (int)$id, 'events' => $events], 201);
    }
    if ($resource !== 'invitations') json_out(['error' => 'Ressource inconnue.'], 404);
    $ev = api_event($org, (int)($b['event_id'] ?? 0));
    $rate = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [(int)($b['rate_id'] ?? 0), $ev['id']]);
    if (!$rate) json_out(['error' => 'Tarif introuvable.'], 422);
    $session = !empty($b['session_id']) ? one('SELECT * FROM sessions WHERE id = ? AND event_id = ?', [(int)$b['session_id'], $ev['id']]) : null;
    $email = trim((string)($b['email'] ?? ''));
    if ($email !== '' && !is_email($email)) json_out(['error' => 'E-mail invalide.'], 422);
    $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'session' => $session, 'quantities' => [$rate['id'] => max(1, min(50, (int)($b['qty'] ?? 1)))],
        'method' => 'invitation', 'channel' => 'api', 'buyer' => ['first_name' => (string)($b['first_name'] ?? ''), 'last_name' => (string)($b['last_name'] ?? ''), 'email' => $email]]);
    if (!empty($r['errors'])) json_out(['error' => implode(' ', $r['errors'])], 422);
    $o = Tickets::confirm((int)$r['order']['id'], [], $email !== '' && !empty($b['send']));
    json_out(['data' => Hooks::orderPayload($o)], 201);
}

function hook_delete(string $id): never
{
    $org = api_org();
    $n = q("DELETE FROM webhook_endpoints WHERE id = ? AND organizer_id = ? AND label LIKE '%(automatique)'", [(int)$id, $org['id']])->rowCount();
    json_out($n ? ['ok' => true] : ['error' => 'Abonnement introuvable.'], $n ? 200 : 404);
}
