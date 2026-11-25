<?php
declare(strict_types=1);

/**
 * 2.7 : moteur de la caisse événementielle (Entrevo Caisse Pro).
 * - Catalogue : catégories (couleur, TVA), produits avec photo, variantes, prix par bar, consigne, stock.
 * - Prix calculés par le serveur à chaque vente : variantes, prix du bar, happy hours et remises programmées,
 *   remise manuelle, articles offerts, consignes (ajoutées, rendues, non remboursables).
 * - Équipe : serveurs et responsables avec code PIN personnel et droits ; une action protégée (offrir, remise,
 *   annulation…) peut être validée par le code d'un responsable.
 * - Crédits offerts (staff, artistes) : X consommations ou X € par jour, sur badge QR ou bracelet NFC.
 * - Sessions de caisse : fond de caisse, apports et remises en banque, clôture (Z) avec écart compté / attendu.
 * - Stocks par bar, tickets numérotés, reçu consultable par QR code et envoyé par e-mail.
 */
final class Till
{
    public const METHODS = ['cashless' => 'Cashless', 'card' => 'Carte bancaire', 'cash' => 'Espèces', 'allowance' => 'Crédit offert', 'offered' => 'Offert'];
    public const PERMS = ['offer' => 'Offrir des articles', 'discount' => 'Faire une remise', 'undo' => 'Annuler une vente', 'deposit' => 'Rendre des consignes',
        'cash' => 'Apports et remises en banque', 'close' => 'Ouvrir et clôturer la caisse'];
    public const ROLES = ['server' => ['Serveur', ['deposit']], 'manager' => ['Responsable', ['offer', 'discount', 'undo', 'deposit', 'cash', 'close']]];
    public const PROMO_KINDS = ['percent' => 'Remise en %', 'amount' => 'Remise en €', 'price' => 'Prix fixe', 'second' => '2e article offert'];
    public const DAYS = ['1' => 'Lun', '2' => 'Mar', '3' => 'Mer', '4' => 'Jeu', '5' => 'Ven', '6' => 'Sam', '7' => 'Dim'];
    public const GROUPS = ['staff' => 'Bénévoles & staff', 'artist' => 'Artistes', 'tech' => 'Technique', 'guest' => 'Invités & partenaires'];
    public const SETTINGS = ['require_staff' => 0, 'sessions' => 1, 'methods' => ['cashless', 'card', 'cash', 'allowance'], 'receipt' => 'ask', 'receipt_footer' => '',
        'default_vat' => 20, 'drop_alert' => 50000, 'photos' => 1, 'offer_reasons' => "Geste commercial\nErreur de service\nÉquipe / artiste\nPartenaire", 'big_buttons' => 0];

    // ---------- réglages ----------
    public static function settings(array $ev): array
    {
        $j = json_decode((string)($ev['pos_json'] ?? ''), true);
        $s = (is_array($j) ? $j : []) + self::SETTINGS;
        $s['methods'] = array_values(array_intersect((array)$s['methods'], ['cashless', 'card', 'cash', 'allowance'])) ?: ['card', 'cash'];
        if (!(int)($ev['cashless_enabled'] ?? 0)) $s['methods'] = array_values(array_diff($s['methods'], ['cashless']));
        return $s;
    }

    public static function reasons(array $s): array { return array_values(array_filter(array_map('trim', preg_split('~\R~', (string)$s['offer_reasons'])))); }

    // ---------- équipe ----------
    public static function perms(?array $st): array
    {
        if (!$st) return [];
        $p = $st['perms'] !== null && $st['perms'] !== '' ? explode(',', (string)$st['perms']) : (self::ROLES[$st['role']][1] ?? []);
        return array_values(array_intersect($p, array_keys(self::PERMS)));
    }

    public static function hasStaff(int $orgId): bool { return (bool)val('SELECT 1 FROM pos_staff WHERE organizer_id = ? AND active = 1 LIMIT 1', [$orgId]); }

