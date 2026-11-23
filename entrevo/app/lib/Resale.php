<?php
declare(strict_types=1);

/**
 * Bourse aux billets officielle : le vendeur met son billet en vente (prix plafonné au prix payé),
 * un nouvel acheteur le paie, l'ancien QR code est annulé, un nouveau est émis,
 * et le vendeur est remboursé automatiquement moins les frais de revente.
 */
final class Resale
{
    public static function canList(array $t, array $o, array $ev): ?string
    {
        if (!$ev['resale_enabled']) return 'La revente n\'est pas ouverte pour cet événement.';
        if ($t['status'] !== 'valid' || $t['type'] !== 'ticket') return 'Ce billet ne peut pas être revendu.';
        if ((int)$t['entries'] > 0) return 'Ce billet a déjà servi : impossible de le revendre.';
        if ($t['resale_status'] === 'listed') return 'Ce billet est déjà en vente.';
        if ((int)$t['price'] <= 0) return 'Les billets gratuits ne se revendent pas 😉';
        if ($o['method'] !== 'online' || !in_array($o['provider'], ['stripe', 'paypal', 'sumup', 'mollie', 'demo'], true)) return 'Seuls les billets achetés en ligne peuvent être revendus.';
        $start = $t['session_id'] ? val('SELECT starts_at FROM sessions WHERE id = ?', [$t['session_id']]) : $ev['starts_at'];
        if (strtotime((string)$start) < time()) return 'L\'événement a commencé, la bourse est fermée.';
        return null;
    }

    public static function list(array $t, array $o, array $ev): int
    {
        $price = (int)$t['price']; // plafonné au prix payé (loi du 12 mars 2012)
        $fee = (int)round($price * (float)$ev['resale_fee_percent'] / 100);
        $id = insert('resales', ['ticket_id' => $t['id'], 'event_id' => $ev['id'], 'seller_order_id' => $o['id'], 'price' => $price, 'fee' => $fee,
            'status' => 'listed', 'created_at' => now()]);
        update('tickets', ['resale_status' => 'listed', 'updated_at' => now()], 'id = ?', [$t['id']]);
        // on prévient les premières personnes de la liste d'attente
        foreach (all("SELECT * FROM waitlist WHERE event_id = ? AND status = 'waiting' AND (session_id IS NULL OR session_id = ?) ORDER BY id LIMIT 5", [$ev['id'], (int)$t['session_id']]) as $w) {
            try { Mailer::send($w['email'], '🔁 Un billet est dispo en revente : ' . $ev['name'], '<p>Un billet vient d\'être mis en vente sur la bourse officielle. Premier arrivé, premier servi !</p><p><a href="' . e(abs_url('/revente/' . $id)) . '">Voir le billet</a></p>'); }
            catch (Throwable $e) { log_line('emails', $e->getMessage()); }
        }
        return $id;
    }

    /** Appelé quand la commande de rachat est payée. */
    public static function settle(array $newOrder): void
    {
        $x = one('SELECT * FROM resales WHERE id = ?', [$newOrder['resale_id']]);
        if (!$x || $x['status'] === 'sold') return;
        $old = one('SELECT * FROM tickets WHERE id = ?', [$x['ticket_id']]);
        $new = one('SELECT * FROM tickets WHERE order_id = ? ORDER BY id LIMIT 1', [$newOrder['id']]);
        update('resales', ['status' => 'sold', 'buyer_order_id' => $newOrder['id'], 'sold_at' => now()], 'id = ?', [$x['id']]);
        update('tickets', ['status' => 'cancelled', 'resale_status' => 'sold', 'replaced_by' => $new['id'] ?? null, 'inside' => 0, 'updated_at' => now()], 'id = ?', [$old['id']]);
        if ($new) update('tickets', ['seat' => $old['seat'], 'session_id' => $old['session_id'], 'max_entries' => $old['max_entries'], 'per_day' => $old['per_day']], 'id = ?', [$new['id']]);
        $seller = one('SELECT * FROM orders WHERE id = ?', [$x['seller_order_id']]);
        $amount = (int)$x['price'] - (int)$x['fee'];
        // compta : la part organisateur passe sur la nouvelle commande, la plateforme garde les frais de revente
        update('orders', ['organizer_amount' => max(0, (int)$seller['organizer_amount'] - (int)$x['price']), 'commission' => (int)$seller['commission'] + (int)$x['fee'],
            'refunded_amount' => (int)$seller['refunded_amount'] + $amount], 'id = ?', [$seller['id']]);
        try {
            $msg = Payments::refund($seller, $amount);
            update('resales', ['refund_status' => 'ok', 'refund_info' => mb_substr($msg, 0, 500)], 'id = ?', [$x['id']]);
        } catch (Throwable $e) {
            update('resales', ['refund_status' => 'failed', 'refund_info' => mb_substr($e->getMessage(), 0, 500)], 'id = ?', [$x['id']]);
            log_line('revente', 'Remboursement vendeur ' . $seller['ref'] . ' : ' . $e->getMessage());
        }
        try {
            if (is_email((string)$seller['email'])) Mailer::send((string)$seller['email'], '💸 Ton billet est revendu !', '<p>Ton billet « ' . e($old['rate_name']) . ' » a trouvé preneur sur la bourse officielle.</p>'
                . '<p>On te rembourse <b>' . e(money($amount)) . '</b> (prix ' . e(money((int)$x['price'])) . ' moins ' . e(money((int)$x['fee'])) . ' de frais de revente) sur ton moyen de paiement d\'origine, sous quelques jours.</p><p>Ton ancien QR code ne marche plus. Bonne continuation 👋</p>');
        } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
    }
}
