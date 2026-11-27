<?php
declare(strict_types=1);

// Tableaux de bord 1.2 : accueil organisateur, vue événement, recherche globale, notifications, journal d'activité.
require_once APP . '/controllers/pro.php';

function search_pro(): never
{
    require_org();
    json_out(['groups' => Insights::search(mb_substr(trim((string)input('q')), 0, 80), current_org(), false)]);
}

function search_admin(): never
{
    require_login(['superadmin']);
    json_out(['groups' => Insights::search(mb_substr(trim((string)input('q')), 0, 80), null, true)]);
}

function notifications_pro(): never { require_org(); json_out(['items' => Insights::notifications(current_org(), false)]); }
function notifications_admin(): never { require_login(['superadmin']); json_out(['items' => Insights::notifications(null, true)]); }

function home_dashboard(): string
{
    $org = require_org();
    $per = Insights::period((int)input('p', 30));
    $w = 'o.organizer_id = ?'; $pa = [$org['id']];
    $cur = Insights::totals($w, $pa, $per['from'], $per['to']);
    $prev = Insights::totals($w, $pa, $per['pFrom'], $per['pTo']);
    $sCur = Insights::series($w, $pa, $per['from'], $per['to']);
    $sPrev = Insights::series($w, $pa, $per['pFrom'], $per['pTo']);
    $evIds = array_map('intval', q('SELECT id FROM events WHERE organizer_id = ?', [$org['id']])->fetchAll(PDO::FETCH_COLUMN));
    $fw = 'event_id IN (' . in_list($evIds) . ')';
    $fun = Traffic::funnel($fw, [], $per['from'], $per['to']);
    $funPrev = Traffic::funnel($fw, [], $per['pFrom'], $per['pTo']);
    $upcoming = all("SELECT * FROM events WHERE organizer_id = ? AND COALESCE(ends_at, starts_at) >= NOW() - INTERVAL 6 HOUR ORDER BY starts_at LIMIT 6", [$org['id']]);
    foreach ($upcoming as &$ev) {
        $ev['stats'] = Tickets::stats((int)$ev['id']);
        $ev['today'] = (int)val("SELECT COUNT(*) FROM tickets t JOIN orders o ON o.id = t.order_id WHERE t.event_id = ? AND t.status = 'valid' AND o.status = 'paid' AND DATE(o.paid_at) = CURDATE() AND (o.channel IS NULL OR o.channel <> 'systeme')", [$ev['id']]);
        $ev['cap'] = (int)$ev['capacity'] ?: (int)val('SELECT COALESCE(SUM(capacity),0) FROM sessions WHERE event_id = ? AND active = 1', [$ev['id']]);
    }
    unset($ev);
    $top = all("SELECT e.id, e.name, COALESCE(SUM(o.total),0) t, COUNT(o.id) n FROM events e LEFT JOIN orders o ON o.event_id = e.id AND o.status = 'paid' AND (o.channel IS NULL OR o.channel <> 'systeme') AND o.paid_at BETWEEN ? AND ?
                WHERE e.organizer_id = ? GROUP BY e.id HAVING t > 0 ORDER BY t DESC LIMIT 5", [$per['from'], $per['to'], $org['id']]);
    $checklist = [
        ['Créer ton premier événement', (bool)$evIds, '/pro/evenements/nouveau'],
        ['Publier un événement', (bool)val("SELECT id FROM events WHERE organizer_id = ? AND status = 'published' LIMIT 1", [$org['id']]), '/pro/evenements'],
        ['Ajouter ton logo et ta présentation', (bool)($org['logo'] && $org['description']), '/pro/organisation'],
        ['Renseigner ton IBAN pour les reversements', (bool)$org['iban'], '/pro/organisation'],
        ['Créer un compte contrôleur pour le jour J', (bool)val("SELECT id FROM users WHERE organizer_id = ? AND role = 'scanner' LIMIT 1", [$org['id']]), '/pro/equipe'],
        ['Faire ta première vente', (bool)val("SELECT id FROM orders WHERE organizer_id = ? AND status = 'paid' LIMIT 1", [$org['id']]), '/pro/evenements'],
    ];
    $activity = Insights::activity((int)$org['id']);
    $balance = Tickets::balance((int)$org['id']);
    $oid = (int)$org['id'];
    $real = "o.organizer_id = ? AND o.status = 'paid' AND (o.channel IS NULL OR o.channel <> 'systeme')";
    // aujourd'hui vs hier
    $day = fn(string $d) => one("SELECT COUNT(*) orders, COALESCE(SUM(o.total),0) revenue, COALESCE(SUM((SELECT SUM(qty) FROM order_items i WHERE i.order_id = o.id)),0) tickets FROM orders o WHERE $real AND DATE(o.paid_at) = ?", [$oid, $d]);
    $today = $day(date('Y-m-d')); $yday = $day(date('Y-m-d', strtotime('-1 day')));
    $vis = fn(string $d) => (int)($evIds ? val('SELECT COALESCE(SUM(visitors),0) FROM event_traffic WHERE ' . $fw . ' AND day = ?', [$d]) : 0);
    $today['visitors'] = $vis(date('Y-m-d')); $yday['visitors'] = $vis(date('Y-m-d', strtotime('-1 day')));
    $scn = fn(string $d) => (int)($evIds ? val("SELECT COUNT(*) FROM scans WHERE $fw AND result = 'ok' AND undone = 0 AND DATE(scanned_at) = ?", [$d]) : 0);
    $today['scans'] = $scn(date('Y-m-d')); $yday['scans'] = $scn(date('Y-m-d', strtotime('-1 day')));
    // à traiter
    $todo = [];
    $n = (int)val("SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND status = 'paid' AND refund_requested_at IS NOT NULL", [$oid]);
    if ($n) $todo[] = ['', $n . ' demande' . ($n > 1 ? 's' : '') . ' de remboursement', 'L\'acheteur attend ta réponse', '/pro/commandes?st=refund_request', 'hot'];
    foreach (all("SELECT id, name FROM events WHERE organizer_id = ? AND status = 'draft' AND starts_at >= NOW() ORDER BY starts_at LIMIT 3", [$oid]) as $d)
        $todo[] = ['', '« ' . $d['name'] . ' » est en brouillon', 'Personne ne peut acheter tant qu\'il n\'est pas publié', '/pro/evenements/' . $d['id'], ''];
    foreach (all("SELECT id, name, starts_at FROM events WHERE organizer_id = ? AND status = 'published' AND starts_at BETWEEN NOW() AND NOW() + INTERVAL 7 DAY ORDER BY starts_at", [$oid]) as $d) {
        if (!val("SELECT id FROM users WHERE organizer_id = ? AND role = 'scanner' AND active = 1 LIMIT 1", [$oid])) { $todo[] = ['', 'Pas de contrôleur pour « ' . $d['name'] . ' »', 'Début ' . lcfirst(ago_until(strtotime($d['starts_at']))) . ' : crée un compte pour scanner', '/pro/equipe', 'hot']; break; }
    }
    foreach (all("SELECT e.id, e.name FROM events e WHERE e.organizer_id = ? AND e.status = 'published' AND e.starts_at >= NOW() AND (e.image IS NULL OR e.image = '')", [$oid]) as $d)
        $todo[] = ['', '« ' . $d['name'] . ' » n\'a pas d\'affiche', 'Les pages avec visuel vendent nettement mieux', '/pro/evenements/' . $d['id'] . '/modifier', ''];
    $n = (int)val("SELECT COUNT(*) FROM waitlist w JOIN events e ON e.id = w.event_id WHERE e.organizer_id = ? AND w.status = 'waiting'", [$oid]);
    if ($n) $todo[] = ['', $n . ' personne' . ($n > 1 ? 's' : '') . ' en liste d\'attente', 'Libère des places ou augmente la jauge', '/pro/evenements', ''];
    $n = (int)val("SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND status = 'pending' AND created_at < NOW() - INTERVAL 20 MINUTE AND created_at > NOW() - INTERVAL 2 DAY", [$oid]);
    if ($n) $todo[] = ['', $n . ' paiement' . ($n > 1 ? 's' : '') . ' en attente', 'Paniers non finalisés : on revérifie automatiquement', '/pro/commandes?st=pending', ''];
    $n = (int)val("SELECT COUNT(*) FROM tasks WHERE organizer_id = ? AND status <> 'done' AND due_date < CURDATE()", [$oid]);
    if ($n) $todo[] = ['', $n . ' tâche' . ($n > 1 ? 's' : '') . ' en retard', 'Dans ton rétroplanning', '/pro/taches', 'hot'];
    foreach (all("SELECT id, title, expires_at FROM documents WHERE organizer_id = ? AND expires_at IS NOT NULL AND expires_at <= CURDATE() + INTERVAL 30 DAY ORDER BY expires_at LIMIT 3", [$oid]) as $d)
        $todo[] = ['', '« ' . $d['title'] . ' » ' . ($d['expires_at'] < date('Y-m-d') ? 'a expiré' : 'expire le ' . date('d/m', strtotime($d['expires_at']))), 'Pense à le renouveler', '/pro/documents', $d['expires_at'] < date('Y-m-d') ? 'hot' : ''];
    if (in_array($org['kyc_status'] ?? 'none', ['none', 'incomplete', 'rejected'], true) && ($balance['balance'] > 0 || Kyc::required()))
        $todo[] = ['', 'Vérifie ton compte', Kyc::required() ? 'Obligatoire pour recevoir tes reversements' : 'Trois documents à déposer', '/pro/verification', Kyc::required() ? 'hot' : ''];
    if (!$org['iban'] && $balance['balance'] > 0) $todo[] = ['', 'Renseigne ton IBAN', money($balance['balance']) . ' t\'attendent', '/pro/organisation', 'hot'];
    // carte de chaleur : ventes par jour de semaine × heure
    $heat = array_fill(0, 7, array_fill(0, 24, 0));
    foreach (all("SELECT WEEKDAY(o.paid_at) d, HOUR(o.paid_at) h, COUNT(*) n FROM orders o WHERE $real AND o.paid_at BETWEEN ? AND ? GROUP BY d, h", [$oid, $per['from'], $per['to']]) as $r) $heat[(int)$r['d']][(int)$r['h']] = (int)$r['n'];
    // d'où viennent les ventes
    $sources = all("SELECT COALESCE(NULLIF(o.utm_source, ''), CASE o.method WHEN 'online' THEN 'Direct / site' WHEN 'guichet' THEN 'Guichet' WHEN 'invitation' THEN 'Invitations' WHEN 'partner' THEN 'Partenaires' ELSE o.method END) src,
        COUNT(*) n, COALESCE(SUM(o.total),0) t FROM orders o WHERE $real AND o.paid_at BETWEEN ? AND ? GROUP BY src ORDER BY t DESC, n DESC LIMIT 6", [$oid, $per['from'], $per['to']]);
    // objectifs
    $goals = all("SELECT e.id, e.name, e.revenue_goal, COALESCE((SELECT SUM(o.total) FROM orders o WHERE o.event_id = e.id AND o.status = 'paid'),0) done FROM events e
        WHERE e.organizer_id = ? AND e.revenue_goal > 0 AND COALESCE(e.ends_at, e.starts_at) >= NOW() - INTERVAL 7 DAY ORDER BY e.starts_at LIMIT 4", [$oid]);
    // modules à venir mis en avant
    $counts = Features::counts(); $mine = Features::votes($oid);
    $soon = array_filter(Features::all(), fn($f, $k) => $f['status'] === 'soon' && !isset($mine[$k]), ARRAY_FILTER_USE_BOTH);
    uksort($soon, fn($a, $b) => ($counts[$b] ?? 0) <=> ($counts[$a] ?? 0));
    $soon = array_slice($soon, 0, 3, true);
    return pro_page('Tableau de bord', 'dashboard2', compact('org', 'per', 'cur', 'prev', 'sCur', 'sPrev', 'fun', 'funPrev', 'upcoming', 'top', 'checklist', 'activity', 'balance', 'today', 'yday', 'todo', 'heat', 'sources', 'goals', 'soon'), 'dash',
        ['actions' => view('pro/_period', ['per' => $per])]);
}

function activity_feed(): string
{
    $org = require_org();
    return view('pro/_activity', ['activity' => Insights::activity((int)$org['id'], input('ev') ? (int)input('ev') : null)]);
}

function event_overview2(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $stats = Tickets::stats((int)$ev['id']);
    $w = 'o.event_id = ?'; $pa = [$ev['id']];
    $per = Insights::period(7);
    $cur = Insights::totals($w, $pa, $per['from'], $per['to']);
    $prev = Insights::totals($w, $pa, $per['pFrom'], $per['pTo']);
    $spark = Insights::series($w, $pa, date('Y-m-d 00:00:00', strtotime('-13 days')), $per['to']);
    $cum = Insights::cumulative((int)$ev['id']);
    $fun = Traffic::funnel('event_id = ?', [$ev['id']], '2000-01-01 00:00:00', date('Y-m-d 23:59:59'));
    $cap = (int)$ev['capacity'] ?: (int)val('SELECT COALESCE(SUM(capacity),0) FROM sessions WHERE event_id = ? AND active = 1', [$ev['id']]);
    $channels = all("SELECT CASE WHEN COALESCE(NULLIF(channel,''), method) IN ('online','web') THEN 'web' ELSE COALESCE(NULLIF(channel,''), method) END c, COUNT(*) n, SUM(total) t
                     FROM orders WHERE event_id = ? AND status = 'paid' AND (channel IS NULL OR channel <> 'systeme') GROUP BY c ORDER BY t DESC", [$ev['id']]);
    $activity = Insights::activity((int)$ev['organizer_id'], (int)$ev['id'], 8);
    $ready = event_readiness($ev);
    $fc = Insights::forecast($ev, $cap);
    require_once APP . '/controllers/pro.php';
    return ev_page($ev, 'overview', 'Tableau de bord', 'event_overview2', compact('stats', 'cur', 'prev', 'spark', 'cum', 'fun', 'cap', 'channels', 'activity', 'ready', 'fc') + ev_dash_extra($ev));
}

/** Score « prêt à publier » : ce qui manque pour une billetterie au top. */
function event_readiness(array $ev): array
{
    $rates = (int)val('SELECT COUNT(*) FROM rates WHERE event_id = ? AND active = 1', [$ev['id']]);
    $checks = [
        ['Au moins un tarif actif', $rates > 0, '/tarifs'],
        ['Une affiche', (bool)$ev['image'], '/modifier'],
        ['Une description d\'au moins 120 caractères', mb_strlen((string)$ev['description']) >= 120, '/modifier'],
        ['Un lieu et une ville', $ev['venue'] && $ev['city'], '/modifier'],
        ['Une jauge définie', (int)$ev['capacity'] > 0 || Tickets::hasSessions((int)$ev['id']), '/modifier'],
        ['Un point d\'accès pour le contrôle', (bool)val('SELECT id FROM access_points WHERE event_id = ? LIMIT 1', [$ev['id']]), '/acces'],
        ['Un texte sur le billet (accès, horaires…)', trim((string)$ev['ticket_info']) !== '', '/modifier'],
    ];
    $ok = count(array_filter($checks, fn($c) => $c[1]));
    return ['checks' => $checks, 'score' => (int)round($ok / count($checks) * 100)];
}

function audit_page(): string
{
    $org = require_org(['owner']);
    $who = (int)input('who'); $type = (string)input('type'); $per = 50;
    $w = 'a.organizer_id = ?'; $p = [$org['id']];
    if ($who) { $w .= ' AND a.user_id = ?'; $p[] = $who; }
    if ($type !== '' && preg_match('~^[a-z]+$~', $type)) { $w .= ' AND a.action LIKE ?'; $p[] = $type . '%'; }
    $total = (int)val("SELECT COUNT(*) FROM audit_log a WHERE $w", $p);
    $pages = max(1, (int)ceil($total / $per)); $page = max(1, min($pages, (int)input('page', 1)));
    $rows = all("SELECT a.*, u.name user_name, e.name ev_name, o.ref FROM audit_log a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN events e ON e.id = a.event_id LEFT JOIN orders o ON o.id = a.order_id
                 WHERE $w ORDER BY a.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
    $users = all('SELECT DISTINCT u.id, u.name FROM audit_log a JOIN users u ON u.id = a.user_id WHERE a.organizer_id = ? ORDER BY u.name', [$org['id']]);
    $types = ['event' => 'Événements', 'rate' => 'Tarifs', 'order' => 'Commandes', 'ticket' => 'Billets', 'box' => 'Guichet', 'invites' => 'Invitations',
        'accred' => 'Accréditations', 'message' => 'Messages', 'cash' => 'Caisse', 'team' => 'Équipe', 'org' => 'Organisation', 'bulk' => 'Actions groupées', 'seatmap' => 'Plans', 'staff' => 'Bénévoles'];
    return pro_page('Journal d\'activité', 'audit', compact('rows', 'users', 'types', 'who', 'type', 'page', 'pages', 'total'), 'audit');
}
