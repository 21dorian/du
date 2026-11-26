<?php
declare(strict_types=1);

/* 3.2 : fidélité & CRM festivaliers — programme de points, niveaux, segments, offres ciblées, fiche festivalier. */

require_once APP . '/controllers/pro.php';

function loyalty_page(): string
{
    $org = require_org();
    $s = Loyalty::settings($org);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'settings') {
            $tiers = [];
            foreach ((array)($_POST['tier_name'] ?? []) as $k => $n) {
                $n = mb_substr(trim((string)$n), 0, 30); if ($n === '') continue;
                $tiers[] = ['name' => $n, 'min' => max(1, (int)($_POST['tier_min'][$k] ?? 0)), 'pct' => max(0, min(50, (int)($_POST['tier_pct'][$k] ?? 0)))];
            }
            $j = ['enabled' => checked('enabled'), 'pts_ticket' => max(0, min(20, (float)str_replace(',', '.', (string)input('pts_ticket')))), 'pts_bar' => max(0, min(20, (float)str_replace(',', '.', (string)input('pts_bar')))),
                'pts_visit' => max(0, min(1000, (int)input('pts_visit'))), 'tiers' => $tiers ?: Loyalty::DEFAULTS['tiers']];
            update('organizers', ['loyalty_json' => json_encode($j, JSON_UNESCAPED_UNICODE)], 'id = ?', [$org['id']]);
            back(['success' => 'Programme de fidélité enregistré.']);
        }
        if ($a === 'offer') {
            // offre ciblée : un code promo réservé au segment, sur un événement
            $ev = one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [(int)input('event_id'), $org['id']]);
            if (!$ev) back(['error' => 'Choisis l\'événement de l\'offre.']);
            $list = Loyalty::segment(Loyalty::members($org), (string)input('seg'), (string)input('tier'));
            if (!$list) back(['error' => 'Ce segment est vide.']);
            $pct = max(1, min(90, (int)input('pct')));
            $code = strtoupper(preg_replace('~[^A-Z0-9]~i', '', (string)input('code'))) ?: 'FIDELE' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            if (val('SELECT id FROM promos WHERE event_id = ? AND code = ?', [$ev['id'], $code])) back(['error' => 'Ce code existe déjà pour cet événement.']);
            insert('promos', ['event_id' => $ev['id'], 'code' => $code, 'kind' => 'percent', 'value' => $pct, 'max_uses' => count($list) * max(1, (int)input('uses')), 'used' => 0,
                'valid_until' => input('until') ? date('Y-m-d 23:59:59', strtotime((string)input('until'))) : null, 'active' => 1, 'once_per_email' => 1, 'label' => mb_substr('Offre fidélité · ' . (Loyalty::SEGMENTS[(string)input('seg')] ?? 'segment') . (input('tier') ? ' ' . input('tier') : ''), 0, 120)]);
            Audit::log('loyalty.offer', $code . ' (−' . $pct . ' %) pour ' . count($list) . ' festivalier(s)', (int)$ev['id']);
            $rows = [['E-mail', 'Prénom', 'Nom', 'Niveau', 'Points', 'Code', 'Réduction', 'Lien']];
            foreach ($list as $m) $rows[] = [$m['email'], $m['first_name'], $m['last_name'], $m['tier']['name'] ?? '', $m['points'], $code, $pct . ' %', abs_url('/e/' . $ev['slug'], ['code' => $code])];
            csv_download('offre-' . strtolower($code) . '.csv', $rows);
        }
        back();
    }
    $all = Loyalty::members($org);
    $seg = (string)input('seg'); $tierName = (string)input('tier'); $q = trim((string)input('q'));
    $list = Loyalty::segment($all, $seg, $tierName);
    if ($q !== '') $list = array_filter($list, fn($m) => stripos($m['email'] . ' ' . $m['first_name'] . ' ' . $m['last_name'], $q) !== false);
    if (input('csv')) {
        $rows = [['E-mail', 'Prénom', 'Nom', 'Téléphone', 'Niveau', 'Points', 'Événements', 'Venu(e)', 'Billetterie', 'Bars', 'Dernier achat', 'Accepte les e-mails']];
        foreach ($list as $m) $rows[] = [$m['email'], $m['first_name'], $m['last_name'], $m['phone'], $m['tier']['name'] ?? '', $m['points'], $m['events'], $m['visits'], money_input((int)$m['spent']), money_input((int)$m['bar']), $m['last_at'], $m['optin'] ? 'oui' : 'non'];
        csv_download('festivaliers-' . date('Ymd') . '.csv', $rows);
    }
    $byTier = []; foreach ($all as $m) { $k = $m['tier']['name'] ?? '—'; $byTier[$k] = ($byTier[$k] ?? 0) + 1; }
    $kpi = ['n' => count($all), 'fideles' => count(array_filter($all, fn($m) => (int)$m['events'] >= 2)), 'bar' => array_sum(array_column($all, 'bar')), 'spent' => array_sum(array_column($all, 'spent')),
        'pts' => $all ? (int)round(array_sum(array_column($all, 'points')) / count($all)) : 0, 'came' => count(array_filter($all, fn($m) => $m['visits'] > 0))];
    $events = all('SELECT id, name, starts_at FROM events WHERE organizer_id = ? ORDER BY starts_at DESC LIMIT 30', [$org['id']]);
    $total = count($list);
    $list = array_slice($list, 0, 100, true);
    return pro_page('Fidélité & festivaliers', 'loyalty', compact('s', 'list', 'seg', 'tierName', 'q', 'byTier', 'kpi', 'events', 'total'), 'fidelite');
}

