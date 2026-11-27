<?php
declare(strict_types=1);

/**
 * 4.1 : tarifs modulables (façon Weezevent / billetterie de spectacle) et lancements programmés.
 * Visibilité planifiée, affichage quand complet, « plus que X places », badge, justificatif, minimum par commande,
 * contingents partagés entre plusieurs tarifs, prix différent selon la séance, grille catégories × tarifs,
 * ouverture des ventes à heure fixe avec compte à rebours et liste « préviens-moi ».
 */
final class RatesPlus
{
    public const BADGES = ['' => 'Aucun', 'Populaire' => 'Populaire', 'Meilleur prix' => 'Meilleur prix', 'Dernières places' => 'Dernières places', 'Nouveau' => 'Nouveau', 'Recommandé' => 'Recommandé'];

    // ---------- visibilité ----------
    /** Le tarif doit-il apparaître sur la page publique maintenant ? (indépendant des dates de vente) */
    public static function visibleNow(array $r, ?array $sale = null): bool
    {
        $now = time();
        if (!empty($r['visible_from']) && strtotime((string)$r['visible_from']) > $now) return false;
        if (!empty($r['visible_until']) && strtotime((string)$r['visible_until']) < $now) return false;
        if ($sale && ($r['soldout_mode'] ?? 'show') === 'hide' && !$sale['ok'] && ($sale['why'] ?? '') === 'Complet') return false;
        return true;
    }

    /** « Plus que X places » si le tarif est sous son seuil d'affichage. */
    public static function leftLabel(array $r, array $sale): ?string
    {
        $t = (int)($r['show_left'] ?? 0);
        if ($t <= 0 || empty($sale['ok']) || !isset($sale['remaining']) || $sale['remaining'] === PHP_INT_MAX || $sale['remaining'] > $t) return null;
        return $sale['remaining'] === 1 ? 'Dernière place' : 'Plus que ' . (int)$sale['remaining'] . ' places';
    }

    // ---------- prix selon la séance ----------
    public static function sessionPrices(array $r): array
    {
        $j = json_decode((string)($r['session_prices'] ?? ''), true);
        return is_array($j) ? array_map('intval', $j) : [];
    }

    public static function price(array $r, ?int $sessionId): int
    {
        if ($sessionId) { $sp = self::sessionPrices($r); if (isset($sp[(string)$sessionId])) return (int)$sp[(string)$sessionId]; }
        return (int)$r['price'];
    }

    // ---------- contingents partagés ----------
    public static function groups(int $eventId): array
    {
        return all('SELECT g.*, (SELECT COUNT(*) FROM rates r WHERE r.quota_group_id = g.id) n FROM quota_groups g WHERE g.event_id = ? ORDER BY g.sort, g.id', [$eventId]);
    }

    public static function groupUsed(int $groupId, ?int $sessionId = null): int
    {
        $n = 0;
        foreach (all('SELECT id FROM rates WHERE quota_group_id = ?', [$groupId]) as $r) $n += Tickets::rateUsed((int)$r['id'], $sessionId);
        return $n;
    }

    /** Places restantes dans le contingent du tarif (PHP_INT_MAX si aucun). */
    public static function groupRemaining(array $r, ?int $sessionId = null): int
    {
        if (empty($r['quota_group_id'])) return PHP_INT_MAX;
        $g = one('SELECT * FROM quota_groups WHERE id = ?', [$r['quota_group_id']]);
        if (!$g || (int)$g['quota'] <= 0) return PHP_INT_MAX;
        return max(0, (int)$g['quota'] - self::groupUsed((int)$g['id'], $sessionId));
    }

