<?php
declare(strict_types=1);

/* Staff & bénévoles côté organisateur : annuaire, fiche, planning d'un événement, pointage, export. */

require_once APP . '/controllers/pro.php';

// ---------- Annuaire ----------
function staff_directory(): string
{
    $org = require_org();
    $oid = (int)$org['id'];
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'add') {
            if (trim((string)input('first_name')) === '' || trim((string)input('last_name')) === '') back(['error' => 'Prénom et nom, au minimum 😉']);
            if (input('email') !== '' && !is_email((string)input('email'))) back(['error' => 'Cet e-mail a l\'air bancal 🤔']);
            $id = Staff::upsertMember($oid, $_POST);
            Audit::log('staff.added', trim(input('first_name') . ' ' . input('last_name')));
            if (input('send') && ($m = Staff::member($oid, $id))) { try { Staff::sendPlanning($m); } catch (Throwable $e) { back(['warning' => 'Ajouté, mais l\'e-mail n\'est pas parti : ' . $e->getMessage()]); } }
            back(['success' => '🙌 Bienvenue dans l\'équipe !']);
        }
        if ($a === 'import') {
            $n = 0; $bad = 0;
            foreach (preg_split('~\R~', (string)input('lines')) as $line) {
                $c = array_map('trim', str_getcsv(str_replace("\t", ';', $line), str_contains($line, ';') || str_contains($line, "\t") ? ';' : ','));
                if (count($c) < 2 || $c[0] === '' || mb_strtolower($c[0]) === 'prénom' || mb_strtolower($c[0]) === 'prenom') { if (trim($line) !== '') $bad++; continue; }
                $email = $c[2] ?? '';
                if ($email !== '' && !is_email($email)) { $bad++; continue; }
                Staff::upsertMember($oid, ['first_name' => $c[0], 'last_name' => $c[1], 'email' => $email, 'phone' => $c[3] ?? '', 'team' => $c[4] ?? '']);
                $n++;
            }
            Audit::log('staff.added', "Import : $n personne(s)");
            back([$n ? 'success' : 'error' => "📥 $n personne(s) importée(s)" . ($bad ? ", $bad ligne(s) ignorée(s)." : '.')]);
        }
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
        if ($ids && in_array($a, ['archive', 'activate', 'send'], true)) {
            $in = implode(',', $ids); $n = 0;
            if ($a === 'send') {
                foreach (all("SELECT * FROM staff_members WHERE organizer_id = ? AND id IN ($in)", [$oid]) as $m) { try { if (Staff::sendPlanning($m)) $n++; } catch (Throwable $e) { log_line('emails', $e->getMessage()); } }
                back(['success' => "📬 Lien d'accès envoyé à $n personne(s)."]);
            }
            $n = q("UPDATE staff_members SET status = ? WHERE organizer_id = ? AND id IN ($in)", [$a === 'archive' ? 'archived' : 'active', $oid])->rowCount();
            back(['success' => $a === 'archive' ? "🗄️ $n personne(s) archivée(s)." : "✅ $n personne(s) activée(s)."]);
        }
        back(['error' => 'Coche au moins une personne 😉']);
    }
    $f = ['q' => trim((string)input('q')), 'team' => (string)input('team'), 'st' => (string)input('st')];
    $w = 'm.organizer_id = ?'; $p = [$oid];
    if ($f['q'] !== '') { $w .= ' AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.email LIKE ? OR m.phone LIKE ? OR m.skills LIKE ?)'; array_push($p, ...array_fill(0, 5, '%' . $f['q'] . '%')); }
    if ($f['team'] !== '') { $w .= ' AND m.team = ?'; $p[] = $f['team']; }
    if (isset(Staff::MEMBER_STATUS[$f['st']])) { $w .= ' AND m.status = ?'; $p[] = $f['st']; } else $w .= " AND m.status <> 'archived'";
    $list = all("SELECT m.*,
            (SELECT COUNT(*) FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id WHERE a.member_id = m.id AND a.status = 'confirmed' AND s.ends_at >= NOW()) upcoming,
            (SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.checked_in_at, COALESCE(a.checked_out_at, s.ends_at))),0) FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id WHERE a.member_id = m.id AND a.checked_in_at IS NOT NULL) minutes
        FROM staff_members m WHERE $w ORDER BY m.status = 'candidate' DESC, m.last_name, m.first_name LIMIT 500", $p);
    $teams = array_column(all("SELECT team, COUNT(*) n FROM staff_members WHERE organizer_id = ? AND team IS NOT NULL AND team <> '' AND status <> 'archived' GROUP BY team ORDER BY n DESC", [$oid]), 'n', 'team');
    $kpi = one("SELECT SUM(status = 'active') active, SUM(status = 'candidate') candidates FROM staff_members WHERE organizer_id = ?", [$oid]);
    $kpi['shifts'] = (int)val("SELECT COUNT(*) FROM staff_shifts s JOIN events e ON e.id = s.event_id WHERE e.organizer_id = ? AND s.ends_at >= NOW()", [$oid]);
    $kpi['holes'] = (int)val("SELECT COALESCE(SUM(GREATEST(0, s.needed - (SELECT COUNT(*) FROM staff_assignments a WHERE a.shift_id = s.id AND a.status = 'confirmed'))),0)
        FROM staff_shifts s JOIN events e ON e.id = s.event_id WHERE e.organizer_id = ? AND s.ends_at >= NOW()", [$oid]);
    $events = all("SELECT e.id, e.name, e.starts_at, (SELECT COUNT(*) FROM staff_shifts s WHERE s.event_id = e.id) shifts FROM events e WHERE e.organizer_id = ? AND COALESCE(e.ends_at, e.starts_at) >= NOW() - INTERVAL 1 DAY ORDER BY e.starts_at LIMIT 8", [$oid]);
    return pro_page('Staff & bénévoles', 'staff/directory', compact('list', 'teams', 'f', 'kpi', 'events'), 'staff');
}

