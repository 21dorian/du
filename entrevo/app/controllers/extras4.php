<?php
declare(strict_types=1);

/* 4.0 : modules sortis du développement côté organisateur — saison, partage des stats, rapport de fin, connecteurs,
   automatisations, vestiaire, badges, borne, contacts (import et doublons), appli mobile. */

require_once APP . '/controllers/pro.php';
require_once APP . '/controllers/event360.php';

// ---------- Saison : dupliquer un événement sur plusieurs dates ----------
function season(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    $done = $_SESSION['season_done'] ?? []; unset($_SESSION['season_done']);
    if (is_post()) {
        $time = preg_match('~^\d{2}:\d{2}$~', (string)input('time')) ? (string)input('time') : date('H:i', strtotime($ev['starts_at']));
        $dates = [];
        if (input('mode') === 'repeat') {
            $from = strtotime((string)input('from')); $n = max(1, min(52, (int)input('count', 4))); $every = in_array((int)input('every'), [1, 7, 14, 28], true) ? (int)input('every') : 7;
            if (!$from) back(['error' => 'Choisis la première date.']);
            for ($i = 0; $i < $n; $i++) $dates[] = date('Y-m-d', strtotime('+' . ($i * $every) . ' days', $from));
        } else {
            foreach (preg_split('~[\r\n,;]+~', (string)input('dates')) as $l) {
                $l = trim($l); if ($l === '') continue;
                if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})(?:\s+(\d{1,2})[h:](\d{2}))?~', $l, $m)) $dates[] = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) . (isset($m[4]) ? sprintf(' %02d:%02d', $m[4], $m[5]) : '');
                elseif (strtotime($l)) $dates[] = $l;
            }
        }
        $dates = array_slice(array_unique($dates), 0, 52);
        if (!$dates) back(['error' => 'Aucune date reconnue. Exemple : 14/03/2027 20:30, une par ligne.']);
        $keys = array_values(array_intersect((array)($_POST['copy'] ?? []), ['sessions', 'fields', 'aps', 'products', 'promos', 'shifts', 'budget', 'tasks']));
        $pattern = trim((string)input('name_pattern')) ?: '{nom} · {date}';
        $made = [];
        foreach ($dates as $d) {
            $start = strlen($d) > 10 ? date('Y-m-d H:i:s', strtotime($d)) : date('Y-m-d H:i:s', strtotime($d . ' ' . $time));
            $name = strtr($pattern, ['{nom}' => $ev['name'], '{date}' => fr_date($start, false)]);
            try { $made[] = event_copy($ev, $start, $name, $keys); } catch (Throwable $e) { back(['error' => $e->getMessage()]); }
        }
        if (input('publish')) foreach ($made as $nid) update('events', ['status' => 'published'], 'id = ?', [$nid]);
        Audit::log('event.season', count($made) . ' dates créées depuis « ' . $ev['name'] . ' »', (int)$ev['id']);
        $_SESSION['season_done'] = $made;
        redirect('/pro/evenements/' . $ev['id'] . '/saison', ['success' => count($made) . ' date(s) créée(s)' . (input('publish') ? ' et mises en vente.' : ' en brouillon.')]);
    }
    $created = $done ? all('SELECT id, name, starts_at, status FROM events WHERE id IN (' . implode(',', array_map('intval', $done)) . ') ORDER BY starts_at') : [];
    $siblings = all("SELECT id, name, starts_at, status, (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid') sold FROM events e WHERE organizer_id = ? AND id <> ? AND name LIKE ? ORDER BY starts_at LIMIT 60", [$org['id'], $ev['id'], mb_substr($ev['name'], 0, 20) . '%']);
    $counts = ['sessions' => 'SELECT COUNT(*) FROM sessions WHERE event_id = ?', 'fields' => 'SELECT COUNT(*) FROM form_fields WHERE event_id = ?', 'aps' => 'SELECT COUNT(*) FROM access_points WHERE event_id = ?', 'products' => 'SELECT COUNT(*) FROM products WHERE event_id = ?',
        'promos' => 'SELECT COUNT(*) FROM promos WHERE event_id = ? AND batch IS NULL', 'shifts' => 'SELECT COUNT(*) FROM staff_shifts WHERE event_id = ?', 'budget' => 'SELECT COUNT(*) FROM budget_lines WHERE event_id = ?', 'tasks' => 'SELECT COUNT(*) FROM tasks WHERE event_id = ?'];
    foreach ($counts as $k => $sql) { try { $counts[$k] = (int)val($sql, [$ev['id']]); } catch (Throwable $e) { $counts[$k] = 0; } }
    return ev_page($ev, 'season', 'Saison', 'season', compact('created', 'siblings', 'counts'));
}

// ---------- Partage des stats (lecture seule) ----------
const SHARE_SECTIONS = ['ventes' => 'Billets vendus et remplissage', 'recette' => 'Recette billetterie', 'presence' => 'Présence le jour J', 'tarifs' => 'Ventes par tarif', 'bars' => 'Bars et cashless', 'courbe' => 'Courbe des ventes'];

