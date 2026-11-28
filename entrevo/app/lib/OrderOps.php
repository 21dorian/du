<?php
declare(strict_types=1);

/**
 * 4.4 : gestion avancée des commandes depuis le back-office.
 *  - annulation avec choix : remboursement total, partiel, hors frais de service, avoir (carte cadeau) ou aucun ; tout ou partie des billets ;
 *    motif obligatoire, e-mail à l'acheteur ; commandes en attente, réservations et invitations comprises ;
 *  - ajout de billets (encaissés sur place, offerts ou réglés par un lien de paiement envoyé à l'acheteur) ;
 *  - changement de tarif d'un billet (différence encaissée, offerte ou remboursée) et changement de date.
 */
final class OrderOps
{
    public const REASONS = ['Demande de l\'acheteur', 'Événement annulé ou reporté', 'Erreur de commande', 'Doublon', 'Paiement contesté', 'Geste commercial', 'Autre'];

    private static function who(): string { $u = user(); return $u ? (string)($u['name'] ?: $u['email']) : 'Organisation'; }

    /**
     * @param array $opt mode (full|nofees|partial|credit|none), amount (partiel, en centimes), tickets (ids ; vide = tous), reason, note, notify (bool)
     */
    public static function cancel(array $o, array $opt): string
    {
        $reason = trim((string)($opt['reason'] ?? '')); $note = trim((string)($opt['note'] ?? ''));
        if ($reason === '') throw new RuntimeException('Choisis le motif de l\'annulation.');
        $motif = mb_substr($reason . ($note !== '' ? ' — ' . $note : ''), 0, 255);
        $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
        $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
        // commande pas encore payée (attente, réservation, lien de paiement) : on libère les places
        if (in_array($o['status'], ['pending', 'expired'], true)) {
            update('orders', ['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $motif], 'id = ?', [$o['id']]);
            q('DELETE FROM seat_holds WHERE order_id = ?', [$o['id']]);
            Audit::log('order.cancelled', 'Commande non payée annulée · ' . $motif, (int)$o['event_id'], (int)$o['id']);
            if (!empty($opt['notify'])) self::mail($o, $ev, $org, 'Ta réservation ' . $o['ref'] . ' est annulée', '<p>Ta réservation <b>' . e($o['ref']) . '</b> pour <b>' . e($ev['name']) . '</b> est annulée : les places sont remises en vente.</p><p>Motif : ' . e($motif) . '</p>');
            return 'Commande annulée, places libérées.';
        }
        if ($o['status'] !== 'paid') throw new RuntimeException('Cette commande est déjà annulée ou remboursée.');
        $valid = all("SELECT * FROM tickets WHERE order_id = ? AND status = 'valid'", [$o['id']]);
        $ids = array_values(array_intersect(array_map('intval', (array)($opt['tickets'] ?? [])), array_map(fn($t) => (int)$t['id'], $valid)));
        $all = !$ids || count($ids) === count($valid);
        if ($all) $ids = array_map(fn($t) => (int)$t['id'], $valid);
        $chosen = array_values(array_filter($valid, fn($t) => in_array((int)$t['id'], $ids, true)));
        if (val('SELECT id FROM tickets WHERE id IN (' . implode(',', $ids ?: [0]) . ') AND entries > 0 LIMIT 1') && empty($opt['force'])) throw new RuntimeException('Un de ces billets a déjà servi à l\'entrée : coche « annuler quand même » pour confirmer.');
        $left = max(0, (int)$o['total'] - (int)$o['refunded_amount']);
        $ticketsValue = array_sum(array_map(fn($t) => (int)$t['price'], $chosen));
        $mode = (string)($opt['mode'] ?? 'full');
        $amount = match ($mode) {
            'full', 'credit' => $all ? $left : min($left, $ticketsValue + (int)round((int)$o['fees'] * count($chosen) / max(1, count($valid)))),
            'nofees' => $all ? max(0, $left - (int)$o['fees']) : min($left, $ticketsValue),
            'partial' => min($left, max(0, (int)($opt['amount'] ?? 0))),
            default => 0,
        };
        if ((int)$o['total'] === 0) { $mode = 'none'; $amount = 0; }
        if ($mode === 'partial' && $amount <= 0) throw new RuntimeException('Indique le montant à rembourser.');
        $msg = ''; $giftCode = null;
        if ($amount > 0 && $mode !== 'credit') {
            if ($o['method'] === 'online') {
                try { $msg = Payments::refund($o, $amount); }
                catch (Throwable $e) { throw new RuntimeException('Le remboursement a échoué : ' . $e->getMessage() . ' — rien n\'a été modifié.'); }
            } else $msg = money($amount) . ' à rendre à l\'acheteur (' . (['especes' => 'espèces', 'cb' => 'carte', 'cheque' => 'chèque', 'virement' => 'virement'][$o['pay_method'] ?? ''] ?? 'comme payé') . ')';
        }
        if ($mode === 'credit' && $amount > 0) {
            $g = Store::newGiftCard($org, $amount, ['buyer_email' => strtolower((string)$o['email']) ?: null, 'recipient_name' => trim($o['first_name'] . ' ' . $o['last_name']) ?: null,
                'recipient_email' => strtolower((string)$o['email']) ?: null, 'message' => 'Avoir — commande ' . $o['ref'] . ' annulée']);
            $giftCode = $g['code']; $msg = 'Avoir de ' . money($amount) . ' créé (code ' . $giftCode . ')';
            update('orders', ['credit_gift_id' => $g['id']], 'id = ?', [$o['id']]);
        }
        $refunded = (int)$o['refunded_amount'] + ($mode === 'credit' ? 0 : $amount);
        $up = ['refunded_amount' => $refunded, 'organizer_amount' => max(0, (int)$o['organizer_amount'] - ($mode === 'credit' ? 0 : $amount)), 'cancel_reason' => $motif];
        if ($all) {
            Tickets::cancelOrder($o, $amount > 0 && $mode !== 'credit' ? 'refunded' : 'cancelled');
        } else {
            q("UPDATE tickets SET status = 'cancelled', inside = 0, updated_at = NOW() WHERE order_id = ? AND id IN (" . implode(',', $ids) . ')', [$o['id']]);
        }
        update('orders', $up, 'id = ?', [$o['id']]);
        $label = ($all ? 'Commande annulée' : count($chosen) . ' billet(s) annulé(s)') . ' · ' . ['full' => 'remboursement total', 'nofees' => 'remboursée hors frais', 'partial' => 'remboursement partiel', 'credit' => 'avoir', 'none' => 'sans remboursement'][$mode] . ($amount ? ' ' . money($amount) : '') . ' · ' . $motif;
        Audit::log($amount > 0 && $mode !== 'credit' ? 'order.refunded' : 'order.cancelled', $label . ' · ' . self::who(), (int)$o['event_id'], (int)$o['id']);
        if (!empty($opt['notify'])) {
            $what = $all ? 'Ta commande <b>' . e($o['ref']) . '</b> pour <b>' . e($ev['name']) . '</b> est annulée.' : count($chosen) . ' billet(s) de ta commande <b>' . e($o['ref']) . '</b> pour <b>' . e($ev['name']) . '</b> sont annulés : ' . e(implode(', ', array_map(fn($t) => trim($t['first_name'] . ' ' . $t['last_name']) . ' (' . $t['rate_name'] . ')', $chosen))) . '.';
            $money = $giftCode ? '<p>Tu reçois un <b>avoir de ' . e(money($amount)) . '</b>, utilisable sur nos prochains événements avec le code <b style="font-family:monospace;font-size:16px">' . e($giftCode) . '</b>.</p>'
                : ($amount > 0 ? '<p><b>' . e(money($amount)) . '</b> te ' . ($o['method'] === 'online' ? 'sont remboursés sur ton moyen de paiement (comptes quelques jours).' : 'seront rendus par l\'organisateur.') . '</p>' : '');
            self::mail($o, $ev, $org, ($all ? 'Commande ' . $o['ref'] . ' annulée' : 'Billets annulés · ' . $o['ref']), '<p>' . $what . '</p>' . $money . '<p style="color:#555">Motif : ' . e($motif) . '</p>');
        }
        return ($all ? 'Commande annulée' : count($chosen) . ' billet(s) annulé(s)') . ($msg ? ' · ' . rtrim($msg, '.') : '') . '.';
    }

    /** Ajoute des billets à la commande : encaissés sur place, offerts, ou par un lien de paiement (commande liée en attente). */
    public static function addTickets(array $o, int $rateId, int $qty, string $pay, ?int $sessionId = null, bool $send = true): string
    {
        $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
        $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
        $rate = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [$rateId, $ev['id']]);
        if (!$rate) throw new RuntimeException('Choisis un tarif.');
        if ($qty < 1 || $qty > 50) throw new RuntimeException('Quantité entre 1 et 50.');
        $sid = $sessionId ?: ($o['session_id'] ? (int)$o['session_id'] : null);
        $session = $sid ? one('SELECT * FROM sessions WHERE id = ? AND event_id = ?', [$sid, $ev['id']]) : null;
        $method = $pay === 'offert' ? 'invitation' : ($pay === 'lien' ? 'online' : 'guichet');
        $buyer = ['first_name' => (string)$o['first_name'], 'last_name' => (string)$o['last_name'], 'email' => (string)$o['email'], 'phone' => (string)$o['phone']];
        if ($pay === 'lien' && !is_email((string)$o['email'])) throw new RuntimeException('Pas d\'e-mail sur cette commande pour envoyer le lien de paiement.');
        $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'session' => $session, 'seats' => [], 'quantities' => [$rateId => $qty], 'buyer' => $buyer, 'method' => $method, 'channel' => 'complement',
            'pay_method' => $method === 'guichet' ? (in_array($pay, ['especes', 'cb', 'cheque', 'virement'], true) ? $pay : 'especes') : null, 'note' => 'Complément de la commande ' . $o['ref'], 'user_id' => user()['id'] ?? null]);
        if (!empty($r['errors'])) throw new RuntimeException(implode(' ', $r['errors']));
        $n = $r['order'];
        update('orders', ['parent_order_id' => $o['id']], 'id = ?', [$n['id']]);
        if ($pay === 'lien') {
            update('orders', ['expires_at' => date('Y-m-d H:i:s', time() + 3 * 86400)], 'id = ?', [$n['id']]);
            $link = abs_url('/paiement/' . $n['ref'], ['t' => $n['token']]);
            self::mail($o, $ev, $org, 'Complète ta commande ' . $o['ref'], '<p>L\'organisateur a ajouté <b>' . $qty . ' × ' . e($rate['name']) . '</b> à ta commande pour <b>' . e($ev['name']) . '</b>.</p><p>Montant à régler : <b>' . e(money((int)$n['total'])) . '</b> (valable 3 jours).</p>'
                . '<p><a href="' . e($link) . '" style="display:inline-block;background:#0071e3;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:600">Payer et recevoir mes billets</a></p>');
            Audit::log('order.complement', $qty . ' × ' . $rate['name'] . ' · lien de paiement ' . money((int)$n['total']) . ' · ' . $n['ref'], (int)$ev['id'], (int)$o['id']);
            return 'Lien de paiement envoyé (' . money((int)$n['total']) . ', commande ' . $n['ref'] . ').';
        }
        $n = Tickets::confirm((int)$n['id'], [], $send && is_email((string)$o['email']));
        Audit::log('order.complement', $qty . ' × ' . $rate['name'] . ' · ' . ($method === 'invitation' ? 'offerts' : money((int)$n['total']) . ' encaissés') . ' · ' . $n['ref'], (int)$ev['id'], (int)$o['id']);
        return $qty . ' billet(s) ajouté(s)' . ($method === 'invitation' ? ' (offerts)' : ' · ' . money((int)$n['total']) . ' encaissés') . ' — commande liée ' . $n['ref'] . '.';
    }

    /** Change le tarif d'un billet. $diff : collect (encaissé sur place), free (offert / non remboursé), refund (remboursé). */
    public static function changeRate(array $o, int $ticketId, int $rateId, string $diff, string $payMethod = 'especes'): string
    {
        $t = one("SELECT * FROM tickets WHERE id = ? AND order_id = ? AND status = 'valid'", [$ticketId, $o['id']]);
        $r = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [$rateId, $o['event_id']]);
        if (!$t || !$r) throw new RuntimeException('Billet ou tarif introuvable.');
        if ((int)$t['rate_id'] === (int)$r['id']) throw new RuntimeException('Ce billet est déjà à ce tarif.');
        $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
        $session = $t['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$t['session_id']]) : null;
        if (Tickets::remaining($r, $ev, $session) < 1) throw new RuntimeException('Plus de place disponible au tarif « ' . $r['name'] . ' ».');
        $delta = (int)$r['price'] - (int)$t['price'];
        $msg = '';
        if ($delta > 0 && $diff === 'collect') {
            update('orders', ['total' => (int)$o['total'] + $delta, 'subtotal' => (int)$o['subtotal'] + $delta, 'organizer_amount' => (int)$o['organizer_amount'] + $delta], 'id = ?', [$o['id']]);
            $msg = money($delta) . ' encaissés (' . $payMethod . ')';
        } elseif ($delta < 0 && $diff === 'refund') {
            $amt = min(-$delta, (int)$o['total'] - (int)$o['refunded_amount']);
            if ($amt > 0) {
                if ($o['method'] === 'online') $msg = Payments::refund($o, $amt); else $msg = money($amt) . ' à rendre';
                update('orders', ['refunded_amount' => (int)$o['refunded_amount'] + $amt, 'organizer_amount' => max(0, (int)$o['organizer_amount'] - $amt)], 'id = ?', [$o['id']]);
            }
        } elseif ($delta !== 0) $msg = 'différence de ' . money(abs($delta)) . ($delta > 0 ? ' offerte' : ' non remboursée');
        update('tickets', ['rate_id' => $r['id'], 'rate_name' => $r['name'], 'price' => (int)$r['price'], 'max_entries' => max(1, (int)$r['max_entries']), 'per_day' => (int)$r['per_day'], 'updated_at' => now()], 'id = ?', [$t['id']]);
        Audit::log('ticket.rate_changed', $t['code'] . ' : ' . $t['rate_name'] . ' → ' . $r['name'] . ($msg ? ' · ' . $msg : ''), (int)$o['event_id'], (int)$o['id']);
        return 'Tarif changé : ' . $t['rate_name'] . ' → ' . $r['name'] . ($msg ? ' · ' . rtrim($msg, '.') : '') . '.';
    }

    /** Déplace des billets (ou toute la commande) sur une autre date. */
    public static function changeSession(array $o, array $ticketIds, int $sessionId, bool $resend): string
    {
        $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
        $s = one('SELECT * FROM sessions WHERE id = ? AND event_id = ?', [$sessionId, $ev['id']]);
        if (!$s) throw new RuntimeException('Choisis une date.');
        $valid = all("SELECT * FROM tickets WHERE order_id = ? AND status = 'valid'", [$o['id']]);
        $ids = array_values(array_intersect(array_map('intval', $ticketIds), array_map(fn($t) => (int)$t['id'], $valid))) ?: array_map(fn($t) => (int)$t['id'], $valid);
        $moving = array_values(array_filter($valid, fn($t) => in_array((int)$t['id'], $ids, true) && (int)$t['session_id'] !== (int)$s['id']));
        if (!$moving) throw new RuntimeException('Ces billets sont déjà sur cette date.');
        $byRate = []; foreach ($moving as $t) $byRate[(int)$t['rate_id']] = ($byRate[(int)$t['rate_id']] ?? 0) + 1;
        foreach ($byRate as $rid => $n) {
            $r = one('SELECT * FROM rates WHERE id = ?', [$rid]);
            if ($r && Tickets::remaining($r, $ev, $s) < $n) throw new RuntimeException('Pas assez de places le ' . fr_date($s['starts_at']) . ' pour « ' . $r['name'] . ' ».');
        }
        q('UPDATE tickets SET session_id = ?, seat = NULL, updated_at = NOW() WHERE id IN (' . implode(',', array_map(fn($t) => (int)$t['id'], $moving)) . ')', [$s['id']]);
        if (count($moving) === count($valid)) update('orders', ['session_id' => $s['id']], 'id = ?', [$o['id']]);
        Audit::log('order.session_changed', count($moving) . ' billet(s) → ' . fr_date($s['starts_at']), (int)$ev['id'], (int)$o['id']);
        if ($resend && $o['status'] === 'paid') Mailer::sendOrderSafe((int)$o['id']);
        return count($moving) . ' billet(s) déplacé(s) au ' . fr_date($s['starts_at']) . ($resend ? ', billets renvoyés' : '') . '.';
    }

    private static function mail(array $o, array $ev, array $org, string $subject, string $html): void
    {
        if (!is_email((string)$o['email'])) return;
        try { Mailer::send((string)$o['email'], $subject, '<p>Bonjour ' . e((string)$o['first_name']) . ',</p>' . $html . '<p style="color:#86868b">' . e($org['name']) . ($org['email'] ? ' · ' . e($org['email']) : '') . '</p>', [], $org['email'] ?? null); }
        catch (Throwable $e) { log_line('emails', 'Commande ' . $o['ref'] . ' : ' . $e->getMessage()); }
    }
}
