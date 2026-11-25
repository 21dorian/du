<?php
declare(strict_types=1);

/**
 * 2.3 : devis de groupe et factures acheteurs.
 * Devis : demande publique (ou créée par l'organisateur) → lignes de tarifs + remise → envoi par e-mail avec PDF →
 * acceptation en ligne → paiement par carte (code promo unique appliqué automatiquement) ou par virement
 * (l'organisateur confirme la réception, les billets partent).
 * Factures : numérotation continue par organisateur (FA-AAAA-0001), émise à la demande de l'acheteur.
 */
final class Quotes
{
    public const STATUS = ['request' => ['Demande reçue', 'orange'], 'sent' => ['Devis envoyé', 'blue'], 'accepted' => ['Accepté · virement attendu', 'orange'],
        'paid' => ['Payé', 'green'], 'declined' => ['Refusé', 'grey'], 'expired' => ['Expiré', 'grey']];

    public static function badge(string $s): string { [$l, $c] = self::STATUS[$s] ?? [$s, 'grey']; return '<span class="badge ' . $c . '">' . e($l) . '</span>'; }

    public static function lines(array $q): array { return json_decode((string)$q['items_json'], true) ?: []; }

    /** @return array{lines:array, gross:int, discount:int, net:int, fees:int, total:int, tickets:int} */
    public static function totals(array $q, array $org): array
    {
        $lines = []; $gross = 0; $tickets = 0;
        foreach (self::lines($q) as $l) {
            $r = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [(int)$l['rate_id'], $q['event_id']]);
            if (!$r || (int)$l['qty'] <= 0) continue;
            $sum = (int)$r['price'] * (int)$l['qty'];
            $lines[] = ['rate_id' => (int)$r['id'], 'label' => $r['name'], 'qty' => (int)$l['qty'], 'unit' => (int)$r['price'], 'total' => $sum, 'pack' => max(1, (int)$r['pack_size'])];
            $gross += $sum; $tickets += (int)$l['qty'] * max(1, (int)$r['pack_size']);
        }
        $disc = (int)round($gross * (float)$q['discount_pct'] / 100);
        $net = $gross - $disc;
        $paidTickets = array_sum(array_map(fn($l) => $l['unit'] > 0 ? $l['qty'] * $l['pack'] : 0, $lines));
        $fees = $org['fee_mode'] === 'buyer' ? Tickets::computeFees($org, $net, $paidTickets) : 0;
        return ['lines' => $lines, 'gross' => $gross, 'discount' => $disc, 'net' => $net, 'fees' => $fees, 'total' => $net + $fees, 'tickets' => $tickets];
    }

    public static function create(array $ev, array $d): array
    {
        do { $ref = 'D' . strtoupper(random_code(6)); } while (val('SELECT id FROM quotes WHERE ref = ?', [$ref]));
        $id = insert('quotes', ['organizer_id' => $ev['organizer_id'], 'event_id' => $ev['id'], 'ref' => $ref, 'token' => token(24), 'status' => 'request',
            'company' => $d['company'] ?: null, 'contact_name' => $d['contact_name'], 'email' => strtolower($d['email']), 'phone' => $d['phone'] ?: null, 'address' => $d['address'] ?? null,
            'qty_wanted' => (int)$d['qty'], 'message' => $d['message'] ?: null, 'items_json' => json_encode($d['lines'] ?? []), 'discount_pct' => (float)($d['discount_pct'] ?? 0),
            'valid_until' => date('Y-m-d', strtotime('+15 days')), 'created_at' => now()]);
        return one('SELECT * FROM quotes WHERE id = ?', [$id]);
    }

    /** Code promo unique qui applique la remise du devis à ses tarifs, utilisable une fois. */
    public static function promo(array $q): ?string
    {
        if ((float)$q['discount_pct'] <= 0) return null;
        if ($q['promo_code'] && val('SELECT id FROM promos WHERE event_id = ? AND code = ?', [$q['event_id'], $q['promo_code']])) return $q['promo_code'];
        $code = 'DEVIS' . strtoupper(random_code(6));
        insert('promos', ['event_id' => $q['event_id'], 'code' => $code, 'kind' => 'percent', 'value' => (int)round((float)$q['discount_pct']), 'rate_ids' => implode(',', array_column(self::lines($q), 'rate_id')),
            'max_uses' => 1, 'used' => 0, 'active' => 1, 'batch' => 'devis-' . $q['ref'], 'label' => 'Devis ' . $q['ref']]);
        update('quotes', ['promo_code' => $code], 'id = ?', [$q['id']]);
        return $code;
    }

    /** Crée la commande du devis (statut « en attente »). */
    public static function order(array $q): array
    {
        $ev = one('SELECT * FROM events WHERE id = ?', [$q['event_id']]);
        $org = one('SELECT * FROM organizers WHERE id = ?', [$q['organizer_id']]);
        $qty = [];
        foreach (self::lines($q) as $l) $qty[(int)$l['rate_id']] = ($qty[(int)$l['rate_id']] ?? 0) + (int)$l['qty'];
        [$first, $last] = array_pad(explode(' ', trim($q['contact_name']), 2), 2, '');
        $session = $q['session_id'] ? one('SELECT * FROM sessions WHERE id = ? AND event_id = ?', [$q['session_id'], $ev['id']]) : null;
        if (!$session && Tickets::hasSessions((int)$ev['id'])) throw new RuntimeException('Choisis la date du devis (événement à plusieurs dates).');
        $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'session' => $session, 'quantities' => $qty, 'promo' => self::promo($q), 'method' => 'online', 'channel' => 'devis', 'group' => true,
            'buyer' => ['email' => $q['email'], 'first_name' => $first, 'last_name' => $last ?: ($q['company'] ?: $first), 'phone' => (string)$q['phone']]]);
        if (!empty($r['errors'])) throw new RuntimeException(implode(' ', $r['errors']));
        update('orders', ['quote_id' => $q['id'], 'invoice_company' => $q['company'], 'invoice_address' => $q['address']], 'id = ?', [$r['order']['id']]);
        update('quotes', ['order_id' => $r['order']['id']], 'id = ?', [$q['id']]);
        return one('SELECT * FROM orders WHERE id = ?', [$r['order']['id']]);
    }

    /** Virement reçu : la commande passe en vente directe (argent chez l'organisateur, pas de commission) et les billets partent. */
    public static function markTransferPaid(array $q): array
    {
        $o = $q['order_id'] ? one('SELECT * FROM orders WHERE id = ?', [$q['order_id']]) : null;
        if (!$o || !in_array($o['status'], ['pending', 'expired'], true)) $o = self::order($q);
        $total = (int)$o['total'] - (int)$o['fees'];
        update('orders', ['method' => 'guichet', 'pay_method' => 'virement', 'provider' => null, 'fees' => 0, 'commission' => 0, 'total' => $total, 'organizer_amount' => $total, 'expires_at' => null], 'id = ?', [$o['id']]);
        Tickets::confirm((int)$o['id']);
        update('quotes', ['status' => 'paid'], 'id = ?', [$q['id']]);
        return one('SELECT * FROM orders WHERE id = ?', [$o['id']]);
    }

    public static function send(array $q): void
    {
        $org = one('SELECT * FROM organizers WHERE id = ?', [$q['organizer_id']]);
        $ev = one('SELECT * FROM events WHERE id = ?', [$q['event_id']]);
        $t = self::totals($q, $org);
        Mailer::send($q['email'], 'Ton devis ' . $q['ref'] . ' · ' . $ev['name'],
            '<p>Bonjour ' . e(strtok($q['contact_name'], ' ')) . ',</p><p>Voici ton devis pour <b>' . e($t['tickets']) . ' places</b> à <b>' . e($ev['name']) . '</b> (' . e(fr_date($ev['starts_at'])) . ') : <b>' . e(money($t['total'])) . '</b>'
            . ((float)$q['discount_pct'] > 0 ? ', remise de groupe de ' . e(rtrim(rtrim(number_format((float)$q['discount_pct'], 2, ',', ''), '0'), ',')) . ' % comprise' : '') . '.</p>'
            . '<p>Il est valable jusqu\'au <b>' . e(date('d/m/Y', strtotime((string)$q['valid_until']))) . '</b>. Tu peux l\'accepter et payer en ligne en un clic :</p>'
            . '<p><a href="' . e(abs_url('/devis/' . $q['token'])) . '" style="display:inline-block;background:#1d1d1f;color:#fff;padding:12px 22px;border-radius:980px;text-decoration:none">Voir et accepter le devis</a></p>',
            [['data' => self::pdf($q), 'name' => 'devis-' . $q['ref'] . '.pdf']], $org['email'] ?? null);
        update('quotes', ['status' => 'sent', 'sent_at' => now()], 'id = ?', [$q['id']]);
    }

    // ---------- Documents PDF ----------
    public static function pdf(array $q): string
    {
        $org = one('SELECT * FROM organizers WHERE id = ?', [$q['organizer_id']]);
        $ev = one('SELECT * FROM events WHERE id = ?', [$q['event_id']]);
        $t = self::totals($q, $org);
        $rows = array_map(fn($l) => [$l['label'] . ($l['pack'] > 1 ? ' (lot de ' . $l['pack'] . ')' : ''), $l['qty'], $l['unit'], $l['total']], $t['lines']);
        $tot = [['Sous-total', $t['gross']]];
        if ($t['discount']) $tot[] = ['Remise de groupe (' . rtrim(rtrim(number_format((float)$q['discount_pct'], 2, ',', ''), '0'), ',') . ' %)', -$t['discount']];
        if ($t['fees']) $tot[] = ['Frais de service', $t['fees']];
        $tot[] = ['Total TTC', $t['total']];
        return self::doc('DEVIS', $q['ref'], $org, ['name' => $q['company'] ?: $q['contact_name'], 'lines' => array_filter([$q['company'] ? $q['contact_name'] : null, $q['address'], $q['email'], $q['phone']])],
            'Billetterie : ' . $ev['name'] . ' - ' . fr_date($ev['starts_at']) . ($ev['venue'] ? ' - ' . $ev['venue'] : ''), $rows, $tot,
            'Devis valable jusqu\'au ' . date('d/m/Y', strtotime((string)$q['valid_until'])) . '. Acceptation et paiement en ligne : ' . abs_url('/devis/' . $q['token']) . ($q['notes'] ? "\n" . $q['notes'] : ''), date('d/m/Y', strtotime($q['created_at'])));
    }

    /** Facture acheteur (numérotée à la première demande). */
    public static function invoice(array $o): string
    {
        $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
        if (!$o['invoice_number']) {
            q('UPDATE organizers SET invoice_seq = invoice_seq + 1 WHERE id = ?', [$org['id']]);
            $n = 'FA-' . date('Y') . '-' . str_pad((string)val('SELECT invoice_seq FROM organizers WHERE id = ?', [$org['id']]), 4, '0', STR_PAD_LEFT);
            update('orders', ['invoice_number' => $n, 'invoice_at' => now()], 'id = ?', [$o['id']]);
            $o = one('SELECT * FROM orders WHERE id = ?', [$o['id']]);
        }
        $ev = $o['event_id'] ? one('SELECT * FROM events WHERE id = ?', [$o['event_id']]) : null;
        $rows = array_map(fn($i) => [$i['name'], (int)$i['qty'], (int)$i['unit_price'], (int)$i['unit_price'] * (int)$i['qty']], all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]));
        $tot = [['Sous-total', (int)$o['subtotal']]];
        if ((int)$o['discount']) $tot[] = ['Réduction', -(int)$o['discount']];
        if ((int)$o['gift_amount']) $tot[] = ['Carte cadeau', -(int)$o['gift_amount']];
        if ((int)$o['fees']) $tot[] = ['Frais de service', (int)$o['fees']];
        if ((int)$o['donation']) $tot[] = ['Don', (int)$o['donation']];
        $tot[] = ['Total TTC payé', (int)$o['total']];
        if ((int)$o['refunded_amount']) $tot[] = ['Remboursé', -(int)$o['refunded_amount']];
        $vat = $ev ? Vat::orders($org, (string)$o['created_at'], (string)$o['created_at']) : [];
        $vatLine = '';
        foreach ($vat as $vo) if ((int)$vo['id'] === (int)$o['id']) foreach ($vo['vat'] as $k => $v) if ($k !== 'don' && (int)$v['vat']) $vatLine .= 'TVA ' . $k . ' % : ' . money((int)$v['vat']) . ' (HT ' . money((int)$v['ht']) . ').  ';
        return self::doc('FACTURE', (string)$o['invoice_number'], $org, ['name' => $o['invoice_company'] ?: trim($o['first_name'] . ' ' . $o['last_name']), 'lines' => array_filter([$o['invoice_company'] ? trim($o['first_name'] . ' ' . $o['last_name']) : null, $o['invoice_address'], $o['invoice_vat'] ? 'TVA : ' . $o['invoice_vat'] : null, $o['email']])],
            'Commande ' . $o['ref'] . ($ev ? ' - ' . $ev['name'] . ' - ' . fr_date($ev['starts_at']) : ''), $rows, $tot,
            trim(($vatLine ?: (($org['vat_rate'] ?? null) === null || (float)$org['vat_rate'] === 0.0 ? 'TVA non applicable, article 293 B du CGI.' : '')) . "\nPayée le " . date('d/m/Y', strtotime((string)($o['paid_at'] ?: $o['created_at']))) . '.'),
            date('d/m/Y', strtotime((string)$o['invoice_at'])));
    }

    private static function doc(string $kind, string $number, array $org, array $client, string $subject, array $rows, array $totals, string $notes, string $date): string
    {
        $t = fn($s) => PdfDoc::t((string)$s);
        $pdf = new PdfDoc('P', 'mm', 'A4');
        $pdf->SetMargins(18, 18, 18); $pdf->SetAutoPageBreak(true, 22); $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 15); $pdf->Cell(110, 8, $t($org['legal_name'] ?: $org['name']), 0, 0);
        $pdf->SetFont('Helvetica', 'B', 22); $pdf->Cell(0, 8, $t($kind), 0, 1, 'R');
        $pdf->SetFont('Helvetica', '', 9); $pdf->SetTextColor(110, 110, 115);
        $y = $pdf->GetY();
        foreach (array_filter([$org['address'] ?? null, $org['email'] ?? null, $org['phone'] ?? null, $org['siret'] ? 'SIRET ' . $org['siret'] : null, $org['vat_number'] ? 'TVA ' . $org['vat_number'] : null]) as $l) $pdf->Cell(110, 4.5, $t(str_replace("\n", ', ', $l)), 0, 1);
        $pdf->SetXY(128, $y); $pdf->SetTextColor(29, 29, 31); $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, $t('N° ' . $number), 0, 2, 'R'); $pdf->Cell(0, 5, $t('Date : ' . $date), 0, 2, 'R');
        $pdf->SetY(max($pdf->GetY(), $y + 24) + 6);
        $pdf->SetFillColor(245, 245, 247); $pdf->SetX(110); $by = $pdf->GetY();
        $pdf->SetFont('Helvetica', 'B', 10); $pdf->Cell(82, 7, $t($client['name']), 0, 2, 'L', true);
        $pdf->SetFont('Helvetica', '', 9);
        foreach ($client['lines'] as $l) $pdf->Cell(82, 5, $t(str_replace("\n", ', ', (string)$l)), 0, 2, 'L', true);
        $pdf->SetY(max($pdf->GetY(), $by) + 8);
        $pdf->SetFont('Helvetica', 'B', 10); $pdf->MultiCell(0, 5, $t($subject)); $pdf->Ln(3);
        $w = [96, 18, 30, 30];
        $pdf->SetFillColor(29, 29, 31); $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 9);
        foreach (['Désignation', 'Qté', 'Prix unitaire', 'Total'] as $i => $h) $pdf->Cell($w[$i], 7, $t($h), 0, 0, $i ? 'R' : 'L', true);
        $pdf->Ln(); $pdf->SetTextColor(29, 29, 31); $pdf->SetFont('Helvetica', '', 9.5);
        foreach ($rows as $r) {
            $pdf->Cell($w[0], 7, $t(mb_strimwidth((string)$r[0], 0, 60, '…')), 'B'); $pdf->Cell($w[1], 7, (string)$r[1], 'B', 0, 'R');
            $pdf->Cell($w[2], 7, $t(money((int)$r[2])), 'B', 0, 'R'); $pdf->Cell($w[3], 7, $t(money((int)$r[3])), 'B', 1, 'R');
        }
        $pdf->Ln(2);
        foreach ($totals as $i => [$label, $v]) {
            $last = $i === count($totals) - 1 || str_starts_with($label, 'Total');
            $pdf->SetFont('Helvetica', $last ? 'B' : '', $last ? 11 : 9.5);
            $pdf->Cell($w[0] + $w[1] + $w[2], 6.5, $t($label), 0, 0, 'R'); $pdf->Cell($w[3], 6.5, $t(money($v)), 0, 1, 'R');
        }
        $pdf->Ln(6); $pdf->SetFont('Helvetica', '', 8.5); $pdf->SetTextColor(110, 110, 115); $pdf->MultiCell(0, 4.5, $t($notes));
        return $pdf->Output('S');
    }
}
