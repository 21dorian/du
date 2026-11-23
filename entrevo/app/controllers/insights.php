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
    return pro_page('Tableau de bord', 'dashboard2', compact('org', 'per', 'cur', 'prev', 'sCur', 'sPrev', 'fun', 'funPrev', 'upcoming', 'top', 'checklist', 'activity', 'balance'), 'dash',
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
    return ev_page($ev, 'overview', $ev['name'], 'event_overview2', compact('stats', 'cur', 'prev', 'spark', 'cum', 'fun', 'cap', 'channels', 'activity', 'ready'));
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
    $rows = all('SELECT a.*, u.name user_name, e.name ev_name, o.ref FROM audit_log a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN events e ON e.id = a.event_id LEFT JOIN orders o ON o.id = a.order_id
                 WHERE a.organizer_id = ? ORDER BY a.id DESC LIMIT 300', [$org['id']]);
    return pro_page('Journal d\'activité', 'audit', ['rows' => $rows], 'org');
}