function loyalty_person(): string
{
    $org = require_org();
    $email = strtolower(trim((string)input('e')));
    if (!is_email($email)) abort(404);
    $s = Loyalty::settings($org);
    $all = Loyalty::members($org);
    $m = $all[$email] ?? null;
    if (!$m) abort(404, 'Aucun achat ni dépense pour cette adresse.');
    $rank = array_search($email, array_keys($all), true) + 1;
    $orders = all("SELECT o.*, e.name ev, e.starts_at, (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id AND t.status = 'valid') tk, (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id AND t.entries > 0) came
        FROM orders o JOIN events e ON e.id = o.event_id WHERE o.organizer_id = ? AND LOWER(o.email) = ? ORDER BY o.created_at DESC LIMIT 50", [$org['id'], $email]);
    $wallets = all("SELECT w.*, e.name ev FROM wallets w JOIN events e ON e.id = w.event_id LEFT JOIN tickets t ON t.id = w.ticket_id LEFT JOIN orders o ON o.id = t.order_id
        WHERE e.organizer_id = ? AND LOWER(COALESCE(NULLIF(w.email, ''), NULLIF(t.holder_email, ''), o.email)) = ? ORDER BY w.id DESC", [$org['id'], $email]);
    $prods = []; $barByEv = [];
    foreach ($wallets as $w) foreach (all("SELECT s.items, s.amount, s.kind FROM pos_sales s WHERE s.wallet_id = ? AND s.undone = 0", [$w['id']]) as $sl) {
        $barByEv[$w['ev']] = ($barByEv[$w['ev']] ?? 0) + (int)$sl['amount'];
        if ($sl['kind'] === 'sale') foreach (json_decode((string)$sl['items'], true) ?: [] as $l) if (($l['kind'] ?? '') === 'product') { $prods[$l['name']] = ($prods[$l['name']] ?? 0) + (int)$l['qty']; }
    }
    arsort($prods);
    $next = Loyalty::nextTier($s, $m['points']);
    $pts = ['ticket' => (int)floor(max(0, (int)$m['spent']) / 100 * (float)$s['pts_ticket']), 'bar' => (int)floor(max(0, $m['bar']) / 100 * (float)$s['pts_bar']), 'visit' => $m['visits'] * (int)$s['pts_visit']];
    return pro_page(trim($m['first_name'] . ' ' . $m['last_name']) ?: $email, 'loyalty_person', compact('m', 's', 'orders', 'wallets', 'prods', 'barByEv', 'next', 'pts', 'rank') + ['n' => count($all)], 'fidelite',
        ['crumbs' => [['Fidélité & festivaliers', '/pro/fidelite'], [$email, null]]]);
}
