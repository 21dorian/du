<?php
declare(strict_types=1);

/**
 * 2.5 : factures & compta de l'organisateur.
 * - Numérotation continue des factures (FA-AAAA-nnnn) et des avoirs (AV-AAAA-nnnn), par organisateur.
 * - Une facture émise ne se modifie jamais : un remboursement sur une commande facturée crée un avoir
 *   du montant remboursé (rapprochement automatique, quel que soit le chemin du remboursement).
 * - Facturation automatique facultative de toutes les ventes payées.
 * - Plan comptable réglable, utilisé par l'export FEC.
 */
final class Accounting
{
    public const ACCOUNTS = [
        'sales' => ['706000', 'Ventes de billetterie'], 'vat' => ['445710', 'TVA collectée'], 'platform' => ['467000', 'Plateforme de billetterie (à recevoir)'],
        'cash' => ['530000', 'Caisse (guichet)'], 'commission' => ['622600', 'Commissions de billetterie'], 'donations' => ['754000', 'Dons reçus'],
        'refunds' => ['709000', 'Remboursements accordés'], 'bank' => ['512000', 'Banque'],
    ];
    public const JOURNALS = ['sales' => ['VT', 'Ventes billetterie'], 'bank' => ['BQ', 'Banque']];

    public static function settings(array $org): array
    {
        $j = json_decode((string)($org['accounting_json'] ?? ''), true);
        return is_array($j) ? $j : [];
    }

    /** Compte utilisé pour une clé (réglage de l'organisateur sinon valeur par défaut). */
    public static function account(array $org, string $key): string
    {
        $v = self::settings($org)['accounts'][$key] ?? '';
        return preg_match('~^\d{3,10}$~', (string)$v) ? (string)$v : self::ACCOUNTS[$key][0];
    }

    public static function journal(array $org, string $key): string
    {
        $v = self::settings($org)['journals'][$key] ?? '';
        return preg_match('~^[A-Z0-9]{1,6}$~', (string)$v) ? (string)$v : self::JOURNALS[$key][0];
    }

    private static function next(int $orgId, string $col): int
    {
        q("UPDATE organizers SET $col = LAST_INSERT_ID($col + 1) WHERE id = ?", [$orgId]);
        return (int)val('SELECT LAST_INSERT_ID()');
    }

    /** Attribue un numéro de facture à une commande (une seule fois). */
    public static function assign(array $o): array
    {
        if ($o['invoice_number']) return $o;
        $n = 'FA-' . date('Y') . '-' . str_pad((string)self::next((int)$o['organizer_id'], 'invoice_seq'), 4, '0', STR_PAD_LEFT);
        q('UPDATE orders SET invoice_number = ?, invoice_at = NOW() WHERE id = ? AND invoice_number IS NULL', [$n, $o['id']]);
        return one('SELECT * FROM orders WHERE id = ?', [$o['id']]);
    }

    public static function invoiceable(array $o): bool
    {
        return in_array($o['status'], ['paid', 'refunded'], true) && (int)$o['total'] > 0;
    }

