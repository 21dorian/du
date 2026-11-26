<?php
declare(strict_types=1);

/**
 * 3.0 : Entrevo Pay — l'espace cashless séparé (sous-domaine pay.), inspiré de Weezpay et d'EasyTransac.
 * - Même installation et même base que la billetterie : billets, bracelets et soldes restent liés.
 * - Sur le sous-domaine réglé par le super-admin (réglage pay_host), toutes les adresses sont servies par Pay ;
 *   ailleurs, Pay reste accessible sous /pay (développement, installation sans sous-domaine).
 * - Accès : organisateur (administrateur), équipe cashless dédiée (rôles), exploitants (leurs stands), festivaliers (portail).
 */
final class Pay
{
    /** Rôles de l'équipe cashless => [libellé, rubriques autorisées ('*' = toutes)]. */
    public const ROLES = [
        'admin' => ['Administrateur cashless', ['*']],
        'regie' => ['Régisseur', ['live', 'wallets', 'bracelets', 'refunds', 'packs', 'ecocups', 'catalogue', 'consignes', 'remises', 'appareils', 'caisses', 'stocks', 'equipe', 'credits', 'rapport', 'ventes', 'materiel', 'exploitants', 'exports', 'reglages', 'journal', 'acces']],
        'bar' => ['Responsable de bar', ['live', 'caisses', 'stocks', 'appareils', 'materiel', 'ventes']],
        'compta' => ['Comptable', ['live', 'rapport', 'ventes', 'caisses', 'exploitants', 'exports', 'refunds', 'journal']],
        'support' => ['Support festivaliers', ['live', 'wallets', 'bracelets', 'refunds', 'acces']],
        'operator' => ['Exploitant', []],
    ];
    public const PROFILES = ['public' => 'Public', 'staff' => 'Staff & bénévoles', 'vip' => 'VIP', 'artist' => 'Artiste', 'press' => 'Presse', 'crew' => 'Technique'];
    public const SETTINGS = ['transfer' => 1, 'virtual' => 1, 'self_block' => 1, 'refund_fee' => 0, 'topups' => '1000,2000,3000,5000', 'max_balance' => 50000, 'message' => '', 'brand_color' => '#5e5ce6'];
    public const TX = ['topup' => 'Recharge en ligne', 'cash' => 'Recharge espèces', 'card' => 'Recharge carte', 'sale' => 'Achat', 'refund' => 'Remboursement', 'undo' => 'Annulation',
        'deposit' => 'Retour de consigne', 'rembours' => 'Remboursement d\'achat', 'adjust' => 'Correction', 'gift' => 'Crédit offert', 'xfer_out' => 'Transfert envoyé', 'xfer_in' => 'Transfert reçu', 'move' => 'Report de bracelet'];

    // ---------- sous-domaine ----------
    public static function host(): string { return strtolower(trim((string)setting('pay_host', ''))); }

    public static function isHost(): bool
    {
        $h = self::host();
        return $h !== '' && strtolower((string)preg_replace('~:\d+$~', '', (string)($_SERVER['HTTP_HOST'] ?? ''))) === $h;
    }

    /** Adresse d'une page Pay ('' = portail, '/pro/...' = espace pro). */
    public static function url(string $path = '', array $q = []): string { return url(self::path($path), $q); }

    /** Chemin interne (sans le dossier d'installation), pour redirect(). */
    public static function path(string $path = ''): string { return self::isHost() ? ($path ?: '/') : '/pay' . $path; }

    /** Adresse absolue (e-mails) : sur le sous-domaine s'il est réglé. */
    public static function absUrl(string $path = '', array $q = []): string
    {
        if (self::host() === '') return abs_url('/pay' . $path, $q);
        $https = !str_starts_with((string)setting('base_url', 'https://'), 'http://');
        $u = ($https ? 'https' : 'http') . '://' . self::host() . ($path ?: '/');
        return $u . ($q ? '?' . http_build_query($q) : '');
    }

    // ---------- accès ----------
    /** Membre de l'équipe cashless (ou organisateur) : ['org' => …, 'role' => …, 'operator_id' => …, 'bars' => [ids]] */
    public static function member(?array $u = null): ?array
    {
        $u ??= user();
        if (!$u) return null;
        if (in_array($u['role'], ['owner', 'manager', 'superadmin'], true)) {
            $org = current_org();
            return $org ? ['org' => $org, 'role' => 'admin', 'operator_id' => null, 'bars' => []] : null;
        }
        $m = one('SELECT * FROM pay_members WHERE user_id = ? ORDER BY id LIMIT 1', [$u['id']]);
        if (!$m) return null;
        $org = one('SELECT * FROM organizers WHERE id = ?', [$m['organizer_id']]);
        return $org ? ['org' => $org, 'role' => $m['role'], 'operator_id' => $m['operator_id'] ? (int)$m['operator_id'] : null, 'bars' => array_values(array_filter(array_map('intval', explode(',', (string)$m['bar_ids']))))] : null;
    }

    public static function can(array $m, string $section): bool
    {
        $allowed = self::ROLES[$m['role']][1] ?? [];
        return in_array('*', $allowed, true) || in_array($section, $allowed, true);
    }

