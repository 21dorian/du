<?php
declare(strict_types=1);

/* 1.3 : catalogue des modules, pages « Bientôt » avec vote, base participants tous événements. */

require_once APP . '/controllers/pro.php';

function modules_page(): string
{
    $org = require_org();
    $votes = Features::votes((int)$org['id']);
    $cat = (string)input('cat');
    $all = Features::all();
    $nOn = count(array_filter($all, fn($f) => $f['status'] !== 'soon'));
    return pro_page('Tous les modules', 'modules', ['all' => $all, 'votes' => $votes, 'cat' => isset(Features::CATEGORIES[$cat]) ? $cat : '', 'nOn' => $nOn, 'nSoon' => count($all) - $nOn], 'modules');
}

function coming_soon(string $key): string
{
    $org = require_org();
    $f = Features::get($key);
    if (!$f) abort(404);
    if ($f['status'] !== 'soon' && !empty($f['url'])) redirect($f['url']);
    if (is_post()) {
        if (input('action') === 'unvote') q('DELETE FROM feature_votes WHERE organizer_id = ? AND feature = ?', [$org['id'], $key]);
        else q('INSERT INTO feature_votes (organizer_id, feature, user_id, comment, created_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE comment = COALESCE(VALUES(comment), comment)',
            [$org['id'], $key, user()['id'], mb_substr(trim((string)input('comment')), 0, 500) ?: null]);
        back(['success' => input('action') === 'unvote' ? 'Vote retiré.' : '🙌 C\'est noté ! On te prévient dès que « ' . $f['name'] . ' » arrive.']);
    }
    $voted = isset(Features::votes((int)$org['id'])[$key]);
    $count = (int)(Features::counts()[$key] ?? 0);
    $others = array_slice(array_filter(Features::all(), fn($x, $k) => $x['status'] === 'soon' && $k !== $key && $x['cat'] === $f['cat'], ARRAY_FILTER_USE_BOTH), 0, 3, true);
    return pro_page($f['name'], 'soon', compact('f', 'voted', 'count', 'others'), $key);
}

/** Base participants : un contact par adresse e-mail, tous événements confondus. */
function participants_all(): string
{
    $org = require_org();
    $q = trim((string)input('q'));
    $evId = (int)input('ev');
    $seg = (string)input('seg');
    $sorts = ['last' => 'last_at', 'spent' => 'spent', 'orders' => 'orders', 'events' => 'events', 'name' => 'last_name'];
    $sort = isset($sorts[(string)input('sort')]) ? (string)input('sort') : 'last';
    $dir = input('dir') === 'asc' ? 'ASC' : 'DESC';
    $w = "o.organizer_id = ? AND o.status = 'paid' AND o.email IS NOT NULL AND o.email <> '' AND (o.channel IS NULL OR o.channel <> 'systeme')";
    $p = [$org['id']];
    if ($q !== '') { $w .= ' AND (o.email LIKE ? OR o.first_name LIKE ? OR o.last_name LIKE ? OR o.phone LIKE ?)'; array_push($p, ...array_fill(0, 4, '%' . $q . '%')); }
    if ($evId) { $w .= ' AND o.email IN (SELECT email FROM orders WHERE event_id = ? AND status = \'paid\')'; $p[] = $evId; }
    $having = match ($seg) {
        'fideles' => 'HAVING events >= 2',
        'gros' => 'HAVING spent >= 10000',
        'recents' => 'HAVING last_at >= NOW() - INTERVAL 30 DAY',
        'dormants' => 'HAVING last_at < NOW() - INTERVAL 180 DAY',
        default => '',
    };
    $base = "SELECT LOWER(o.email) email, SUBSTRING_INDEX(GROUP_CONCAT(o.first_name ORDER BY o.created_at DESC SEPARATOR '\x1f'), '\x1f', 1) first_name,
        SUBSTRING_INDEX(GROUP_CONCAT(o.last_name ORDER BY o.created_at DESC SEPARATOR '\x1f'), '\x1f', 1) last_name, MAX(o.phone) phone,
        COUNT(*) orders, COUNT(DISTINCT o.event_id) events, SUM(o.total - o.refunded_amount) spent, MIN(o.created_at) first_at, MAX(o.created_at) last_at,
        COALESCE(SUM(tk.valid),0) tickets, COALESCE(SUM(tk.came),0) came
        FROM orders o LEFT JOIN (SELECT order_id, SUM(status = 'valid') valid, SUM(status = 'valid' AND entries > 0) came FROM tickets GROUP BY order_id) tk ON tk.order_id = o.id WHERE $w GROUP BY LOWER(o.email) $having";
    if (input('csv')) {
        $rows = [['E-mail', 'Prénom', 'Nom', 'Téléphone', 'Commandes', 'Événements', 'Billets', 'Venu(e)', 'Dépensé', 'Premier achat', 'Dernier achat']];
        foreach (all($base . ' ORDER BY ' . $sorts[$sort] . " $dir", $p) as $c) $rows[] = [$c['email'], $c['first_name'], $c['last_name'], $c['phone'], $c['orders'], $c['events'], $c['tickets'], $c['came'], money_input((int)$c['spent']), $c['first_at'], $c['last_at']];
        csv_download('contacts-' . date('Ymd') . '.csv', $rows);
    }
    $total = (int)val("SELECT COUNT(*) FROM ($base) x", $p);
    $per = 50; $pages = max(1, (int)ceil($total / $per)); $page = max(1, min($pages, (int)input('page', 1)));
    $list = all($base . ' ORDER BY ' . $sorts[$sort] . " $dir LIMIT $per OFFSET " . (($page - 1) * $per), $p);
    $kpi = one("SELECT COUNT(DISTINCT LOWER(email)) contacts FROM orders WHERE organizer_id = ? AND status = 'paid' AND email IS NOT NULL AND email <> ''", [$org['id']]);
    $kpi['fideles'] = (int)val("SELECT COUNT(*) FROM (SELECT LOWER(email) e FROM orders WHERE organizer_id = ? AND status = 'paid' AND email <> '' GROUP BY LOWER(email) HAVING COUNT(DISTINCT event_id) >= 2) x", [$org['id']]);
    $kpi['new30'] = (int)val("SELECT COUNT(*) FROM (SELECT LOWER(email) e FROM orders WHERE organizer_id = ? AND status = 'paid' AND email <> '' GROUP BY LOWER(email) HAVING MIN(created_at) >= NOW() - INTERVAL 30 DAY) x", [$org['id']]);
    $kpi['came'] = (int)val("SELECT COUNT(*) FROM tickets t JOIN orders o ON o.id = t.order_id WHERE o.organizer_id = ? AND t.status = 'valid' AND t.entries > 0", [$org['id']]);
    $events = all('SELECT id, name FROM events WHERE organizer_id = ? ORDER BY starts_at DESC', [$org['id']]);
    return pro_page('Participants', 'participants', compact('list', 'q', 'evId', 'seg', 'sort', 'dir', 'total', 'page', 'pages', 'kpi', 'events'), 'participants');
}

