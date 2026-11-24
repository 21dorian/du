<?php
declare(strict_types=1);

/**
 * E-mails automatiques pour les organisateurs :
 *  - récap des ventes (chaque matin, ou chaque lundi) selon organizers.recap ;
 *  - alertes de remplissage à 50 %, 80 % et 100 % (une seule fois par seuil et par événement).
 * Destinataires : l'adresse de l'organisation + les comptes « responsable » actifs.
 */
final class Recap
{
    public const THRESHOLDS = [50, 80, 100];

    public static function recipients(array $org): array
    {
        $to = array_column(all("SELECT email FROM users WHERE organizer_id = ? AND role = 'owner' AND active = 1", [$org['id']]), 'email');
        if (is_email((string)$org['email'])) $to[] = $org['email'];
        return array_values(array_unique(array_map('strtolower', array_filter($to, fn($e) => is_email((string)$e)))));
    }

    public static function run(): int
    {
        if ((int)date('G') < 7) return 0; // on attend le matin
        $n = 0;
        foreach (all("SELECT * FROM organizers WHERE status = 'active' AND recap IN ('daily','weekly')") as $org) {
            $last = $org['recap_sent_at'] ? strtotime($org['recap_sent_at']) : 0;
            if ($org['recap'] === 'daily' && date('Y-m-d', $last) === date('Y-m-d')) continue;
            if ($org['recap'] === 'weekly' && (date('N') !== '1' || date('o-W', $last) === date('o-W'))) continue;
            update('organizers', ['recap_sent_at' => now()], 'id = ?', [$org['id']]);
            try { if (self::send($org)) $n++; } catch (Throwable $e) { log_line('emails', 'Récap ' . $org['id'] . ' : ' . $e->getMessage()); }
            if ($n >= 20) break;
        }
        return $n;
    }

