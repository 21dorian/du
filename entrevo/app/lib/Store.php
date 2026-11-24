<?php
declare(strict_types=1);

/**
 * 2.1 : ventes « hors événement » d'un organisateur (cartes cadeaux, abonnements & pass, boutique).
 * Ce sont des commandes classiques (même paiement, même page de commande) avec event_id = 0.
 */
final class Store
{
    public const KIND_LABEL = ['gift' => 'Carte cadeau', 'pass' => 'Abonnement', 'shop' => 'Boutique'];

    /** Pseudo-événement utilisé par le paiement et les e-mails. */
    public static function pseudoEvent(array $o): array
    {
        $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
        $kind = (string)val('SELECT kind FROM order_items WHERE order_id = ? LIMIT 1', [$o['id']]);
        return ['id' => 0, 'name' => (self::KIND_LABEL[$kind] ?? 'Commande') . ' · ' . ($org['name'] ?? ''), 'slug' => '', 'organizer_id' => $o['organizer_id'],
            'starts_at' => $o['created_at'], 'ends_at' => null, 'venue' => '', 'city' => '', 'address' => '', 'image' => null, 'ticket_color' => null, 'ticket_logo' => null, 'kind' => $kind];
    }

    /**
     * Crée une commande en attente de paiement.
     * @param array $items [['kind' => gift|pass|shop, 'name', 'qty', 'unit_price', 'item_ref', 'variant']]
     */
    public static function createOrder(array $org, array $buyer, array $items, array $extra = []): array
    {
        $net = array_sum(array_map(fn($i) => (int)$i['unit_price'] * (int)$i['qty'], $items));
        $ship = (int)($extra['ship_price'] ?? 0);
        // pas de commission sur une carte cadeau : elle est prélevée quand la carte sert à payer des billets
        $units = array_sum(array_map(fn($i) => $i['kind'] === 'gift' ? 0 : (int)$i['qty'], $items));
        $feeBase = $net - array_sum(array_map(fn($i) => $i['kind'] === 'gift' ? (int)$i['unit_price'] * (int)$i['qty'] : 0, $items));
        $fees = $feeBase > 0 ? Tickets::computeFees($org, $feeBase, $units) : 0;
        $feeMode = $org['fee_mode'] === 'organizer' ? 'organizer' : 'buyer';
        if ($feeMode === 'buyer') { $total = $net + $fees + $ship; $orgAmount = $net + $ship; $commission = $fees; }
        else { $total = $net + $ship; $commission = min($fees, $feeBase); $orgAmount = $net + $ship - $commission; }
        $order = [
            'ref' => 'E' . random_code(7), 'token' => token(24), 'event_id' => 0, 'organizer_id' => $org['id'],
            'email' => mb_substr(strtolower(trim((string)$buyer['email'])), 0, 190), 'first_name' => mb_substr(trim((string)$buyer['first_name']), 0, 120),
            'last_name' => mb_substr(trim((string)$buyer['last_name']), 0, 120), 'phone' => mb_substr(trim((string)($buyer['phone'] ?? '')), 0, 40),
            'subtotal' => $net + $ship, 'discount' => 0, 'fees' => $fees, 'total' => $total, 'fee_mode' => $feeMode, 'commission' => $commission, 'organizer_amount' => $orgAmount,
            'method' => 'online', 'channel' => (string)($extra['channel'] ?? 'boutique'), 'status' => 'pending',
            'delivery' => $extra['delivery'] ?? null, 'ship_address' => $extra['ship_address'] ?? null, 'answers' => json_encode(['store' => $extra['meta'] ?? []], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'expires_at' => date('Y-m-d H:i:s', time() + 60 * max(5, (int)setting('pending_minutes'))),
        ];
        while (val('SELECT id FROM orders WHERE ref = ?', [$order['ref']])) $order['ref'] = 'E' . random_code(7);
        $order['id'] = insert('orders', $order);
        foreach ($items as $i) insert('order_items', ['order_id' => $order['id'], 'rate_id' => 0, 'kind' => $i['kind'], 'name' => mb_substr((string)$i['name'], 0, 190), 'qty' => (int)$i['qty'],
            'unit_price' => (int)$i['unit_price'], 'item_ref' => $i['item_ref'] ?? null, 'variant' => $i['variant'] ?? null, 'product_id' => null]);
        return $order;
    }

    /** Après paiement : crée les cartes cadeaux et les pass, envoie l'e-mail. */
    public static function fulfil(array $o): void
    {
        if ((int)$o['event_id'] !== 0 || val('SELECT id FROM gift_cards WHERE order_id = ? LIMIT 1', [$o['id']]) || val('SELECT id FROM passes WHERE order_id = ? LIMIT 1', [$o['id']])) return;
        $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
        $meta = (json_decode((string)$o['answers'], true) ?: [])['store'] ?? [];
        foreach (all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]) as $it) {
            for ($k = 0; $k < (int)$it['qty']; $k++) {
                if ($it['kind'] === 'gift') {
                    $card = self::newGiftCard($org, (int)$it['unit_price'], ['order_id' => $o['id'], 'buyer_email' => $o['email'],
                        'recipient_name' => $meta['recipient_name'] ?? null, 'recipient_email' => $meta['recipient_email'] ?? null, 'message' => $meta['message'] ?? null]);
                    self::mailGiftCard($card, $org, $o);
                } elseif ($it['kind'] === 'pass') {
                    $type = one('SELECT * FROM pass_types WHERE id = ?', [$it['item_ref']]);
                    if (!$type) continue;
                    $id = insert('passes', ['pass_type_id' => $type['id'], 'organizer_id' => $org['id'], 'order_id' => $o['id'], 'code' => self::uniqueCode('passes', 'AB'), 'token' => token(20),
                        'first_name' => $o['first_name'], 'last_name' => $o['last_name'], 'email' => $o['email'], 'uses_left' => $type['kind'] === 'carnet' ? (int)$type['entries'] : null, 'created_at' => now()]);
                    self::mailPass(one('SELECT * FROM passes WHERE id = ?', [$id]), $type, $org);
                }
            }
        }
        if (val("SELECT 1 FROM order_items WHERE order_id = ? AND kind = 'shop' LIMIT 1", [$o['id']])) self::mailShop($o, $org);
    }

    private static function uniqueCode(string $table, string $prefix): string
    {
        do { $c = $prefix . '-' . random_code(4) . '-' . random_code(4); } while (val("SELECT id FROM $table WHERE code = ?", [$c]));
        return $c;
    }

    // ---------- Cartes cadeaux ----------

    public static function newGiftCard(array $org, int $amount, array $data = []): array
    {
        $id = insert('gift_cards', $data + ['organizer_id' => $org['id'], 'code' => self::uniqueCode('gift_cards', 'KDO'), 'initial' => $amount, 'balance' => $amount,
            'status' => 'active', 'expires_at' => date('Y-m-d 23:59:59', strtotime('+' . max(1, (int)($org['gift_months'] ?? 12)) . ' months')), 'created_at' => now()]);
        return one('SELECT * FROM gift_cards WHERE id = ?', [$id]);
    }

    /** Carte utilisable chez cet organisateur (null + raison sinon). */
    public static function giftFind(int $orgId, string $code): array
    {
        $code = strtoupper(trim($code));
        $c = one('SELECT * FROM gift_cards WHERE code = ? AND organizer_id = ?', [$code, $orgId]);
        if (!$c) return [null, null];
        if ($c['status'] !== 'active') return [null, 'Cette carte cadeau n\'est plus active.'];
        if ($c['expires_at'] && strtotime((string)$c['expires_at']) < time()) return [null, 'Cette carte cadeau a expiré le ' . fr_date((string)$c['expires_at'], false) . '.'];
        if ((int)$c['balance'] <= 0) return [null, 'Cette carte cadeau est épuisée.'];
        return [$c, null];
    }

    /** Débite la carte au moment du paiement (appelé dans la transaction de markPaid). */
    public static function giftDebit(array $o): void
    {
        if (!$o['gift_code'] || (int)$o['gift_amount'] <= 0) return;
        $c = one('SELECT * FROM gift_cards WHERE code = ? AND organizer_id = ? FOR UPDATE', [$o['gift_code'], $o['organizer_id']]);
        if (!$c) return;
        $amt = min((int)$o['gift_amount'], (int)$c['balance']);
        update('gift_cards', ['balance' => (int)$c['balance'] - $amt, 'status' => (int)$c['balance'] - $amt <= 0 ? 'used' : 'active'], 'id = ?', [$c['id']]);
        insert('gift_card_uses', ['gift_card_id' => $c['id'], 'order_id' => $o['id'], 'amount' => $amt, 'created_at' => now()]);
    }

    public static function giftPdf(array $c, array $org): string
    {
        $pdf = new PdfDoc('L', 'mm', [100, 180]);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->SetFillColor(11, 11, 12); $pdf->Rect(0, 0, 180, 100, 'F');
        $pdf->SetFillColor(212, 175, 55); $pdf->Rect(0, 0, 4, 100, 'F');
        $pdf->SetTextColor(161, 161, 166); $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(14, 12); $pdf->Cell(150, 5, PdfDoc::t('CARTE CADEAU · ' . mb_strtoupper($org['name'])));
        $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 34);
        $pdf->SetXY(14, 24); $pdf->Cell(150, 16, PdfDoc::t(money((int)$c['initial'])));
        $pdf->SetFont('Helvetica', '', 11); $pdf->SetTextColor(210, 210, 215);
        $pdf->SetXY(14, 44);
        $pdf->MultiCell(150, 5.5, PdfDoc::t(($c['recipient_name'] ? 'Pour ' . $c['recipient_name'] . "\n" : '') . ($c['message'] ? '« ' . $c['message'] . ' »' : '')), 0, 'L');
        $pdf->SetFillColor(255, 255, 255); $pdf->RoundedRect(14, 72, 100, 16, 3, 'F');
        $pdf->SetTextColor(11, 11, 12); $pdf->SetFont('Courier', 'B', 15);
        $pdf->SetXY(14, 72); $pdf->Cell(100, 16, $c['code'], 0, 0, 'C');
        $pdf->SetTextColor(161, 161, 166); $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(120, 72); $pdf->MultiCell(52, 4, PdfDoc::t('À saisir dans « Code promo ou carte cadeau » au moment de réserver. Valable jusqu\'au ' . date('d/m/Y', strtotime((string)$c['expires_at'])) . '.'), 0, 'L');
        return $pdf->Output('S');
    }

    private static function mailGiftCard(array $c, array $org, array $o): void
    {
        $to = $c['recipient_email'] ?: $o['email'];
        if (!is_email((string)$to)) return;
        $forOther = $c['recipient_email'] && strtolower((string)$c['recipient_email']) !== strtolower((string)$o['email']);
        try {
            Mailer::send((string)$to, ($forOther ? trim($o['first_name']) . ' t\'offre une carte cadeau ' : 'Ta carte cadeau ') . $org['name'],
                '<p>Bonjour' . ($c['recipient_name'] ? ' ' . e($c['recipient_name']) : '') . ',</p>'
                . ($forOther ? '<p><b>' . e(trim($o['first_name'] . ' ' . $o['last_name'])) . '</b> t\'offre une carte cadeau de <b>' . e(money((int)$c['initial'])) . '</b> à utiliser pour les événements de <b>' . e($org['name']) . '</b>.</p>'
                    : '<p>Voici ta carte cadeau de <b>' . e(money((int)$c['initial'])) . '</b> pour les événements de <b>' . e($org['name']) . '</b>. Le PDF est prêt à être imprimé ou offert.</p>')
                . ($c['message'] ? '<blockquote style="margin:16px 0;padding:12px 16px;border-left:3px solid #d4af37;background:#f5f5f7">' . nl2br(e($c['message'])) . '</blockquote>' : '')
                . '<p style="font-size:20px;font-family:monospace;letter-spacing:1px"><b>' . e($c['code']) . '</b></p>'
                . '<p>À saisir dans « Code promo ou carte cadeau » au moment de réserver. Valable jusqu\'au ' . e(date('d/m/Y', strtotime((string)$c['expires_at']))) . '.</p>'
                . '<p><a href="' . e(abs_url('/o/' . $org['slug'])) . '" style="display:inline-block;background:#1d1d1f;color:#fff;padding:12px 20px;border-radius:980px;text-decoration:none">Voir les événements</a></p>',
                [['data' => self::giftPdf($c, $org), 'name' => 'carte-cadeau-' . $c['code'] . '.pdf']], $org['email'] ?? null);
            if ($forOther && is_email((string)$o['email'])) Mailer::send((string)$o['email'], 'Ta carte cadeau est partie', '<p>C\'est envoyé : ' . e((string)$c['recipient_name'] ?: (string)$c['recipient_email']) . ' a reçu sa carte cadeau de ' . e(money((int)$c['initial'])) . ' (code ' . e($c['code']) . ').</p>');
        } catch (Throwable $e) { log_line('emails', 'Carte cadeau ' . $c['code'] . ' : ' . $e->getMessage()); }
    }

    // ---------- Abonnements & pass ----------

    public static function passTypeEvents(array $type): array
    {
        $ids = array_filter(array_map('intval', explode(',', (string)$type['event_ids'])));
        $sql = "SELECT * FROM events WHERE organizer_id = ? AND status = 'published' AND COALESCE(ends_at, starts_at) >= NOW()";
        $p = [$type['organizer_id']];
        if ($ids) $sql .= ' AND id IN (' . implode(',', $ids) . ')';
        if ($type['valid_until']) { $sql .= ' AND starts_at <= ?'; $p[] = $type['valid_until']; }
        return all($sql . ' ORDER BY starts_at', $p);
    }

    public static function passSold(array $type): int { return (int)val("SELECT COUNT(*) FROM passes WHERE pass_type_id = ? AND status <> 'cancelled'", [$type['id']]); }

    /** Réserve la place d'un abonné pour un événement (billet à 0 €, mêmes règles de jauge). */
    public static function passBook(array $pass, array $ev): array
    {
        $type = one('SELECT * FROM pass_types WHERE id = ?', [$pass['pass_type_id']]);
        if ($pass['status'] !== 'active') return ['error' => 'Ce pass n\'est plus actif.'];
        if (!in_array((int)$ev['id'], array_map(fn($e) => (int)$e['id'], self::passTypeEvents($type)), true)) return ['error' => 'Cet événement n\'est pas compris dans ton pass.'];
        if ($pass['uses_left'] !== null && (int)$pass['uses_left'] <= 0) return ['error' => 'Ton carnet est épuisé.'];
        if (val("SELECT id FROM orders WHERE pass_id = ? AND event_id = ? AND status = 'paid' LIMIT 1", [$pass['id'], $ev['id']])) return ['error' => 'Tu as déjà ta place pour cet événement.'];
        $rate = one('SELECT * FROM rates WHERE event_id = ? AND active = 1 AND hidden = 0 ORDER BY price, sort, id LIMIT 1', [$ev['id']]);
        if (!$rate) return ['error' => 'Aucun tarif disponible pour cet événement.'];
        $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
        $sessions = Tickets::sessions((int)$ev['id']);
        $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'session' => $sessions[0] ?? null, 'quantities' => [$rate['id'] => 1], 'method' => 'pass', 'channel' => 'abonnement',
            'buyer' => ['email' => $pass['email'], 'first_name' => $pass['first_name'], 'last_name' => $pass['last_name']], 'note' => 'Pass ' . $pass['code']]);
        if (!empty($r['errors'])) return ['error' => implode(' ', $r['errors'])];
        update('orders', ['pass_id' => $pass['id']], 'id = ?', [$r['order']['id']]);
        q("UPDATE order_items SET name = ? WHERE order_id = ? AND kind = 'rate'", [$type['name'], $r['order']['id']]);
        $o = Tickets::confirm((int)$r['order']['id'], ['provider' => 'free']);
        q("UPDATE tickets SET rate_name = ? WHERE order_id = ?", [$type['name'], $o['id']]);
        if ($pass['uses_left'] !== null) q('UPDATE passes SET uses_left = uses_left - 1 WHERE id = ?', [$pass['id']]);
        return ['order' => $o];
    }

    private static function mailPass(array $p, array $type, array $org): void
    {
        if (!is_email((string)$p['email'])) return;
        try {
            Mailer::send((string)$p['email'], 'Ton ' . $type['name'] . ' · ' . $org['name'],
                '<p>Bonjour ' . e((string)$p['first_name']) . ',</p><p>Bienvenue parmi les abonnés de <b>' . e($org['name']) . '</b> !</p>'
                . '<p>Ton ' . e($type['name']) . ($p['uses_left'] !== null ? ' (' . (int)$p['uses_left'] . ' entrées)' : '') . ' est actif. Réserve ta place à chaque événement depuis ton espace, en un clic :</p>'
                . '<p><a href="' . e(abs_url('/pass/' . $p['token'])) . '" style="display:inline-block;background:#1d1d1f;color:#fff;padding:12px 20px;border-radius:980px;text-decoration:none">Mon abonnement</a></p>'
                . '<p style="color:#86868b">Numéro d\'abonné : ' . e($p['code']) . '</p>', [], $org['email'] ?? null);
        } catch (Throwable $e) { log_line('emails', 'Pass ' . $p['code'] . ' : ' . $e->getMessage()); }
    }

    // ---------- Boutique ----------

    public static function shopRemaining(array $p): int
    {
        if ((int)$p['stock'] <= 0) return PHP_INT_MAX;
        $sold = (int)val("SELECT COALESCE(SUM(i.qty), 0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.kind = 'shop' AND i.item_ref = ? AND (o.status = 'paid' OR (o.status = 'pending' AND o.expires_at > NOW()))", [$p['id']]);
        return max(0, (int)$p['stock'] - $sold);
    }

    private static function mailShop(array $o, array $org): void
    {
        if (!is_email((string)$o['email'])) return;
        $rows = implode('', array_map(fn($i) => '<li>' . (int)$i['qty'] . ' × ' . e($i['name']) . ($i['variant'] ? ' (' . e($i['variant']) . ')' : '') . '</li>', all("SELECT * FROM order_items WHERE order_id = ? AND kind = 'shop'", [$o['id']])));
        try {
            Mailer::send((string)$o['email'], 'Ta commande ' . $o['ref'] . ' · ' . $org['name'],
                '<p>Merci ' . e((string)$o['first_name']) . ' !</p><p>Ta commande <b>' . e($o['ref']) . '</b> est confirmée :</p><ul>' . $rows . '</ul>'
                . ($o['delivery'] === 'ship' ? '<p>Elle sera expédiée à :<br>' . nl2br(e((string)$o['ship_address'])) . '</p>' : '<p>Retrait sur place' . ($org['pickup_info'] ? ' : ' . e($org['pickup_info']) : '') . '. Présente ce numéro de commande.</p>')
                . '<p><a href="' . e(abs_url('/commande/' . $o['ref'], ['t' => $o['token']])) . '">Voir ma commande</a></p>', [], $org['email'] ?? null);
        } catch (Throwable $e) { log_line('emails', 'Boutique ' . $o['ref'] . ' : ' . $e->getMessage()); }
    }
}