    // ---------- grille catégories × tarifs (salles de spectacle) ----------
    /**
     * $cats : [[name, quota]], $types : [[name, proof]], $prices[ci][ti] en centimes (vide = pas de tarif).
     * Crée ou met à jour un contingent par catégorie et un tarif « Catégorie · Type » par case remplie.
     * @return array{created:int, updated:int}
     */
    public static function applyGrid(array $ev, array $cats, array $types, array $prices): array
    {
        $created = 0; $updated = 0;
        $sort = (int)val('SELECT COALESCE(MAX(sort), 0) FROM rates WHERE event_id = ?', [$ev['id']]);
        foreach ($cats as $ci => [$cname, $cquota]) {
            $cname = mb_substr(trim((string)$cname), 0, 60); if ($cname === '') continue;
            $g = one('SELECT * FROM quota_groups WHERE event_id = ? AND name = ?', [$ev['id'], $cname]);
            if ($g) update('quota_groups', ['quota' => max(0, (int)$cquota), 'sort' => $ci], 'id = ?', [$g['id']]);
            $gid = $g ? (int)$g['id'] : (int)insert('quota_groups', ['event_id' => $ev['id'], 'name' => $cname, 'quota' => max(0, (int)$cquota), 'sort' => $ci, 'created_at' => now()]);
            foreach ($types as $ti => [$tname, $proof]) {
                $tname = mb_substr(trim((string)$tname), 0, 60); if ($tname === '') continue;
                $raw = trim((string)($prices[$ci][$ti] ?? ''));
                if ($raw === '') continue;
                $price = parse_money($raw);
                $name = $cname . ' · ' . $tname;
                $data = ['price' => $price, 'quota_group_id' => $gid, 'seat_category' => self::seatCatId($ev, $cname), 'proof' => mb_substr(trim((string)$proof), 0, 160) ?: null, 'description' => trim((string)$proof) !== '' ? 'Sur justificatif : ' . trim((string)$proof) : null];
                $r = one('SELECT id FROM rates WHERE event_id = ? AND name = ?', [$ev['id'], $name]);
                if ($r) { update('rates', $data, 'id = ?', [$r['id']]); $updated++; }
                else { insert('rates', $data + ['event_id' => $ev['id'], 'name' => $name, 'active' => 1, 'sort' => ($sort += 10), 'price_mode' => 'fixed', 'pack_size' => 1, 'max_entries' => 1]); $created++; }
            }
        }
        return compact('created', 'updated');
    }

    /** Relie une catégorie de la grille à la catégorie du plan de salle du même nom (si plan). */
    private static function seatCatId(array $ev, string $name): ?string
    {
        $map = class_exists('Seating') ? Seating::map($ev) : null;
        foreach ($map['categories'] ?? [] as $c) if (mb_strtolower((string)$c['name']) === mb_strtolower($name)) return (string)$c['id'];
        return null;
    }

    /** Grille actuelle reconstruite à partir des tarifs « Catégorie · Type ». */
    public static function currentGrid(int $eventId): array
    {
        $cats = []; $types = []; $cells = [];
        foreach (self::groups($eventId) as $g) $cats[$g['name']] = (int)$g['quota'];
        foreach (all('SELECT r.*, g.name gname FROM rates r JOIN quota_groups g ON g.id = r.quota_group_id WHERE r.event_id = ? ORDER BY r.sort, r.id', [$eventId]) as $r) {
            $parts = explode(' · ', (string)$r['name'], 2);
            if (count($parts) !== 2 || $parts[0] !== $r['gname']) continue;
            $types[$parts[1]] = $types[$parts[1]] ?? (string)$r['proof'];
            $cells[$parts[0]][$parts[1]] = (int)$r['price'];
        }
        return ['cats' => $cats, 'types' => $types, 'cells' => $cells];
    }

    // ---------- lancement programmé ----------
    public static function launchPending(array $ev): bool
    {
        return !empty($ev['on_sale_at']) && strtotime((string)$ev['on_sale_at']) > time();
    }

    public static function alertSubscribe(array $ev, string $email): bool
    {
        if (!is_email($email)) return false;
        q('INSERT IGNORE INTO sale_alerts (event_id, email, token, created_at) VALUES (?, ?, ?, NOW())', [$ev['id'], strtolower($email), bin2hex(random_bytes(12))]);
        return true;
    }

    /** Tâche de fond : à l'heure d'ouverture, prévient les inscrits (par lots de 300). */
    public static function notifyOpenings(): int
    {
        $n = 0;
        foreach (all("SELECT e.*, o.name org FROM events e JOIN organizers o ON o.id = e.organizer_id WHERE e.status = 'published' AND e.on_sale_at IS NOT NULL AND e.on_sale_at <= NOW() AND e.on_sale_at >= NOW() - INTERVAL 3 DAY") as $ev) {
            $list = all('SELECT * FROM sale_alerts WHERE event_id = ? AND notified_at IS NULL LIMIT 300', [$ev['id']]);
            if (!$list) { if (!$ev['on_sale_notified_at']) update('events', ['on_sale_notified_at' => now()], 'id = ?', [$ev['id']]); continue; }
            foreach ($list as $a) {
                update('sale_alerts', ['notified_at' => now()], 'id = ?', [$a['id']]);
                try { Mailer::send($a['email'], 'C\'est ouvert : ' . $ev['name'], '<p>La billetterie de <b>' . e($ev['name']) . '</b> vient d\'ouvrir.</p><p style="font-size:18px">' . e(fr_date($ev['starts_at'])) . ($ev['venue'] ? ' · ' . e($ev['venue']) : '') . '</p><p><a href="' . e(abs_url('/e/' . $ev['slug'], ['utm_source' => 'alerte', 'utm_medium' => 'email'])) . '" style="display:inline-block;background:#0071e3;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:600">Prendre mes places</a></p><p style="color:#86868b">Tu reçois cet e-mail parce que tu as demandé à être prévenu(e). ' . e($ev['org']) . '</p>'); $n++; } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            }
        }
        return $n;
    }
}
