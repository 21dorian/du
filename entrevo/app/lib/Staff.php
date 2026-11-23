<?php
declare(strict_types=1);

/**
 * Staff & bénévoles : annuaire par organisateur, créneaux (postes) par événement,
 * affectations, portail personnel (lien secret), pointage et rappels.
 *
 * Statuts d'affectation :
 *  - requested : le bénévole s'est proposé, l'organisateur doit valider
 *  - proposed  : l'organisateur l'a placé, le bénévole doit confirmer
 *  - confirmed : c'est calé
 *  - declined  : refusé (par l'un ou l'autre)
 */
final class Staff
{
    public const STATUS = ['requested' => ['Candidature', 'orange'], 'proposed' => ['À confirmer', 'grey'], 'confirmed' => ['Confirmé', 'green'], 'declined' => ['Décliné', 'red']];
    public const MEMBER_STATUS = ['active' => ['Actif', 'green'], 'candidate' => ['Candidat', 'orange'], 'archived' => ['Archivé', 'grey']];

    public static function badge(string $s, bool $member = false): string
    {
        [$l, $c] = ($member ? self::MEMBER_STATUS : self::STATUS)[$s] ?? [$s, 'grey'];
        return '<span class="badge ' . $c . '">' . e($l) . '</span>';
    }

    public static function portalUrl(array $m): string { return abs_url('/benevole/' . $m['token']); }

    public static function member(int $orgId, int $id): ?array
    {
        return one('SELECT * FROM staff_members WHERE id = ? AND organizer_id = ?', [$id, $orgId]);
    }

    /** Crée (ou retrouve par e-mail) un membre. */
    public static function upsertMember(int $orgId, array $d, string $status = 'active'): int
    {
        $email = strtolower(trim((string)($d['email'] ?? '')));
        if ($email !== '' && ($ex = one('SELECT * FROM staff_members WHERE organizer_id = ? AND email = ?', [$orgId, $email]))) {
            $up = [];
            foreach (['phone', 'team', 'skills', 'tshirt'] as $k) if (!empty($d[$k]) && empty($ex[$k])) $up[$k] = $d[$k];
            if ($ex['status'] === 'archived') $up['status'] = $status;
            if ($up) update('staff_members', $up, 'id = ?', [$ex['id']]);
            return (int)$ex['id'];
        }
        return insert('staff_members', [
            'organizer_id' => $orgId, 'first_name' => mb_substr(trim((string)$d['first_name']), 0, 80), 'last_name' => mb_substr(trim((string)$d['last_name']), 0, 80),
            'email' => $email ?: null, 'phone' => mb_substr(trim((string)($d['phone'] ?? '')), 0, 40) ?: null, 'team' => mb_substr(trim((string)($d['team'] ?? '')), 0, 80) ?: null,
            'skills' => mb_substr(trim((string)($d['skills'] ?? '')), 0, 255) ?: null, 'tshirt' => mb_substr(trim((string)($d['tshirt'] ?? '')), 0, 10) ?: null,
            'notes' => trim((string)($d['notes'] ?? '')) ?: null, 'status' => $status, 'token' => token(24), 'created_at' => now(),
        ]);
    }

