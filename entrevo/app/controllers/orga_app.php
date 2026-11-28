<?php
declare(strict_types=1);

/* 4.3 : appli organisateur (web installable + APK « Entrevo Pro ») : ventes en direct, alertes, vente rapide, message express, ouverture des ventes. */

function oa_events(int $orgId): array
{
    return all("SELECT e.id, e.name, e.slug, e.starts_at, e.ends_at, e.status, e.capacity FROM events e WHERE e.organizer_id = ? AND e.status IN ('published','closed')
        AND COALESCE(e.ends_at, e.starts_at) >= NOW() - INTERVAL 2 DAY ORDER BY e.starts_at LIMIT 20", [$orgId])
        ?: all("SELECT id, name, slug, starts_at, ends_at, status, capacity FROM events WHERE organizer_id = ? ORDER BY starts_at DESC LIMIT 5", [$orgId]);
}

function oa_event(array $org): ?array
{
    $id = (int)input('e');
    if ($id) return one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [$id, $org['id']]);
    $list = oa_events((int)$org['id']);
    return $list ? one('SELECT * FROM events WHERE id = ?', [$list[0]['id']]) : null;
}

function orga_app(): string
{
    $org = require_org();
    $ev = oa_event($org);
    $events = oa_events((int)$org['id']);
    $rates = $ev ? all('SELECT id, name, price FROM rates WHERE event_id = ? AND active = 1 ORDER BY sort, id', [$ev['id']]) : [];
    $u = user();
    return view('pro/orga_app', compact('org', 'ev', 'events', 'rates', 'u'));
}

/** Données en direct (toutes les 15 s). */
function orga_app_data(): never
{
    $org = require_org();
    $ev = oa_event($org);
    $oid = (int)$org['id'];
    $alerts = all('SELECT id, kind, title, body, url, created_at FROM app_alerts WHERE organizer_id = ? AND id > ? ORDER BY id DESC LIMIT 20', [$oid, (int)input('since')]);
    $out = ['alerts' => array_map(fn($a) => ['id' => (int)$a['id'], 'kind' => $a['kind'], 'title' => $a['title'], 'body' => (string)$a['body'], 'url' => url((string)$a['url']), 'at' => substr($a['created_at'], 11, 5)], $alerts),
        'today' => one("SELECT COUNT(*) orders, COALESCE(SUM(total), 0) total FROM orders WHERE organizer_id = ? AND status = 'paid' AND paid_at >= CURDATE()", [$oid])];
    if ($ev) {
        $evId = (int)$ev['id'];
        $k = one("SELECT COUNT(*) sold, SUM(inside = 1) inside, SUM(entries > 0) came FROM tickets WHERE event_id = ? AND status = 'valid'", [$evId]);
        $money = one("SELECT COALESCE(SUM(total - refunded_amount), 0) net, COALESCE(SUM(CASE WHEN paid_at >= CURDATE() THEN total END), 0) today, SUM(paid_at >= CURDATE()) today_n FROM orders WHERE event_id = ? AND status IN ('paid','refunded')", [$evId]);
        $week = all("SELECT DATE(t.created_at) d, COUNT(*) n FROM tickets t WHERE t.event_id = ? AND t.status = 'valid' AND t.created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY d", [$evId]);
        $days = []; for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = 0;
        foreach ($week as $w) if (isset($days[$w['d']])) $days[$w['d']] = (int)$w['n'];
        $out['event'] = ['id' => $evId, 'name' => $ev['name'], 'status' => $ev['status'], 'date' => fr_date($ev['starts_at']), 'capacity' => (int)$ev['capacity'], 'url' => abs_url('/e/' . $ev['slug']),
            'sold' => (int)$k['sold'], 'inside' => (int)$k['inside'], 'came' => (int)$k['came'], 'net' => money((int)$money['net']), 'today' => money((int)$money['today']), 'today_n' => (int)$money['today_n'],
            'days' => array_values($days),
            'rates' => array_map(fn($r) => ['name' => $r['name'], 'sold' => (int)$r['sold'], 'quota' => (int)$r['quota'], 'price' => money((int)$r['price']), 'active' => (int)$r['active']],
                all("SELECT r.name, r.quota, r.price, r.active, (SELECT COUNT(*) FROM tickets t WHERE t.rate_id = r.id AND t.status = 'valid') sold FROM rates r WHERE r.event_id = ? ORDER BY r.sort, r.id", [$evId])),
            'orders' => array_map(fn($o) => ['id' => (int)$o['id'], 'who' => trim($o['first_name'] . ' ' . $o['last_name']), 'n' => (int)$o['n'], 'total' => money((int)$o['total']), 'at' => substr((string)$o['paid_at'], 11, 5), 'channel' => (string)$o['channel'], 'url' => url('/pro/commandes/' . $o['id'])],
                all("SELECT o.id, o.first_name, o.last_name, o.total, o.paid_at, o.channel, (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id) n FROM orders o WHERE o.event_id = ? AND o.status = 'paid' ORDER BY o.paid_at DESC LIMIT 12", [$evId]))];
    }
    json_out($out);
}