// ---------- Fiche ----------
function staff_member_page(string $id): string
{
    $org = require_org();
    $m = Staff::member((int)$org['id'], (int)$id);
    if (!$m) abort(404);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'save') {
            $d = [];
            foreach (['first_name' => 80, 'last_name' => 80, 'phone' => 40, 'team' => 80, 'skills' => 255, 'tshirt' => 10] as $k => $len) $d[$k] = mb_substr(trim((string)input($k)), 0, $len) ?: null;
            $d['email'] = strtolower(trim((string)input('email'))) ?: null;
            $d['notes'] = trim((string)input('notes')) ?: null;
            if (!$d['first_name'] || !$d['last_name']) back(['error' => 'Prénom et nom obligatoires.']);
            if ($d['email'] && !is_email($d['email'])) back(['error' => 'Cet e-mail a l\'air bancal 🤔']);
            if (isset(Staff::MEMBER_STATUS[(string)input('status')])) $d['status'] = (string)input('status');
            update('staff_members', $d, 'id = ?', [$m['id']]);
            back(['success' => '✅ Fiche enregistrée.']);
        }
        if ($a === 'send') {
            try { Staff::sendPlanning($m) ? back(['success' => '📬 Planning et lien envoyés à ' . $m['email'] . '.']) : back(['error' => 'Pas d\'e-mail valide sur cette fiche.']); }
            catch (Throwable $e) { back(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
        }
        if ($a === 'new_link') { update('staff_members', ['token' => token(24)], 'id = ?', [$m['id']]); back(['success' => '🔑 Nouveau lien créé : l\'ancien (et l\'ancien badge) ne marchent plus.']); }
        if ($a === 'delete') {
            q('DELETE FROM staff_assignments WHERE member_id = ?', [$m['id']]);
            q('DELETE FROM staff_members WHERE id = ?', [$m['id']]);
            Audit::log('staff.removed', $m['first_name'] . ' ' . $m['last_name']);
            redirect('/pro/benevoles', ['success' => '🗑️ Fiche supprimée.']);
        }
        back();
    }
    $shifts = Staff::memberShifts((int)$m['id']);
    $planned = 0; $done = 0;
    foreach ($shifts as $s) if ($s['status'] === 'confirmed') { [$pl, $dn] = Staff::minutes($s); $planned += $pl; $done += $dn; }
    $teams = array_column(all("SELECT DISTINCT team FROM staff_members WHERE organizer_id = ? AND team IS NOT NULL AND team <> ''", [$org['id']]), 'team');
    return view('layout_back', ['title' => $m['first_name'] . ' ' . $m['last_name'], 'area' => 'pro', 'active' => 'staff',
        'crumbs' => [['Staff & bénévoles', '/pro/benevoles']],
        'content' => view('pro/staff/member', compact('m', 'shifts', 'planned', 'done', 'teams'))]);
}