    // ---------- réglages du portail ----------
    public static function settings(array $ev): array
    {
        $j = json_decode((string)($ev['pay_json'] ?? ''), true);
        return (is_array($j) ? $j : []) + self::SETTINGS;
    }

    public static function topups(array $ev): array
    {
        $a = array_values(array_filter(array_map(fn($v) => (int)round((float)str_replace(',', '.', trim($v)) * (str_contains($v, ',') || (float)$v < 1000 ? 100 : 1)), explode(',', (string)self::settings($ev)['topups']))));
        return $a ?: [1000, 2000, 3000, 5000];
    }

    // ---------- porte-monnaie ----------
    public static function holder(array $w): string
    {
        if ($w['holder_name'] ?? null) return (string)$w['holder_name'];
        $t = $w['ticket_id'] ? one('SELECT first_name, last_name FROM tickets WHERE id = ?', [$w['ticket_id']]) : null;
        return $t ? trim($t['first_name'] . ' ' . $t['last_name']) : '';
    }

    /** Recherche : code billet ou carte, numéro ou UID de bracelet, nom, e-mail. */
    public static function search(int $eventId, string $q, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') return all('SELECT * FROM wallets WHERE event_id = ? ORDER BY balance DESC, id DESC LIMIT ' . $limit, [$eventId]);
        $like = '%' . $q . '%'; $up = strtoupper(preg_replace('~\s+~', '', $q));
        return all("SELECT DISTINCT w.* FROM wallets w LEFT JOIN tickets t ON t.id = w.ticket_id LEFT JOIN pay_wristbands b ON b.wallet_id = w.id
            WHERE w.event_id = ? AND (w.code = ? OR w.nfc_uid = ? OR b.number = ? OR b.uid = ? OR w.email LIKE ? OR w.holder_name LIKE ? OR CONCAT(t.first_name, ' ', t.last_name) LIKE ? OR t.holder_email LIKE ?)
            ORDER BY w.id DESC LIMIT " . $limit, [$eventId, $up, $up, $q, $up, $like, $like, $like, $like]);
    }

    /** Opération manuelle sur un solde (correction, crédit offert, report). */
    public static function credit(array $w, int $amount, string $kind, ?string $note, ?int $userId): int
    {
        if (!$amount) throw new RuntimeException('Indique un montant.');
        db()->beginTransaction();
        try {
            $cur = one('SELECT * FROM wallets WHERE id = ? FOR UPDATE', [$w['id']]);
            if ((int)$cur['balance'] + $amount < 0) throw new RuntimeException('Le solde ne peut pas devenir négatif (' . money((int)$cur['balance']) . ' disponibles).');
            insert('wallet_tx', ['wallet_id' => $w['id'], 'event_id' => $cur['event_id'], 'kind' => $kind, 'amount' => $amount, 'user_id' => $userId, 'items' => $note ? json_encode(['note' => mb_substr($note, 0, 200)], JSON_UNESCAPED_UNICODE) : null, 'created_at' => now()]);
            q('UPDATE wallets SET balance = balance + ? WHERE id = ?', [$amount, $w['id']]);
            db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        return (int)val('SELECT balance FROM wallets WHERE id = ?', [$w['id']]);
    }

    public static function block(array $w, string $reason): void
    {
        update('wallets', ['status' => 'blocked', 'blocked_reason' => mb_substr($reason, 0, 120)], 'id = ?', [$w['id']]);
        q("UPDATE pay_wristbands SET status = 'blocked' WHERE wallet_id = ?", [$w['id']]);
    }

    public static function unblock(array $w): void
    {
        update('wallets', ['status' => 'active', 'blocked_reason' => null], 'id = ?', [$w['id']]);
        q("UPDATE pay_wristbands SET status = 'active' WHERE wallet_id = ? AND status = 'blocked'", [$w['id']]);
    }

    /** Associe un bracelet (numéro imprimé ou UID NFC) ; l'ancien bracelet est désactivé (perdu, cassé). */
    public static function attachBracelet(array $w, string $ref, ?int $orgId = null): array
    {
        $ref = trim($ref);
        $orgId ??= (int)val('SELECT organizer_id FROM events WHERE id = ?', [$w['event_id']]);
        $b = one('SELECT * FROM pay_wristbands WHERE organizer_id = ? AND (number = ? OR uid = ?)', [$orgId, $ref, Pos::nfc($ref)]);
        $uid = $b ? (string)$b['uid'] : Pos::nfc($ref);
        if (strlen($uid) < 8) throw new RuntimeException('Bracelet inconnu : saisis le numéro imprimé ou l\'identifiant NFC.');
        if ($b && $b['wallet_id'] && (int)$b['wallet_id'] !== (int)$w['id']) throw new RuntimeException('Ce bracelet est déjà utilisé par un autre porte-monnaie.');
        if ($b && in_array($b['status'], ['blocked', 'lost'], true)) throw new RuntimeException('Ce bracelet est bloqué.');
        $other = one('SELECT id FROM wallets WHERE event_id = ? AND nfc_uid = ? AND id <> ?', [$w['event_id'], $uid, $w['id']]);
        if ($other) throw new RuntimeException('Ce bracelet est déjà utilisé par un autre porte-monnaie.');
        q("UPDATE pay_wristbands SET status = 'lost', wallet_id = NULL WHERE wallet_id = ? AND uid <> ?", [$w['id'], $uid]);
        update('wallets', ['nfc_uid' => $uid], 'id = ?', [$w['id']]);
        if ($b) update('pay_wristbands', ['status' => 'active', 'wallet_id' => $w['id'], 'event_id' => $w['event_id']], 'id = ?', [$b['id']]);
        return one('SELECT * FROM wallets WHERE id = ?', [$w['id']]);
    }

    /** Transfert de solde entre deux porte-monnaie du même événement (entre amis, ou report d'un bracelet). */
    public static function transfer(array $from, string $toRef, int $amount, ?int $userId = null): array
    {
        if ($amount <= 0) throw new RuntimeException('Indique un montant.');
        $to = self::find((int)$from['event_id'], $toRef);
        if (!$to || (int)$to['id'] === (int)$from['id']) throw new RuntimeException('Carte ou bracelet du destinataire introuvable pour cet événement.');
        if ($to['status'] !== 'active') throw new RuntimeException('La carte du destinataire n\'est pas active.');
        db()->beginTransaction();
        try {
            $cur = one('SELECT * FROM wallets WHERE id = ? FOR UPDATE', [$from['id']]);
            if ($cur['status'] !== 'active') throw new RuntimeException('Cette carte n\'est pas active.');
            if ((int)$cur['balance'] < $amount) throw new RuntimeException('Solde insuffisant : ' . money((int)$cur['balance']) . ' disponibles.');
            $note = json_encode(['vers' => $to['code']]); $back = json_encode(['de' => $cur['code']]);
            insert('wallet_tx', ['wallet_id' => $cur['id'], 'event_id' => $cur['event_id'], 'kind' => 'xfer_out', 'amount' => -$amount, 'user_id' => $userId, 'items' => $note, 'created_at' => now()]);
            insert('wallet_tx', ['wallet_id' => $to['id'], 'event_id' => $cur['event_id'], 'kind' => 'xfer_in', 'amount' => $amount, 'user_id' => $userId, 'items' => $back, 'created_at' => now()]);
            q('UPDATE wallets SET balance = balance - ? WHERE id = ?', [$amount, $cur['id']]);
            q('UPDATE wallets SET balance = balance + ? WHERE id = ?', [$amount, $to['id']]);
            db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        return $to;
    }

    /** Porte-monnaie à partir d'un code de carte, d'un code billet, d'un numéro ou UID de bracelet. */
    public static function find(int $eventId, string $ref): ?array
    {
        $ref = trim($ref); if ($ref === '') return null;
        $w = Cashless::byCode($eventId, $ref);
        if ($w) return $w;
        $uid = Pos::nfc($ref);
        $w = strlen($uid) >= 8 ? one('SELECT * FROM wallets WHERE event_id = ? AND nfc_uid = ?', [$eventId, $uid]) : null;
        if ($w) return $w;
        return one('SELECT w.* FROM pay_wristbands b JOIN wallets w ON w.id = b.wallet_id WHERE w.event_id = ? AND b.number = ?', [$eventId, $ref]);
    }

    /** Carte cashless virtuelle (sans billet ni bracelet) : un QR code sur le téléphone. */
    public static function virtualCard(array $ev, string $email, string $name, string $profile = 'public'): array
    {
        do { $code = 'CV' . strtoupper(bin2hex(random_bytes(5))); } while (val('SELECT id FROM wallets WHERE code = ?', [$code]));
        $id = insert('wallets', ['event_id' => $ev['id'], 'ticket_id' => null, 'code' => $code, 'token' => token(24), 'email' => mb_substr(strtolower($email), 0, 190) ?: null, 'holder_name' => mb_substr($name, 0, 120) ?: null,
            'balance' => 0, 'status' => 'active', 'virtual' => 1, 'profile' => array_key_exists($profile, self::PROFILES) ? $profile : 'public', 'created_at' => now()]);
        return one('SELECT * FROM wallets WHERE id = ?', [$id]);
    }

    public static function sendWalletLink(array $w, array $ev): void
    {
        if (!$w['email']) return;
        try {
            Mailer::send((string)$w['email'], 'Ta carte cashless · ' . $ev['name'], '<p>Voici ta carte cashless pour <b>' . e($ev['name']) . '</b>.</p><p>Solde : <b>' . e(money((int)$w['balance'])) . '</b></p>'
                . '<p><a href="' . e(self::absUrl('/w/' . $w['token'])) . '">Ouvrir ma carte (recharge, solde, achats)</a></p><p style="color:#86868b">Au bar, présente le QR code de ta carte ou ton bracelet.</p>');
        } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
    }

    // ---------- pilotage en direct ----------
    public static function live(array $ev, array $barIds = []): array
    {
        $bw = $barIds ? ' AND bar_id IN (' . implode(',', array_map('intval', $barIds)) . ')' : '';
        $s = one("SELECT COUNT(*) n, COALESCE(SUM(amount), 0) ttc, COALESCE(SUM(offered_value), 0) off FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'sale'$bw", [$ev['id']]);
        $today = one("SELECT COUNT(*) n, COALESCE(SUM(amount), 0) ttc FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'sale' AND created_at >= CURDATE()$bw", [$ev['id']]);
        $hour = one("SELECT COUNT(*) n, COALESCE(SUM(amount), 0) ttc FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'sale' AND created_at >= NOW() - INTERVAL 1 HOUR$bw", [$ev['id']]);
        $k = Cashless::stats((int)$ev['id']);
        // ventes par tranche de 15 minutes sur les 6 dernières heures
        $slots = []; $t0 = (int)(floor(time() / 900) * 900) - 23 * 900;
        for ($i = 0; $i < 24; $i++) $slots[date('H:i', $t0 + $i * 900)] = 0;
        foreach (all("SELECT FLOOR(UNIX_TIMESTAMP(created_at) / 900) * 900 t, SUM(amount) a FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'sale' AND created_at >= FROM_UNIXTIME(?)$bw GROUP BY t", [$ev['id'], $t0]) as $r) {
            $key = date('H:i', (int)$r['t']); if (isset($slots[$key])) $slots[$key] = (int)$r['a'];
        }
        $methods = Till::byMethod(all("SELECT method, kind, amount, offered_value, discount, payments_json FROM pos_sales WHERE event_id = ? AND undone = 0$bw", [$ev['id']]))['methods'];
        $bars = [];
        foreach (all('SELECT b.id, b.name, b.active, o.name operator FROM bars b LEFT JOIN pay_operators o ON o.id = b.operator_id WHERE b.event_id = ?' . ($barIds ? ' AND b.id IN (' . implode(',', array_map('intval', $barIds)) . ')' : '') . ' ORDER BY b.id', [$ev['id']]) as $b) {
            $x = one("SELECT COUNT(*) n, COALESCE(SUM(amount), 0) ttc, MAX(created_at) last FROM pos_sales WHERE bar_id = ? AND undone = 0 AND kind = 'sale'", [$b['id']]);
            $h = one("SELECT COUNT(*) n, COALESCE(SUM(amount), 0) ttc FROM pos_sales WHERE bar_id = ? AND undone = 0 AND kind = 'sale' AND created_at >= NOW() - INTERVAL 15 MINUTE", [$b['id']]);
            $se = Till::barSession((int)$ev['id'], (int)$b['id']);
            $devs = (int)val("SELECT COUNT(*) FROM pos_devices WHERE event_id = ? AND bar_id = ? AND revoked = 0 AND last_seen >= NOW() - INTERVAL 5 MINUTE", [$ev['id'], $b['id']]);
            $bars[] = ['id' => (int)$b['id'], 'name' => $b['name'], 'operator' => $b['operator'], 'n' => (int)$x['n'], 'ttc' => (int)$x['ttc'], 'last' => $x['last'], 'q15' => (int)$h['n'], 'ttc15' => (int)$h['ttc'], 'open' => (bool)$se, 'cash' => $se ? Till::sessionTotals($se)['expected_cash'] : 0, 'devices' => $devs];
        }
        usort($bars, fn($a, $b) => $b['ttc'] <=> $a['ttc']);
        $prods = [];
        foreach (all("SELECT items FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'sale' AND created_at >= NOW() - INTERVAL 24 HOUR$bw", [$ev['id']]) as $r) foreach (json_decode((string)$r['items'], true) ?: [] as $l) if (($l['kind'] ?? '') === 'product') {
            $prods[$l['name']]['q'] = ($prods[$l['name']]['q'] ?? 0) + (int)$l['qty']; $prods[$l['name']]['t'] = ($prods[$l['name']]['t'] ?? 0) + (int)$l['total'];
        }
        uasort($prods, fn($a, $b) => $b['t'] <=> $a['t']);
        $alerts = [];
        $drop = (int)Till::settings($ev)['drop_alert'];
        foreach ($bars as $b) if ($drop && $b['cash'] >= $drop) $alerts[] = ['warn', $b['name'] . ' : ' . money($b['cash']) . ' d\'espèces dans la caisse, prévois une remise en banque.'];
        foreach (all("SELECT p.name, b.name bar, s.qty FROM pos_stock s JOIN bar_products p ON p.id = s.product_id JOIN bars b ON b.id = s.bar_id WHERE p.event_id = ? AND p.track_stock = 1 AND s.qty <= COALESCE(p.stock_alert, 10)" . ($barIds ? ' AND s.bar_id IN (' . implode(',', array_map('intval', $barIds)) . ')' : '') . ' ORDER BY s.qty LIMIT 8', [$ev['id']]) as $r)
            $alerts[] = [(int)$r['qty'] <= 0 ? 'bad' : 'warn', $r['name'] . ' · ' . $r['bar'] . ' : ' . ((int)$r['qty'] <= 0 ? 'rupture' : (int)$r['qty'] . ' restant(s)')];
        foreach (self::forecast($ev, $barIds) as $f) if ($f['mins'] !== null && $f['mins'] <= 60 && $f['qty'] > 0) $alerts[] = [$f['mins'] <= 20 ? 'bad' : 'warn', $f['name'] . ' · ' . $f['bar'] . ' : rupture estimée vers ' . $f['eta'] . ' (' . $f['qty'] . ' restants, ' . $f['per_hour'] . '/h)' . ($f['requested'] ? ' · réassort demandé' : '')];
        foreach (self::zones((int)$ev['id']) as $z) if ((int)$z['capacity'] > 0 && $z['inside'] >= (int)$z['capacity'] * 0.9) $alerts[] = [$z['inside'] >= (int)$z['capacity'] ? 'bad' : 'warn', 'Zone ' . $z['name'] . ' : ' . $z['inside'] . ' / ' . (int)$z['capacity'] . ' personnes'];
        $off = []; $lowBat = [];
        foreach (all("SELECT name, last_seen, battery FROM pos_devices WHERE event_id = ? AND revoked = 0 AND token_hash IS NOT NULL AND station_at >= NOW() - INTERVAL 12 HOUR AND (last_seen < NOW() - INTERVAL 5 MINUTE OR battery < 15)", [$ev['id']]) as $d) {
            if ($d['last_seen'] && strtotime((string)$d['last_seen']) < time() - 300) $off[$d['name']] = true;
            elseif ($d['battery'] !== null) $lowBat[] = $d['name'] . ' (' . (int)$d['battery'] . ' %)';
        }
        if ($off) $alerts[] = ['warn', count($off) . ' terminal(aux) hors ligne : ' . implode(', ', array_slice(array_keys($off), 0, 5)) . (count($off) > 5 ? '…' : '') . '. Les ventes carte et espèces s\'enverront au retour du réseau.'];
        if ($lowBat) $alerts[] = ['warn', 'Batterie faible : ' . implode(', ', array_slice($lowBat, 0, 5)) . '.'];
        $req = (int)val("SELECT COUNT(*) FROM wallets WHERE event_id = ? AND status = 'refund_req'", [$ev['id']]);
        if ($req) $alerts[] = ['info', $req . ' demande(s) de remboursement de solde à traiter.'];
        $online = (int)val('SELECT COUNT(*) FROM pos_devices WHERE event_id = ? AND revoked = 0 AND last_seen >= NOW() - INTERVAL 5 MINUTE', [$ev['id']]);
        $devices = (int)val('SELECT COUNT(*) FROM pos_devices WHERE event_id = ? AND revoked = 0 AND token_hash IS NOT NULL', [$ev['id']]);
        $act = one("SELECT COUNT(*) n, COUNT(CASE WHEN nfc_uid IS NOT NULL THEN 1 END) nfc, COUNT(CASE WHEN virtual = 1 THEN 1 END) virt FROM wallets WHERE event_id = ?", [$ev['id']]);
        return ['at' => date('H:i:s'), 'sales' => (int)$s['n'], 'ttc' => (int)$s['ttc'], 'offered' => (int)$s['off'], 'basket' => (int)$s['n'] ? (int)round((int)$s['ttc'] / (int)$s['n']) : 0,
            'today' => (int)$today['ttc'], 'today_n' => (int)$today['n'], 'hour' => (int)$hour['ttc'], 'hour_n' => (int)$hour['n'], 'per_min' => round((int)$hour['n'] / 60, 1),
            'loaded' => (int)$k['loaded'], 'online' => (int)$k['online'], 'onsite' => (int)$k['onsite'], 'float' => (int)$k['left'], 'refunded' => (int)$k['refunded'],
            'wallets' => (int)$act['n'], 'nfc' => (int)$act['nfc'], 'virtual' => (int)$act['virt'], 'devices_online' => $online, 'devices' => $devices,
            'slots' => $slots, 'methods' => $methods, 'bars' => $bars, 'products' => array_slice($prods, 0, 8, true), 'alerts' => $alerts];
    }

    // ---------- exploitants ----------
    /** Relevé d'un exploitant pour un événement : ventes de ses stands, commission, ce qui lui revient. */
    public static function statement(array $ev, array $op): array
    {
        $bars = all('SELECT id, name FROM bars WHERE event_id = ? AND operator_id = ?', [$ev['id'], $op['id']]);
        $ids = array_map(fn($b) => (int)$b['id'], $bars);
        $rows = $ids ? all("SELECT method, kind, amount, offered_value, discount, payments_json, vat_json, bar_id FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'sale' AND bar_id IN (" . implode(',', $ids) . ')', [$ev['id']]) : [];
        $bm = Till::byMethod($rows); $m = $bm['methods'];
        $get = fn($k) => (int)($m[$k]['total'] ?? 0);
        $ttc = $get('cashless') + $get('card') + $get('cash');
        $ht = 0; foreach ($rows as $r) foreach (json_decode((string)$r['vat_json'], true) ?: [] as $rate => $v) if (!in_array($r['method'], ['allowance', 'offered'], true)) $ht += (int)round($v / (1 + (float)$rate / 100));
        $collectedByOrg = $get('cashless') + ((int)$op['keeps_card'] ? 0 : $get('card')) + ((int)$op['keeps_cash'] ? 0 : $get('cash'));
        $commission = (int)round($ttc * (float)$op['commission_pct'] / 100) + (int)$op['fixed_fee'];
        $paid = (int)val('SELECT COALESCE(SUM(amount), 0) FROM pay_payouts WHERE operator_id = ? AND event_id = ?', [$op['id'], $ev['id']]);
        $due = $collectedByOrg - $commission;
        $perBar = [];
        foreach ($bars as $b) { $br = array_values(array_filter($rows, fn($r) => (int)$r['bar_id'] === (int)$b['id'])); $perBar[] = ['name' => $b['name'], 'n' => count($br), 'ttc' => array_sum(array_map(fn($r) => (int)$r['amount'], $br))]; }
        return ['bars' => $bars, 'per_bar' => $perBar, 'n' => $bm['count'], 'cashless' => $get('cashless'), 'card' => $get('card'), 'cash' => $get('cash'), 'offered' => (int)$bm['offered'], 'ttc' => $ttc, 'ht' => $ht,
            'collected_by_org' => $collectedByOrg, 'kept_by_operator' => $ttc - $collectedByOrg, 'commission' => $commission, 'due' => $due, 'paid' => $paid, 'left' => $due - $paid,
            'payouts' => all('SELECT * FROM pay_payouts WHERE operator_id = ? AND event_id = ? ORDER BY id', [$op['id'], $ev['id']])];
    }

    // ---------- 4.0 : contrôle d'accès par profil ----------
    public static function zones(int $eventId, bool $withCounts = true): array
    {
        $out = [];
        foreach (all('SELECT * FROM pay_zones WHERE event_id = ? AND active = 1 ORDER BY id', [$eventId]) as $z) {
            $z['profile_list'] = array_values(array_filter(explode(',', (string)$z['profiles'])));
            if ($withCounts) $z['inside'] = self::zoneInside((int)$z['id']);
            $out[] = $z;
        }
        return $out;
    }

    /** Personnes dans la zone : dernier passage de chaque porte-monnaie = entrée. */
    public static function zoneInside(int $zoneId): int
    {
        return (int)val("SELECT COUNT(*) FROM (SELECT wallet_id, MAX(id) mid FROM pay_zone_scans WHERE zone_id = ? AND result = 'ok' AND wallet_id IS NOT NULL GROUP BY wallet_id) x JOIN pay_zone_scans s ON s.id = x.mid WHERE s.direction = 'in'", [$zoneId]);
    }

    /** Contrôle d'un bracelet ou d'un billet à l'entrée (ou à la sortie) d'une zone. */
    public static function zoneScan(array $d, array $in): array
    {
        $z = one('SELECT * FROM pay_zones WHERE id = ? AND event_id = ? AND active = 1', [(int)($in['zone_id'] ?? 0), $d['event_id']]);
        if (!$z) throw new RuntimeException('Zone inconnue : choisis ta zone.');
        $dir = ($in['direction'] ?? 'in') === 'out' ? 'out' : 'in';
        $w = Pos::wallet((int)$d['event_id'], $in);
        $staff = Till::staffFrom($d, $in);
        $log = fn(string $res, ?string $why) => insert('pay_zone_scans', ['zone_id' => $z['id'], 'event_id' => $d['event_id'], 'wallet_id' => $w['id'] ?? null, 'direction' => $dir, 'result' => $res, 'reason' => $why, 'device_id' => $d['id'], 'staff' => $staff['name'] ?? null, 'created_at' => now()]);
        $who = $w ? (self::holder($w) ?: Pos::holder($w)) : '';
        $prof = $w ? (self::PROFILES[$w['profile']] ?? $w['profile']) : '';
        $deny = function (string $why) use ($log, $z, $who, $prof) { $log('denied', $why); return ['ok' => true, 'allowed' => false, 'why' => $why, 'name' => $who, 'profile' => $prof, 'inside' => self::zoneInside((int)$z['id']), 'capacity' => (int)$z['capacity']]; };
        if (!$w) return $deny(!empty($in['nfc']) ? 'Bracelet inconnu' : 'Billet ou bracelet inconnu');
        if ($w['status'] === 'blocked') return $deny('Bracelet bloqué' . ($w['blocked_reason'] ? ' : ' . $w['blocked_reason'] : ''));
        $allowed = array_values(array_filter(explode(',', (string)$z['profiles'])));
        if ($dir === 'in' && $allowed && !in_array($w['profile'], $allowed, true)) return $deny('Profil « ' . $prof . ' » non autorisé');
        $last = one("SELECT direction FROM pay_zone_scans WHERE zone_id = ? AND wallet_id = ? AND result = 'ok' ORDER BY id DESC LIMIT 1", [$z['id'], $w['id']]);
        if ($dir === 'in' && $last && $last['direction'] === 'in' && empty($in['force'])) return ['ok' => true, 'allowed' => false, 'already' => true, 'why' => 'Déjà dans la zone (pas de sortie enregistrée)', 'name' => $who, 'profile' => $prof, 'inside' => self::zoneInside((int)$z['id']), 'capacity' => (int)$z['capacity']];
        if ($dir === 'in' && (int)$z['capacity'] > 0 && self::zoneInside((int)$z['id']) >= (int)$z['capacity']) return $deny('Zone pleine (' . (int)$z['capacity'] . ' personnes)');
        $log('ok', null);
        return ['ok' => true, 'allowed' => true, 'direction' => $dir, 'name' => $who, 'profile' => $prof, 'inside' => self::zoneInside((int)$z['id']), 'capacity' => (int)$z['capacity']];
    }

    // ---------- 3.2 : prévisions de rupture ----------
    /** Vitesse de vente (dernière heure) de chaque produit suivi, par point de vente : heure de rupture estimée et réassort suggéré. */
    public static function forecast(array $ev, array $barIds = [], int $window = 60, int $cover = 120): array
    {
        $bw = $barIds ? ' AND s.bar_id IN (' . implode(',', array_map('intval', $barIds)) . ')' : '';
        $rate = [];
        foreach (all("SELECT product_id, bar_id, -SUM(delta) q FROM pos_stock_moves WHERE event_id = ? AND kind IN ('sale','undo','refund') AND created_at >= NOW() - INTERVAL $window MINUTE GROUP BY product_id, bar_id", [$ev['id']]) as $r) $rate[(int)$r['product_id']][(int)$r['bar_id']] = max(0, (int)$r['q']);
        $req = []; foreach (all("SELECT product_id, bar_id, SUM(qty) q FROM pos_stock_requests WHERE event_id = ? AND status = 'open' GROUP BY product_id, bar_id", [$ev['id']]) as $r) $req[(int)$r['product_id']][(int)$r['bar_id']] = (int)$r['q'];
        $out = [];
        foreach (all("SELECT s.product_id, s.bar_id, s.qty, p.name, p.stock_alert, p.cost, b.name bar FROM pos_stock s JOIN bar_products p ON p.id = s.product_id JOIN bars b ON b.id = s.bar_id WHERE p.event_id = ? AND p.track_stock = 1 AND p.active = 1 AND b.active = 1$bw", [$ev['id']]) as $r) {
            $q = (int)$r['qty']; $sold = $rate[(int)$r['product_id']][(int)$r['bar_id']] ?? 0; $perMin = $sold / $window;
            $mins = $perMin > 0 ? (int)floor(max(0, $q) / $perMin) : null;
            $need = $perMin > 0 ? max(0, (int)ceil(($perMin * $cover - $q) / 6) * 6) : 0;
            $level = $q <= 0 ? 'bad' : ($mins !== null && $mins <= 30 ? 'bad' : ($mins !== null && $mins <= 90 ? 'warn' : ($q <= (int)($r['stock_alert'] ?? 10) ? 'warn' : 'ok')));
            $out[] = ['product_id' => (int)$r['product_id'], 'bar_id' => (int)$r['bar_id'], 'name' => $r['name'], 'bar' => $r['bar'], 'qty' => $q, 'per_hour' => (int)round($perMin * 60), 'mins' => $mins,
                'eta' => $mins !== null ? date('H:i', time() + $mins * 60) : null, 'suggest' => $need, 'requested' => $req[(int)$r['product_id']][(int)$r['bar_id']] ?? 0, 'level' => $level];
        }
        usort($out, fn($a, $b) => [($a['mins'] ?? PHP_INT_MAX), $a['qty']] <=> [($b['mins'] ?? PHP_INT_MAX), $b['qty']]);
        return $out;
    }

    // ---------- 3.2 : analyse des ventes ----------
    public static function analyse(array $ev, ?string $day = null, array $barIds = []): array
    {
        $w = "s.event_id = ? AND s.undone = 0 AND s.kind = 'sale'"; $p = [$ev['id']];
        if ($day) { $w .= ' AND DATE(s.created_at) = ?'; $p[] = $day; }
        if ($barIds) $w .= ' AND s.bar_id IN (' . implode(',', array_map('intval', $barIds)) . ')';
        // carte de chaleur heure × point de vente
        $heat = []; $hours = [];
        foreach (all("SELECT b.name bar, HOUR(s.created_at) h, SUM(s.amount) t, COUNT(*) n FROM pos_sales s JOIN bars b ON b.id = s.bar_id WHERE $w GROUP BY s.bar_id, h", $p) as $r) { $heat[$r['bar']][(int)$r['h']] = ['t' => (int)$r['t'], 'n' => (int)$r['n']]; $hours[(int)$r['h']] = true; }
        $hours = array_keys($hours); sort($hours);
        // journées
        $days = all("SELECT DATE(s.created_at) d, COUNT(*) n, SUM(s.amount) t, MAX(HOUR(s.created_at)) last_h FROM pos_sales s WHERE s.event_id = ? AND s.undone = 0 AND s.kind = 'sale' GROUP BY d ORDER BY d", [$ev['id']]);
        foreach ($days as &$d) { $pk = one("SELECT HOUR(created_at) h, SUM(amount) t FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'sale' AND DATE(created_at) = ? GROUP BY h ORDER BY t DESC LIMIT 1", [$ev['id'], $d['d']]); $d['peak'] = $pk ? (int)$pk['h'] : null; $d['peak_t'] = $pk ? (int)$pk['t'] : 0; }
        unset($d);
        // panier moyen par profil et par moyen de paiement
        $prof = all("SELECT COALESCE(w.profile, 'sans') profile, COUNT(*) n, SUM(s.amount) t FROM pos_sales s LEFT JOIN wallets w ON w.id = s.wallet_id WHERE $w AND s.wallet_id IS NOT NULL GROUP BY profile ORDER BY t DESC", $p);
        $meth = all("SELECT s.method, COUNT(*) n, SUM(s.amount) t FROM pos_sales s WHERE $w GROUP BY s.method ORDER BY t DESC", $p);
        // produits achetés ensemble
        $pairs = []; $single = []; $nTickets = 0;
        foreach (all("SELECT s.items FROM pos_sales s WHERE $w ORDER BY s.id DESC LIMIT 5000", $p) as $r) {
            $names = []; foreach (json_decode((string)$r['items'], true) ?: [] as $l) if (($l['kind'] ?? '') === 'product') $names[preg_replace('~ · .*$| \(.*$~', '', (string)$l['name'])] = true;
            $names = array_keys($names); sort($names); $nTickets++;
            foreach ($names as $a) $single[$a] = ($single[$a] ?? 0) + 1;
            for ($i = 0; $i < count($names); $i++) for ($j = $i + 1; $j < count($names); $j++) { $k = $names[$i] . '|' . $names[$j]; $pairs[$k] = ($pairs[$k] ?? 0) + 1; }
        }
        arsort($pairs);
        $assoc = [];
        foreach (array_slice($pairs, 0, 12, true) as $k => $n) { if ($n < 2) continue; [$a, $b] = explode('|', $k); $assoc[] = ['a' => $a, 'b' => $b, 'n' => $n, 'pct' => (int)round($n / max(1, min($single[$a], $single[$b])) * 100)]; }
        // panier : nombre d'articles
        $sizes = []; foreach (all("SELECT s.items FROM pos_sales s WHERE $w LIMIT 5000", $p) as $r) { $q = 0; foreach (json_decode((string)$r['items'], true) ?: [] as $l) if (($l['kind'] ?? '') === 'product') $q += (int)$l['qty']; $k = min(5, max(1, $q)); $sizes[$k] = ($sizes[$k] ?? 0) + 1; }
        ksort($sizes);
        return compact('heat', 'hours', 'days', 'prof', 'meth', 'assoc', 'sizes') + ['tickets' => $nTickets];
    }

    /** Comparaison avec un autre événement (édition précédente) : totaux et produits. */
    public static function compare(array $ev, array $other): array
    {
        $tot = fn($id) => one("SELECT COUNT(*) n, COALESCE(SUM(amount), 0) t, COUNT(DISTINCT wallet_id) w, COALESCE(SUM(offered_value), 0) o FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'sale'", [$id]);
        $prods = function ($id) { $o = []; foreach (all("SELECT items FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind = 'sale'", [$id]) as $r) foreach (json_decode((string)$r['items'], true) ?: [] as $l) if (($l['kind'] ?? '') === 'product') { $n = preg_replace('~ · .*$| \(.*$~', '', (string)$l['name']); $o[$n]['q'] = ($o[$n]['q'] ?? 0) + (int)$l['qty']; $o[$n]['t'] = ($o[$n]['t'] ?? 0) + (int)$l['total']; } return $o; };
        $att = fn($id) => (int)val("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status = 'valid' AND entries > 0", [$id]);
        $a = $tot((int)$ev['id']); $b = $tot((int)$other['id']); $pa = $prods((int)$ev['id']); $pb = $prods((int)$other['id']);
        $rows = []; foreach (array_unique(array_merge(array_keys($pa), array_keys($pb))) as $n) $rows[$n] = ['a' => $pa[$n]['t'] ?? 0, 'b' => $pb[$n]['t'] ?? 0, 'qa' => $pa[$n]['q'] ?? 0, 'qb' => $pb[$n]['q'] ?? 0];
        uasort($rows, fn($x, $y) => ($y['a'] + $y['b']) <=> ($x['a'] + $x['b']));
        return ['a' => $a + ['att' => $att((int)$ev['id'])], 'b' => $b + ['att' => $att((int)$other['id'])], 'products' => array_slice($rows, 0, 15, true)];
    }

    // ---------- éco-cups ----------
    public static function ecocups(array $ev): array
    {
        $types = Till::cups((int)$ev['id']);
        $byBar = [];
        foreach (all("SELECT s.items, b.name bar FROM pos_sales s JOIN bars b ON b.id = s.bar_id WHERE s.event_id = ? AND s.undone = 0", [$ev['id']]) as $s) foreach (json_decode((string)$s['items'], true) ?: [] as $l) {
            $k = $l['kind'] ?? ''; if ($k !== 'deposit' && $k !== 'return') continue;
            $byBar[$s['bar']][$k] = ($byBar[$s['bar']][$k] ?? 0) + (int)$l['qty'];
        }
        $toWallet = (int)val("SELECT COALESCE(SUM(amount), 0) FROM wallet_tx WHERE event_id = ? AND kind = 'deposit' AND undone = 0", [$ev['id']]);
        return ['types' => $types, 'bars' => $byBar, 'to_wallet' => $toWallet];
    }
}
