<?php
declare(strict_types=1);

/* 2.4 : intégrations — webhooks multiples, journal, relances, API. */

require_once APP . '/controllers/pro.php';

function pro_integrations(): string
{
    $org = require_org(['owner']);
    require_module('api');
    if (is_post()) {
        $a = (string)input('action');
        $ep = (int)input('endpoint_id') ? one('SELECT * FROM webhook_endpoints WHERE id = ? AND organizer_id = ?', [(int)input('endpoint_id'), $org['id']]) : null;
        if ($a === 'toggle' && $ep) { update('webhook_endpoints', ['active' => 1 - (int)$ep['active'], 'fail_streak' => 0], 'id = ?', [$ep['id']]); back(); }
        if ($a === 'delete' && $ep) { q('DELETE FROM webhook_deliveries WHERE endpoint_id = ?', [$ep['id']]); q('DELETE FROM webhook_endpoints WHERE id = ?', [$ep['id']]); back(['success' => 'Adresse supprimée.']); }
        if ($a === 'test' && $ep) {
            $ev = (string)input('event', 'order.paid');
            $body = json_encode(['event' => $ev, 'id' => token(12), 'test' => true, 'date' => date('c'), 'data' => Hooks::sample($ev)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            [$code, $resp] = Hooks::post($ep['url'], $body, $ep['secret'], $ev);
            insert('webhook_deliveries', ['endpoint_id' => $ep['id'], 'event' => $ev, 'payload' => $body, 'status' => $code >= 200 && $code < 300 ? 'ok' : 'failed', 'http_code' => $code ?: null, 'response' => $resp, 'attempts' => 1, 'created_at' => now(), 'delivered_at' => now()]);
            back($code >= 200 && $code < 300 ? ['success' => 'Test reçu (HTTP ' . $code . ').'] : ['error' => 'Le test a échoué (' . ($code ?: 'pas de réponse') . ') : ' . mb_substr($resp, 0, 120)]);
        }
        if ($a === 'replay') {
            $d = one('SELECT d.* FROM webhook_deliveries d JOIN webhook_endpoints e ON e.id = d.endpoint_id WHERE d.id = ? AND e.organizer_id = ?', [(int)input('delivery_id'), $org['id']]);
            if ($d) update('webhook_deliveries', ['status' => 'pending', 'next_try_at' => now()], 'id = ?', [$d['id']]);
            Hooks::deliver(5);
            back(['success' => 'Renvoyé.']);
        }
        if ($a === 'save') {
            $url = trim((string)input('url'));
            if (!preg_match('~^https://[^\s]+$~', $url)) back(['error' => 'L\'adresse doit commencer par https://']);
            $evs = array_values(array_intersect(array_keys(Hooks::EVENTS), (array)($_POST['events'] ?? [])));
            if (!$evs) back(['error' => 'Choisis au moins un événement à recevoir.']);
            $d = ['url' => mb_substr($url, 0, 500), 'events' => implode(',', $evs), 'label' => mb_substr(trim((string)input('label')), 0, 120) ?: null];
            if ($ep) update('webhook_endpoints', $d, 'id = ?', [$ep['id']]);
            else insert('webhook_endpoints', $d + ['organizer_id' => $org['id'], 'secret' => bin2hex(random_bytes(16)), 'active' => 1, 'created_at' => now()]);
            back(['success' => 'Adresse enregistrée.']);
        }
    }
    $eps = all('SELECT e.*, (SELECT COUNT(*) FROM webhook_deliveries d WHERE d.endpoint_id = e.id AND d.status = \'ok\' AND d.created_at >= NOW() - INTERVAL 7 DAY) ok7,
        (SELECT COUNT(*) FROM webhook_deliveries d WHERE d.endpoint_id = e.id AND d.status IN (\'failed\',\'pending\') AND d.attempts > 0 AND d.created_at >= NOW() - INTERVAL 7 DAY) ko7 FROM webhook_endpoints e WHERE organizer_id = ? ORDER BY id', [$org['id']]);
    $log = all('SELECT d.id, d.event, d.status, d.http_code, d.attempts, d.created_at, d.next_try_at, d.response, e.label, e.url FROM webhook_deliveries d JOIN webhook_endpoints e ON e.id = d.endpoint_id WHERE e.organizer_id = ? ORDER BY d.id DESC LIMIT 40', [$org['id']]);
    $edit = input('edit') === 'new' ? [] : (input('edit') ? one('SELECT * FROM webhook_endpoints WHERE id = ? AND organizer_id = ?', [(int)input('edit'), $org['id']]) : null);
    return pro_page('Intégrations', 'integrations', compact('org', 'eps', 'log', 'edit'), 'integrations');
}
