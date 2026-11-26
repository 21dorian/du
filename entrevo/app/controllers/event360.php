<?php
declare(strict_types=1);

/* 3.2 : vue 360 d'un événement — billetterie, entrées, bars et cashless, recettes totales et prévisions, sur une seule page. */

require_once APP . '/controllers/pro.php';

function event360_data(array $ev): array
{
    $id = (int)$ev['id'];
    $cap = (int)$ev['capacity'];
    // billetterie
    $tk = one("SELECT COUNT(*) n, COUNT(CASE WHEN t.type = 'invitation' THEN 1 END) inv, COUNT(CASE WHEN t.checked_in_at IS NOT NULL THEN 1 END) inside_ever, COUNT(CASE WHEN t.inside = 1 THEN 1 END) inside_now
        FROM tickets t WHERE t.event_id = ? AND t.status = 'valid' AND t.type IN ('ticket','invitation')", [$id]);
    $ord = one("SELECT COUNT(*) n, COALESCE(SUM(total), 0) total, COALESCE(SUM(fees), 0) fees, COALESCE(SUM(organizer_amount), 0) net, COALESCE(SUM(refunded_amount), 0) refunded, COALESCE(SUM(donation), 0) donation
        FROM orders WHERE event_id = ? AND status = 'paid'", [$id]);
    $items = []; foreach (all("SELECT i.kind, SUM(i.qty * i.unit_price) a FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.event_id = ? AND o.status = 'paid' GROUP BY i.kind", [$id]) as $r) $items[$r['kind']] = (int)$r['a'];
    $byRate = all("SELECT r.name, r.price, r.quota, (SELECT COUNT(*) FROM tickets t WHERE t.rate_id = r.id AND t.status = 'valid') sold, r.dyn_json FROM rates r WHERE r.event_id = ? ORDER BY r.sort, r.id", [$id]);
    $byDay = all("SELECT DATE(paid_at) d, COUNT(*) n, SUM(total) t FROM orders WHERE event_id = ? AND status = 'paid' AND paid_at >= NOW() - INTERVAL 30 DAY GROUP BY DATE(paid_at) ORDER BY d", [$id]);
    $fc = class_exists('Insights') ? Insights::forecast($ev, $cap) : null;
    // entrées par heure (jour J) et ventes des bars à la même heure
    $hours = [];
    foreach (all("SELECT DATE_FORMAT(scanned_at, '%d/%m %Hh') h, MIN(scanned_at) m, COUNT(*) n FROM scans WHERE event_id = ? AND result = 'ok' AND direction = 'in' AND undone = 0 GROUP BY h ORDER BY m", [$id]) as $r) $hours[$r['h']] = ['in' => (int)$r['n'], 'bar' => 0, 'm' => $r['m']];
    foreach (all("SELECT DATE_FORMAT(created_at, '%d/%m %Hh') h, MIN(created_at) m, SUM(amount) a FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'sale' GROUP BY h ORDER BY m", [$id]) as $r) { $hours[$r['h']] = ($hours[$r['h']] ?? ['in' => 0, 'm' => $r['m']]); $hours[$r['h']]['bar'] = (int)$r['a']; }
    uasort($hours, fn($a, $b) => strcmp((string)$a['m'], (string)$b['m']));
    // bars et cashless
    $bar = one("SELECT COUNT(*) n, COALESCE(SUM(amount), 0) ttc, COALESCE(SUM(tip), 0) tips FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'sale'", [$id]);
    $refund = (int)val("SELECT COALESCE(-SUM(amount), 0) FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'refund'", [$id]);
    $k = Cashless::stats($id);
    $topBars = all("SELECT b.name, COALESCE(SUM(s.amount), 0) ttc, COUNT(s.id) n FROM bars b LEFT JOIN pos_sales s ON s.bar_id = b.id AND s.undone = 0 AND s.kind = 'sale' WHERE b.event_id = ? GROUP BY b.id ORDER BY ttc DESC LIMIT 6", [$id]);
    $present = max(1, (int)$tk['inside_ever']);
    // budget prévisionnel (si saisi)
    $budget = null;
    try { $budget = one("SELECT COALESCE(SUM(CASE WHEN kind = 'income' THEN planned END), 0) inc, COALESCE(SUM(CASE WHEN kind = 'expense' THEN planned END), 0) exp, COALESCE(SUM(CASE WHEN kind = 'expense' THEN actual END), 0) exp_real FROM budget_lines WHERE event_id = ?", [$id]); } catch (Throwable $e) { $budget = null; }
    $ticketRev = (int)$ord['total'] - (int)$ord['refunded'];
    $barRev = (int)$bar['ttc'] - $refund;
    return compact('cap', 'tk', 'ord', 'items', 'byRate', 'byDay', 'fc', 'hours', 'bar', 'refund', 'k', 'topBars', 'budget', 'ticketRev', 'barRev')
        + ['per_head' => (int)round($barRev / $present), 'spend_per_head' => (int)round(((int)$k['spent']) / $present), 'show_rate' => (int)$tk['n'] ? round((int)$tk['inside_ever'] / (int)$tk['n'] * 100) : 0];
}

function event360(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $D = event360_data($ev);
    if (input('export') === 'csv') {
        $rows = [['Indicateur', 'Valeur']];
        foreach ([['Billets vendus', $D['tk']['n']], ['dont invitations', $D['tk']['inv']], ['Capacité', $D['cap']], ['Entrés au moins une fois', $D['tk']['inside_ever']], ['Taux de présence (%)', $D['show_rate']],
            ['Recette billetterie TTC', money_input($D['ticketRev'])], ['dont frais de service', money_input((int)$D['ord']['fees'])], ['Montant reversé à l\'organisateur', money_input((int)$D['ord']['net'])],
            ['CA des bars TTC', money_input($D['barRev'])], ['Pourboires', money_input((int)$D['bar']['tips'])], ['Dépense bar par personne présente', money_input($D['per_head'])],
            ['Cashless rechargé', money_input((int)$D['k']['loaded'])], ['Soldes cashless restants', money_input((int)$D['k']['left'])], ['Recette totale (billetterie + bars)', money_input($D['ticketRev'] + $D['barRev'])]] as $r) $rows[] = $r;
        foreach ($D['byRate'] as $r) $rows[] = ['Tarif ' . $r['name'], $r['sold'] . ' vendus'];
        foreach ($D['hours'] as $h => $x) $rows[] = ['Heure ' . $h, $x['in'] . ' entrées · ' . money_input($x['bar']) . ' aux bars'];
        csv_download('vue-360-' . $ev['slug'] . '.csv', $rows);
    }
    return ev_page($ev, '360', 'Vue 360', 'event360', ['D' => $D]);
}