function event_share(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        if (input('action') === 'revoke') { update('share_links', ['revoked' => 1], 'id = ? AND event_id = ?', [(int)input('link_id'), $ev['id']]); back(['success' => 'Lien désactivé : il ne fonctionne plus.']); }
        $secs = array_values(array_intersect((array)($_POST['sections'] ?? []), array_keys(SHARE_SECTIONS)));
        if (!$secs) back(['error' => 'Coche au moins un bloc de chiffres à partager.']);
        $days = (int)input('days');
        insert('share_links', ['event_id' => $ev['id'], 'token' => bin2hex(random_bytes(16)), 'label' => mb_substr(trim((string)input('label')), 0, 120) ?: 'Partenaire', 'sections' => implode(',', $secs),
            'expires_at' => $days > 0 ? date('Y-m-d 23:59:59', strtotime('+' . min(365, $days) . ' days')) : null, 'created_at' => now()]);
        Audit::log('stats.shared', (string)input('label'), (int)$ev['id']);
        back(['success' => 'Lien créé : copie-le et envoie-le à ton partenaire.']);
    }
    $links = all('SELECT * FROM share_links WHERE event_id = ? ORDER BY revoked, id DESC', [$ev['id']]);
    return ev_page($ev, 'share', 'Partager les stats', 'share', ['links' => $links]);
}

function share_public(string $token): string
{
    $l = one('SELECT s.*, e.organizer_id FROM share_links s JOIN events e ON e.id = s.event_id WHERE s.token = ?', [$token]);
    if (!$l || $l['revoked'] || ($l['expires_at'] && strtotime($l['expires_at']) < time())) abort(404, 'Ce lien de partage n\'existe pas ou a expiré.');
    q('UPDATE share_links SET views = views + 1 WHERE id = ?', [$l['id']]);
    $ev = one('SELECT * FROM events WHERE id = ?', [$l['event_id']]);
    $org = one('SELECT name, logo FROM organizers WHERE id = ?', [$l['organizer_id']]);
    header('X-Robots-Tag: noindex');
    return view('layout_public', ['title' => 'Chiffres · ' . $ev['name'], 'noindex' => true, 'content' => view('public/share_stats', ['ev' => $ev, 'org' => $org, 'l' => $l, 'secs' => explode(',', (string)$l['sections']), 'D' => event360_data($ev)])]);
}

// ---------- Rapport de fin (PDF) ----------
function event_report_pdf(string $id): never
{
    $org = require_org();
    $ev = org_event((int)$id);
    $D = event360_data($ev);
    $t = fn($s) => PdfDoc::t((string)$s);
    $pdf = new PdfDoc('P', 'mm', 'A4'); $pdf->SetMargins(16, 16, 16); $pdf->SetAutoPageBreak(true, 16); $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 20); $pdf->Cell(0, 10, $t('Bilan de l\'événement'), 0, 1);
    $pdf->SetFont('Helvetica', '', 10); $pdf->SetTextColor(110, 110, 115);
    $pdf->MultiCell(0, 5, $t($ev['name'] . ' · ' . fr_date($ev['starts_at']) . ($ev['venue'] ? ' · ' . $ev['venue'] : '') . "\n" . ($org['legal_name'] ?: $org['name']) . ' · édité le ' . date('d/m/Y H:i')));
    $pdf->Ln(4); $pdf->SetTextColor(29, 29, 31);
    $tiles = [[money($D['ticketRev'] + $D['barRev']), 'recette totale'], [(string)$D['tk']['n'], 'billets' . ($D['cap'] ? ' sur ' . $D['cap'] : '')], [$D['show_rate'] . ' %', 'taux de présence'],
        [money($D['ticketRev']), 'billetterie'], [money($D['barRev']), 'bars'], [money($D['per_head']), 'bar par présent']];
    $y0 = $pdf->GetY();
    foreach ($tiles as $i => [$v, $l]) {
        $x = 16 + ($i % 3) * 60; $y = $y0 + intdiv($i, 3) * 24;
        $pdf->SetFillColor(245, 245, 247); $pdf->RoundedRect($x, $y, 56, 20, 3, 'F');
        $pdf->SetXY($x + 4, $y + 3); $pdf->SetFont('Helvetica', 'B', 13); $pdf->Cell(48, 7, $t($v), 0, 2); $pdf->SetFont('Helvetica', '', 8.5); $pdf->SetTextColor(110, 110, 115); $pdf->Cell(48, 5, $t($l)); $pdf->SetTextColor(29, 29, 31);
    }
    $pdf->SetY($y0 + 52);
    $table = function (string $title, array $head, array $rows, array $w) use ($pdf, $t) {
        if (!$rows) return;
        if ($pdf->GetY() > 240) $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 12); $pdf->Cell(0, 8, $t($title), 0, 1);
        $pdf->SetFont('Helvetica', 'B', 8); $pdf->SetTextColor(110, 110, 115);
        foreach ($head as $i => $h) $pdf->Cell($w[$i], 6, $t($h), 'B', 0, $i ? 'R' : 'L');
        $pdf->Ln(); $pdf->SetFont('Helvetica', '', 9); $pdf->SetTextColor(29, 29, 31); $pdf->SetDrawColor(225, 225, 230);
        foreach ($rows as $row) { if ($pdf->GetY() > 275) $pdf->AddPage(); foreach ($row as $i => $c) $pdf->Cell($w[$i], 6, $t(mb_strimwidth((string)$c, 0, $i ? 20 : 52, '...')), 'B', 0, $i ? 'R' : 'L'); $pdf->Ln(); }
        $pdf->Ln(4);
    };
    $table('Billetterie', ['', 'Montant'], [['Encaissé TTC', money((int)$D['ord']['total'])], ['Remboursé', money((int)$D['ord']['refunded'])], ['Frais de service', money((int)$D['ord']['fees'])], ['Reversé à l\'organisateur', money((int)$D['ord']['net'])], ['Dons', money((int)$D['ord']['donation'])], ['Commandes', (string)$D['ord']['n']]], [142, 36]);
    $table('Ventes par tarif', ['Tarif', 'Prix', 'Vendus', 'Quota'], array_map(fn($r) => [$r['name'], money((int)$r['price']), $r['sold'], (int)$r['quota'] ?: '-'], $D['byRate']), [100, 26, 26, 26]);
    $table('Présence', ['', 'Nombre'], [['Billets valides', $D['tk']['n']], ['dont invitations', $D['tk']['inv']], ['Entrés au moins une fois', $D['tk']['inside_ever']], ['Taux de présence', $D['show_rate'] . ' %']], [142, 36]);
    if ((int)$D['bar']['n']) $table('Bars et cashless', ['', 'Montant'], [['CA des bars TTC', money($D['barRev'])], ['Tickets', (string)$D['bar']['n']], ['Pourboires', money((int)$D['bar']['tips'])], ['Cashless rechargé', money((int)$D['k']['loaded'])], ['Soldes restants', money((int)$D['k']['left'])]], [142, 36]);
    $table('Points de vente', ['Bar', 'Tickets', 'CA TTC'], array_map(fn($b) => [$b['name'], $b['n'], money((int)$b['ttc'])], array_filter($D['topBars'], fn($b) => (int)$b['n'] > 0)), [118, 30, 30]);
    $table('Heure par heure', ['Heure', 'Entrées', 'Bars'], array_map(fn($h, $x) => [$h, $x['in'], money($x['bar'])], array_keys($D['hours']), $D['hours']), [118, 30, 30]);
    if ($D['budget'] && ((int)$D['budget']['exp'] || (int)$D['budget']['inc'])) $table('Budget', ['', 'Montant'], [['Dépenses prévues', money((int)$D['budget']['exp'])], ['Dépenses réelles', money((int)$D['budget']['exp_real'])], ['Recettes prévues', money((int)$D['budget']['inc'])], ['Recettes réelles (billetterie + bars)', money($D['ticketRev'] + $D['barRev'])], ['Résultat', money($D['ticketRev'] + $D['barRev'] - max((int)$D['budget']['exp_real'], (int)$D['budget']['exp']))]], [142, 36]);
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="bilan-' . $ev['slug'] . '.pdf"');
    echo $pdf->Output('S'); exit;
}