/** Super-admin : votes des organisateurs pour les modules à venir. */
function admin_modules(): string
{
    require_login(['superadmin']);
    $counts = Features::counts();
    $comments = [];
    try { $comments = all('SELECT v.*, o.name org_name FROM feature_votes v JOIN organizers o ON o.id = v.organizer_id WHERE v.comment IS NOT NULL ORDER BY v.created_at DESC LIMIT 100'); } catch (Throwable $e) {}
    $all = Features::all();
    $all = array_filter($all, fn($f) => $f['status'] === 'soon');
    uksort($all, fn($a, $b) => ($counts[$b] ?? 0) <=> ($counts[$a] ?? 0));
    $publicCounts = array_column(all('SELECT feature, COUNT(*) n, SUM(email IS NOT NULL) emails FROM public_interest GROUP BY feature'), null, 'feature');
    $prio = array_filter(explode(',', (string)setting('admin_roadmap')));
    $adminItems = Features::admin();
    uksort($adminItems, fn($a, $b) => (int)in_array($b, $prio, true) <=> (int)in_array($a, $prio, true));
    return view('layout_back', ['title' => 'Feuille de route', 'area' => 'admin', 'active' => 'modules', 'content' => view('admin/modules', compact('all', 'counts', 'comments', 'adminItems', 'prio', 'publicCounts'))]);
}

/** Super-admin : page d'un chantier de la plateforme (en développement), marquable comme prioritaire. */
function admin_soon(string $key): string
{
    require_login(['superadmin']);
    $f = Features::getAdmin($key);
    if (!$f) abort(404);
    $prio = array_filter(explode(',', (string)setting('admin_roadmap')));
    if (is_post()) {
        $prio = in_array($key, $prio, true) ? array_values(array_diff($prio, [$key])) : array_merge($prio, [$key]);
        set_setting('admin_roadmap', implode(',', $prio));
        back(['success' => in_array($key, $prio, true) ? '⭐ Ajouté à tes priorités.' : 'Retiré de tes priorités.']);
    }
    return view('layout_back', ['title' => $f['name'], 'area' => 'admin', 'active' => $key,
        'content' => view('admin/soon', ['f' => $f, 'prio' => in_array($key, $prio, true)])]);
}