/** Vente rapide (guichet de poche). */
function orga_app_sale(): never
{
    $org = require_org();
    $ev = oa_event($org);
    if (!$ev) json_out(['ok' => false, 'error' => 'Aucun événement.'], 422);
    $q = [];
    foreach ((array)($_POST['qty'] ?? []) as $rid => $n) if ((int)$n > 0) $q[(int)$rid] = min(50, (int)$n);
    if (!$q) json_out(['ok' => false, 'error' => 'Choisis au moins un billet.'], 422);
    $pm = (string)input('pay_method');
    $method = $pm === 'offert' ? 'invitation' : 'guichet';
    $buyer = ['first_name' => trim((string)input('first_name')), 'last_name' => trim((string)input('last_name')) ?: 'Vente guichet', 'email' => trim((string)input('email')), 'phone' => ''];
    if ($buyer['email'] !== '' && !is_email($buyer['email'])) json_out(['ok' => false, 'error' => 'Cette adresse e-mail a l\'air bizarre.'], 422);
    $session = Tickets::hasSessions((int)$ev['id']) ? (one('SELECT * FROM sessions WHERE event_id = ? AND starts_at >= NOW() - INTERVAL 6 HOUR ORDER BY starts_at LIMIT 1', [$ev['id']]) ?: null) : null;
    $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'session' => $session, 'seats' => [], 'quantities' => $q, 'buyer' => $buyer, 'method' => $method, 'channel' => 'appli',
        'pay_method' => $method === 'guichet' ? (in_array($pm, ['especes', 'cb', 'cheque'], true) ? $pm : 'especes') : null, 'note' => 'Appli organisateur', 'user_id' => user()['id']]);
    if (!empty($r['errors'])) json_out(['ok' => false, 'error' => implode(' ', $r['errors'])], 422);
    $send = $buyer['email'] !== '';
    $o = Tickets::confirm((int)$r['order']['id'], [], $send);
    Audit::log('box.sale', $o['ref'] . ' · ' . money((int)$o['total']) . ' (appli)', (int)$ev['id'], (int)$o['id']);
    json_out(['ok' => true, 'ref' => $o['ref'], 'total' => money((int)$o['total']), 'tickets' => (int)val('SELECT COUNT(*) FROM tickets WHERE order_id = ?', [$o['id']]),
        'pdf' => url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token']]), 'detail' => url('/pro/commandes/' . $o['id']), 'sent' => $send]);
}

/** Message express à tous les participants (e-mail). */
function orga_app_message(): never
{
    $org = require_org();
    $ev = oa_event($org);
    require_once APP . '/controllers/pro_plus.php';
    $subject = mb_substr(trim((string)input('subject')), 0, 190); $body = trim((string)input('body'));
    if (!$ev || $subject === '' || $body === '') json_out(['ok' => false, 'error' => 'Écris un objet et un message.'], 422);
    $f = ['rate' => 0, 'session' => 0, 'who' => input('who') === 'in' ? 'in' : 'all'];
    $emails = message_recipients($ev, $f);
    if (!$emails) json_out(['ok' => false, 'error' => 'Personne à prévenir pour l\'instant.'], 422);
    @set_time_limit(600);
    $sent = 0; $failed = 0;
    foreach ($emails as $mail => $first) { try { Mailer::send($mail, $subject, message_html($ev, $body, $first)); $sent++; } catch (Throwable $e) { $failed++; log_line('emails', 'Message appli : ' . $e->getMessage()); } }
    insert('messages', ['event_id' => $ev['id'], 'subject' => $subject, 'body' => $body, 'filters' => json_encode($f), 'recipients' => count($emails), 'sent' => $sent, 'failed' => $failed, 'user_id' => user()['id'], 'created_at' => now()]);
    Audit::log('message.sent', $sent . ' destinataire(s) (appli) · ' . $subject, (int)$ev['id']);
    json_out(['ok' => $sent > 0, 'sent' => $sent, 'failed' => $failed, 'error' => $sent ? null : 'Aucun e-mail n\'est parti : vérifie les réglages d\'envoi.']);
}

/** Ouvrir / clôturer les ventes. */
function orga_app_status(): never
{
    $org = require_org();
    $ev = oa_event($org);
    $st = input('status') === 'closed' ? 'closed' : 'published';
    if (!$ev || $ev['status'] === 'draft') json_out(['ok' => false, 'error' => 'Publie d\'abord l\'événement depuis l\'espace complet.'], 422);
    update('events', ['status' => $st], 'id = ?', [$ev['id']]);
    Audit::log('event.status', $ev['name'] . ' → ' . $st . ' (appli)', (int)$ev['id']);
    json_out(['ok' => true, 'status' => $st]);
}

/** Abonnement aux notifications et préférences. */
function orga_app_push(): never
{
    $org = require_org();
    $a = (string)input('action');
    $b = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
    $sub = $b['subscription'] ?? null;
    $endpoint = (string)($sub['endpoint'] ?? ($b['endpoint'] ?? ''));
    $hash = $endpoint !== '' ? sha1($endpoint) : '';
    if ($a === 'key') json_out(['key' => WebPush::publicKey()]);
    if ($a === 'subscribe') {
        if (!preg_match('~^https://~', $endpoint) || empty($sub['keys']['p256dh']) || empty($sub['keys']['auth'])) json_out(['ok' => false, 'error' => 'Abonnement invalide.'], 422);
        $prefs = json_encode(array_intersect_key(array_merge(AppPush::DEFAULT_PREFS, (array)($b['prefs'] ?? [])), AppPush::DEFAULT_PREFS));
        q('INSERT INTO push_subscriptions (user_id, organizer_id, endpoint, endpoint_hash, p256dh, auth, prefs, device, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
           ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), organizer_id = VALUES(organizer_id), p256dh = VALUES(p256dh), auth = VALUES(auth), fails = 0',
            [user()['id'], $org['id'], mb_substr($endpoint, 0, 700), $hash, (string)$sub['keys']['p256dh'], (string)$sub['keys']['auth'], $prefs, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120)]);
        json_out(['ok' => true, 'prefs' => AppPush::prefs($prefs)]);
    }
    $s = $hash ? one('SELECT * FROM push_subscriptions WHERE endpoint_hash = ? AND organizer_id = ?', [$hash, $org['id']]) : null;
    if ($a === 'prefs' && $s) {
        $p = AppPush::prefs($s['prefs']);
        foreach ((array)($b['prefs'] ?? []) as $k => $v) if (array_key_exists($k, AppPush::DEFAULT_PREFS)) $p[$k] = $k === 'step' ? max(10, min(10000, (int)$v)) : (int)(bool)$v;
        update('push_subscriptions', ['prefs' => json_encode($p)], 'id = ?', [$s['id']]);
        json_out(['ok' => true, 'prefs' => $p]);
    }
    if ($a === 'unsubscribe' && $s) { q('DELETE FROM push_subscriptions WHERE id = ?', [$s['id']]); json_out(['ok' => true]); }
    if ($a === 'test' && $s) {
        try { $code = WebPush::send($s, ['title' => 'Notifications activées', 'body' => 'Tu recevras ici les ventes et les alertes de ' . $org['name'] . '.', 'url' => '/pro/app']); }
        catch (Throwable $e) { json_out(['ok' => false, 'error' => $e->getMessage()]); }
        json_out(['ok' => $code >= 200 && $code < 300, 'code' => $code, 'error' => $code >= 200 && $code < 300 ? null : 'Le service de notification a répondu ' . $code . '.']);
    }
    if ($a === 'status') json_out(['subscribed' => (bool)$s, 'prefs' => $s ? AppPush::prefs($s['prefs']) : AppPush::DEFAULT_PREFS]);
    json_out(['ok' => false], 400);
}

