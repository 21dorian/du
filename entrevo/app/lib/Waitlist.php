<?php
declare(strict_types=1);

/** Liste d'attente : quand des places se libèrent, la première personne inscrite reçoit une offre réservée 24 h. */
final class Waitlist
{
    public const HOLD_HOURS = 24;

    public static function hours(array $ev): int { return max(1, (int)($ev['waitlist_hours'] ?? 0) ?: self::HOLD_HOURS); }

    /** Propose sa place à une personne précise (bouton de l'organisateur ou tour automatique). */
    public static function offer(array $w, array $ev): void
    {
        $session = $w['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$w['session_id']]) : null;
        $exp = date('Y-m-d H:i:s', time() + self::hours($ev) * 3600);
        update('waitlist', ['status' => 'offered', 'offered_at' => now(), 'expires_at' => $exp], 'id = ?', [$w['id']]);
        self::mailOffer($w, $ev, $session, $exp);
    }

    public static function process(): int
    {
        q("UPDATE waitlist SET status = 'expired' WHERE status = 'offered' AND expires_at < NOW()");
        $offers = 0;
        $groups = all("SELECT w.event_id, COALESCE(w.session_id, 0) sid FROM waitlist w JOIN events e ON e.id = w.event_id
                       WHERE w.status = 'waiting' AND e.waitlist_enabled = 1 AND e.status = 'published' GROUP BY w.event_id, COALESCE(w.session_id, 0)");
        foreach ($groups as $g) {
            $ev = one('SELECT * FROM events WHERE id = ?', [$g['event_id']]);
            $session = $g['sid'] ? one('SELECT * FROM sessions WHERE id = ?', [$g['sid']]) : null;
            if ($session && strtotime((string)$session['starts_at']) < time()) continue;
            $free = self::freePlaces($ev, $session);
            if ($free <= 0) continue;
            foreach (all("SELECT * FROM waitlist WHERE event_id = ? AND COALESCE(session_id, 0) = ? AND status = 'waiting' ORDER BY id", [$ev['id'], $g['sid']]) as $w) {
                if ((int)$w['qty'] > $free) break; // on respecte l'ordre d'inscription
                $exp = date('Y-m-d H:i:s', time() + self::hours($ev) * 3600);
                update('waitlist', ['status' => 'offered', 'offered_at' => now(), 'expires_at' => $exp], 'id = ?', [$w['id']]);
                $free -= (int)$w['qty'];
                $offers++;
                self::mailOffer($w, $ev, $session, $exp);
            }
        }
        return $offers;
    }

    /** Places libres (capacité de la séance ou de l'événement, sinon somme des quotas des tarifs). */
    public static function freePlaces(array $ev, ?array $session): int
    {
        $cap = Tickets::capacity($ev, $session);
        if ($cap > 0) return $cap - Tickets::eventUsed((int)$ev['id'], $session ? (int)$session['id'] : null);
        $free = 0;
        foreach (all('SELECT * FROM rates WHERE event_id = ? AND active = 1 AND hidden = 0', [$ev['id']]) as $r) {
            if ((int)$r['quota'] <= 0) return 0; // tarif illimité : jamais complet, pas de liste d'attente
            $free += Tickets::remaining($r, $ev, $session);
        }
        return $free;
    }

    private static function mailOffer(array $w, array $ev, ?array $session, string $exp): void
    {
        $link = abs_url('/attente/' . $w['token']);
        try {
            Mailer::send($w['email'], 'Une place s\'est libérée pour ' . $ev['name'] . ' !', '<p>Salut ' . e($w['first_name'] ?: '') . '</p>'
                . '<p>Bonne nouvelle : ' . ((int)$w['qty'] > 1 ? (int)$w['qty'] . ' places se sont libérées' : 'une place s\'est libérée') . ' pour <b>' . e($ev['name']) . '</b>'
                . ($session ? ' (' . e(fr_date($session['starts_at'])) . ')' : '') . '.</p>'
                . '<p>On te la garde jusqu\'au <b>' . e(fr_date($exp)) . '</b>. Après, elle passe au suivant. Pas de pression. (Un peu quand même.)</p>'
                . '<p><a href="' . e($link) . '" style="background:#0071e3;color:#111;padding:12px 18px;text-decoration:none;font-weight:bold;display:inline-block">JE PRENDS MA PLACE</a></p>');
        } catch (Throwable $e) { log_line('emails', 'Liste d\'attente : ' . $e->getMessage()); }
    }

    public static function counts(int $eventId): array
    {
        $r = [];
        foreach (all('SELECT status, COUNT(*) n, SUM(qty) q FROM waitlist WHERE event_id = ? GROUP BY status', [$eventId]) as $x) $r[$x['status']] = ['n' => (int)$x['n'], 'q' => (int)$x['q']];
        return $r;
    }
}
