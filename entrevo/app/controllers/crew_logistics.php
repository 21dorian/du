<?php
declare(strict_types=1);

/* 4.2 : logistique des équipes (dotations, documents signés, messages par poste, planning PDF), click & collect, stats du contrôle par opérateur. */

require_once APP . '/controllers/pro.php';

function crew_logistics(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    $evId = (int)$ev['id'];
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'auto_meals') back(['success' => 'Repas calculés pour ' . Crew::autoMeals($evId) . ' personne(s) : 1 repas par tranche de 4 h de créneau confirmé.']);
        if ($a === 'provisions') {
            $valid = array_map('intval', array_column(Crew::eventMembers($evId), 'id'));
            foreach ((array)($_POST['p'] ?? []) as $mid => $p) {
                if (!in_array((int)$mid, $valid, true) || !is_array($p)) continue;
                q('INSERT INTO staff_provisions (event_id, member_id, meals, meals_used, lodging, items, given_at) VALUES (?, ?, ?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE meals = VALUES(meals), meals_used = VALUES(meals_used), lodging = VALUES(lodging), items = VALUES(items), given_at = IF(VALUES(given_at) IS NULL, NULL, COALESCE(given_at, VALUES(given_at)))',
                    [$evId, (int)$mid, max(0, min(99, (int)($p['meals'] ?? 0))), max(0, min(99, (int)($p['used'] ?? 0))), mb_substr(trim((string)($p['lodging'] ?? '')), 0, 190) ?: null, mb_substr(trim((string)($p['items'] ?? '')), 0, 255) ?: null, !empty($p['given']) ? now() : null]);
            }
            back(['success' => 'Dotations enregistrées.']);
        }
        if ($a === 'meal') { // repas servi (cantine) : +1 consommé
            $p = one('SELECT * FROM staff_provisions WHERE event_id = ? AND member_id = ?', [$evId, (int)input('member_id')]);
            if (!$p) back(['error' => 'Aucune dotation pour cette personne.']);
            if ((int)$p['meals_used'] >= (int)$p['meals']) back(['error' => 'Tous les repas de cette personne ont déjà été servis.']);
            update('staff_provisions', ['meals_used' => (int)$p['meals_used'] + 1], 'id = ?', [$p['id']]);
            back(['success' => 'Repas servi (' . ((int)$p['meals_used'] + 1) . '/' . (int)$p['meals'] . ').']);
        }
        if ($a === 'doc') {
            $title = mb_substr(trim((string)input('title')), 0, 190); $body = trim((string)input('body'));
            if ($title === '' || mb_strlen($body) < 10) back(['error' => 'Donne un titre et le texte du document.']);
            insert('staff_docs', ['organizer_id' => $org['id'], 'event_id' => input('scope') === 'org' ? null : $evId, 'team' => mb_substr(trim((string)input('team')), 0, 80) ?: null, 'title' => $title, 'body' => $body, 'created_at' => now()]);
            back(['success' => 'Document publié : chaque personne concernée le signe depuis son espace bénévole.']);
        }
        if ($a === 'doc_delete') {
            $d = one('SELECT id FROM staff_docs WHERE id = ? AND organizer_id = ?', [(int)input('doc_id'), $org['id']]);
            if ($d && !val('SELECT COUNT(*) FROM staff_signatures WHERE doc_id = ?', [$d['id']])) q('DELETE FROM staff_docs WHERE id = ?', [$d['id']]);
            else back(['error' => 'Un document déjà signé ne peut plus être supprimé.']);
            back(['success' => 'Document supprimé.']);
        }
        if ($a === 'message') {
            $target = (string)input('target');
            $subject = mb_substr(trim((string)input('subject')), 0, 160); $body = trim((string)input('body'));
            if ($subject === '' || $body === '') back(['error' => 'Écris un objet et un message.']);
            $w = ["s.event_id = ?", "a.status = 'confirmed'"]; $p = [$evId];
            if (str_starts_with($target, 'team:')) { $w[] = 's.team = ?'; $p[] = substr($target, 5); }
            elseif (str_starts_with($target, 'shift:')) { $w[] = 's.id = ?'; $p[] = (int)substr($target, 6); }
            $to = all('SELECT DISTINCT m.* FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id JOIN staff_members m ON m.id = a.member_id WHERE ' . implode(' AND ', $w), $p);
            $n = 0; $fail = 0;
            foreach ($to as $m) {
                if (!is_email((string)$m['email'])) continue;
                $n++;
                try { Mailer::send((string)$m['email'], $subject, '<p>Bonjour ' . e($m['first_name']) . ',</p><div style="white-space:pre-line">' . e($body) . '</div><p><a href="' . e(Staff::portalUrl($m)) . '">Mon espace bénévole et mon planning</a></p><p style="color:#86868b">' . e($org['name']) . ' · ' . e($ev['name']) . '</p>', [], $org['email'] ?? null); }
                catch (Throwable $e) { $fail++; log_line('emails', 'Équipe : ' . $e->getMessage()); }
            }
            Audit::log('staff.message', $subject . ' → ' . $n, $evId);
            if (!$n) back(['error' => 'Personne avec un e-mail sur ce poste.']);
            back($fail ? ['error' => ($n - $fail) . " message(s) envoyé(s), $fail en échec : vérifie les réglages d'envoi des e-mails."] : ['success' => "Message envoyé à $n personne(s)."]);
        }
        back();
    }
    $members = Crew::eventMembers($evId);
    $docs = all('SELECT d.*, (SELECT COUNT(*) FROM staff_signatures g WHERE g.doc_id = d.id) signed FROM staff_docs d WHERE d.organizer_id = ? AND (d.event_id IS NULL OR d.event_id = ?) ORDER BY d.id DESC', [$org['id'], $evId]);
    $sigs = [];
    foreach (all('SELECT g.doc_id, g.member_id, g.signed_at FROM staff_signatures g JOIN staff_docs d ON d.id = g.doc_id WHERE d.organizer_id = ?', [$org['id']]) as $g) $sigs[$g['doc_id']][$g['member_id']] = $g['signed_at'];
    $shifts = all('SELECT id, title, team, starts_at FROM staff_shifts WHERE event_id = ? ORDER BY starts_at', [$evId]);
    $teams = array_values(array_unique(array_filter(array_column($shifts, 'team'))));
    $tot = ['meals' => array_sum(array_map(fn($m) => (int)$m['meals'], $members)), 'used' => array_sum(array_map(fn($m) => (int)$m['meals_used'], $members)), 'lodging' => count(array_filter($members, fn($m) => !empty($m['lodging'])))];
    return ev_page($ev, 'crew', 'Logistique des équipes', 'crew', compact('org', 'ev', 'members', 'docs', 'sigs', 'shifts', 'teams', 'tot'));
}