function orga_app_manifest(): never
{
    header('Content-Type: application/manifest+json');
    echo json_encode(['name' => setting('platform_name', 'Entrevo') . ' Pro', 'short_name' => 'Entrevo Pro', 'start_url' => url('/pro/app'), 'scope' => url('/pro/app'),
        'display' => 'standalone', 'background_color' => '#f5f5f7', 'theme_color' => '#1d1d1f', 'lang' => 'fr',
        'icons' => [['src' => url('assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'], ['src' => url('assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable']]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Service worker : notifications et écran hors ligne minimal. */
function orga_app_sw(): never
{
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: ' . url('/pro/app'));
    header('Cache-Control: no-cache');
    $home = json_encode(url('/pro/app'));
    $icon = json_encode(url('assets/img/icon-192.png'));
    echo <<<JS
self.addEventListener('install', function (e) { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('push', function (e) {
  var d = {}; try { d = e.data ? e.data.json() : {}; } catch (x) { d = { title: e.data ? e.data.text() : 'Entrevo' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'Entrevo', { body: d.body || '', tag: d.tag || undefined, renotify: !!d.tag, icon: $icon, badge: $icon, data: { url: d.url || $home } }));
});
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) || $home;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) { if (list[i].url.indexOf($home) >= 0 && 'focus' in list[i]) { list[i].navigate(url); return list[i].focus(); } }
    return self.clients.openWindow(url);
  }));
});
JS;
    exit;
}
