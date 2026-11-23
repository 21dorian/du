<?php
declare(strict_types=1);

/** Documents PDF : badges, liste de contrôle, ticket de caisse 80 mm, relevé de commissions, reçu acheteur. */
final class Docs
{
    private static function t(?string $s): string { return PdfDoc::t($s); }

    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');
        return strlen($h) === 6 ? [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))] : [0, 50, 250];
    }

    /** Badges A6 (4 par page A4), avec QR code, population et zones autorisées. */
    public static function badges(array $ev, array $people, array $aps): string
    {
        $pdf = new PdfDoc('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        foreach (array_values($people) as $i => $p) {
            if ($i % 4 === 0) $pdf->AddPage();
            $x = ($i % 2) * 105; $y = (intdiv($i % 4, 2)) * 148.5;
            [$r, $g, $b] = self::rgb((string)$p['color']);
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->Rect($x + 2, $y + 2, 101, 144.5);
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect($x + 2, $y + 2, 101, 26, 'F');
            $pdf->SetTextColor(17, 17, 17);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetXY($x + 7, $y + 6);
            $pdf->Cell(91, 5, self::t(mb_strtoupper($ev['name'])));
            $pdf->SetFont('Helvetica', 'B', 20);
            $pdf->SetXY($x + 7, $y + 13);
            $pdf->Cell(91, 10, self::t(mb_strtoupper($p['pop_name'])));
            $pdf->SetFont('Helvetica', 'B', 17);
            $pdf->SetXY($x + 7, $y + 36);
            $pdf->MultiCell(91, 8, self::t(trim($p['first_name'] . ' ' . mb_strtoupper((string)$p['last_name']))), 0, 'C');
            if ($p['company']) { $pdf->SetFont('Helvetica', '', 11); $pdf->SetX($x + 7); $pdf->Cell(91, 6, self::t($p['company']), 0, 1, 'C'); }
            Qr::drawPdf($pdf, $p['code'], $x + 30, $y + 62, 45);
            $pdf->SetFont('Courier', 'B', 9);
            $pdf->SetXY($x + 7, $y + 108);
            $pdf->Cell(91, 4, $p['code'], 0, 0, 'C');
            $zones = array_filter(array_map(fn($z) => $aps[(int)$z] ?? null, explode(',', (string)$p['access_point_ids'])));
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->SetXY($x + 7, $y + 118);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->Cell(91, 4, self::t('ZONES AUTORISÉES'), 0, 1, 'C');
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(17, 17, 17);
            $pdf->SetX($x + 7);
            $pdf->MultiCell(91, 4.5, self::t($zones ? implode(' · ', $zones) : 'Toutes les zones'), 0, 'C');
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect($x + 2, $y + 140.5, 101, 6, 'F');
        }
        return $pdf->Output('S');
    }

    /** Liste de contrôle papier (secours si plus de batterie ni de réseau). */
    public static function controlList(array $ev, array $rows, ?array $session): string
    {
        $pdf = new PdfDoc('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 15);
        $pdf->Cell(0, 8, self::t('Liste de contrôle - ' . $ev['name']), 0, 1);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 5, self::t(($session ? fr_date($session['starts_at']) : fr_date($ev['starts_at'])) . ' - ' . count($rows) . ' personne(s) - imprimée le ' . date('d/m/Y H:i')), 0, 1);
        $pdf->Ln(3);
        $w = [8, 52, 46, 42, 32, 10];
        $head = ['', 'Nom', 'Prénom', 'Tarif / population', 'Code', 'OK'];
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetFillColor(17, 17, 17); $pdf->SetTextColor(255, 255, 255);
        foreach ($head as $k => $h) $pdf->Cell($w[$k], 6, self::t($h), 0, 0, 'L', true);
        $pdf->Ln();
        $pdf->SetTextColor(17, 17, 17);
        $pdf->SetFont('Helvetica', '', 8.5);
        foreach ($rows as $i => $t) {
            $fill = $i % 2 === 1;
            $pdf->SetFillColor(243, 242, 239);
            $pdf->Cell($w[0], 6, (string)($i + 1), 0, 0, 'L', $fill);
            $pdf->Cell($w[1], 6, self::t(mb_strtoupper(mb_strimwidth((string)$t['last_name'], 0, 28, '.'))), 0, 0, 'L', $fill);
            $pdf->Cell($w[2], 6, self::t(mb_strimwidth((string)$t['first_name'], 0, 24, '.')), 0, 0, 'L', $fill);
            $pdf->Cell($w[3], 6, self::t(mb_strimwidth((string)($t['pop_name'] ?: $t['rate_name']) . ($t['seat'] ? ' ' . $t['seat'] : ''), 0, 26, '.')), 0, 0, 'L', $fill);
            $pdf->SetFont('Courier', '', 8);
            $pdf->Cell($w[4], 6, $t['code'], 0, 0, 'L', $fill);
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->Cell($w[5], 6, $t['entries'] > 0 ? 'X' : '[  ]', 0, 1, 'C', $fill);
        }
        return $pdf->Output('S');
    }

    /** Ticket thermique 80 mm (imprimante de guichet). */
    public static function thermal(array $o, array $ev, array $org, array $tickets): string
    {
        $map = Seating::map($ev);
        $pdf = new PdfDoc('P', 'mm', [80, 140]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(4, 4, 4);
        foreach ($tickets as $i => $t) {
            $pdf->AddPage();
            $start = $t['session_id'] ? (string)val('SELECT starts_at FROM sessions WHERE id = ?', [$t['session_id']]) : $ev['starts_at'];
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(72, 4, self::t(mb_strtoupper($org['name'])), 0, 1, 'C');
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->MultiCell(72, 6, self::t(mb_strtoupper($ev['name'])), 0, 'C');
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(72, 5, self::t(fr_date($start)), 0, 1, 'C');
            if ($ev['venue']) $pdf->Cell(72, 5, self::t($ev['venue']), 0, 1, 'C');
            $pdf->Ln(2);
            Qr::drawPdf($pdf, $t['code'], 18, $pdf->GetY(), 44);
            $pdf->SetY($pdf->GetY() + 46);
            $pdf->SetFont('Courier', 'B', 10);
            $pdf->Cell(72, 5, $t['code'], 0, 1, 'C');
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->Cell(72, 6, self::t($t['rate_name'] . ' - ' . ((int)$t['price'] ? money((int)$t['price']) : 'Gratuit')), 0, 1, 'C');
            if ($t['seat']) $pdf->Cell(72, 6, self::t(Seating::label($t['seat'], $map)), 0, 1, 'C');
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->Cell(72, 4, self::t(trim($t['first_name'] . ' ' . $t['last_name'])), 0, 1, 'C');
            $pdf->Cell(72, 4, self::t('Commande ' . $o['ref'] . ' - ' . date('d/m/Y H:i', strtotime($o['created_at'])) . ' - ' . ($i + 1) . '/' . count($tickets)), 0, 1, 'C');
            $pdf->Cell(72, 4, self::t('Billet valable pour une seule entrée.'), 0, 1, 'C');
        }
        return $pdf->Output('S');
    }

    /** Reçu de paiement pour l'acheteur. */
    public static function receipt(array $o, array $ev, array $org, array $items): string
    {
        $pdf = new PdfDoc('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->Cell(0, 10, self::t('Reçu de paiement'), 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, self::t('Commande ' . $o['ref'] . ' - payée le ' . date('d/m/Y à H:i', strtotime((string)($o['paid_at'] ?: $o['created_at'])))), 0, 1);
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(95, 5, self::t('Vendu par'), 0, 0); $pdf->Cell(95, 5, self::t('Acheteur'), 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $left = $org['legal_name'] ?: $org['name'];
        $left .= "\n" . trim((string)$org['address']) . ($org['siret'] ? "\nSIRET " . $org['siret'] : '') . ($org['vat_number'] ? "\nTVA " . $org['vat_number'] : '');
        $y = $pdf->GetY();
        $pdf->MultiCell(95, 5, self::t($left));
        $pdf->SetXY(105, $y);
        $pdf->MultiCell(95, 5, self::t(trim($o['first_name'] . ' ' . $o['last_name']) . "\n" . $o['email']));
        $pdf->Ln(8);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 6, self::t($ev['name'] . ' - ' . fr_date($ev['starts_at'])), 0, 1);
        $pdf->Ln(2);
        $pdf->SetFillColor(17, 17, 17); $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(110, 7, self::t('Désignation'), 0, 0, 'L', true); $pdf->Cell(20, 7, self::t('Qté'), 0, 0, 'R', true);
        $pdf->Cell(30, 7, self::t('Prix unit.'), 0, 0, 'R', true); $pdf->Cell(30, 7, self::t('Total'), 0, 1, 'R', true);
        $pdf->SetTextColor(17, 17, 17); $pdf->SetFont('Helvetica', '', 10);
        $line = function ($label, $q, $u, $tot) use ($pdf) {
            $pdf->Cell(110, 7, self::t($label), 'B'); $pdf->Cell(20, 7, $q, 'B', 0, 'R');
            $pdf->Cell(30, 7, self::t($u), 'B', 0, 'R'); $pdf->Cell(30, 7, self::t($tot), 'B', 1, 'R');
        };
        foreach ($items as $i) $line($i['name'], (string)$i['qty'], money((int)$i['unit_price']), money((int)$i['qty'] * (int)$i['unit_price']));
        if ((int)$o['discount']) $line('Réduction' . ($o['promo_code'] ? ' (' . $o['promo_code'] . ')' : ''), '', '', '-' . money((int)$o['discount']));
        if ((int)$o['fees'] && $o['fee_mode'] === 'buyer') $line('Frais de service (' . setting('platform_name') . ')', '', '', money((int)$o['fees']));
        if ((int)$o['donation']) $line('Don à l\'organisateur', '', '', money((int)$o['donation']));
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(160, 9, self::t('Total payé'), 0, 0, 'R'); $pdf->Cell(30, 9, self::t(money((int)$o['total'])), 0, 1, 'R');
        if ((int)$o['refunded_amount']) { $pdf->SetFont('Helvetica', '', 10); $pdf->Cell(160, 6, self::t('Remboursé'), 0, 0, 'R'); $pdf->Cell(30, 6, self::t('-' . money((int)$o['refunded_amount'])), 0, 1, 'R'); }
        $pdf->Ln(8);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell(0, 4.5, self::t('Paiement : ' . provider_label($o['provider'] ?: $o['pay_method']) . '. Billetterie opérée par ' . setting('platform_name') . ' pour le compte de l\'organisateur. '
            . 'Ce reçu ne vaut pas billet. ' . (string)setting('company_info')));
        return $pdf->Output('S');
    }

    /** Relevé mensuel de commissions pour un organisateur. */
    public static function statement(array $org, string $month): string
    {
        $from = $month . '-01 00:00:00';
        $to = date('Y-m-d 00:00:00', strtotime($from . ' +1 month'));
        $rows = all("SELECT e.name, COUNT(o.id) n, SUM(o.total) total, SUM(o.commission) com, SUM(o.organizer_amount) due, SUM(o.refunded_amount) ref
                     FROM orders o JOIN events e ON e.id = o.event_id WHERE o.organizer_id = ? AND o.status IN ('paid','refunded') AND o.method IN ('online','partner')
                     AND o.provider IN ('stripe','paypal','sumup','mollie') AND o.paid_at >= ? AND o.paid_at < ? GROUP BY e.id ORDER BY e.name", [$org['id'], $from, $to]);
        $payouts = all('SELECT * FROM payouts WHERE organizer_id = ? AND created_at >= ? AND created_at < ? ORDER BY created_at', [$org['id'], $from, $to]);
        $pdf = new PdfDoc('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->Cell(0, 10, self::t('Relevé de ventes et de commissions'), 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $pdf->Cell(0, 5, self::t(($org['legal_name'] ?: $org['name']) . ' - ' . $mois[(int)substr($month, 5, 2)] . ' ' . substr($month, 0, 4)), 0, 1);
        $pdf->Cell(0, 5, self::t('Émis par ' . setting('platform_name') . ' le ' . date('d/m/Y')), 0, 1);
        $pdf->Ln(6);
        $pdf->SetFillColor(17, 17, 17); $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 9);
        $w = [70, 20, 30, 25, 20, 25];
        foreach (['Événement', 'Cmd', 'Encaissé', 'Commission', 'Remb.', 'Votre part'] as $k => $h) $pdf->Cell($w[$k], 7, self::t($h), 0, 0, $k ? 'R' : 'L', true);
        $pdf->Ln();
        $pdf->SetTextColor(17, 17, 17); $pdf->SetFont('Helvetica', '', 9.5);
        $tot = ['n' => 0, 'total' => 0, 'com' => 0, 'ref' => 0, 'due' => 0];
        foreach ($rows as $r) {
            $pdf->Cell($w[0], 7, self::t(mb_strimwidth($r['name'], 0, 40, '.')), 'B');
            $pdf->Cell($w[1], 7, (string)$r['n'], 'B', 0, 'R');
            foreach (['total' => 2, 'com' => 3, 'ref' => 4, 'due' => 5] as $k => $c) $pdf->Cell($w[$c], 7, self::t(money((int)$r[$k])), 'B', 0, 'R');
            $pdf->Ln();
            foreach ($tot as $k => $v) $tot[$k] += (int)$r[$k];
        }
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell($w[0], 8, 'Total'); $pdf->Cell($w[1], 8, (string)$tot['n'], 0, 0, 'R');
        foreach (['total' => 2, 'com' => 3, 'ref' => 4, 'due' => 5] as $k => $c) $pdf->Cell($w[$c], 8, self::t(money($tot[$k])), 0, 0, 'R');
        $pdf->Ln(14);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 6, self::t('Reversements effectués ce mois'), 0, 1);
        $pdf->SetFont('Helvetica', '', 9.5);
        foreach ($payouts as $p) $pdf->Cell(0, 6, self::t(date('d/m/Y', strtotime($p['created_at'])) . ' - ' . money((int)$p['amount']) . ($p['reference'] ? ' - réf. ' . $p['reference'] : '')), 0, 1);
        if (!$payouts) $pdf->Cell(0, 6, self::t('Aucun.'), 0, 1);
        $bal = Tickets::balance((int)$org['id']);
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 6, self::t('Solde restant à vous reverser à ce jour : ' . money($bal['balance'])), 0, 1);
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell(0, 4, self::t('Commission de service : ' . implode(' % + ', array_map(fn($v) => is_float($v) ? rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',') : money($v), Tickets::feeRates($org))) . ' par billet payant vendu en ligne. ' . (string)setting('company_info')));
        return $pdf->Output('S');
    }
}