function crew_planning_pdf(string $id, string $mid): never
{
    $org = require_org();
    $ev = org_event((int)$id);
    $m = one('SELECT * FROM staff_members WHERE id = ? AND organizer_id = ?', [(int)$mid, $org['id']]) ?: abort(404);
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="planning-' . slugify($m['first_name'] . '-' . $m['last_name']) . '.pdf"');
    echo Crew::planningPdf($m, (int)$ev['id']); exit;
}

function crew_doc_signatures(string $id, string $did): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    $d = one('SELECT * FROM staff_docs WHERE id = ? AND organizer_id = ?', [(int)$did, $org['id']]) ?: abort(404);
    $rows = all('SELECT g.*, m.first_name, m.last_name, m.team FROM staff_signatures g JOIN staff_members m ON m.id = g.member_id WHERE g.doc_id = ? ORDER BY g.signed_at', [$d['id']]);
    if (input('format') === 'csv') csv_download('signatures-' . $d['id'] . '.csv', array_merge([['Nom', 'Équipe', 'Signé le', 'Nom saisi', 'IP', 'Empreinte du document']], array_map(fn($r) => [$r['first_name'] . ' ' . $r['last_name'], $r['team'], $r['signed_at'], $r['name'], $r['ip'], $r['hash']], $rows)));
    $html = '<div class="page-head"><div><p class="muted" style="margin-top:0">' . count($rows) . ' signature(s) · <a href="' . url('/pro/evenements/' . $ev['id'] . '/equipes') . '">Retour</a> · <a href="?format=csv">Export CSV</a></p></div></div>'
        . '<div class="card"><div style="white-space:pre-line">' . e($d['body']) . '</div></div><div class="card table-card mt"><table class="table"><thead><tr><th>Personne</th><th>Équipe</th><th>Signé le</th><th>Nom saisi</th><th>IP</th></tr></thead><tbody>';
    foreach ($rows as $r) $html .= '<tr><td>' . e($r['first_name'] . ' ' . $r['last_name']) . '</td><td>' . e((string)$r['team']) . '</td><td>' . e(short_date($r['signed_at'])) . '</td><td>' . e($r['name']) . '</td><td class="muted">' . e((string)$r['ip']) . '</td></tr>';
    return ev_page($ev, 'crew', $d['title'], '_raw', ['html' => $html . '</tbody></table></div>']);
}