// ---------- Connecteurs ----------
function connectors(): string
{
    $org = require_org(['owner']);
    $s = Extras::settings($org);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'sheets') { Extras::save($org, ['sheets_token' => bin2hex(random_bytes(16))]); back(['success' => 'Nouveaux liens Google Sheets créés (les anciens ne marchent plus).']); }
        if ($a === 'sheets_off') { Extras::save($org, ['sheets_token' => '']); back(['success' => 'Flux Google Sheets coupés.']); }
        if ($a === 'mail') {
            $p = in_array(input('mail_provider'), ['brevo', 'mailchimp'], true) ? (string)input('mail_provider') : '';
            Extras::save($org, ['mail_provider' => $p, 'mail_list' => trim((string)input('mail_list'))] + (trim((string)input('mail_key')) !== '' ? ['mail_key' => trim((string)input('mail_key'))] : []));
            back(['success' => $p ? 'Synchro ' . ucfirst($p) . ' enregistrée : les contacts consentants partent toutes les 10 minutes.' : 'Synchro e-mailing coupée.']);
        }
        if ($a === 'mail_test') {
            if ($s['mail_provider'] === '' || $s['mail_key'] === '') back(['error' => 'Enregistre d\'abord la clé.']);
            [$code, $resp] = $s['mail_provider'] === 'brevo' ? Extras::http('GET', 'https://api.brevo.com/v3/account', [], ['api-key: ' . $s['mail_key']])
                : Extras::http('GET', 'https://' . (explode('-', $s['mail_key'])[1] ?? 'us1') . '.api.mailchimp.com/3.0/ping', [], ['Authorization: Basic ' . base64_encode('entrevo:' . $s['mail_key'])]);
            back($code >= 200 && $code < 300 ? ['success' => 'Connexion réussie.'] : ['error' => 'Échec (' . ($code ?: 'pas de réponse') . ') : vérifie la clé. ' . mb_substr($resp, 0, 120)]);
        }
        if ($a === 'ads') {
            $patch = ['meta_pixel' => preg_replace('~\D~', '', (string)input('meta_pixel')), 'meta_test' => trim((string)input('meta_test')), 'ga4_id' => strtoupper(trim((string)input('ga4_id')))];
            if (trim((string)input('meta_token')) !== '') $patch['meta_token'] = trim((string)input('meta_token'));
            if (trim((string)input('ga4_secret')) !== '') $patch['ga4_secret'] = trim((string)input('ga4_secret'));
            if (input('clear_meta')) $patch += ['meta_token' => ''];
            Extras::save($org, $patch);
            back(['success' => 'Suivi publicitaire enregistré : chaque commande payée sera envoyée côté serveur.']);
        }
        if ($a === 'api_key' && empty($org['api_key'])) { update('organizers', ['api_key' => bin2hex(random_bytes(20))], 'id = ?', [$org['id']]); back(['success' => 'Clé API créée.']); }
        back();
    }
    $sync = all('SELECT provider, status, COUNT(*) n, MAX(synced_at) last FROM contact_sync WHERE organizer_id = ? GROUP BY provider, status', [$org['id']]);
    $capi = all('SELECT provider, status, COUNT(*) n, MAX(COALESCE(sent_at, created_at)) last FROM capi_queue WHERE organizer_id = ? GROUP BY provider, status', [$org['id']]);
    $lastErr = one("SELECT provider, response, created_at FROM capi_queue WHERE organizer_id = ? AND status <> 'sent' AND response IS NOT NULL ORDER BY id DESC LIMIT 1", [$org['id']]);
    $hooks = all("SELECT * FROM webhook_endpoints WHERE organizer_id = ? AND label LIKE 'Zapier%' OR (organizer_id = ? AND label LIKE 'Make%') OR (organizer_id = ? AND label LIKE 'n8n%') OR (organizer_id = ? AND label LIKE 'REST%') ORDER BY id DESC", [$org['id'], $org['id'], $org['id'], $org['id']]);
    $optins = (int)val('SELECT COUNT(DISTINCT LOWER(email)) FROM orders WHERE organizer_id = ? AND status = \'paid\' AND marketing_optin = 1', [$org['id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$org['id']]);
    return pro_page('Connecteurs', 'connectors', compact('org', 's', 'sync', 'capi', 'lastErr', 'hooks', 'optins'), 'connectors');
}

/** Flux CSV pour Google Sheets (=IMPORTDATA) ou Excel (données > à partir du web). */
function sheets_feed(string $token, string $file): never
{
    if (strlen($token) < 20) abort(404);
    $org = one("SELECT * FROM organizers WHERE connect_json LIKE ?", ['%"sheets_token":"' . preg_replace('~[^a-f0-9]~', '', $token) . '"%']);
    if (!$org || Extras::settings($org)['sheets_token'] !== $token) abort(404);
    $type = preg_replace('~\.csv$~', '', $file);
    $ev = (int)($_GET['e'] ?? 0); $evSql = $ev ? ' AND o.event_id = ' . $ev : '';
    if ($type === 'commandes') {
        $rows = [['Référence', 'Date', 'Événement', 'Nom', 'Prénom', 'E-mail', 'Téléphone', 'Billets', 'Total', 'Remboursé', 'Statut', 'Moyen', 'Code promo', 'Source']];
        foreach (all("SELECT o.*, e.name ev, (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id) nt FROM orders o JOIN events e ON e.id = o.event_id WHERE o.organizer_id = ? AND o.status IN ('paid','refunded')$evSql ORDER BY o.id DESC LIMIT 20000", [$org['id']]) as $o)
            $rows[] = [$o['ref'], $o['paid_at'] ?: $o['created_at'], $o['ev'], $o['last_name'], $o['first_name'], $o['email'], $o['phone'], $o['nt'], money_input((int)$o['total']), money_input((int)$o['refunded_amount']), $o['status'], $o['method'], $o['promo_code'] ?? '', $o['utm_source'] ?? ''];
    } elseif ($type === 'participants') {
        $rows = [['Code', 'Événement', 'Tarif', 'Nom', 'Prénom', 'E-mail acheteur', 'Statut', 'Entré', 'Heure d\'entrée', 'Commande']];
        foreach (all("SELECT t.*, e.name ev, o.email, o.ref FROM tickets t JOIN orders o ON o.id = t.order_id JOIN events e ON e.id = t.event_id WHERE o.organizer_id = ? AND t.status = 'valid'$evSql ORDER BY t.id DESC LIMIT 50000", [$org['id']]) as $t)
            $rows[] = [$t['code'], $t['ev'], $t['rate_name'], $t['last_name'], $t['first_name'], $t['email'], $t['status'], $t['entries'] > 0 ? 'oui' : 'non', $t['checked_in_at'] ?? '', $t['ref']];
    } elseif ($type === 'ventes') {
        $rows = [['Jour', 'Événement', 'Commandes', 'Billets', 'Montant']];
        foreach (all("SELECT DATE(o.paid_at) d, e.name ev, COUNT(DISTINCT o.id) n, (SELECT COUNT(*) FROM tickets t JOIN orders o2 ON o2.id = t.order_id WHERE o2.event_id = e.id AND DATE(o2.paid_at) = DATE(o.paid_at) AND o2.status = 'paid') nt, SUM(o.total) tot FROM orders o JOIN events e ON e.id = o.event_id WHERE o.organizer_id = ? AND o.status = 'paid'$evSql GROUP BY DATE(o.paid_at), e.id ORDER BY d DESC LIMIT 5000", [$org['id']]) as $r)
            $rows[] = [$r['d'], $r['ev'], $r['n'], $r['nt'], money_input((int)$r['tot'])];
    } else abort(404);
    header('Content-Type: text/csv; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $r) fputcsv($out, array_map(fn($v) => (string)$v, $r), ',', '"', '\\');
    exit;
}

// ---------- Automatisations ----------
function automations(): string
{
    $org = require_org();
    $s = Extras::settings($org);
    if (is_post()) {
        $pct = fn(string $k) => max(5, min(80, (int)input($k)));
        Extras::save($org, ['auto_birthday' => checked('auto_birthday'), 'auto_birthday_pct' => $pct('auto_birthday_pct'), 'auto_comeback' => checked('auto_comeback'), 'auto_comeback_pct' => $pct('auto_comeback_pct'),
            'auto_thanks' => checked('auto_thanks'), 'auto_thanks_pct' => $pct('auto_thanks_pct'), 'referral' => checked('referral'), 'referral_n' => max(1, min(20, (int)input('referral_n'))), 'referral_pct' => $pct('referral_pct'), 'end_report' => checked('end_report')]);
        Audit::log('automations.saved', '');
        back(['success' => 'Automatisations enregistrées : elles tournent toutes seules, plusieurs fois par jour.']);
    }
    $codes = all('SELECT a.*, p.used, e.name ev FROM auto_codes a LEFT JOIN promos p ON p.event_id = a.event_id AND p.code = a.code LEFT JOIN events e ON e.id = a.event_id WHERE a.organizer_id = ? ORDER BY a.id DESC LIMIT 60', [$org['id']]);
    $stats = []; foreach (all('SELECT a.scenario, COUNT(*) n, SUM(CASE WHEN p.used > 0 THEN 1 ELSE 0 END) used FROM auto_codes a LEFT JOIN promos p ON p.event_id = a.event_id AND p.code = a.code WHERE a.organizer_id = ? GROUP BY a.scenario', [$org['id']]) as $r) $stats[$r['scenario']] = $r;
    $pot = [
        'birthday' => (int)val("SELECT COUNT(DISTINCT LOWER(email)) FROM orders WHERE organizer_id = ? AND status = 'paid' AND marketing_optin = 1 AND birth_date IS NOT NULL", [$org['id']]),
        'comeback' => (int)val("SELECT COUNT(*) FROM (SELECT LOWER(email) FROM orders WHERE organizer_id = ? AND status = 'paid' AND marketing_optin = 1 GROUP BY LOWER(email) HAVING MAX(created_at) < NOW() - INTERVAL 365 DAY) x", [$org['id']]),
        'referrers' => (int)val("SELECT COUNT(DISTINCT referrer_order_id) FROM orders WHERE organizer_id = ? AND status = 'paid' AND referrer_order_id IS NOT NULL", [$org['id']]),
        'referred' => (int)val("SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND status = 'paid' AND referrer_order_id IS NOT NULL", [$org['id']]),
    ];
    $next = one("SELECT name, starts_at FROM events WHERE organizer_id = ? AND status = 'published' AND starts_at > NOW() ORDER BY starts_at LIMIT 1", [$org['id']]);
    $top = all("SELECT r.first_name, r.last_name, r.email, COUNT(f.id) n FROM orders f JOIN orders r ON r.id = f.referrer_order_id WHERE r.organizer_id = ? AND f.status = 'paid' GROUP BY r.id ORDER BY n DESC LIMIT 8", [$org['id']]);
    return pro_page('Automatisations', 'automations', compact('s', 'codes', 'stats', 'pot', 'next', 'top'), 'automations');
}

// ---------- Vestiaire ----------
function cloakroom_page(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'in') {
            $code = strtoupper(trim((string)input('ticket_code')));
            $t = $code !== '' ? one('SELECT * FROM tickets WHERE event_id = ? AND code = ?', [$ev['id'], $code]) : null;
            if ($code !== '' && !$t) back(['error' => 'Billet ' . $code . ' introuvable sur cet événement.']);
            $num = (int)input('number') ?: (int)val('SELECT COALESCE(MAX(number), 0) + 1 FROM cloakroom WHERE event_id = ?', [$ev['id']]);
            if (val("SELECT id FROM cloakroom WHERE event_id = ? AND number = ? AND status = 'in'", [$ev['id'], $num])) back(['error' => 'Le numéro ' . $num . ' est déjà occupé.']);
            insert('cloakroom', ['event_id' => $ev['id'], 'number' => $num, 'ticket_code' => $t ? $t['code'] : null, 'name' => mb_substr(trim((string)input('name')) ?: ($t ? trim($t['first_name'] . ' ' . $t['last_name']) : ''), 0, 120) ?: null,
                'items' => mb_substr(trim((string)input('items')), 0, 190) ?: null, 'fee' => parse_money(input('fee')), 'user_id' => user()['id'], 'created_at' => now()]);
            $_SESSION['cr_last'] = $num;
            back(['success' => 'Ticket n° ' . $num . ' : donne ce numéro à la personne.']);
        }
        if ($a === 'out') {
            $q = strtoupper(trim((string)input('q')));
            $row = ctype_digit($q) ? one("SELECT * FROM cloakroom WHERE event_id = ? AND number = ? AND status = 'in'", [$ev['id'], (int)$q]) : one("SELECT * FROM cloakroom WHERE event_id = ? AND ticket_code = ? AND status = 'in' ORDER BY id DESC LIMIT 1", [$ev['id'], $q]);
            if (!$row && (int)input('row_id')) $row = one("SELECT * FROM cloakroom WHERE event_id = ? AND id = ? AND status = 'in'", [$ev['id'], (int)input('row_id')]);
            if (!$row) back(['error' => 'Rien au vestiaire pour « ' . $q . ' ».']);
            update('cloakroom', ['status' => 'out', 'returned_at' => now()], 'id = ?', [$row['id']]);
            back(['success' => 'N° ' . $row['number'] . ' rendu' . ($row['items'] ? ' (' . $row['items'] . ')' : '') . '.']);
        }
        back();
    }
    $f = (string)input('f', 'in');
    $rows = all('SELECT c.*, u.name staff FROM cloakroom c LEFT JOIN users u ON u.id = c.user_id WHERE c.event_id = ? ' . ($f === 'all' ? '' : "AND c.status = 'in'") . ' ORDER BY c.number', [$ev['id']]);
    $st = one("SELECT COUNT(*) n, SUM(status = 'in') inside, COALESCE(SUM(fee), 0) fees FROM cloakroom WHERE event_id = ?", [$ev['id']]);
    $next = (int)val('SELECT COALESCE(MAX(number), 0) + 1 FROM cloakroom WHERE event_id = ?', [$ev['id']]);
    if (input('export') === 'csv') csv_download('vestiaire-' . $ev['slug'] . '.csv', array_merge([['N°', 'Billet', 'Nom', 'Affaires', 'Prix', 'Statut', 'Déposé', 'Rendu']], array_map(fn($r) => [$r['number'], $r['ticket_code'], $r['name'], $r['items'], money_input((int)$r['fee']), $r['status'] === 'in' ? 'au vestiaire' : 'rendu', $r['created_at'], $r['returned_at']], all('SELECT * FROM cloakroom WHERE event_id = ? ORDER BY number', [$ev['id']]))));
    return ev_page($ev, 'cloak', 'Vestiaire', 'cloakroom', compact('rows', 'st', 'next', 'f'));
}

// ---------- Badges imprimés ----------
function badges(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $src = (string)input('src', 'tickets');
    $rate = (int)input('rate');
    $q = trim((string)input('q'));
    $params = [$ev['id']]; $w = '';
    if ($rate) { $w .= ' AND t.rate_id = ?'; $params[] = $rate; }
    if ($q !== '') { $w .= ' AND (t.code = ? OR CONCAT(t.first_name, \' \', t.last_name) LIKE ?)'; $params[] = strtoupper($q); $params[] = '%' . $q . '%'; }
    if (input('only') === 'in') $w .= ' AND t.entries > 0';
    $w .= $src === 'accred' ? " AND t.type = 'accreditation'" : " AND t.type <> 'accreditation'";
    $people = [];
    foreach (all("SELECT t.code, t.first_name, t.last_name, t.rate_name, t.answers, t.company FROM tickets t WHERE t.event_id = ? AND t.status = 'valid'$w ORDER BY t.last_name, t.first_name LIMIT 500", $params) as $t) {
        $company = (string)$t['company'];
        if ($company === '') foreach (json_decode((string)$t['answers'], true) ?: [] as $k => $v) { $val = is_array($v) ? (string)($v['value'] ?? '') : (string)$v; $lab = is_array($v) ? (string)($v['label'] ?? $k) : (string)$k; if (preg_match('~soci|entreprise|structure|company|organisation|média|media~iu', $lab)) $company = $val; }
        $people[] = ['name' => trim($t['first_name'] . ' ' . $t['last_name']), 'role' => $t['rate_name'], 'company' => $company, 'code' => $t['code']];
    }
    $rates = all('SELECT id, name FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    $format = in_array(input('format'), ['a4', 'label'], true) ? (string)input('format') : 'a4';
    if (input('print')) return view('pro/badges_print', compact('ev', 'people', 'format'));
    return ev_page($ev, 'badges', 'Badges', 'badges', compact('people', 'rates', 'src', 'rate', 'q', 'format'));
}

// ---------- Borne libre-service ----------
function kiosk_start(string $slug): never
{
    $u = require_org();
    $ev = one('SELECT * FROM events WHERE slug = ? AND organizer_id = ?', [$slug, $u['id']]);
    if (!$ev) abort(404);
    $k = ['slug' => $ev['slug'], 'pin' => preg_match('~^\d{4}$~', (string)input('pin')) ? (string)input('pin') : '0000', 'idle' => max(30, min(600, (int)input('idle', 90)))];
    Audit::log('kiosk.start', $ev['name'], (int)$ev['id']);
    logout_user(); // la borne ne garde jamais la session de l'organisateur
    $_SESSION['kiosk'] = $k;
    redirect('/e/' . $ev['slug']);
}

function kiosk_exit(): string
{
    $k = $_SESSION['kiosk'] ?? null;
    if (!$k) redirect('/');
    if (is_post()) {
        if ((string)input('pin') !== $k['pin']) back(['error' => 'Code incorrect.']);
        unset($_SESSION['kiosk']);
        redirect('/connexion', ['success' => 'Mode borne désactivé. Reconnecte-toi pour revenir à ton espace.']);
    }
    return view('layout_public', ['title' => 'Quitter la borne', 'content' => '<form method="post" class="card auth-box center stack">' . csrf_field() . '<h1>Quitter le mode borne</h1><p class="muted">Code à 4 chiffres choisi au lancement.</p><input name="pin" inputmode="numeric" maxlength="4" autofocus style="text-align:center;font-size:28px;letter-spacing:8px"><button class="btn">Quitter</button><a class="btn btn-ghost" href="' . url('/e/' . $k['slug']) . '">Revenir à la borne</a></form>']);
}

// ---------- Contacts : import et doublons ----------
function contacts(): string
{
    $org = require_org();
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'import') {
            if (!checked('consent')) back(['error' => 'Confirme que ces personnes ont accepté de recevoir tes e-mails.']);
            $txt = (string)input('paste');
            if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) $txt .= "\n" . (string)file_get_contents($_FILES['file']['tmp_name'], false, null, 0, 5_000_000);
            $txt = preg_replace('~^\xEF\xBB\xBF~', '', $txt);
            $n = 0; $skip = 0; $tag = mb_substr(trim((string)input('source')) ?: 'import', 0, 30);
            foreach (preg_split('~\r\n|\r|\n~', $txt) as $line) {
                if (!preg_match('~[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}~i', $line, $m)) continue;
                $email = strtolower($m[0]);
                $cells = array_map('trim', str_getcsv(str_replace("\t", ';', $line), str_contains($line, ';') || str_contains($line, "\t") ? ';' : ','));
                $name = trim(implode(' ', array_filter($cells, fn($c) => $c !== '' && !str_contains($c, '@') && !preg_match('~^[\d +().-]+$~', $c))));
                if (val('SELECT 1 FROM email_unsubscribes WHERE organizer_id = ? AND email = ?', [$org['id'], $email]) || val('SELECT id FROM site_contacts WHERE organizer_id = ? AND email = ?', [$org['id'], $email])) { $skip++; continue; }
                insert('site_contacts', ['organizer_id' => $org['id'], 'email' => $email, 'name' => mb_substr($name, 0, 190) ?: null, 'source' => $tag, 'created_at' => now()]);
                $n++;
            }
            Audit::log('contacts.imported', $n . ' contacts');
            back(['success' => $n . ' contact(s) importé(s)' . ($skip ? ', ' . $skip . ' déjà présent(s) ou désinscrit(s)' : '') . '. Ils reçoivent les campagnes envoyées aux inscrits aux actus.']);
        }
        if ($a === 'merge') {
            $keep = strtolower(trim((string)input('keep')));
            $drop = array_values(array_filter(array_map(fn($e) => strtolower(trim((string)$e)), (array)($_POST['drop'] ?? [])), fn($e) => $e !== '' && $e !== $keep));
            if (!is_email($keep) || !$drop) back(['error' => 'Choisis l\'adresse à garder.']);
            $in = implode(',', array_fill(0, count($drop), '?'));
            $n = q("UPDATE orders SET email = ? WHERE organizer_id = ? AND LOWER(email) IN ($in)", array_merge([$keep, $org['id']], $drop))->rowCount();
            q("DELETE FROM site_contacts WHERE organizer_id = ? AND email IN ($in)", array_merge([$org['id']], $drop));
            Audit::log('contacts.merged', implode(', ', $drop) . ' → ' . $keep);
            back(['success' => 'Fusionné : ' . $n . ' commande(s) rattachée(s) à ' . $keep . '.']);
        }
        if ($a === 'delete') { q('DELETE FROM site_contacts WHERE organizer_id = ? AND id = ?', [$org['id'], (int)input('id')]); back(); }
        back();
    }
    // doublons : même nom et prénom, e-mails différents ; ou même e-mail à la casse / aux points Gmail près
    $dups = [];
    foreach (all("SELECT LOWER(TRIM(first_name)) fn, LOWER(TRIM(last_name)) ln, GROUP_CONCAT(DISTINCT LOWER(email) ORDER BY id SEPARATOR '|') emails, COUNT(DISTINCT LOWER(email)) ne, COUNT(*) n, MAX(first_name) f, MAX(last_name) l
        FROM orders WHERE organizer_id = ? AND status IN ('paid','refunded') AND first_name <> '' AND last_name <> '' AND email <> '' GROUP BY fn, ln HAVING ne > 1 ORDER BY n DESC LIMIT 40", [$org['id']]) as $r)
        $dups[] = ['why' => 'Même nom', 'name' => $r['f'] . ' ' . $r['l'], 'emails' => explode('|', $r['emails'])];
    $norm = [];
    foreach (all("SELECT DISTINCT LOWER(email) e FROM orders WHERE organizer_id = ? AND email LIKE '%@gmail.com' AND email LIKE '%.%@%' LIMIT 5000", [$org['id']]) as $r) {
        [$u, $d] = explode('@', $r['e']); $k = str_replace('.', '', explode('+', $u)[0]) . '@' . $d; $norm[$k][] = $r['e'];
    }
    foreach ($norm as $k => $list) { $all = array_unique(array_merge($list, (int)val('SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND LOWER(email) = ?', [$org['id'], $k]) ? [$k] : [])); if (count($all) > 1) $dups[] = ['why' => 'Même adresse Gmail', 'name' => '', 'emails' => array_values($all)]; }
    foreach ($dups as &$d) $d['counts'] = array_map(fn($e) => (int)val('SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND LOWER(email) = ?', [$org['id'], $e]), $d['emails']);
    unset($d);
    $imported = all('SELECT * FROM site_contacts WHERE organizer_id = ? ORDER BY id DESC LIMIT 100', [$org['id']]);
    $nImported = (int)val('SELECT COUNT(*) FROM site_contacts WHERE organizer_id = ? AND unsub_at IS NULL', [$org['id']]);
    $sources = all('SELECT source, COUNT(*) n FROM site_contacts WHERE organizer_id = ? GROUP BY source ORDER BY n DESC', [$org['id']]);
    return pro_page('Contacts', 'contacts', compact('dups', 'imported', 'nImported', 'sources'), 'contacts');
}

// ---------- Appli mobile organisateur (PWA) ----------
function mobile(): string
{
    $org = require_org();
    $today = one("SELECT COUNT(*) n, COALESCE(SUM(total), 0) t FROM orders WHERE organizer_id = ? AND status = 'paid' AND paid_at >= CURDATE()", [$org['id']]);
    $yday = one("SELECT COUNT(*) n, COALESCE(SUM(total), 0) t FROM orders WHERE organizer_id = ? AND status = 'paid' AND paid_at >= CURDATE() - INTERVAL 1 DAY AND paid_at < CURDATE()", [$org['id']]);
    $events = all("SELECT e.id, e.name, e.starts_at, e.capacity, e.status, (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid') sold,
        (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid' AND t.inside = 1) inside, (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.event_id = e.id AND o.status = 'paid' AND o.paid_at >= CURDATE()) today
        FROM events e WHERE e.organizer_id = ? AND e.status IN ('published','closed') AND COALESCE(e.ends_at, e.starts_at) >= NOW() - INTERVAL 1 DAY ORDER BY e.starts_at LIMIT 8", [$org['id']]);
    $last = all("SELECT o.ref, o.first_name, o.last_name, o.total, o.paid_at, e.name ev, (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id) nt FROM orders o JOIN events e ON e.id = o.event_id WHERE o.organizer_id = ? AND o.status = 'paid' ORDER BY o.paid_at DESC LIMIT 12", [$org['id']]);
    if (input('json')) json_out(['today' => $today, 'last' => $last[0]['ref'] ?? null]);
    return view('pro/mobile', compact('org', 'today', 'yday', 'events', 'last'));
}

function mobile_manifest(): never
{
    header('Content-Type: application/manifest+json');
    echo json_encode(['name' => (string)setting('site_name', 'Entrevo') . ' Pro', 'short_name' => 'Entrevo Pro', 'start_url' => url('/pro/mobile'), 'scope' => url('/pro/'), 'display' => 'standalone', 'background_color' => '#f5f5f7', 'theme_color' => '#1d1d1f',
        'icons' => [['src' => url('/caisse/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'], ['src' => url('/caisse/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png']]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- Rôles personnalisés (appelé depuis la page Équipe) ----------
function custom_roles_post(array $org, string $a): void
{
    if ($a === 'crole_save') {
        $name = mb_substr(trim((string)input('crole_name')), 0, 80);
        $secs = array_values(array_intersect((array)($_POST['crole_sections'] ?? []), array_keys(Extras::SECTIONS)));
        if ($name === '' || !$secs) back(['error' => 'Donne un nom au rôle et coche au moins une rubrique.']);
        $evs = implode(',', array_filter(array_map('intval', (array)($_POST['crole_events'] ?? [])))) ?: null;
        $rid = (int)input('crole_id');
        if ($rid && val('SELECT id FROM org_roles WHERE id = ? AND organizer_id = ?', [$rid, $org['id']])) update('org_roles', ['name' => $name, 'sections' => implode(',', $secs), 'event_ids' => $evs], 'id = ?', [$rid]);
        else insert('org_roles', ['organizer_id' => $org['id'], 'name' => $name, 'sections' => implode(',', $secs), 'event_ids' => $evs, 'created_at' => now()]);
        Audit::log('team.role', $name);
        redirect('/pro/equipe', ['success' => 'Rôle « ' . $name . ' » enregistré. Attribue-le dans la liste de l\'équipe.']);
    }
    if ($a === 'crole_delete') {
        $rid = (int)input('crole_id');
        if (val('SELECT id FROM org_roles WHERE id = ? AND organizer_id = ?', [$rid, $org['id']])) { q('UPDATE users SET custom_role_id = NULL WHERE custom_role_id = ?', [$rid]); q('DELETE FROM org_roles WHERE id = ?', [$rid]); }
        back(['success' => 'Rôle supprimé : les personnes concernées repassent gestionnaires.']);
    }
}