// ---------- Planning d'un événement ----------
function staff_event(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    $base = '/pro/evenements/' . $ev['id'] . '/benevoles';
    if (is_post()) staff_event_action($org, $ev);
    $shifts = Staff::shifts((int)$ev['id']);
    $asg = Staff::assignments((int)$ev['id']);
    $members = all("SELECT id, first_name, last_name, team, status FROM staff_members WHERE organizer_id = ? AND status <> 'archived' ORDER BY last_name, first_name", [$org['id']]);
    $teams = array_values(array_unique(array_filter(array_merge(array_column($shifts, 'team'), array_column($members, 'team')))));
    sort($teams);
    $needed = array_sum(array_column($shifts, 'needed'));
    $confirmed = array_sum(array_column($shifts, 'confirmed'));
    $pending = (int)val("SELECT COUNT(*) FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id WHERE s.event_id = ? AND a.status = 'requested'", [$ev['id']]);
    $people = (int)val("SELECT COUNT(DISTINCT a.member_id) FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id WHERE s.event_id = ? AND a.status = 'confirmed'", [$ev['id']]);
    $hours = (int)val("SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.starts_at, s.ends_at)),0) FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id WHERE s.event_id = ? AND a.status = 'confirmed'", [$ev['id']]);
    $edit = input('modifier') ? one('SELECT * FROM staff_shifts WHERE id = ? AND event_id = ?', [(int)input('modifier'), $ev['id']]) : null;
    $view = input('vue') === 'grille' ? 'grid' : 'list';
    return ev_page($ev, 'staff', 'Bénévoles', 'staff/event', compact('shifts', 'asg', 'members', 'teams', 'needed', 'confirmed', 'pending', 'people', 'hours', 'edit', 'base', 'view'));
}