function click_collect_admin(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    $bars = all('SELECT * FROM bars WHERE event_id = ? ORDER BY name', [$ev['id']]);
    if (is_post() && input('action') === 'cancel_order') { // 4.4 : annuler une commande click & collect (recréditée sur le porte-monnaie)
        $s = one("SELECT id FROM pos_sales WHERE id = ? AND event_id = ? AND source = 'collect'", [(int)input('sale_id'), $ev['id']]);
        try { if (!$s) throw new RuntimeException('Commande introuvable.'); $r = PayAdmin::cancelSale((int)$ev['id'], (int)$s['id'], null, (string)input('reason') ?: 'Annulée par l\'organisation'); back(['success' => $r['message']]); }
        catch (RuntimeException $e) { back(['error' => $e->getMessage()]); }
    }
    if (is_post()) {
        $ids = array_values(array_intersect(array_map('intval', (array)($_POST['bars'] ?? [])), array_map('intval', array_column($bars, 'id'))));
        update('events', ['click_collect' => checked('click_collect'), 'click_collect_bars' => $ids ? implode(',', $ids) : null], 'id = ?', [$ev['id']]);
        back(['success' => checked('click_collect') ? 'Click & collect activé : les festivaliers commandent depuis leur carte cashless.' : 'Click & collect désactivé.']);
    }
    $orders = all("SELECT s.*, b.name bar FROM pos_sales s JOIN bars b ON b.id = s.bar_id WHERE s.event_id = ? AND s.source = 'collect' ORDER BY s.id DESC LIMIT 100", [$ev['id']]);
    $kpi = one("SELECT COUNT(*) n, COALESCE(SUM(amount), 0) total, SUM(prep_status = 'new') waiting, AVG(CASE WHEN ready_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, ready_at) END) prep FROM pos_sales WHERE event_id = ? AND source = 'collect' AND undone = 0", [$ev['id']]);
    $sel = array_filter(array_map('intval', explode(',', (string)$ev['click_collect_bars'])));
    return ev_page($ev, 'collect', 'Click & collect', 'click_collect', compact('ev', 'bars', 'orders', 'kpi', 'sel'));
}

function access_operators(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    $rows = Crew::operatorStats((int)$ev['id']);
    if (input('format') === 'csv') csv_download('controle-operateurs-' . $ev['slug'] . '.csv', array_merge([['Opérateur', 'Scans', 'Entrées', 'Sorties', 'Refus', 'Hors ligne', 'Points', 'Premier scan', 'Dernier scan']],
        array_map(fn($r) => [$r['who'], $r['n'], $r['ok'], $r['exits'], $r['refused'], $r['offline'], $r['point_names'], $r['first_at'], $r['last_at']], $rows)));
    return ev_page($ev, 'operators', 'Contrôle par opérateur', 'access_operators', compact('ev', 'rows'));
}
