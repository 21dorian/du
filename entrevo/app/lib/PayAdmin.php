<?php
declare(strict_types=1);

/**
 * 4.4 : annulations depuis le back-office Entrevo Pay (sans limite de temps, avec motif et trace).
 *  - vente de bar : en totalité ou article par article ; le cashless est recrédité, l'espèce ou la carte est notée « à rendre » ;
 *    les ventes offertes ou payées avec un crédit sont simplement annulées ; le stock revient ; une commande click & collect passe « annulée » ;
 *  - recharge : en ligne, remboursée chez le prestataire de paiement puis retirée du porte-monnaie ; en espèces ou par carte, retirée du
 *    porte-monnaie (le montant est à rendre).
 */
final class PayAdmin
{
    private static function who(): string { $u = user(); return $u ? (string)($u['name'] ?: $u['email']) : 'Back-office'; }

    /** @param array|null $want [index de ligne => quantité] ; null = tout ce qui reste */
    public static function cancelSale(int $eventId, int $saleId, ?array $want, string $reason): array
    {
        $reason = mb_substr(trim($reason), 0, 90);
        if ($reason === '') throw new RuntimeException('Indique le motif de l\'annulation.');
        $s = one('SELECT * FROM pos_sales WHERE id = ? AND event_id = ?', [$saleId, $eventId]);
        if (!$s) throw new RuntimeException('Vente introuvable.');
        if ((int)$s['undone']) throw new RuntimeException('Cette vente est déjà annulée.');
        $kind = $s['kind'] ?? 'sale';
        if ($kind === 'topup') return self::cancelPosTopup($s, $reason);
        if ($kind !== 'sale') throw new RuntimeException('Seules les ventes et les recharges s\'annulent.');
        $info = Pos::saleGet(['event_id' => $eventId, 'bar_id' => 0], ['sale_id' => $saleId]);
        $items = json_decode((string)$s['items'], true) ?: [];
        $note = 'Annulée (back-office) : ' . $reason . ' · ' . self::who();
        // vente sans encaissement (offerte, crédit) : on l'annule simplement
        if (!$info['refundable']) {
            db()->beginTransaction();
            try {
                update('pos_sales', ['undone' => 1, 'offer_reason' => mb_substr(trim(($s['offer_reason'] ? $s['offer_reason'] . ' · ' : '') . $note), 0, 120)], 'id = ?', [$s['id']]);
                self::restock($s, null);
                if ($s['prep_status'] && $s['prep_status'] !== 'served') update('pos_sales', ['prep_status' => 'cancelled'], 'id = ?', [$s['id']]);
                db()->commit();
            } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
            Audit::log('pay.sale_cancel', 'Ticket n° ' . $s['ticket_no'] . ' annulé · ' . $reason, $eventId);
            return ['amount' => 0, 'method' => $s['method'], 'message' => 'Vente annulée, stock remis.'];
        }
        $left = []; foreach ($info['lines'] as $l) $left[$l['i']] = $l['left'];
        if ($want === null) $want = array_filter($left);
        $want = array_filter(array_map('intval', $want), fn($q) => $q > 0);
        if (!$want) throw new RuntimeException('Il ne reste rien à annuler sur ce ticket.');
        $lines = []; $sum = 0; $vat = []; $restock = [];
        foreach ($want as $i => $q) {
            if (!isset($left[$i]) || $q > $left[$i]) throw new RuntimeException('Quantité supérieure à ce qui reste sur le ticket.');
            $l = $items[$i]; $amt = (int)round((int)$l['total'] * $q / max(1, (int)$l['qty']));
            $lines[] = ['kind' => 'product', 'id' => (int)$l['id'], 'name' => 'Annulation ' . $l['name'], 'qty' => $q, 'unit' => -(int)round($amt / $q), 'full' => -$amt, 'total' => -$amt, 'ref' => $i,
                'vat' => $l['vat'] ?? null, 'category_id' => $l['category_id'] ?? null];
            $sum += $amt;
            if (isset($l['vat'])) { $k = number_format((float)$l['vat'], 1, '.', ''); $vat[$k] = ($vat[$k] ?? 0) - $amt; }
            foreach ($l['stock_items'] ?? (!empty($l['stock']) ? [[(int)$l['id'], (int)$l['qty']]] : []) as [$spid, $sq]) $restock[] = [(int)$spid, (int)round((int)$sq * $q / max(1, (int)$l['qty']))];
        }
        $method = $info['refund_method'];
        $all = array_sum($want) === array_sum($left);
        db()->beginTransaction();
        try {
            $row = ['event_id' => $eventId, 'bar_id' => (int)$s['bar_id'], 'device_id' => null, 'method' => $method, 'kind' => 'refund', 'amount' => -$sum, 'subtotal' => -$sum, 'discount' => 0, 'offered_value' => 0,
                'staff' => mb_substr(self::who(), 0, 60), 'refund_of' => $s['id'], 'offer_reason' => mb_substr($note, 0, 120), 'items' => json_encode($lines, JSON_UNESCAPED_UNICODE), 'vat_json' => $vat ? json_encode($vat) : null,
                'created_at' => now(), 'received_at' => now(), 'receipt_token' => bin2hex(random_bytes(12)), 'wallet_id' => $s['wallet_id'], 'offline' => 0, 'undone' => 0, 'ticket_no' => Till::nextTicket($eventId), 'source' => 'admin'];
            if ($method === 'cashless' && $s['wallet_id']) {
                $tx = insert('wallet_tx', ['wallet_id' => $s['wallet_id'], 'event_id' => $eventId, 'kind' => 'rembours', 'amount' => $sum, 'bar_id' => $s['bar_id'], 'device' => 'back-office', 'items' => json_encode(['ticket' => (int)$s['ticket_no'], 'motif' => $reason], JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
                q('UPDATE wallets SET balance = balance + ? WHERE id = ?', [$sum, $s['wallet_id']]);
                $row['wallet_tx_id'] = $tx;
            }
            $id = insert('pos_sales', $row);
            foreach ($restock as [$pid, $q]) if ($q > 0) Till::stockMove($eventId, $pid, (int)$s['bar_id'], $q, 'refund', $id);
            if ($all && $s['prep_status'] && $s['prep_status'] !== 'served') update('pos_sales', ['prep_status' => 'cancelled'], 'id = ?', [$s['id']]);
            db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        Audit::log('pay.sale_cancel', 'Ticket n° ' . $s['ticket_no'] . ($all ? ' annulé' : ' annulé en partie') . ' · ' . money($sum) . ' · ' . $reason, $eventId);
        $how = ['cashless' => 'recrédités sur le porte-monnaie', 'cash' => 'à rendre en espèces', 'card' => 'à rembourser sur le terminal de paiement'][$method] ?? 'à rendre';
        return ['amount' => $sum, 'method' => $method, 'message' => ($all ? 'Vente annulée' : 'Annulation partielle enregistrée') . ' : ' . money($sum) . ' ' . $how . '.'];
    }

    /** Recharge faite en caisse (espèces ou carte) : retirée du porte-monnaie, montant à rendre. */
    private static function cancelPosTopup(array $s, string $reason): array
    {
        if (!$s['wallet_tx_id']) throw new RuntimeException('Recharge sans porte-monnaie associé.');
        $r = self::undoTx((int)$s['wallet_tx_id'], (int)$s['event_id'], $reason);
        update('pos_sales', ['undone' => 1], 'id = ?', [$s['id']]);
        return ['amount' => $r['amount'], 'method' => $s['method'], 'message' => 'Recharge annulée : ' . money($r['amount']) . ' retirés du porte-monnaie, à rendre ' . ($s['method'] === 'card' ? 'sur le terminal de paiement' : 'en espèces') . '.'];
    }

    /** Recharge listée dans le back-office (en ligne, en caisse, crédit offert). */
    public static function cancelTopup(int $eventId, int $txId, string $reason): array
    {
        $reason = mb_substr(trim($reason), 0, 90);
        if ($reason === '') throw new RuntimeException('Indique le motif de l\'annulation.');
        $t = one("SELECT * FROM wallet_tx WHERE id = ? AND event_id = ? AND kind IN ('topup','cash','card','gift','adjust') AND undone = 0", [$txId, $eventId]);
        if (!$t || (int)$t['amount'] <= 0) throw new RuntimeException('Recharge introuvable ou déjà annulée.');
        $msg = '';
        if ($t['kind'] === 'topup' && $t['order_id']) {
            $o = one('SELECT * FROM orders WHERE id = ?', [$t['order_id']]);
            $w = one('SELECT balance FROM wallets WHERE id = ?', [$t['wallet_id']]);
            if ((int)$w['balance'] < (int)$t['amount']) throw new RuntimeException('Le solde (' . money((int)$w['balance']) . ') est inférieur à la recharge : une partie a déjà été dépensée.');
            if ($o && $o['status'] === 'paid') {
                if ($o['method'] === 'online' && $o['provider'] !== 'demo') $msg = Payments::refund($o, (int)$t['amount']) . ' ';
                update('orders', ['status' => 'refunded', 'refunded_amount' => (int)$o['refunded_amount'] + (int)$t['amount'], 'cancelled_at' => now(), 'organizer_amount' => max(0, (int)$o['organizer_amount'] - (int)$t['amount'])], 'id = ?', [$o['id']]);
            }
        }
        $r = self::undoTx($txId, $eventId, $reason);
        q('UPDATE pos_sales SET undone = 1 WHERE wallet_tx_id = ? AND kind = ?', [$txId, 'topup']);
        $how = ['topup' => 'remboursés à l\'acheteur', 'cash' => 'à rendre en espèces', 'card' => 'à rembourser sur le terminal de paiement', 'gift' => 'retirés (crédit offert)', 'adjust' => 'retirés'][$t['kind']] ?? 'retirés';
        return ['amount' => $r['amount'], 'message' => trim($msg . 'Recharge annulée : ' . money($r['amount']) . ' ' . $how . '.')];
    }

    /** Retire du porte-monnaie le montant d'une opération créditée (vérifie le solde), avec trace. */
    private static function undoTx(int $txId, int $eventId, string $reason): array
    {
        db()->beginTransaction();
        try {
            $t = one('SELECT * FROM wallet_tx WHERE id = ? AND event_id = ? AND undone = 0 FOR UPDATE', [$txId, $eventId]);
            if (!$t) throw new RuntimeException('Opération introuvable ou déjà annulée.');
            $w = one('SELECT * FROM wallets WHERE id = ? FOR UPDATE', [$t['wallet_id']]);
            $amt = (int)$t['amount'];
            if ((int)$w['balance'] - $amt < 0) throw new RuntimeException('Solde insuffisant pour annuler : ' . money((int)$w['balance']) . ' disponibles sur ' . money($amt) . '.');
            update('wallet_tx', ['undone' => 1], 'id = ?', [$t['id']]);
            insert('wallet_tx', ['wallet_id' => $w['id'], 'event_id' => $eventId, 'kind' => 'undo', 'amount' => -$amt, 'device' => 'back-office', 'items' => json_encode(['annule' => (int)$t['id'], 'motif' => $reason, 'par' => self::who()], JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
            q('UPDATE wallets SET balance = balance - ? WHERE id = ?', [$amt, $w['id']]);
            db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        Audit::log('pay.topup_cancel', 'Porte-monnaie ' . $w['code'] . ' · ' . money($amt) . ' · ' . $reason, $eventId);
        return ['amount' => $amt];
    }

    private static function restock(array $s, ?array $want): void
    {
        foreach (json_decode((string)$s['items'], true) ?: [] as $l) if (($l['kind'] ?? '') === 'product')
            foreach ($l['stock_items'] ?? (!empty($l['stock']) ? [[(int)$l['id'], (int)$l['qty']]] : []) as [$spid, $sq]) Till::stockMove((int)$s['event_id'], (int)$spid, (int)$s['bar_id'], (int)$sq, 'undo', (int)$s['id']);
    }
}
