<?php
declare(strict_types=1);

/**
 * 2.2 : litiges & impayés (contestations de paiement, « chargebacks »).
 *
 * Un litige est rattaché à une commande et à son organisateur. Tant qu'il est ouvert, rien n'est retenu.
 * Perdu (ou accepté) : montant contesté + frais bancaires retenus sur le prochain reversement de l'organisateur
 * (registre org_charges). Gagné ou rouvert : la retenue est annulée.
 * Stripe : les événements charge.dispute.* créent ou mettent à jour le litige (relu via l'API, jamais cru sur parole).
 */
final class Disputes
{
    public const STATUS = ['open' => ['À traiter', 'red'], 'evidence' => ['Preuves envoyées', 'orange'], 'won' => ['Gagné', 'green'], 'lost' => ['Perdu', 'grey'], 'accepted' => ['Accepté', 'grey']];
    public const REASONS = ['fraude' => 'Fraude (carte volée)', 'non_recu' => 'Billet non reçu', 'non_conforme' => 'Prestation non conforme', 'annule' => 'Événement annulé / reporté',
        'doublon' => 'Paiement en double', 'non_reconnu' => 'Paiement non reconnu', 'autre' => 'Autre'];

    public static function badge(string $s): string { [$l, $c] = self::STATUS[$s] ?? [$s, 'grey']; return '<span class="badge ' . $c . '">' . e($l) . '</span>'; }

    public static function create(array $o, int $amount, string $reason, ?string $due, ?int $by, array $extra = []): int
    {
        $id = insert('disputes', ['order_id' => $o['id'], 'organizer_id' => $o['organizer_id'], 'provider' => $o['provider'], 'amount' => $amount, 'reason' => $reason,
            'status' => 'open', 'due_on' => $due, 'evidence' => self::evidence($o), 'created_by' => $by, 'created_at' => now()] + $extra);
        Audit::log('dispute.created', 'Litige sur ' . $o['ref'] . ' (' . money($amount) . ')', $o['event_id'] ? (int)$o['event_id'] : null, (int)$o['id'], (int)$o['organizer_id']);
        self::notifyOrg($id);
        return $id;
    }

    /** Change le statut et applique (ou annule) la retenue sur le reversement. */
    public static function setStatus(array $d, string $status, ?int $by = null): void
    {
        if (!isset(self::STATUS[$status])) return;
        $closed = in_array($status, ['won', 'lost', 'accepted'], true);
        $data = ['status' => $status, 'closed_at' => $closed ? ($d['closed_at'] ?: now()) : null];
        if (in_array($status, ['lost', 'accepted'], true) && !$d['charge_id']) {
            $o = $d['order_id'] ? one('SELECT ref FROM orders WHERE id = ?', [$d['order_id']]) : null;
            $data['charge_id'] = Billing::charge((int)$d['organizer_id'], 'litige', 'Litige ' . ($status === 'lost' ? 'perdu' : 'accepté') . ($o ? ' · commande ' . $o['ref'] : '') . ((int)$d['bank_fee'] ? ' (dont ' . money((int)$d['bank_fee']) . ' de frais bancaires)' : ''),
                (int)$d['amount'] + (int)$d['bank_fee'], 'd' . $d['id'], $by);
        }
        if (!in_array($status, ['lost', 'accepted'], true) && $d['charge_id']) { q('DELETE FROM org_charges WHERE id = ?', [$d['charge_id']]); $data['charge_id'] = null; }
        update('disputes', $data, 'id = ?', [$d['id']]);
        Audit::log('dispute.status', 'Litige #' . $d['id'] . ' : ' . self::STATUS[$status][0], null, $d['order_id'] ? (int)$d['order_id'] : null, (int)$d['organizer_id']);
    }

    /** Dossier de preuves pré-rempli à partir de ce que la plateforme sait de la commande. */
    public static function evidence(array $o): string
    {
        $ev = $o['event_id'] ? one('SELECT * FROM events WHERE id = ?', [$o['event_id']]) : null;
        $tickets = all('SELECT code, rate_name, first_name, last_name, entries, checked_in_at, holder_email FROM tickets WHERE order_id = ?', [$o['id']]);
        $scans = (int)val("SELECT COUNT(*) FROM scans s JOIN tickets t ON t.id = s.ticket_id WHERE t.order_id = ? AND s.result = 'ok'", [$o['id']]);
        $l = [];
        $l[] = 'Commande ' . $o['ref'] . ' passée le ' . fr_date((string)$o['created_at']) . ', payée le ' . fr_date((string)($o['paid_at'] ?: $o['created_at'])) . ' (' . money((int)$o['total']) . ', ' . ($o['provider'] ?: $o['method']) . ').';
        $l[] = 'Acheteur : ' . trim($o['first_name'] . ' ' . $o['last_name']) . ' <' . $o['email'] . '>' . ($o['phone'] ? ', ' . $o['phone'] : '') . '.';
        if ($ev) $l[] = 'Événement : « ' . $ev['name'] . ' », le ' . fr_date($ev['starts_at']) . ($ev['venue'] ? ' à ' . $ev['venue'] . ($ev['city'] ? ' (' . $ev['city'] . ')' : '') : '') . '.';
        if (setting('cgv')) $l[] = 'Les conditions générales de vente ont été acceptées au moment du paiement.';
        $l[] = 'Billets envoyés par e-mail à ' . $o['email'] . ' juste après le paiement (' . count($tickets) . ' billet' . (count($tickets) > 1 ? 's' : '') . ').';
        foreach ($tickets as $t) $l[] = '  · ' . $t['code'] . ' – ' . $t['rate_name'] . ' – ' . trim($t['first_name'] . ' ' . $t['last_name']) . ($t['checked_in_at'] ? ' – SCANNÉ À L\'ENTRÉE le ' . fr_date($t['checked_in_at']) : ' – pas encore scanné') . ($t['holder_email'] ? ' – transmis à ' . $t['holder_email'] : '');
        if ($scans) $l[] = $scans . ' passage' . ($scans > 1 ? 's' : '') . ' validé' . ($scans > 1 ? 's' : '') . ' au contrôle d\'accès : la prestation a été consommée.';
        if ($o['marketing_optin'] ?? 0) $l[] = 'L\'acheteur a accepté de recevoir les actualités de l\'organisateur.';
        return implode("\n", $l);
    }

