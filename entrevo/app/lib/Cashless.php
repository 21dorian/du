<?php
declare(strict_types=1);

/**
 * 2.4 : cashless. Le QR code du billet sert de porte-monnaie : le participant le recharge en ligne avant ou pendant
 * l'événement, le bar le scanne pour payer. Recharges aussi possibles au bar (espèces, carte). Après l'événement,
 * le solde restant peut être remboursé sur demande jusqu'à la date choisie par l'organisateur.
 * Toutes les opérations sont dans wallet_tx (le solde en est la somme) ; balance est une copie pour aller vite.
 */
final class Cashless
{
    public const KINDS = ['topup' => 'Recharge en ligne', 'cash' => 'Recharge espèces', 'card' => 'Recharge carte', 'sale' => 'Achat', 'refund' => 'Remboursement', 'undo' => 'Annulation', 'deposit' => 'Retour de consigne'];

    /** Porte-monnaie d'un billet (créé au premier usage). */
    public static function forTicket(array $t): array
    {
        $w = one('SELECT * FROM wallets WHERE ticket_id = ?', [$t['id']]);
        if ($w) return $w;
        $email = (string)val('SELECT email FROM orders WHERE id = ?', [$t['order_id']]);
        $id = insert('wallets', ['event_id' => $t['event_id'], 'ticket_id' => $t['id'], 'code' => $t['code'], 'token' => token(24), 'email' => $t['holder_email'] ?: $email ?: null, 'balance' => 0, 'status' => 'active', 'created_at' => now()]);
        return one('SELECT * FROM wallets WHERE id = ?', [$id]);
    }

    /** Retrouve un porte-monnaie à partir du code scanné (QR du billet). */
    public static function byCode(int $eventId, string $raw): ?array
    {
        $code = strtoupper(trim(preg_replace('~^.*[/=]~', '', trim($raw))));
        $w = one('SELECT * FROM wallets WHERE code = ? AND event_id = ?', [$code, $eventId]);
        if ($w) return $w;
        $t = one("SELECT * FROM tickets WHERE code = ? AND event_id = ? AND status = 'valid'", [$code, $eventId]);
        return $t ? self::forTicket($t) : null;
    }

    private static function move(array $w, string $kind, int $amount, array $extra = []): array
    {
        $own = !db()->inTransaction(); // 2.7 : peut s'inscrire dans la transaction d'une vente de caisse
        if ($own) db()->beginTransaction();
        try {
            $cur = one('SELECT * FROM wallets WHERE id = ? FOR UPDATE', [$w['id']]);
            if ($cur['status'] !== 'active' && $kind !== 'refund') throw new RuntimeException('Porte-monnaie bloqué.');
            if ($amount < 0 && (int)$cur['balance'] + $amount < 0) throw new RuntimeException('Solde insuffisant : ' . money((int)$cur['balance']) . ' disponibles.');
            $tx = insert('wallet_tx', ['wallet_id' => $w['id'], 'event_id' => $cur['event_id'], 'kind' => $kind, 'amount' => $amount, 'created_at' => now()] + $extra);
            q('UPDATE wallets SET balance = balance + ? WHERE id = ?', [$amount, $w['id']]);
            if ($own) db()->commit();
        } catch (Throwable $e) { if ($own && db()->inTransaction()) db()->rollBack(); throw $e; }
        return ['tx' => $tx, 'balance' => (int)val('SELECT balance FROM wallets WHERE id = ?', [$w['id']])];
    }

    public static function topup(array $w, int $amount, string $kind, array $extra = []): array
    {
        if ($amount <= 0 || $amount > 50000) throw new RuntimeException('Montant de recharge invalide.');
        return self::move($w, $kind, $amount, $extra);
    }

    /** Achat au bar. $items : [[product_id, qty]] */
    public static function sale(array $w, int $barId, array $items, ?int $userId, string $device = ''): array
    {
        $total = 0; $lines = [];
        foreach ($items as [$pid, $qty]) {
            $p = one('SELECT * FROM bar_products WHERE id = ? AND event_id = ? AND active = 1', [(int)$pid, $w['event_id']]);
            if (!$p || (int)$qty === 0) continue;
            $total += ((int)$p['price'] + (int)$p['deposit']) * (int)$qty;
            $lines[] = ['id' => (int)$p['id'], 'name' => $p['name'], 'qty' => (int)$qty, 'unit' => (int)$p['price'] + (int)$p['deposit']];
        }
        if (!$lines) throw new RuntimeException('Panier vide.');
        if ($total === 0) throw new RuntimeException('Rien à payer.');
        return self::move($w, 'sale', -$total, ['bar_id' => $barId, 'items' => json_encode($lines, JSON_UNESCAPED_UNICODE), 'user_id' => $userId, 'device' => mb_substr($device, 0, 60)]) + ['total' => $total, 'lines' => $lines];
    }

