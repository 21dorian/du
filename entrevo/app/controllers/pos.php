<?php
declare(strict_types=1);

/* 2.6 : API de l'application « Entrevo Caisse » (web app /caisse/ et application native). */

function pos_api(): never
{
    header('Cache-Control: no-store');
    $in = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($in)) $in = $_POST;
    $a = (string)($in['action'] ?? '');
    $fail = fn(string $m, int $c = 400) => json_out(['ok' => false, 'error' => $m], $c);
    try {
        if ($a === 'pair') {
            if (!throttle_pos('pair')) $fail('Trop d\'essais. Patiente une minute.', 429);
            $r = Pos::pair((string)($in['code'] ?? ''), (string)($in['platform'] ?? 'web'));
            if (!$r) $fail('Code inconnu ou expiré. Demande un nouveau code à l\'organisateur.', 404);
            [$d, $token] = $r;
            json_out(['ok' => true, 'token' => $token, 'config' => Pos::config($d)]);
        }
        $d = Pos::device((string)($_SERVER['HTTP_X_POS_TOKEN'] ?? ($in['token'] ?? '')));
        if (!$d) $fail('Cet appareil n\'est plus autorisé. Appaire-le à nouveau.', 401);
        $ev = one('SELECT * FROM events WHERE id = ?', [$d['event_id']]);
        switch ($a) {
            case 'config':
            case 'ping':
                json_out(['ok' => true, 'config' => Pos::config($d)]);
            case 'lookup':
                $w = Pos::wallet((int)$ev['id'], $in);
                if (!$w) $fail(!empty($in['nfc']) ? 'Bracelet non associé.' : 'Billet inconnu pour cet événement.', 404);
                json_out(['ok' => true, 'balance' => (int)$w['balance'], 'name' => Pos::holder($w), 'status' => $w['status'], 'nfc' => (bool)$w['nfc_uid']]);
            case 'sale':
                json_out(Pos::sale($d, $in));
            case 'sync':
                $done = [];
                foreach (array_slice((array)($in['sales'] ?? []), 0, 200) as $s) {
                    if (!is_array($s) || ($s['method'] ?? '') === 'cashless') continue;
                    try { $r = Pos::sale($d, $s + ['offline' => 1]); $done[] = ['client_id' => $s['client_id'] ?? null, 'ok' => true, 'id' => $r['sale']['id']]; }
                    catch (Throwable $e) { $done[] = ['client_id' => $s['client_id'] ?? null, 'ok' => false, 'error' => $e->getMessage()]; }
                }
                json_out(['ok' => true, 'results' => $done]);
            case 'undo':
                json_out(Pos::undo($d, (int)($in['sale_id'] ?? 0)));
            case 'link_nfc':
                $w = Pos::linkNfc((int)$ev['id'], (string)($in['code'] ?? ''), (string)($in['nfc'] ?? ''));
                json_out(['ok' => true, 'balance' => (int)$w['balance'], 'name' => Pos::holder($w)]);
            case 'summary':
                $since = !empty($in['since']) && ($ts = strtotime((string)$in['since'])) ? date('Y-m-d H:i:s', $ts) : date('Y-m-d 00:00:00');
                json_out(['ok' => true, 'summary' => Pos::summary((int)$ev['id'], $d['bar_id'] ? (int)$d['bar_id'] : ((int)($in['bar_id'] ?? 0) ?: null), !empty($in['mine']) ? (int)$d['id'] : null, $since)]);
        }
        $fail('Action inconnue.');
    } catch (Throwable $e) {
        if ($e instanceof RuntimeException) $fail($e->getMessage());
        log_line('caisse', $e->getMessage());
        $fail('Erreur du serveur.', 500);
    }
}

/** Limite simple par adresse IP (appairage). */
function throttle_pos(string $k): bool
{
    $f = ROOT . '/storage/logs/.throttle-pos-' . md5($k . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $n = is_file($f) && filemtime($f) > time() - 60 ? (int)file_get_contents($f) : 0;
    @file_put_contents($f, (string)($n + 1));
    return $n < 10;
}

/** L'application est servie depuis /caisse/ (fichiers statiques) ; sans barre oblique finale, on y renvoie. */
function pos_app(): never
{
    $uri = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!str_ends_with($uri, '/')) { header('Location: ' . url('/caisse/') . (($q = $_SERVER['QUERY_STRING'] ?? '') ? '?' . $q : '')); exit; }
    header('Content-Type: text/html; charset=utf-8');
    readfile(ROOT . '/caisse/index.html');
    exit;
}