    public static function notifyOrg(int $id): void
    {
        $d = one('SELECT d.*, o.ref, o.email buyer, g.email org_email, g.name org_name FROM disputes d LEFT JOIN orders o ON o.id = d.order_id JOIN organizers g ON g.id = d.organizer_id WHERE d.id = ?', [$id]);
        if (!$d || !$d['org_email']) return;
        try {
            Mailer::send($d['org_email'], 'Contestation de paiement sur la commande ' . $d['ref'],
                '<p>Bonjour,</p><p>L\'acheteur de la commande <b>' . e((string)$d['ref']) . '</b> (' . e((string)$d['buyer']) . ') conteste son paiement de <b>' . e(money((int)$d['amount'])) . '</b> auprès de sa banque. Motif : ' . e(self::REASONS[$d['reason']] ?? $d['reason']) . '.</p>'
                . '<p>Nous préparons la réponse avec les preuves (billets envoyés, scans à l\'entrée). Si tu as d\'autres éléments (échanges avec l\'acheteur, photos, conditions particulières), réponds simplement à cet e-mail' . ($d['due_on'] ? ' avant le <b>' . e(date('d/m/Y', strtotime($d['due_on']) - 3 * 86400)) . '</b>' : '') . '.</p>'
                . '<p>Si le litige est perdu, le montant contesté et les frais bancaires sont retenus sur ton prochain reversement.</p>');
        } catch (Throwable $e) { log_line('emails', 'Litige : ' . $e->getMessage()); }
    }

    /** Événement Stripe charge.dispute.* : on relit le litige chez Stripe puis on crée ou met à jour. */
    public static function fromStripe(string $disputeId): string
    {
        if (!preg_match('~^du_[A-Za-z0-9]+$~', $disputeId) || !setting('stripe_secret')) return 'litige ignoré';
        $ch = curl_init('https://api.stripe.com/v1/disputes/' . rawurlencode($disputeId));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . setting('stripe_secret')]]);
        $s = json_decode((string)curl_exec($ch), true) ?: []; curl_close($ch);
        if (($s['object'] ?? '') !== 'dispute') return 'litige introuvable chez Stripe';
        $pi = (string)($s['payment_intent'] ?? '');
        $o = $pi !== '' ? one("SELECT * FROM orders WHERE provider = 'stripe' AND provider_payment = ?", [$pi]) : null;
        if (!$o) return 'commande introuvable pour ' . $disputeId;
        $status = match ((string)($s['status'] ?? '')) { 'won' => 'won', 'lost' => 'lost', 'under_review', 'warning_under_review' => 'evidence', default => 'open' };
        $reason = match ((string)($s['reason'] ?? '')) { 'fraudulent' => 'fraude', 'product_not_received' => 'non_recu', 'product_unacceptable' => 'non_conforme', 'duplicate' => 'doublon', 'unrecognized' => 'non_reconnu', default => 'autre' };
        $due = !empty($s['evidence_details']['due_by']) ? date('Y-m-d', (int)$s['evidence_details']['due_by']) : null;
        $d = one("SELECT * FROM disputes WHERE provider = 'stripe' AND provider_ref = ?", [$disputeId]);
        if (!$d) { $id = self::create($o, (int)$s['amount'], $reason, $due, null, ['provider_ref' => $disputeId, 'bank_fee' => (int)setting('dispute_fee', '1500')]); $d = one('SELECT * FROM disputes WHERE id = ?', [$id]); }
        if ($d['status'] !== $status) self::setStatus($d, $status);
        return 'litige ' . $disputeId . ' : ' . $status;
    }

    public static function kpis(): array
    {
        return one("SELECT SUM(status IN ('open','evidence')) open, COALESCE(SUM(CASE WHEN status IN ('open','evidence') THEN amount END), 0) at_stake,
            SUM(status = 'open' AND due_on IS NOT NULL AND due_on <= CURDATE() + INTERVAL 7 DAY) urgent,
            SUM(status = 'won') won, SUM(status IN ('lost','accepted')) lost, COALESCE(SUM(CASE WHEN status IN ('lost','accepted') THEN amount + bank_fee END), 0) lost_amount
            FROM disputes") ?: [];
    }
}