    public static function staffByPin(int $orgId, string $pin): ?array
    {
        $pin = preg_replace('~\D~', '', $pin);
        if (strlen($pin) < 4) return null;
        foreach (all('SELECT * FROM pos_staff WHERE organizer_id = ? AND active = 1 AND (pin_hint IS NULL OR pin_hint = ?)', [$orgId, substr($pin, 0, 2)]) as $s)
            if (password_verify($pin, $s['pin_hash'])) return $s;
        return null;
    }

    public static function pinTaken(int $orgId, string $pin, int $except = 0): bool
    {
        $s = self::staffByPin($orgId, $pin);
        return $s && (int)$s['id'] !== $except;
    }

    /** Jeton de session du serveur sur un appareil (valable la journée). */
    public static function staffToken(array $d, array $st): string { return hash_hmac('sha256', $d['id'] . '|' . $st['id'] . '|' . date('Ymd'), (string)config('secret', 'entrevo')); }

    public static function staffFrom(array $d, array $in): ?array
    {
        $id = (int)($in['staff_id'] ?? 0); $tok = (string)($in['staff_token'] ?? '');
        if (!$id || $tok === '') return null;
        $st = one('SELECT * FROM pos_staff WHERE id = ? AND active = 1', [$id]);
        if (!$st) return null;
        foreach ([date('Ymd'), date('Ymd', time() - 86400)] as $day)
            if (hash_equals(hash_hmac('sha256', $d['id'] . '|' . $st['id'] . '|' . $day, (string)config('secret', 'entrevo')), $tok)) return $st;
        return null;
    }

    /** Droit accordé au serveur connecté, ou validé par le code d'un responsable. @return array|null qui a validé */
    public static function authorize(int $orgId, ?array $staff, string $perm, ?string $approverPin, bool $required): ?array
    {
        if ($staff && in_array($perm, self::perms($staff), true)) return $staff;
        if (!self::hasStaff($orgId)) return null; // pas encore d'équipe déclarée : pas de validation possible, donc pas exigée
        if ($approverPin) { $a = self::staffByPin($orgId, $approverPin); if ($a && in_array($perm, self::perms($a), true)) return $a; throw new RuntimeException('Ce code ne permet pas : ' . mb_strtolower(self::PERMS[$perm]) . '.'); }
        if ($required) throw new RuntimeException('Validation d\'un responsable nécessaire : ' . mb_strtolower(self::PERMS[$perm]) . '.');
        return null;
    }

    // ---------- catalogue ----------
    public static function variants(array $p): array
    {
        $v = json_decode((string)($p['variants_json'] ?? ''), true);
        return is_array($v) ? array_values(array_filter($v, fn($x) => is_array($x) && trim((string)($x['name'] ?? '')) !== '')) : [];
    }

    public static function barPrices(array $p): array
    {
        $v = json_decode((string)($p['bar_prices_json'] ?? ''), true);
        return is_array($v) ? array_map('intval', $v) : [];
    }

    public static function basePrice(array $p, int $barId, ?int $variant): int
    {
        $vs = self::variants($p);
        if ($variant !== null && isset($vs[$variant])) return (int)$vs[$variant]['price'];
        return self::barPrices($p)[$barId] ?? (int)$p['price'];
    }

    public static function promos(int $eventId): array { return all('SELECT * FROM pos_promos WHERE event_id = ? AND active = 1 ORDER BY id', [$eventId]); }

    public static function promoActive(array $pr, int $barId, int $ts): bool
    {
        $d = date('Y-m-d', $ts); $t = date('H:i:s', $ts); $dow = date('N', $ts);
        if ($pr['starts_on'] && $d < $pr['starts_on']) return false;
        if ($pr['ends_on'] && $d > $pr['ends_on']) return false;
        if ($pr['days'] && !in_array($dow, explode(',', (string)$pr['days']), true)) return false;
        if ($pr['bar_ids'] && !in_array((string)$barId, explode(',', (string)$pr['bar_ids']), true)) return false;
        if ($pr['start_time'] && $pr['end_time']) {
            $in = $pr['start_time'] <= $pr['end_time'] ? ($t >= $pr['start_time'] && $t < $pr['end_time']) : ($t >= $pr['start_time'] || $t < $pr['end_time']);
            if (!$in) return false;
        }
        return true;
    }

