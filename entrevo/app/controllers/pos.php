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
        // 2.9 : le point de vente est choisi sur l'appareil et envoyé à chaque requête (une vente hors ligne garde le sien).
        if (!empty($in['bar_id']) && (int)$in['bar_id'] !== (int)$d['bar_id'] && val('SELECT id FROM bars WHERE id = ? AND event_id = ?', [(int)$in['bar_id'], $ev['id']])) $d['bar_id'] = (int)$in['bar_id'];
        switch ($a) {
            case 'beat':
                // 3.0 : battement de cœur du terminal (parc de terminaux d'Entrevo Pay)
                update('pos_devices', ['battery' => isset($in['battery']) ? max(0, min(100, (int)$in['battery'])) : null, 'charging' => isset($in['charging']) ? (int)(bool)$in['charging'] : null,
                    'app_version' => mb_substr((string)($in['app_version'] ?? ''), 0, 20) ?: null, 'queue_size' => max(0, (int)($in['queue'] ?? 0)), 'last_seen' => now()] + (empty($in['queue']) ? ['last_sync' => now()] : []), 'id = ?', [$d['id']]);
                json_out(['ok' => true, 'session' => (bool)Till::session($d), 'time' => date('c')]);
            case 'tabs':
                json_out(['ok' => true, 'tabs' => Pos::tabs($d)]);
            case 'tab_save':
                json_out(['ok' => true, 'tab' => Pos::tabSave($d, $in)]);
            case 'tab_cancel':
                Pos::tabCancel($d, $in);
                json_out(['ok' => true, 'tabs' => Pos::tabs($d)]);
            case 'station':
                // poste choisi sur l'appareil : point de vente et rôle
                $mode = in_array($in['mode'] ?? '', Pos::MODES, true) ? (string)$in['mode'] : 'bar';
                $bar = (int)($in['bar_id'] ?? 0);
                if ($bar && !val('SELECT id FROM bars WHERE id = ? AND event_id = ? AND active = 1', [$bar, $ev['id']])) $fail('Point de vente inconnu.');
                if (!$bar && in_array($mode, ['bar', 'topup'], true)) $fail('Choisis ton point de vente.');
                $staff = Till::staffFrom($d, $in);
                if (Till::settings($ev)['require_staff'] && !$staff && in_array($mode, ['bar', 'topup'], true)) $fail('Connecte-toi avec ton code serveur.', 403);
                update('pos_devices', ['bar_id' => $bar ?: null, 'mode' => $mode, 'staff_name' => $staff['name'] ?? (mb_substr(trim((string)($in['staff'] ?? '')), 0, 60) ?: null), 'station_at' => now()], 'id = ?', [$d['id']]);
                json_out(['ok' => true, 'config' => Pos::config(one('SELECT * FROM pos_devices WHERE id = ?', [$d['id']]))]);
            case 'config':
            case 'ping':
                json_out(['ok' => true, 'config' => Pos::config($d)]);
            case 'lookup':
                $w = Pos::wallet((int)$ev['id'], $in);
                if (!$w) $fail(!empty($in['nfc']) ? 'Bracelet non associé.' : 'Billet inconnu pour cet événement.', 404);
                if ($w['status'] === 'blocked') $fail('Porte-monnaie bloqué' . ($w['blocked_reason'] ? ' : ' . $w['blocked_reason'] : '') . '. Envoie le festivalier au point info.', 423);
                json_out(['ok' => true, 'balance' => (int)$w['balance'], 'name' => Pay::holder($w) ?: Pos::holder($w), 'status' => $w['status'], 'nfc' => (bool)$w['nfc_uid'], 'profile' => Pay::PROFILES[$w['profile'] ?? 'public'] ?? null]);
            case 'sale':
            case 'topup':
            case 'cashout':
                // caisse du point de vente clôturée depuis un autre appareil : l'appli revient à l'ouverture
                if (Till::settings($ev)['sessions'] && !Till::session($d)) json_out(['ok' => false, 'till_closed' => true, 'error' => 'La caisse de ce point de vente est clôturée.'], 409);
                json_out($a === 'sale' ? Pos::sale($d, $in) : ($a === 'topup' ? Pos::topup($d, $in) : Pos::cashout($d, $in)));
            case 'sync':
                update('pos_devices', ['last_sync' => now(), 'queue_size' => 0], 'id = ?', [$d['id']]);
                $done = [];
                foreach (array_slice((array)($in['sales'] ?? []), 0, 200) as $s) {
                    if (!is_array($s) || ($s['method'] ?? '') === 'cashless') continue;
                    try { $r = Pos::sale($d, $s + ['offline' => 1]); $done[] = ['client_id' => $s['client_id'] ?? null, 'ok' => true, 'id' => $r['sale']['id']]; }
                    catch (Throwable $e) { $done[] = ['client_id' => $s['client_id'] ?? null, 'ok' => false, 'error' => $e->getMessage()]; }
                }
                json_out(['ok' => true, 'results' => $done]);
            case 'undo':
                json_out(Pos::undo($d, (int)($in['sale_id'] ?? 0), $in));
            case 'staff_login':
                if (!throttle_pos('pin-' . $d['id'])) $fail('Trop d\'essais. Patiente une minute.', 429);
                $st = Till::staffByPin((int)$ev['organizer_id'], (string)($in['pin'] ?? ''));
                if (!$st) $fail('Code serveur inconnu.', 403);
                update('pos_staff', ['last_login' => now()], 'id = ?', [$st['id']]);
                json_out(['ok' => true, 'staff' => ['id' => (int)$st['id'], 'name' => $st['name'], 'role' => $st['role'], 'perms' => Till::perms($st), 'token' => Till::staffToken($d, $st)]]);
            case 'check_pin':
                if (!throttle_pos('pin-' . $d['id'])) $fail('Trop d\'essais. Patiente une minute.', 429);
                $st = Till::staffByPin((int)$ev['organizer_id'], (string)($in['pin'] ?? ''));
                $perm = (string)($in['perm'] ?? '');
                if (!$st || !in_array($perm, Till::perms($st), true)) $fail('Ce code ne permet pas cette action.', 403);
                json_out(['ok' => true, 'name' => $st['name']]);
            case 'quote':
                $bar = (int)($d['bar_id'] ?: ($in['bar_id'] ?? 0));
                $pr = Till::price($ev, $bar, $in, time());
                json_out(['ok' => true, 'quote' => ['total' => $pr['total'], 'subtotal' => $pr['subtotal'], 'discount' => $pr['discount'], 'offered' => $pr['offered'], 'deposits' => $pr['deposits'],
                    'lines' => array_map(fn($l) => ['kind' => $l['kind'], 'name' => $l['name'], 'qty' => $l['qty'], 'total' => $l['total'], 'full' => $l['full'], 'promo' => $l['promo'] ?? null, 'offered' => !empty($l['offered'])], $pr['lines'])]]);
            case 'allowance':
                $a = Till::allowance((int)$ev['id'], $in);
                if (!$a) $fail('Badge de crédit inconnu.', 404);
                $u = Till::allowanceUsed($a);
                json_out(['ok' => true, 'holder' => $a['holder'], 'group' => Till::GROUPS[$a['grp']] ?? '', 'mode' => $a['mode'], 'left' => $u['left'] === PHP_INT_MAX ? null : $u['left'], 'label' => $u['left'] === PHP_INT_MAX ? 'illimité' : Till::allowanceLabel($a, $u['left'])]);
            case 'session_open':
                // une seule caisse ouverte par point de vente : si un autre appareil l'a déjà ouverte, on la rejoint
                if (Till::session($d)) json_out(['ok' => true, 'joined' => true, 'config' => Pos::config($d)]);
                $staff = Till::staffFrom($d, $in);
                $by = Till::settings($ev)['require_staff'] ? Till::authorize((int)$ev['organizer_id'], $staff, 'close', $in['approver_pin'] ?? null, true) : $staff;
                $bar = (int)$d['bar_id'];
                if (!val('SELECT id FROM bars WHERE id = ? AND event_id = ?', [$bar, $ev['id']])) $fail('Choisis ton point de vente.');
                [$fsum, $fjson] = is_array($in['count'] ?? null) ? Till::countSum($in['count']) : [max(0, (int)($in['float'] ?? 0)), null];
                insert('pos_sessions', ['event_id' => $ev['id'], 'bar_id' => $bar, 'device_id' => $d['id'], 'opened_by' => $by['id'] ?? null, 'opened_at' => now(), 'float_amount' => $fsum, 'float_json' => $fjson]);
                json_out(['ok' => true, 'config' => Pos::config($d)]);
            case 'session':
                $se = Till::session($d);
                $devs = $se ? all('SELECT DISTINCT d.name FROM pos_sales s JOIN pos_devices d ON d.id = s.device_id WHERE s.session_id = ?', [$se['id']]) : [];
                json_out(['ok' => true, 'session' => $se ? ['id' => (int)$se['id'], 'opened_at' => $se['opened_at'], 'bar' => (string)val('SELECT name FROM bars WHERE id = ?', [$se['bar_id']]),
                    'opener' => $se['opened_by'] ? (string)val('SELECT name FROM pos_staff WHERE id = ?', [$se['opened_by']]) : null, 'devices' => array_column($devs, 'name'), 'tabs' => array_map(fn($t) => ['label' => $t['label'], 'total' => $t['total']], Pos::tabs($d))] + Till::sessionTotals($se) : null]);
            case 'cash_move':
                $se = Till::session($d);
                if (!$se) $fail('Aucune caisse ouverte.');
                $staff = Till::staffFrom($d, $in);
                $by = Till::authorize((int)$ev['organizer_id'], $staff, 'cash', $in['approver_pin'] ?? null, (bool)Till::settings($ev)['require_staff']);
                [$amt, $cjson] = is_array($in['count'] ?? null) ? Till::countSum($in['count']) : [(int)($in['amount'] ?? 0), null];
                if ($amt <= 0) $fail('Indique un montant.');
                $kind = ($in['kind'] ?? '') === 'add' ? 'add' : 'drop';
                insert('pos_cash_moves', ['session_id' => $se['id'], 'kind' => $kind, 'amount' => $amt, 'count_json' => $cjson, 'staff_id' => $by['id'] ?? ($staff['id'] ?? null), 'note' => mb_substr(trim((string)($in['note'] ?? '')), 0, 255) ?: null, 'created_at' => now()]);
                json_out(['ok' => true, 'session' => Till::sessionTotals($se)]);
            case 'session_close':
                $se = Till::session($d);
                if (!$se) $fail('Aucune caisse ouverte.');
                $staff = Till::staffFrom($d, $in);
                $by = Till::authorize((int)$ev['organizer_id'], $staff, 'close', $in['approver_pin'] ?? null, (bool)Till::settings($ev)['require_staff']);
                $tot = Till::sessionTotals($se);
                if (is_array($in['count'] ?? null)) { [$csum, $cjson] = Till::countSum($in['count']); $in['counted'] = $csum; } else $cjson = null;
                $z = (int)val('SELECT COALESCE(MAX(z_number), 0) + 1 FROM pos_sessions WHERE event_id = ?', [$ev['id']]);
                update('pos_sessions', ['closed_at' => now(), 'closed_by' => $by['id'] ?? ($staff['id'] ?? null), 'expected_cash' => $tot['expected_cash'], 'counted_cash' => isset($in['counted']) ? max(0, (int)$in['counted']) : null, 'count_json' => $cjson,
                    'z_number' => $z, 'totals_json' => json_encode($tot, JSON_UNESCAPED_UNICODE), 'note' => mb_substr(trim((string)($in['note'] ?? '')), 0, 500) ?: null], 'id = ?', [$se['id']]);
                $openTabs = (int)val("SELECT COUNT(*) FROM pos_tabs WHERE event_id = ? AND bar_id = ? AND status = 'open'", [$ev['id'], $se['bar_id']]);
                json_out(['ok' => true, 'z' => $z, 'totals' => $tot, 'counted' => isset($in['counted']) ? (int)$in['counted'] : null, 'open_tabs' => $openTabs]);
            case 'new_wallet':
                $w = Pos::newWallet($d, $in);
                json_out(['ok' => true, 'code' => $w['code'], 'balance' => 0]);
            case 'prep_list':
                json_out(['ok' => true, 'orders' => Pos::prepList($d), 'time' => date('H:i:s')]);
            case 'prep_set':
                Pos::prepSet($d, (int)($in['sale_id'] ?? 0), (string)($in['status'] ?? ''));
                json_out(['ok' => true, 'orders' => Pos::prepList($d)]);
            case 'calls':
                json_out(['ok' => true, 'orders' => Pos::prepList($d, true), 'preparing' => array_values(array_map(fn($o) => $o['order'], array_filter(Pos::prepList($d), fn($o) => $o['status'] === 'new')))]);
            case 'receipt':
                $sale = one('SELECT * FROM pos_sales WHERE id = ? AND event_id = ?', [(int)($in['sale_id'] ?? 0), $ev['id']]);
                if (!$sale) $fail('Vente introuvable.', 404);
                if (!throttle_pos('receipt-' . $d['id'])) $fail('Trop d\'envois. Patiente une minute.', 429);
                Till::sendReceipt($sale, (string)($in['email'] ?? ''));
                json_out(['ok' => true]);
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

/** 2.7 : ticket de caisse consultable par le client (QR code affiché par la caisse). */
function pos_receipt(string $token): string
{
    require_once APP . '/controllers/public.php';
    $s = strlen($token) >= 20 ? one('SELECT * FROM pos_sales WHERE receipt_token = ?', [$token]) : null;
    if (!$s) abort(404);
    if (is_post()) {
        try { if (!throttle_pos('recu-' . $token)) throw new RuntimeException('Trop d\'envois, réessaie dans une minute.'); Till::sendReceipt($s, (string)input('email')); redirect('/recu/' . $token, ['success' => 'Ticket envoyé à ' . strtolower(trim((string)input('email'))) . '.']); }
        catch (RuntimeException $e) { redirect('/recu/' . $token, ['error' => $e->getMessage()]); }
    }
    $ev = one('SELECT name FROM events WHERE id = ?', [$s['event_id']]);
    return pub('pos_receipt', ['s' => $s, 'html' => Till::receiptHtml($s), 'ev' => $ev], 'Ticket n° ' . $s['ticket_no'], ['noindex' => true]);
}

/** QR code du ticket (affiché par la caisse après la vente). */
function pos_receipt_qr(string $token): never
{
    $s = strlen($token) >= 20 ? one('SELECT receipt_token FROM pos_sales WHERE receipt_token = ?', [$token]) : null;
    if (!$s) abort(404);
    header('Content-Type: image/svg+xml'); header('Cache-Control: public, max-age=86400');
    echo Qr::svg(abs_url('/recu/' . $token), 220); exit;
}
