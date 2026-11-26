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
        'regie' => ['Régisseur', ['live', 'wallets', 'bracelets', 'refunds', 'packs', 'ecocups', 'catalogue', 'consignes', 'remises', 'appareils', 'caisses', 'stocks', 'equipe', 'credits', 'rapport', 'ventes', 'materiel', 'exploitants', 'exports', 'reglages', 'journal']],
        'bar' => ['Responsable de bar', ['live', 'caisses', 'stocks', 'appareils', 'materiel', 'ventes']],
        'compta' => ['Comptable', ['live', 'rapport', 'ventes', 'caisses', 'exploitants', 'exports', 'refunds', 'journal']],
        'support' => ['Support festivaliers', ['live', 'wallets', 'bracelets', 'refunds']],
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