function staff_event_action(array $org, array $ev): never
{
    $a = (string)input('action');
    $evId = (int)$ev['id'];
    $shift = input('shift_id') ? one('SELECT * FROM staff_shifts WHERE id = ? AND event_id = ?', [(int)input('shift_id'), $evId]) : null;
    switch ($a) {
        case 'settings':
            update('events', ['volunteer_recruit' => checked('volunteer_recruit'), 'volunteer_intro' => trim((string)input('volunteer_intro')) ?: null], 'id = ?', [$evId]);
            back(['success' => checked('volunteer_recruit') ? '📣 Recrutement ouvert : partage le lien !' : '✅ Enregistré.']);
        case 'shift':
            $date = (string)input('date');
            $st = strtotime($date . ' ' . input('start')); $en = strtotime($date . ' ' . input('end'));
            if (!$st || !$en || trim((string)input('title')) === '') back(['error' => 'Il faut un intitulé, une date, une heure de début et de fin.']);
            if ($en <= $st) $en += 86400; // créneau de nuit (22:00 → 02:00)
            $d = ['event_id' => $evId, 'title' => mb_substr(trim((string)input('title')), 0, 120), 'team' => mb_substr(trim((string)input('team')), 0, 80) ?: null,
                'location' => mb_substr(trim((string)input('location')), 0, 120) ?: null, 'starts_at' => date('Y-m-d H:i:s', $st), 'ends_at' => date('Y-m-d H:i:s', $en),
                'needed' => max(1, min(500, (int)input('needed', 1))), 'description' => trim((string)input('description')) ?: null, 'open_signup' => checked('open_signup')];
            $repeat = max(1, min(30, (int)input('repeat', 1)));
            if ($shift) { unset($d['event_id']); update('staff_shifts', $d, 'id = ?', [$shift['id']]); redirect('/pro/evenements/' . $evId . '/benevoles', ['success' => '✅ Créneau modifié.']); }
            // « répéter » : crée N créneaux consécutifs de même durée (ex. 4 × 2 h au bar)
            $len = $en - $st;
            for ($i = 0; $i < $repeat; $i++) {
                $d['starts_at'] = date('Y-m-d H:i:s', $st + $i * $len); $d['ends_at'] = date('Y-m-d H:i:s', $en + $i * $len); $d['created_at'] = now();
                insert('staff_shifts', $d);
            }
            Audit::log('staff.shift', $d['title'] . ($repeat > 1 ? " × $repeat" : ''), $evId);
            back(['success' => $repeat > 1 ? "🗓️ $repeat créneaux créés." : '🗓️ Créneau créé.']);
        case 'delete_shift':
            if ($shift) { q('DELETE FROM staff_assignments WHERE shift_id = ?', [$shift['id']]); q('DELETE FROM staff_shifts WHERE id = ?', [$shift['id']]); }
            back(['success' => '🗑️ Créneau supprimé.']);
        case 'duplicate_shift':
            if ($shift) { $c = $shift; unset($c['id']); $c['created_at'] = now(); insert('staff_shifts', $c); }
            back(['success' => '📑 Créneau dupliqué.']);
        case 'assign':
            if (!$shift) back();
            $ids = array_values(array_filter(array_map('intval', (array)($_POST['member_ids'] ?? [input('member_id')]))));
            $st = input('confirm') ? 'confirmed' : 'proposed';
            $n = 0; $warn = [];
            foreach ($ids as $mid) {
                $m = Staff::member((int)$org['id'], $mid);
                if (!$m) continue;
                if ($c = Staff::conflict($mid, $shift)) $warn[] = $m['first_name'] . ' est déjà sur « ' . $c . ' »';
                Staff::assign((int)$shift['id'], $mid, $st);
                if ($m['status'] === 'candidate') update('staff_members', ['status' => 'active'], 'id = ?', [$mid]);
                $n++;
            }
            back([$warn ? 'warning' : 'success' => "👍 $n personne(s) placée(s) sur « {$shift['title']} »." . ($warn ? ' Attention : ' . implode(' ; ', $warn) . '.' : '')]);
        case 'set_status':
            $as = one('SELECT a.* FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id WHERE a.id = ? AND s.event_id = ?', [(int)input('assignment_id'), $evId]);
            $st = (string)input('status');
            if ($as && $st === 'remove') { q('DELETE FROM staff_assignments WHERE id = ?', [$as['id']]); back(['success' => 'Retiré du créneau.']); }
            if ($as && isset(Staff::STATUS[$st])) {
                update('staff_assignments', ['status' => $st], 'id = ?', [$as['id']]);
                if ($st === 'confirmed') q("UPDATE staff_members SET status = 'active' WHERE id = ? AND status = 'candidate'", [$as['member_id']]);
            }
            back();
        case 'accept_all':
            $n = q("UPDATE staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id SET a.status = 'confirmed' WHERE s.event_id = ? AND a.status = 'requested'", [$evId])->rowCount();
            q("UPDATE staff_members m JOIN staff_assignments a ON a.member_id = m.id JOIN staff_shifts s ON s.id = a.shift_id SET m.status = 'active' WHERE s.event_id = ? AND a.status = 'confirmed' AND m.status = 'candidate'", [$evId]);
            back(['success' => "✅ $n candidature(s) acceptée(s)."]);
        case 'send_all':
            $members = all("SELECT DISTINCT m.* FROM staff_members m JOIN staff_assignments a ON a.member_id = m.id JOIN staff_shifts s ON s.id = a.shift_id WHERE s.event_id = ? AND a.status <> 'declined'", [$evId]);
            $n = 0; $intro = trim((string)input('intro'));
            foreach ($members as $m) { try { if (Staff::sendPlanning($m, $evId, $intro)) $n++; } catch (Throwable $e) { log_line('emails', $e->getMessage()); } }
            Audit::log('message.sent', "Plannings bénévoles : $n e-mail(s)", $evId);
            back(['success' => "📬 Planning envoyé à $n bénévole(s)."]);
    }
    back();
}

