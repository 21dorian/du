<?php
declare(strict_types=1);

/* Bénévoles côté public : candidature, espace perso (lien secret), badge QR. */

function volunteer_apply(string $slug): string
{
    $ev = one("SELECT * FROM events WHERE slug = ? AND status IN ('published','closed')", [$slug]);
    if (!$ev || !$ev['volunteer_recruit']) abort(404, 'Le recrutement de bénévoles n\'est pas ouvert pour cet événement.');
    $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
    $shifts = array_filter(Staff::shifts((int)$ev['id']), fn($s) => $s['open_signup'] && strtotime($s['ends_at']) > time());
    $errors = [];
    if (is_post()) {
        if (!empty($_POST['website'])) redirect('/e/' . $slug); // pot de miel anti-robots
        $d = ['first_name' => trim((string)input('first_name')), 'last_name' => trim((string)input('last_name')), 'email' => trim((string)input('email')), 'phone' => trim((string)input('phone')),
            'team' => trim((string)input('team')), 'skills' => trim((string)input('skills')), 'tshirt' => (string)input('tshirt'), 'notes' => trim((string)input('message'))];
        if ($d['first_name'] === '' || $d['last_name'] === '') $errors[] = 'Prénom et nom, s\'il te plaît.';
        if (!is_email($d['email'])) $errors[] = 'Il nous faut un e-mail valide pour te répondre.';
        if (!$errors) {
            $mid = Staff::upsertMember((int)$org['id'], $d, 'candidate');
            $m = one('SELECT * FROM staff_members WHERE id = ?', [$mid]);
            $picked = array_map('intval', (array)($_POST['shifts'] ?? []));
            foreach ($shifts as $s) if (in_array((int)$s['id'], $picked, true) && !val('SELECT id FROM staff_assignments WHERE shift_id = ? AND member_id = ?', [$s['id'], $mid])) Staff::assign((int)$s['id'], $mid, 'requested');
            try {
                Mailer::send($m['email'], '🙌 Candidature reçue — ' . $ev['name'], '<p>Merci ' . e($m['first_name']) . ' !</p><p>Ta candidature pour être bénévole à <b>' . e($ev['name']) . '</b> est bien arrivée. L\'équipe de ' . e($org['name']) . ' revient vers toi très vite.</p>'
                    . '<p>En attendant, ton espace bénévole : <a href="' . e(Staff::portalUrl($m)) . '">' . e(Staff::portalUrl($m)) . '</a></p>');
                if (is_email((string)$org['email'])) Mailer::send($org['email'], '🙋 Nouvelle candidature bénévole : ' . $m['first_name'] . ' ' . $m['last_name'], '<p>Pour « ' . e($ev['name']) . ' » · ' . count($picked) . ' créneau(x) demandé(s).</p><p><a href="' . e(abs_url('/pro/evenements/' . $ev['id'] . '/benevoles')) . '">Voir le planning</a></p>');
            } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            redirect('/benevole/' . $m['token'], ['success' => '🙌 Candidature envoyée ! Voici ton espace bénévole : garde bien ce lien.']);
        }
    }
    return view('layout_public', ['title' => 'Bénévoles · ' . $ev['name'], 'content' => view('public/volunteer_apply', compact('ev', 'org', 'shifts', 'errors'))]);
}

function volunteer_member(string $token): array
{
    $m = preg_match('~^[A-Za-z0-9_-]{10,40}$~', $token) ? one("SELECT * FROM staff_members WHERE token = ? AND status <> 'archived'", [$token]) : null;
    if (!$m) abort(404, 'Ce lien bénévole n\'existe pas ou plus. Demande un nouveau lien à l\'organisateur.');
    return $m;
}

function volunteer_portal(string $token): string
{
    $m = volunteer_member($token);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$m['organizer_id']]);
    if (is_post()) {
        $a = (string)input('action');
        $as = one('SELECT a.*, s.title, s.needed, s.starts_at, s.ends_at, s.id sid, s.open_signup FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id WHERE a.id = ? AND a.member_id = ?', [(int)input('assignment_id'), $m['id']]);
        if ($a === 'confirm' && $as) { update('staff_assignments', ['status' => 'confirmed'], 'id = ?', [$as['id']]); back(['success' => '👍 C\'est noté, merci !']); }
        if ($a === 'decline' && $as && !$as['checked_in_at']) { update('staff_assignments', ['status' => 'declined'], 'id = ?', [$as['id']]); back(['success' => 'OK, on prévient l\'équipe. Merci de nous avoir dit 🙏']); }
        if ($a === 'signup') {
            $s = one('SELECT s.* FROM staff_shifts s JOIN events e ON e.id = s.event_id WHERE s.id = ? AND e.organizer_id = ? AND s.open_signup = 1 AND s.ends_at > NOW()', [(int)input('shift_id'), $m['organizer_id']]);
            if (!$s) back(['error' => 'Ce créneau n\'est plus disponible.']);
            if (Staff::free($s) <= 0) back(['error' => 'Ce créneau est déjà complet 😅']);
            if ($c = Staff::conflict((int)$m['id'], $s)) back(['error' => 'Tu es déjà sur « ' . $c . ' » à ce moment-là.']);
            Staff::assign((int)$s['id'], (int)$m['id'], 'requested');
            back(['success' => '🙋 Demande envoyée : l\'organisateur va la valider.']);
        }
        back();
    }
    update('staff_members', ['last_seen_at' => now()], 'id = ?', [$m['id']]);
    $mine = array_filter(Staff::memberShifts((int)$m['id']), fn($s) => strtotime($s['ends_at']) > time() - 86400 || $s['checked_in_at']);
    $mineIds = array_column($mine, 'shift_id');
    $open = all("SELECT s.*, e.name ev_name, (SELECT COUNT(*) FROM staff_assignments a WHERE a.shift_id = s.id AND a.status IN ('confirmed','proposed')) taken
        FROM staff_shifts s JOIN events e ON e.id = s.event_id WHERE e.organizer_id = ? AND s.open_signup = 1 AND s.ends_at > NOW() ORDER BY s.starts_at LIMIT 60", [$m['organizer_id']]);
    $open = array_values(array_filter($open, fn($s) => !in_array($s['id'], $mineIds) && (int)$s['taken'] < (int)$s['needed']));
    $minutes = 0; foreach ($mine as $s) if ($s['status'] === 'confirmed') $minutes += Staff::minutes($s)[0];
    return view('layout_public', ['title' => 'Mon espace bénévole', 'content' => view('public/volunteer_portal', compact('m', 'org', 'mine', 'open', 'minutes'))]);
}

function volunteer_badge(string $token): string
{
    $m = volunteer_member($token);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$m['organizer_id']]);
    return view('public/volunteer_badge', compact('m', 'org'));
}