    /** 2.7 : débit (ou crédit pour un retour de consigne) d'un montant calculé par la caisse. */
    public static function charge(array $w, int $total, int $barId, array $lines, string $device): array
    {
        $items = array_map(fn($l) => ['name' => $l['name'], 'qty' => (int)$l['qty'], 'unit' => (int)$l['unit']], $lines);
        if ($total === 0) return ['tx' => null, 'balance' => (int)val('SELECT balance FROM wallets WHERE id = ?', [$w['id']])];
        return self::move($w, $total > 0 ? 'sale' : 'deposit', -$total, ['bar_id' => $barId, 'items' => json_encode($items, JSON_UNESCAPED_UNICODE), 'device' => mb_substr($device, 0, 60)]);
    }

    /** Annule une opération de bar (erreur de saisie) dans les 10 minutes. */
    public static function undo(int $txId, int $eventId, ?int $userId): array
    {
        $tx = one("SELECT * FROM wallet_tx WHERE id = ? AND event_id = ? AND kind IN ('sale','cash','card','deposit') AND undone = 0 AND created_at >= NOW() - INTERVAL 10 MINUTE", [$txId, $eventId]);
        if (!$tx) throw new RuntimeException('Opération introuvable ou trop ancienne pour être annulée.');
        $w = one('SELECT * FROM wallets WHERE id = ?', [$tx['wallet_id']]);
        update('wallet_tx', ['undone' => 1], 'id = ?', [$tx['id']]);
        return self::move($w, 'undo', -(int)$tx['amount'], ['bar_id' => $tx['bar_id'], 'user_id' => $userId, 'items' => json_encode(['annule' => (int)$tx['id']])]);
    }

    /** Recharge en ligne payée (appelée par Store::fulfil). */
    public static function creditOrder(array $o, array $item): void
    {
        if (val("SELECT id FROM wallet_tx WHERE order_id = ? AND kind = 'topup'", [$o['id']])) return;
        $w = one('SELECT * FROM wallets WHERE id = ?', [(int)$item['item_ref']]);
        if (!$w) return;
        $r = self::topup($w, (int)$item['unit_price'] * (int)$item['qty'], 'topup', ['order_id' => $o['id']]);
        if (class_exists('Hooks')) Hooks::fire((int)$o['organizer_id'], 'wallet.topup', ['ticket' => $w['code'], 'amount' => (int)$item['unit_price'], 'balance' => $r['balance'], 'order' => $o['ref']]);
        $ev = one('SELECT name FROM events WHERE id = ?', [$w['event_id']]);
        try { Mailer::send((string)$o['email'], 'Recharge cashless confirmée', '<p>Ta recharge de <b>' . e(money((int)$item['unit_price'])) . '</b> pour <b>' . e($ev['name'] ?? '') . '</b> est créditée. Nouveau solde : <b>' . e(money($r['balance'])) . '</b>.</p><p>Au bar, présente simplement le QR code de ton billet.</p>'
            . '<p><a href="' . e(abs_url('/cashless/' . $w['token'])) . '">Voir mon solde et mes achats</a></p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
    }

    public static function stats(int $eventId): array
    {
        $k = one("SELECT COALESCE(SUM(CASE WHEN kind IN ('topup','cash','card') AND undone = 0 THEN amount END), 0) loaded,
            COALESCE(SUM(CASE WHEN kind = 'topup' THEN amount END), 0) online, COALESCE(SUM(CASE WHEN kind IN ('cash','card') AND undone = 0 THEN amount END), 0) onsite,
            COALESCE(-SUM(CASE WHEN kind = 'sale' AND undone = 0 THEN amount END), 0) spent, COUNT(CASE WHEN kind = 'sale' AND undone = 0 THEN 1 END) sales,
            COALESCE(-SUM(CASE WHEN kind = 'refund' THEN amount END), 0) refunded FROM wallet_tx WHERE event_id = ?", [$eventId]) ?: [];
        $k['left'] = (int)val('SELECT COALESCE(SUM(balance), 0) FROM wallets WHERE event_id = ?', [$eventId]);
        $k['wallets'] = (int)val('SELECT COUNT(*) FROM wallets WHERE event_id = ? AND balance > 0', [$eventId]);
        $k['basket'] = (int)$k['sales'] ? (int)round((int)$k['spent'] / (int)$k['sales']) : 0;
        return $k;
    }
}