    public static function promoApplies(array $pr, array $p): bool
    {
        $pids = array_filter(explode(',', (string)$pr['product_ids'])); $cids = array_filter(explode(',', (string)$pr['category_ids']));
        if (!$pids && !$cids) return true;
        return in_array((string)$p['id'], $pids, true) || ($p['category_id'] && in_array((string)$p['category_id'], $cids, true));
    }

    /** Meilleur prix du moment pour qty unités. @return array{total:int, unit:int, promo:?string} */
    public static function bestPrice(array $p, int $unit, int $qty, array $promos, int $barId, int $ts): array
    {
        $best = ['total' => $unit * $qty, 'promo' => null];
        foreach ($promos as $pr) {
            if (!self::promoActive($pr, $barId, $ts) || !self::promoApplies($pr, $p)) continue;
            $v = (int)$pr['value'];
            $tot = match ($pr['kind']) {
                'percent' => (int)round($unit * (100 - min(100, $v)) / 100) * $qty,
                'amount' => max(0, $unit - $v) * $qty,
                'price' => min($unit, $v) * $qty,
                'second' => $unit * ($qty - intdiv($qty, 2)),
                default => $unit * $qty,
            };
            if ($tot < $best['total']) $best = ['total' => $tot, 'promo' => $pr['name']];
        }
        return $best;
    }