    /** Créneaux d'un événement avec leurs compteurs. */
    public static function shifts(int $eventId): array
    {
        return all("SELECT s.*,
                (SELECT COUNT(*) FROM staff_assignments a WHERE a.shift_id = s.id AND a.status = 'confirmed') confirmed,
                (SELECT COUNT(*) FROM staff_assignments a WHERE a.shift_id = s.id AND a.status IN ('proposed','requested')) pending,
                (SELECT COUNT(*) FROM staff_assignments a WHERE a.shift_id = s.id AND a.checked_in_at IS NOT NULL) present
            FROM staff_shifts s WHERE s.event_id = ? ORDER BY s.starts_at, s.team, s.title", [$eventId]);
    }

    public static function assignments(int $eventId): array
    {
        $rows = all("SELECT a.*, m.first_name, m.last_name, m.email, m.phone, m.team member_team, m.token FROM staff_assignments a
            JOIN staff_shifts s ON s.id = a.shift_id JOIN staff_members m ON m.id = a.member_id WHERE s.event_id = ? ORDER BY m.last_name, m.first_name", [$eventId]);
        $by = [];
        foreach ($rows as $r) $by[(int)$r['shift_id']][] = $r;
        return $by;
    }

    /** Places encore libres sur un créneau (en comptant les demandes en attente). */
    public static function free(array $shift): int
    {
        $taken = (int)val("SELECT COUNT(*) FROM staff_assignments WHERE shift_id = ? AND status IN ('confirmed','proposed')", [$shift['id']]);
        return max(0, (int)$shift['needed'] - $taken);
    }

    /** Créneau qui chevauche un autre créneau actif du même membre ? Renvoie son intitulé. */
    public static function conflict(int $memberId, array $shift): ?string
    {
        $c = one("SELECT s.title, s.starts_at FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id
            WHERE a.member_id = ? AND a.status <> 'declined' AND s.id <> ? AND s.starts_at < ? AND s.ends_at > ? LIMIT 1",
            [$memberId, $shift['id'], $shift['ends_at'], $shift['starts_at']]);
        return $c ? $c['title'] . ' (' . short_date($c['starts_at']) . ')' : null;
    }

    public static function assign(int $shiftId, int $memberId, string $status): void
    {
        q('INSERT INTO staff_assignments (shift_id, member_id, status, created_at) VALUES (?, ?, ?, NOW())
           ON DUPLICATE KEY UPDATE status = VALUES(status)', [$shiftId, $memberId, $status]);
    }

    /** Heures prévues / pointées (en minutes). */
    public static function minutes(array $a): array
    {
        $planned = max(0, (int)((strtotime($a['ends_at']) - strtotime($a['starts_at'])) / 60));
        $done = 0;
        if ($a['checked_in_at']) {
            $end = $a['checked_out_at'] ? strtotime($a['checked_out_at']) : min(time(), strtotime($a['ends_at']));
            $done = max(0, (int)(($end - strtotime($a['checked_in_at'])) / 60));
        }
        return [$planned, $done];
    }

    public static function hm(int $min): string { return intdiv($min, 60) . ' h' . ($min % 60 ? ' ' . str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT) : ''); }

    /** Planning d'un membre (à venir + passés récents). */
    public static function memberShifts(int $memberId, bool $upcomingOnly = false): array
    {
        return all("SELECT a.*, s.title, s.team, s.location, s.starts_at, s.ends_at, s.description, s.event_id, e.name ev_name, e.venue, e.city, e.slug
            FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id JOIN events e ON e.id = s.event_id
            WHERE a.member_id = ?" . ($upcomingOnly ? ' AND s.ends_at >= NOW()' : '') . ' ORDER BY s.starts_at', [$memberId]);
    }

    /** E-mail « ton planning » (tous les créneaux à venir, ou ceux d'un événement). */
    public static function sendPlanning(array $m, ?int $eventId = null, string $intro = ''): bool
    {
        if (!$m['email'] || !is_email((string)$m['email'])) return false;
        $rows = array_filter(self::memberShifts((int)$m['id'], true), fn($r) => $r['status'] !== 'declined' && (!$eventId || (int)$r['event_id'] === $eventId));
        $org = one('SELECT name FROM organizers WHERE id = ?', [$m['organizer_id']]);
        $list = '';
        foreach ($rows as $r) {
            $list .= '<li style="margin-bottom:8px"><b>' . e($r['title']) . '</b> — ' . e($r['ev_name']) . '<br>' . e(fr_date($r['starts_at'])) . ' → ' . e(date('H:i', strtotime($r['ends_at'])))
                . ($r['location'] ? ' · 📍 ' . e($r['location']) : '') . ' · <i>' . e(self::STATUS[$r['status']][0]) . '</i></li>';
        }
        $html = '<p>Salut ' . e($m['first_name']) . ' 👋</p>' . ($intro !== '' ? '<div style="white-space:pre-line">' . e($intro) . '</div>' : '<p>Voici ton planning avec <b>' . e($org['name'] ?? '') . '</b>.</p>')
            . ($list ? '<ul style="padding-left:18px">' . $list . '</ul>' : '<p>Pas encore de créneau pour toi : jette un œil aux créneaux ouverts 👀</p>')
            . '<p><a href="' . e(self::portalUrl($m)) . '" style="display:inline-block;background:#0032fa;color:#fff;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:700">Ouvrir mon espace bénévole</a></p>'
            . '<p style="color:#777;font-size:13px">Confirme tes créneaux, inscris-toi sur d\'autres et retrouve ton badge QR. Ce lien est personnel, ne le partage pas.</p>';
        Mailer::send((string)$m['email'], '🙌 Ton planning bénévole — ' . ($org['name'] ?? ''), $html);
        return true;
    }

    /** Rappel la veille (tâche de fond). */
    public static function reminders(): int
    {
        $rows = all("SELECT a.id, a.member_id FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id
            WHERE a.status = 'confirmed' AND a.reminder_sent_at IS NULL AND s.starts_at BETWEEN NOW() AND NOW() + INTERVAL 26 HOUR LIMIT 50");
        $n = 0;
        foreach ($rows as $r) {
            update('staff_assignments', ['reminder_sent_at' => now()], 'id = ?', [$r['id']]);
            $m = one('SELECT * FROM staff_members WHERE id = ?', [$r['member_id']]);
            $s = one('SELECT s.*, e.name ev_name FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id JOIN events e ON e.id = s.event_id WHERE a.id = ?', [$r['id']]);
            if (!$m || !$s || !$m['email']) continue;
            try {
                Mailer::send((string)$m['email'], '⏰ Demain : ' . $s['title'] . ' — ' . $s['ev_name'],
                    '<p>Salut ' . e($m['first_name']) . ' !</p><p>Petit rappel : tu es attendu·e <b>' . e(fr_date($s['starts_at'])) . '</b> (jusqu\'à ' . e(date('H:i', strtotime($s['ends_at']))) . ')'
                    . ($s['location'] ? ', rendez-vous <b>' . e($s['location']) . '</b>' : '') . ' pour « ' . e($s['title']) . ' ».</p>'
                    . ($s['description'] ? '<div style="white-space:pre-line">' . e($s['description']) . '</div>' : '')
                    . '<p>Pense à ton badge QR (dans ton espace) pour pointer en arrivant 😉</p><p><a href="' . e(self::portalUrl($m)) . '">Mon espace bénévole</a></p>');
                $n++;
            } catch (Throwable $e) { log_line('emails', 'Rappel bénévole : ' . $e->getMessage()); }
        }
        return $n;
    }

    /** Pointage par code de badge (token du membre) : arrivée puis départ sur le créneau en cours le plus proche. */
    public static function checkByBadge(int $eventId, string $code): array
    {
        $code = trim(preg_replace('~^.*/benevole/~', '', $code));
        $m = one('SELECT m.* FROM staff_members m JOIN events e ON e.organizer_id = m.organizer_id WHERE m.token = ? AND e.id = ?', [$code, $eventId]);
        if (!$m) return ['ok' => false, 'msg' => '❌ Badge inconnu pour cet événement.'];
        $a = one("SELECT a.*, s.title, s.starts_at, s.ends_at FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id
            WHERE a.member_id = ? AND s.event_id = ? AND a.status IN ('confirmed','proposed') AND s.ends_at >= NOW() - INTERVAL 2 HOUR AND s.starts_at <= NOW() + INTERVAL 3 HOUR
            ORDER BY (a.checked_in_at IS NOT NULL AND a.checked_out_at IS NULL) DESC, ABS(TIMESTAMPDIFF(MINUTE, s.starts_at, NOW())) LIMIT 1", [$m['id'], $eventId]);
        $who = $m['first_name'] . ' ' . $m['last_name'];
        if (!$a) return ['ok' => false, 'msg' => '🤔 ' . $who . ' n\'a pas de créneau maintenant.'];
        return self::toggle($a, $who);
    }

    public static function toggle(array $a, string $who): array
    {
        if (!$a['checked_in_at']) {
            update('staff_assignments', ['checked_in_at' => now(), 'status' => 'confirmed'], 'id = ?', [$a['id']]);
            return ['ok' => true, 'msg' => '✅ ' . $who . ' est arrivé·e — ' . $a['title']];
        }
        if (!$a['checked_out_at']) {
            update('staff_assignments', ['checked_out_at' => now()], 'id = ?', [$a['id']]);
            return ['ok' => true, 'msg' => '👋 ' . $who . ' est reparti·e — merci ! (' . self::hm(max(0, (int)((time() - strtotime($a['checked_in_at'])) / 60))) . ')'];
        }
        return ['ok' => false, 'msg' => 'ℹ️ ' . $who . ' a déjà pointé son arrivée et son départ.'];
    }
}