    /** Construit et envoie le récap. Renvoie false s'il n'y a rien à dire (pas d'événement du tout). */
    public static function send(array $org, ?string $to = null): bool
    {
        $weekly = $org['recap'] === 'weekly';
        $from = date('Y-m-d 00:00:00', strtotime($weekly ? '-7 days' : '-1 day'));
        $until = date('Y-m-d 23:59:59', strtotime('-1 day'));
        $real = "o.organizer_id = ? AND o.status = 'paid' AND (o.channel IS NULL OR o.channel <> 'systeme')";
        $k = one("SELECT COUNT(*) n, COALESCE(SUM(o.total),0) t, COALESCE(SUM((SELECT SUM(qty) FROM order_items i WHERE i.order_id = o.id)),0) q FROM orders o WHERE $real AND o.paid_at BETWEEN ? AND ?", [$org['id'], $from, $until]);
        $events = all("SELECT e.*, (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid') sold,
                (SELECT COUNT(*) FROM tickets t JOIN orders o ON o.id = t.order_id WHERE t.event_id = e.id AND t.status = 'valid' AND o.status = 'paid' AND o.paid_at BETWEEN ? AND ?) recent
            FROM events e WHERE e.organizer_id = ? AND e.status = 'published' AND COALESCE(e.ends_at, e.starts_at) >= NOW() ORDER BY e.starts_at LIMIT 8", [$from, $until, $org['id']]);
        if (!$events && !(int)$k['n']) return false;
        $refunds = (int)val("SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND status = 'paid' AND refund_requested_at IS NOT NULL", [$org['id']]);
        $period = $weekly ? 'ces 7 derniers jours' : 'hier';
        $cell = fn($l, $v) => '<td style="padding:12px;background:#f0f5fa;border-radius:12px;text-align:center"><div style="font-size:12px;color:#667085;text-transform:uppercase;letter-spacing:.05em">' . $l . '</div><div style="font-size:24px;font-weight:bold;color:#0f0f14">' . $v . '</div></td>';
        $html = '<p>Salut 👋 Voici ce qui s\'est passé <b>' . $period . '</b> chez <b>' . e($org['name']) . '</b>.</p>'
            . '<table role="presentation" width="100%" cellspacing="6" cellpadding="0"><tr>' . $cell('Recettes', e(money((int)$k['t']))) . $cell('Billets', (int)$k['q']) . $cell('Commandes', (int)$k['n']) . '</tr></table>';
        if ($events) {
            $html .= '<h3 style="margin:22px 0 8px">📅 Tes prochains événements</h3><table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
            foreach ($events as $ev) {
                $cap = (int)$ev['capacity'];
                $pct = $cap ? min(100, round($ev['sold'] / $cap * 100)) : null;
                $html .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee"><b>' . e($ev['name']) . '</b><br><span style="color:#667085;font-size:13px">' . e(fr_date($ev['starts_at'])) . '</span></td>'
                    . '<td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . (int)$ev['sold'] . ($cap ? ' / ' . $cap . ' <span style="color:#667085">(' . $pct . ' %)</span>' : ' billets')
                    . ((int)$ev['recent'] ? '<br><span style="color:#1f9d55;font-size:13px">+' . (int)$ev['recent'] . ' ' . $period . '</span>' : '') . '</td></tr>';
            }
            $html .= '</table>';
        }
        if ($refunds) $html .= '<p style="background:#fbefd9;color:#8a5300;padding:10px 12px;border-radius:10px">💸 ' . $refunds . ' demande(s) de remboursement attendent ta réponse.</p>';
        $html .= '<p><a href="' . e(abs_url('/pro')) . '" style="display:inline-block;background:#0071e3;color:#fff;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:bold">Ouvrir mon tableau de bord</a></p>'
            . '<p style="color:#777;font-size:12px">Tu reçois ce récap car il est activé dans Mon organisation › Notifications. Tu peux le couper en un clic là-bas.</p>';
        $subject = ($weekly ? '📊 Ta semaine' : '☀️ Ton récap') . ' : ' . money((int)$k['t']) . ', ' . (int)$k['q'] . ' billet' . ((int)$k['q'] > 1 ? 's' : '');
        foreach ($to ? [$to] : self::recipients($org) as $email) Mailer::send($email, $subject, $html);
        return true;
    }

    /** Alertes de remplissage. */
    public static function thresholds(): int
    {
        $n = 0;
        $rows = all("SELECT e.*, (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid' AND t.type IN ('ticket','invitation')) sold
            FROM events e JOIN organizers o ON o.id = e.organizer_id WHERE e.status = 'published' AND e.capacity > 0 AND COALESCE(e.ends_at, e.starts_at) > NOW() AND o.status = 'active' LIMIT 200");
        foreach ($rows as $ev) {
            $pct = $ev['sold'] / max(1, (int)$ev['capacity']) * 100;
            $done = array_filter(array_map('intval', explode(',', (string)$ev['alerts_sent'])));
            $hit = array_values(array_filter(self::THRESHOLDS, fn($t) => $pct >= $t && !in_array($t, $done, true)));
            if (!$hit) continue;
            $t = max($hit);
            update('events', ['alerts_sent' => implode(',', array_unique(array_merge($done, $hit)))], 'id = ?', [$ev['id']]);
            $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
            $title = $t >= 100 ? '🫠 COMPLET : ' . $ev['name'] : ($t >= 80 ? '🔥 ' . $ev['name'] . ' est rempli à ' . $t . ' %' : '🎉 ' . $ev['name'] . ' a passé la moitié');
            $tip = $t >= 100 ? 'Ouvre une liste d\'attente ou ajoute une date pour ne pas perdre ces ventes.' : ($t >= 80 ? 'C\'est le moment d\'envoyer une campagne « dernières places » 😉' : 'Belle dynamique, continue de partager le lien !');
            $html = '<p><b>' . e($title) . '</b></p><p>' . (int)$ev['sold'] . ' billets vendus sur ' . (int)$ev['capacity'] . '.</p><p>' . e($tip) . '</p>'
                . '<p><a href="' . e(abs_url('/pro/evenements/' . $ev['id'])) . '">Voir l\'événement</a></p>';
            foreach (self::recipients($org) as $email) { try { Mailer::send($email, $title, $html); } catch (Throwable $e) { log_line('emails', 'Alerte seuil : ' . $e->getMessage()); } }
            $n++;
        }
        return $n;
    }
}