    /** Catalogue envoyé à l'appli (prix de base ; l'appli affiche aussi les happy hours, le serveur recalcule tout). */
    public static function catalog(array $ev): array
    {
        $cats = all('SELECT id, name, color, sort FROM pos_categories WHERE event_id = ? AND active = 1 ORDER BY sort, id', [$ev['id']]);
        $deps = all('SELECT id, name, amount, refundable FROM pos_deposit_types WHERE event_id = ? AND active = 1 ORDER BY id', [$ev['id']]);
        $stock = [];
        foreach (all('SELECT s.* FROM pos_stock s JOIN bar_products p ON p.id = s.product_id WHERE p.event_id = ?', [$ev['id']]) as $r) $stock[(int)$r['product_id']][(int)$r['bar_id']] = (int)$r['qty'];
        $products = array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name'], 'desc' => $p['description'], 'category_id' => $p['category_id'] ? (int)$p['category_id'] : null,
            'price' => (int)$p['price'], 'bar_prices' => (object)self::barPrices($p), 'variants' => array_map(fn($v) => ['name' => $v['name'], 'price' => (int)$v['price']], self::variants($p)),
            'photo' => $p['photo'] ? abs_url('/' . ltrim($p['photo'], '/')) : null, 'color' => $p['color'], 'deposit_type_id' => $p['deposit_type_id'] ? (int)$p['deposit_type_id'] : null,
            'bars' => $p['bar_ids'] ? array_map('intval', explode(',', (string)$p['bar_ids'])) : [], 'stock' => (int)$p['track_stock'] ? (object)($stock[(int)$p['id']] ?? []) : null],
            all('SELECT * FROM bar_products WHERE event_id = ? AND active = 1 ORDER BY sort, name', [$ev['id']]));
        $promos = array_map(fn($pr) => ['name' => $pr['name'], 'kind' => $pr['kind'], 'value' => (int)$pr['value'], 'products' => array_map('intval', array_filter(explode(',', (string)$pr['product_ids']))),
            'categories' => array_map('intval', array_filter(explode(',', (string)$pr['category_ids']))), 'bars' => array_map('intval', array_filter(explode(',', (string)$pr['bar_ids']))),
            'days' => array_map('intval', array_filter(explode(',', (string)$pr['days']))), 'from' => $pr['start_time'] ? substr($pr['start_time'], 0, 5) : null, 'to' => $pr['end_time'] ? substr($pr['end_time'], 0, 5) : null,
            'starts_on' => $pr['starts_on'], 'ends_on' => $pr['ends_on']], self::promos((int)$ev['id']));
        return ['categories' => array_map(fn($c) => ['id' => (int)$c['id'], 'name' => $c['name'], 'color' => $c['color']], $cats), 'products' => $products,
            'deposits' => array_map(fn($d) => ['id' => (int)$d['id'], 'name' => $d['name'], 'amount' => (int)$d['amount'], 'refundable' => (bool)$d['refundable']], $deps), 'promos' => $promos];
    }

    // ---------- calcul d'un panier ----------
    /**
     * $in : items [{id, variant, qty, offered}], returns [{deposit_id, qty}], discount_pct, offer_all.
     * @return array{lines:array, subtotal:int, discount:int, offered:int, total:int, vat:array, products:int}
     */
    public static function price(array $ev, int $barId, array $in, int $ts): array
    {
        $s = self::settings($ev);
        $promos = self::promos((int)$ev['id']);
        $deps = []; foreach (all('SELECT * FROM pos_deposit_types WHERE event_id = ?', [$ev['id']]) as $d) $deps[(int)$d['id']] = $d;
        $cats = []; foreach (all('SELECT * FROM pos_categories WHERE event_id = ?', [$ev['id']]) as $c) $cats[(int)$c['id']] = $c;
        $lines = []; $sub = 0; $offered = 0; $vat = []; $depCount = []; $nProducts = 0;
        $pct = max(0, min(100, (float)($in['discount_pct'] ?? 0)));
        foreach ((array)($in['items'] ?? []) as $it) {
            $qty = (int)($it['qty'] ?? 0);
            if ($qty <= 0 || $qty > 200) continue;
            $p = one('SELECT * FROM bar_products WHERE id = ? AND event_id = ?', [(int)($it['id'] ?? 0), $ev['id']]);
            if (!$p) { // produit retiré depuis une vente hors ligne : prix envoyé par l'appli
                if (!isset($it['unit'])) continue;
                $p = ['id' => (int)($it['id'] ?? 0), 'name' => mb_substr((string)($it['name'] ?? 'Article'), 0, 80), 'price' => max(0, min(100000, (int)$it['unit'])), 'category_id' => null, 'variants_json' => null, 'bar_prices_json' => null, 'deposit_type_id' => null, 'vat_rate' => null, 'track_stock' => 0];
            }
            if ($p['bar_ids'] ?? null) if (!in_array((string)$barId, explode(',', (string)$p['bar_ids']), true)) throw new RuntimeException($p['name'] . ' n\'est pas vendu dans ce bar.');
            $variant = isset($it['variant']) && $it['variant'] !== null && $it['variant'] !== '' ? (int)$it['variant'] : null;
            $vs = self::variants($p);
            $unit = self::basePrice($p, $barId, $variant);
            $name = $p['name'] . ($variant !== null && isset($vs[$variant]) ? ' · ' . $vs[$variant]['name'] : '');
            $bp = self::bestPrice($p, $unit, $qty, $promos, $barId, $ts);
            $full = $unit * $qty; $tot = $bp['total'];
            $isOff = !empty($it['offered']) || !empty($in['offer_all']);
            $rate = $p['vat_rate'] !== null ? (float)$p['vat_rate'] : (($p['category_id'] && isset($cats[(int)$p['category_id']]) && $cats[(int)$p['category_id']]['vat_rate'] !== null) ? (float)$cats[(int)$p['category_id']]['vat_rate'] : (float)$s['default_vat']);
            $line = ['kind' => 'product', 'id' => (int)$p['id'], 'name' => $name, 'variant' => $variant, 'qty' => $qty, 'unit' => $unit, 'full' => $full, 'total' => $tot, 'promo' => $bp['promo'], 'vat' => $rate,
                'category_id' => $p['category_id'] ? (int)$p['category_id'] : null, 'stock' => (int)$p['track_stock']];
            if ($isOff) { $offered += $tot; $line['offered'] = true; $line['total'] = 0; }
            $lines[] = $line; $sub += $line['total']; $nProducts += $qty;
            if ($p['deposit_type_id'] && isset($deps[(int)$p['deposit_type_id']])) $depCount[(int)$p['deposit_type_id']] = ($depCount[(int)$p['deposit_type_id']] ?? 0) + $qty;
        }
        // remise manuelle (produits seulement)
        $discount = $pct > 0 ? (int)round($sub * $pct / 100) : 0;
        if ($discount) {
            $left = $discount; $base = max(1, $sub);
            foreach ($lines as $i => $l) { if ($l['total'] <= 0) continue; $cut = min($l['total'], (int)round($discount * $l['total'] / $base)); $lines[$i]['total'] -= $cut; $left -= $cut; }
            foreach ($lines as $i => $l) { if ($left === 0) break; if ($lines[$i]['total'] > 0) { $c = $left > 0 ? min($left, $lines[$i]['total']) : $left; $lines[$i]['total'] -= $c; $left -= $c; } }
        }
        foreach ($lines as $l) if ($l['kind'] === 'product' && $l['total'] > 0) { $k = number_format($l['vat'], 1, '.', ''); $vat[$k] = ($vat[$k] ?? 0) + $l['total']; }
        // consignes ajoutées (toujours payées, même pour un article offert)
        foreach ($depCount as $did => $q) {
            $d = $deps[$did];
            $lines[] = ['kind' => 'deposit', 'id' => $did, 'name' => $d['name'] . ((int)$d['refundable'] ? ' (consigne)' : ''), 'qty' => $q, 'unit' => (int)$d['amount'], 'full' => (int)$d['amount'] * $q, 'total' => (int)$d['amount'] * $q, 'refundable' => (bool)$d['refundable']];
        }
        // consignes rendues
        foreach ((array)($in['returns'] ?? []) as $r) {
            $did = (int)($r['deposit_id'] ?? 0); $q = (int)($r['qty'] ?? 0);
            if (!$q || !isset($deps[$did])) continue;
            if (!(int)$deps[$did]['refundable']) throw new RuntimeException($deps[$did]['name'] . ' n\'est pas remboursable.');
            $q = min(200, abs($q));
            $lines[] = ['kind' => 'return', 'id' => $did, 'name' => 'Retour ' . $deps[$did]['name'], 'qty' => $q, 'unit' => -(int)$deps[$did]['amount'], 'full' => -(int)$deps[$did]['amount'] * $q, 'total' => -(int)$deps[$did]['amount'] * $q];
        }
        $total = array_sum(array_column($lines, 'total'));
        $depTotal = array_sum(array_map(fn($l) => $l['kind'] !== 'product' ? $l['total'] : 0, $lines));
        return ['lines' => $lines, 'subtotal' => $sub + $discount + $offered, 'discount' => $discount, 'offered' => $offered, 'total' => $total, 'deposits' => $depTotal, 'vat' => $vat, 'products' => $nProducts];
    }

    // ---------- crédits offerts ----------
    public static function allowance(int $eventId, array $in): ?array
    {
        if (!empty($in['nfc'])) { $u = Pos::nfc((string)$in['nfc']); return $u ? one('SELECT * FROM pos_allowances WHERE event_id = ? AND nfc_uid = ? AND active = 1', [$eventId, $u]) : null; }
        $c = strtoupper(trim(preg_replace('~^.*[/=]~', '', (string)($in['code'] ?? ''))));
        return $c !== '' ? one('SELECT * FROM pos_allowances WHERE event_id = ? AND code = ? AND active = 1', [$eventId, $c]) : null;
    }

    /** Consommé aujourd'hui et au total (en € ou en articles). */
    public static function allowanceUsed(array $a): array
    {
        $today = 0; $all = 0;
        foreach (all("SELECT created_at, offered_value, items FROM pos_sales WHERE allowance_id = ? AND undone = 0", [$a['id']]) as $s) {
            $v = $a['mode'] === 'euros' ? (int)$s['offered_value'] : array_sum(array_map(fn($l) => ($l['kind'] ?? 'product') === 'product' ? (int)$l['qty'] : 0, json_decode((string)$s['items'], true) ?: []));
            $all += $v; if (substr($s['created_at'], 0, 10) === date('Y-m-d')) $today += $v;
        }
        $leftDay = (int)$a['per_day'] > 0 ? max(0, (int)$a['per_day'] - $today) : PHP_INT_MAX;
        $leftAll = $a['total_limit'] !== null ? max(0, (int)$a['total_limit'] - $all) : PHP_INT_MAX;
        return ['today' => $today, 'all' => $all, 'left' => min($leftDay, $leftAll)];
    }

    public static function allowanceLabel(array $a, int $v): string { return $a['mode'] === 'euros' ? money($v) : $v . ' conso' . ($v > 1 ? 's' : ''); }

    // ---------- numéros, sessions ----------
    public static function nextTicket(int $eventId): int
    {
        q('UPDATE events SET pos_seq = LAST_INSERT_ID(pos_seq + 1) WHERE id = ?', [$eventId]);
        return (int)val('SELECT LAST_INSERT_ID()');
    }

    public static function session(array $d): ?array { return one('SELECT * FROM pos_sessions WHERE device_id = ? AND closed_at IS NULL ORDER BY id DESC LIMIT 1', [$d['id']]); }

    public static function sessionTotals(array $se): array
    {
        $by = [];
        foreach (all('SELECT method, COUNT(*) n, SUM(amount) total, SUM(offered_value) offered, SUM(discount) discount FROM pos_sales WHERE session_id = ? AND undone = 0 GROUP BY method', [$se['id']]) as $r)
            $by[$r['method']] = ['n' => (int)$r['n'], 'total' => (int)$r['total'], 'offered' => (int)$r['offered'], 'discount' => (int)$r['discount']];
        $moves = ['add' => 0, 'drop' => 0];
        foreach (all('SELECT kind, SUM(amount) a FROM pos_cash_moves WHERE session_id = ? GROUP BY kind', [$se['id']]) as $m) $moves[$m['kind']] = (int)$m['a'];
        $cash = (int)$se['float_amount'] + ($by['cash']['total'] ?? 0) + $moves['add'] - $moves['drop'];
        $prod = [];
        foreach (all('SELECT items FROM pos_sales WHERE session_id = ? AND undone = 0', [$se['id']]) as $r) foreach (json_decode((string)$r['items'], true) ?: [] as $l) {
            $k = (string)$l['name']; $prod[$k]['qty'] = ($prod[$k]['qty'] ?? 0) + (int)$l['qty']; $prod[$k]['total'] = ($prod[$k]['total'] ?? 0) + (int)$l['total'];
        }
        uasort($prod, fn($a, $b) => $b['qty'] <=> $a['qty']);
        return ['methods' => $by, 'moves' => $moves, 'float' => (int)$se['float_amount'], 'expected_cash' => $cash, 'products' => $prod,
            'total' => array_sum(array_map(fn($m) => $m['total'], $by)), 'count' => array_sum(array_column($by, 'n')), 'offered' => array_sum(array_column($by, 'offered'))];
    }

    // ---------- stocks ----------
    public static function stockMove(int $eventId, int $productId, int $barId, int $delta, string $kind, ?int $saleId = null, ?int $userId = null, ?string $note = null): void
    {
        q('INSERT INTO pos_stock (product_id, bar_id, qty) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)', [$productId, $barId, $delta]);
        insert('pos_stock_moves', ['event_id' => $eventId, 'product_id' => $productId, 'bar_id' => $barId, 'delta' => $delta, 'kind' => $kind, 'sale_id' => $saleId, 'user_id' => $userId, 'note' => $note ? mb_substr($note, 0, 255) : null, 'created_at' => now()]);
    }

    /** Gobelets : sortis (vendus avec consigne), rendus, gardés. */
    public static function cups(int $eventId): array
    {
        $out = [];
        foreach (all('SELECT * FROM pos_deposit_types WHERE event_id = ? ORDER BY id', [$eventId]) as $d) $out[(int)$d['id']] = $d + ['issued' => 0, 'returned' => 0, 'value_in' => 0, 'value_out' => 0];
        foreach (all('SELECT items FROM pos_sales WHERE event_id = ? AND undone = 0', [$eventId]) as $s) foreach (json_decode((string)$s['items'], true) ?: [] as $l) {
            if (!in_array($l['kind'] ?? '', ['deposit', 'return'], true) || !isset($out[(int)$l['id']])) continue;
            if ($l['kind'] === 'deposit') { $out[(int)$l['id']]['issued'] += (int)$l['qty']; $out[(int)$l['id']]['value_in'] += (int)$l['total']; }
            else { $out[(int)$l['id']]['returned'] += (int)$l['qty']; $out[(int)$l['id']]['value_out'] += -(int)$l['total']; }
        }
        foreach ($out as &$d) { $d['out'] = max(0, $d['issued'] - $d['returned']); $d['left'] = $d['stock_initial'] !== null ? (int)$d['stock_initial'] - $d['out'] : null; }
        return array_values($out);
    }

    // ---------- reçu ----------
    public static function receiptUrl(array $s): string { return abs_url('/recu/' . $s['receipt_token']); }

    public static function receiptHtml(array $s): string
    {
        $ev = one('SELECT * FROM events WHERE id = ?', [$s['event_id']]);
        $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
        $bar = (string)val('SELECT name FROM bars WHERE id = ?', [$s['bar_id']]);
        $rows = '';
        foreach (json_decode((string)$s['items'], true) ?: [] as $l)
            $rows .= '<tr><td style="padding:6px 0">' . (int)$l['qty'] . ' × ' . e($l['name']) . (!empty($l['offered']) ? ' <i>(offert)</i>' : '') . (!empty($l['promo']) ? ' <small style="color:#86868b">' . e($l['promo']) . '</small>' : '') . '</td><td style="padding:6px 0;text-align:right">' . e(money((int)$l['total'])) . '</td></tr>';
        $vat = '';
        foreach (json_decode((string)$s['vat_json'], true) ?: [] as $rate => $ttc) { $tax = (int)round($ttc - $ttc / (1 + (float)$rate / 100)); $vat .= 'TVA ' . rtrim(rtrim($rate, '0'), '.') . ' % : ' . money($tax) . ' · '; }
        $footer = trim((string)self::settings($ev)['receipt_footer']);
        return '<p style="margin:0 0 4px;color:#86868b">Ticket n° ' . (int)$s['ticket_no'] . ' · ' . e(date('d/m/Y H:i', strtotime($s['created_at']))) . '</p>'
            . '<p style="margin:0 0 14px"><b>' . e($ev['name']) . '</b> · ' . e($bar) . '</p>'
            . '<table style="width:100%;border-collapse:collapse;font-size:15px">' . $rows
            . ((int)$s['discount'] ? '<tr><td style="padding:6px 0">Remise</td><td style="padding:6px 0;text-align:right">−' . e(money((int)$s['discount'])) . '</td></tr>' : '')
            . '<tr><td style="padding:10px 0;border-top:1px solid #e5e5ea"><b>Total payé</b></td><td style="padding:10px 0;border-top:1px solid #e5e5ea;text-align:right"><b>' . e(money((int)$s['amount'])) . '</b></td></tr></table>'
            . '<p style="color:#86868b;font-size:13px;margin:10px 0 0">' . e(self::METHODS[$s['method']] ?? $s['method']) . ((int)$s['offered_value'] ? ' · offert : ' . e(money((int)$s['offered_value'])) : '') . ($vat ? '<br>' . e(rtrim($vat, ' ·')) : '') . '</p>'
            . '<p style="color:#86868b;font-size:12px;margin:12px 0 0">' . e($org['legal_name'] ?: $org['name']) . ($org['address'] ? ' · ' . e(str_replace("\n", ', ', (string)$org['address'])) : '') . ($org['siret'] ? ' · SIRET ' . e($org['siret']) : '') . ($footer ? '<br>' . nl2br(e($footer)) : '') . '</p>';
    }

    public static function sendReceipt(array $s, string $email): void
    {
        $email = strtolower(trim($email));
        if (!is_email($email)) throw new RuntimeException('Adresse e-mail invalide.');
        $ev = one('SELECT name FROM events WHERE id = ?', [$s['event_id']]);
        Mailer::send($email, 'Ton ticket de caisse · ' . $ev['name'], self::receiptHtml($s) . '<p style="margin-top:18px"><a href="' . e(self::receiptUrl($s)) . '">Voir le ticket en ligne</a></p>');
        update('pos_sales', ['receipt_email' => $email, 'receipt_sent_at' => now()], 'id = ?', [$s['id']]);
    }
}