// ---------- Pointage ----------
function staff_checkin(string $id): string
{
    $org = require_org(['owner', 'manager', 'scanner']);
    $ev = org_event((int)$id);
    if (is_post()) {
        if (input('badge') !== '') $r = Staff::checkByBadge((int)$ev['id'], (string)input('badge'));
        else {
            $a = one('SELECT a.*, s.title, m.first_name, m.last_name FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id JOIN staff_members m ON m.id = a.member_id WHERE a.id = ? AND s.event_id = ?', [(int)input('assignment_id'), $ev['id']]);
            if ($a && input('undo')) { update('staff_assignments', ['checked_in_at' => null, 'checked_out_at' => null], 'id = ?', [$a['id']]); $r = ['ok' => true, 'msg' => '↩️ Pointage annulé.']; }
            else $r = $a ? Staff::toggle($a, $a['first_name'] . ' ' . $a['last_name']) : ['ok' => false, 'msg' => 'Introuvable.'];
        }
        if (input('ajax')) json_out($r);
        back([$r['ok'] ? 'success' : 'error' => $r['msg']]);
    }
    $day = (string)input('jour') ?: date('Y-m-d');
    $days = array_column(all('SELECT DISTINCT DATE(starts_at) d FROM staff_shifts WHERE event_id = ? ORDER BY d', [$ev['id']]), 'd');
    if (!in_array($day, $days, true) && $days) $day = in_array(date('Y-m-d'), $days, true) ? date('Y-m-d') : $days[0];
    $rows = all("SELECT a.*, s.title, s.team, s.location, s.starts_at, s.ends_at, m.first_name, m.last_name, m.phone FROM staff_assignments a
        JOIN staff_shifts s ON s.id = a.shift_id JOIN staff_members m ON m.id = a.member_id
        WHERE s.event_id = ? AND DATE(s.starts_at) = ? AND a.status IN ('confirmed','proposed') ORDER BY s.starts_at, s.title, m.last_name", [$ev['id'], $day]);
    return ev_page($ev, 'staff', 'Pointage bénévoles', 'staff/checkin', compact('rows', 'day', 'days'));
}

function staff_export(string $id): never
{
    require_org();
    $ev = org_event((int)$id);
    $rows = [['Créneau', 'Équipe', 'Lieu', 'Début', 'Fin', 'Prénom', 'Nom', 'E-mail', 'Téléphone', 'Statut', 'Arrivée', 'Départ', 'Heures prévues', 'Heures pointées']];
    foreach (all("SELECT a.*, s.title, s.team, s.location, s.starts_at, s.ends_at, m.first_name, m.last_name, m.email, m.phone FROM staff_assignments a
        JOIN staff_shifts s ON s.id = a.shift_id JOIN staff_members m ON m.id = a.member_id WHERE s.event_id = ? ORDER BY s.starts_at, s.title, m.last_name", [$ev['id']]) as $a) {
        [$pl, $dn] = Staff::minutes($a);
        $rows[] = [$a['title'], $a['team'], $a['location'], $a['starts_at'], $a['ends_at'], $a['first_name'], $a['last_name'], $a['email'], $a['phone'], Staff::STATUS[$a['status']][0],
            $a['checked_in_at'], $a['checked_out_at'], number_format($pl / 60, 2, ',', ''), number_format($dn / 60, 2, ',', '')];
    }
    csv_download('benevoles-' . $ev['slug'] . '.csv', $rows);
}