    /** Avoirs manquants : remboursé > déjà crédité sur une commande facturée. */
    public static function syncCredits(?int $orgId = null): int
    {
        $rows = all('SELECT o.id, o.organizer_id, o.refunded_amount, o.status, (SELECT COALESCE(SUM(amount), 0) FROM credit_notes c WHERE c.order_id = o.id) credited
            FROM orders o WHERE o.invoice_number IS NOT NULL AND o.refunded_amount > 0' . ($orgId ? ' AND o.organizer_id = ' . (int)$orgId : '') . ' HAVING o.refunded_amount > credited');
        foreach ($rows as $r) {
            $amount = (int)$r['refunded_amount'] - (int)$r['credited'];
            $num = 'AV-' . date('Y') . '-' . str_pad((string)self::next((int)$r['organizer_id'], 'credit_seq'), 4, '0', STR_PAD_LEFT);
            insert('credit_notes', ['organizer_id' => $r['organizer_id'], 'order_id' => $r['id'], 'number' => $num, 'amount' => $amount,
                'reason' => $r['status'] === 'refunded' ? 'Remboursement de la commande' : 'Remboursement partiel', 'created_at' => now()]);
        }
        return count($rows);
    }

    /** Facturation automatique (organisateurs qui l'ont activée), à partir de la date d'activation. */
    public static function autoInvoice(?int $orgId = null): int
    {
        $n = 0;
        foreach (all('SELECT * FROM organizers WHERE auto_invoice = 1' . ($orgId ? ' AND id = ' . (int)$orgId : '')) as $org) {
            $from = self::settings($org)['auto_from'] ?? date('Y-m-d');
            foreach (all("SELECT * FROM orders WHERE organizer_id = ? AND invoice_number IS NULL AND status IN ('paid','refunded') AND total > 0 AND paid_at >= ? ORDER BY paid_at, id LIMIT 2000", [$org['id'], $from]) as $o) { self::assign($o); $n++; }
        }
        return $n;
    }

    /** Facturer d'un coup les ventes payées d'une période qui n'ont pas encore de facture. */
    public static function invoicePeriod(int $orgId, string $from, string $to): int
    {
        $n = 0;
        foreach (all("SELECT * FROM orders WHERE organizer_id = ? AND invoice_number IS NULL AND status IN ('paid','refunded') AND total > 0 AND paid_at >= ? AND paid_at < ? + INTERVAL 1 DAY ORDER BY paid_at, id", [$orgId, $from, $to]) as $o) { self::assign($o); $n++; }
        self::syncCredits($orgId);
        return $n;
    }

    public static function nightly(): int { return self::autoInvoice() + self::syncCredits(); }

    /** Registre chronologique des factures et avoirs d'une période. */
    public static function register(int $orgId, string $from, string $to, string $kind = ''): array
    {
        $out = [];
        if ($kind !== 'avoir') foreach (all("SELECT o.id, o.ref, o.invoice_number number, o.invoice_at at, o.total amount, o.first_name, o.last_name, o.invoice_company, o.email, o.status, o.refunded_amount, e.name ev
            FROM orders o LEFT JOIN events e ON e.id = o.event_id WHERE o.organizer_id = ? AND o.invoice_number IS NOT NULL AND o.invoice_at >= ? AND o.invoice_at < ? + INTERVAL 1 DAY", [$orgId, $from, $to]) as $r) $out[] = $r + ['kind' => 'facture', 'doc_id' => $r['id']];
        if ($kind !== 'facture') foreach (all("SELECT c.id doc_id, c.number, c.created_at at, -c.amount amount, c.reason, o.id, o.ref, o.invoice_number inv, o.first_name, o.last_name, o.invoice_company, o.email, o.status, e.name ev
            FROM credit_notes c JOIN orders o ON o.id = c.order_id LEFT JOIN events e ON e.id = o.event_id WHERE c.organizer_id = ? AND c.created_at >= ? AND c.created_at < ? + INTERVAL 1 DAY", [$orgId, $from, $to]) as $r) $out[] = $r + ['kind' => 'avoir'];
        usort($out, fn($a, $b) => strcmp($b['at'], $a['at']) ?: strcmp($b['number'], $a['number']));
        return $out;
    }

    public static function creditPdf(array $cn): string
    {
        $o = one('SELECT * FROM orders WHERE id = ?', [$cn['order_id']]);
        $org = one('SELECT * FROM organizers WHERE id = ?', [$cn['organizer_id']]);
        $ev = $o['event_id'] ? one('SELECT * FROM events WHERE id = ?', [$o['event_id']]) : null;
        $client = ['name' => $o['invoice_company'] ?: trim($o['first_name'] . ' ' . $o['last_name']), 'lines' => array_filter([$o['invoice_company'] ? trim($o['first_name'] . ' ' . $o['last_name']) : null, $o['invoice_address'], $o['invoice_vat'] ? 'TVA : ' . $o['invoice_vat'] : null, $o['email']])];
        $rows = [[(string)$cn['reason'] . ' ' . $o['ref'], 1, -(int)$cn['amount'], -(int)$cn['amount']]];
        $ratio = (int)$o['total'] > 0 ? (int)$cn['amount'] / (int)$o['total'] : 0;
        $vat = '';
        foreach (Vat::orders($org, (string)$o['created_at'], (string)$o['created_at']) as $vo) if ((int)$vo['id'] === (int)$o['id'])
            foreach ($vo['vat'] as $k => $v) if ($k !== 'don' && (int)$v['vat']) $vat .= 'Dont TVA ' . $k . ' % : ' . money(-(int)round($v['vat'] * $ratio)) . '.  ';
        return Quotes::doc('AVOIR', (string)$cn['number'], $org, $client,
            'Avoir sur la facture ' . $o['invoice_number'] . ($ev ? ' - ' . $ev['name'] : ''), $rows, [['Total TTC de l\'avoir', -(int)$cn['amount']]],
            trim($vat . "\nCet avoir annule partiellement ou totalement la facture " . $o['invoice_number'] . ' du ' . date('d/m/Y', strtotime((string)$o['invoice_at'])) . '. Le montant a été remboursé sur le moyen de paiement d\'origine.'),
            date('d/m/Y', strtotime((string)$cn['created_at'])));
    }

    /** Archive ZIP des factures et avoirs d'un mois, avec le registre en CSV. */
    public static function zip(array $org, string $month): string
    {
        $from = $month . '-01'; $to = date('Y-m-t', strtotime($from));
        $reg = self::register((int)$org['id'], $from, $to);
        $file = tempnam(sys_get_temp_dir(), 'fz');
        $z = new ZipArchive(); $z->open($file, ZipArchive::OVERWRITE);
        $csv = fopen('php://temp', 'w+'); fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, ['Type', 'Numéro', 'Date', 'Client', 'Commande', 'Événement', 'Montant TTC'], ';');
        foreach (array_reverse($reg) as $r) {
            fputcsv($csv, [$r['kind'], $r['number'], substr((string)$r['at'], 0, 10), $r['invoice_company'] ?: trim($r['first_name'] . ' ' . $r['last_name']), $r['ref'], (string)$r['ev'], number_format((int)$r['amount'] / 100, 2, ',', '')], ';');
            if ($r['kind'] === 'facture') $z->addFromString('factures/' . $r['number'] . '.pdf', Quotes::invoice(one('SELECT * FROM orders WHERE id = ?', [$r['doc_id']])));
            else $z->addFromString('avoirs/' . $r['number'] . '.pdf', self::creditPdf(one('SELECT * FROM credit_notes WHERE id = ?', [$r['doc_id']])));
        }
        rewind($csv); $z->addFromString('registre-' . $month . '.csv', stream_get_contents($csv));
        $z->close();
        $data = (string)file_get_contents($file); @unlink($file);
        return $data;
    }
}
