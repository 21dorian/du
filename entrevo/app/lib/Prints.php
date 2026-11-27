<?php
declare(strict_types=1);

/**
 * 4.2 : impressions professionnelles.
 *  - billets thermiques format Boca / FGL (152 × 54 mm, paysage) avec talon détachable ;
 *  - planches de billets pré-imprimés (A4, 3 billets par page, ligne de découpe) ;
 *  - cartes membres format carte bancaire CR80 (85,6 × 54 mm), une par page pour imprimante à cartes PVC,
 *    ou 10 par planche A4.
 */
final class Prints
{
    private static function t(?string $s): string { return PdfDoc::t($s); }
    private static function rgb(string $hex): array { $h = ltrim($hex, '#'); return strlen($h) === 6 ? [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))] : [0, 113, 227]; }
    private static function accent(array $ev): array { return self::rgb((string)(($ev['ticket_color'] ?? '') ?: setting('primary_color', '#0071e3'))); }
    private static function start(array $t, array $ev): string { return !empty($t['session_id']) ? ((string)val('SELECT starts_at FROM sessions WHERE id = ?', [$t['session_id']]) ?: $ev['starts_at']) : $ev['starts_at']; }

    /** Billets thermiques 152 × 54 mm (Boca, FGL…) : corps du billet + talon de contrôle. */
    public static function boca(array $o, array $ev, array $org, array $tickets): string
    {
        $pdf = new PdfDoc('L', 'mm', [54, 152]);
        $pdf->SetAutoPageBreak(false); $pdf->SetMargins(0, 0, 0);
        $map = Seating::map($ev); $acc = self::accent($ev); $n = count($tickets);
        foreach (array_values($tickets) as $i => $t) {
            $pdf->AddPage();
            $start = self::start($t, $ev);
            $pdf->SetFillColor(...$acc); $pdf->Rect(0, 0, 4, 54, 'F');
            $pdf->SetTextColor(110, 110, 120); $pdf->SetFont('Helvetica', 'B', 6.5); $pdf->SetXY(8, 4); $pdf->Cell(80, 3, self::t(mb_strtoupper($org['name'])));
            $pdf->SetTextColor(17, 17, 20); $pdf->SetFont('Helvetica', 'B', 13); $pdf->SetXY(8, 8); $pdf->MultiCell(84, 5.4, self::t(mb_strimwidth(mb_strtoupper($ev['name']), 0, 60, '…')));
            $y = max(20, $pdf->GetY() + 1);
            $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetXY(8, $y); $pdf->Cell(84, 4.5, self::t(fr_date($start)));
            $pdf->SetFont('Helvetica', '', 8); $pdf->SetXY(8, $y + 4.6); $pdf->Cell(84, 4, self::t(trim((string)$ev['venue'] . ($ev['city'] ? ', ' . $ev['city'] : ''))));
            $pdf->SetFont('Helvetica', 'B', 10); $pdf->SetXY(8, 38); $pdf->Cell(60, 5, self::t($t['rate_name']));
            $pdf->SetXY(68, 38); $pdf->Cell(24, 5, self::t((int)$t['price'] ? money((int)$t['price']) : 'Exonéré'), 0, 0, 'R');
            $pdf->SetFont('Helvetica', '', 7); $pdf->SetTextColor(90, 90, 100); $pdf->SetXY(8, 43.5);
            $who = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
            $pdf->Cell(84, 3.5, self::t(($t['seat'] ? Seating::label((string)$t['seat'], $map) . ' · ' : 'Placement libre · ') . ($who ?: 'Billet au porteur')));
            $pdf->SetXY(8, 47.5); $pdf->Cell(84, 3.5, self::t('N° ' . $t['code'] . ' · ' . ($i + 1) . '/' . $n . ' · ' . $o['ref']));
            Qr::drawPdf($pdf, $t['code'], 94, 9, 30);
            $pdf->SetFont('Courier', 'B', 6.5); $pdf->SetTextColor(17, 17, 20); $pdf->SetXY(92, 40); $pdf->Cell(34, 3, $t['code'], 0, 0, 'C');
            // talon
            $pdf->SetDrawColor(170, 170, 180); $pdf->SetLineWidth(0.2);
            for ($d = 2; $d < 52; $d += 2.4) $pdf->Line(128, $d, 128, $d + 1.2);
            $pdf->SetFont('Helvetica', 'B', 6); $pdf->SetTextColor(110, 110, 120); $pdf->SetXY(130, 4); $pdf->Cell(20, 3, self::t('CONTRÔLE'), 0, 0, 'C');
            $pdf->SetFont('Helvetica', 'B', 7.5); $pdf->SetTextColor(17, 17, 20); $pdf->SetXY(130, 9); $pdf->MultiCell(20, 3.4, self::t(mb_strimwidth($ev['name'], 0, 40, '…')), 0, 'C');
            $pdf->SetFont('Helvetica', '', 6.5); $pdf->SetXY(130, 26); $pdf->MultiCell(20, 3, self::t(date('d/m/Y H:i', strtotime($start)) . "\n" . mb_strimwidth((string)$t['rate_name'], 0, 26, '…')), 0, 'C');
            $pdf->SetFont('Courier', 'B', 6); $pdf->SetXY(130, 44); $pdf->Cell(20, 3, substr((string)$t['code'], 0, 10), 0, 0, 'C');
        }
        return $pdf->Output('S');
    }

    /** Planche A4 de billets pré-imprimés (3 par page, découpe). */
    public static function sheet(array $o, array $ev, array $org, array $tickets, string $label): string
    {
        $pdf = new PdfDoc('P', 'mm', 'A4'); $pdf->SetAutoPageBreak(false);
        $pdf->SetTitle(self::t('Billets ' . $ev['name'])); $acc = self::accent($ev); $map = Seating::map($ev); $n = count($tickets);
        foreach (array_values($tickets) as $i => $t) {
            if ($i % 3 === 0) { $pdf->AddPage(); $pdf->SetFont('Helvetica', '', 7); $pdf->SetTextColor(140, 140, 150); $pdf->SetXY(10, 4); $pdf->Cell(190, 4, self::t('Stock « ' . $label . ' » · page ' . (intdiv($i, 3) + 1) . '/' . (int)ceil($n / 3) . ' · découper sur les pointillés'), 0, 0, 'C'); }
            $y = 10 + ($i % 3) * 94; $start = self::start($t, $ev);
            $pdf->SetDrawColor(210, 210, 215); $pdf->SetLineWidth(0.3); $pdf->RoundedRect(10, $y, 190, 86, 4, 'D');
            $pdf->SetFillColor(...$acc); $pdf->RoundedRect(10, $y, 8, 86, 4, 'F'); $pdf->Rect(14, $y, 4, 86, 'F');
            $pdf->SetTextColor(110, 110, 120); $pdf->SetFont('Helvetica', 'B', 8); $pdf->SetXY(24, $y + 6); $pdf->Cell(120, 4, self::t(mb_strtoupper($org['name'])));
            $pdf->SetTextColor(17, 17, 20); $pdf->SetFont('Helvetica', 'B', 18); $pdf->SetXY(24, $y + 12); $pdf->MultiCell(120, 8, self::t($ev['name']));
            $yy = max($y + 30, $pdf->GetY() + 2);
            $pdf->SetFont('Helvetica', 'B', 11); $pdf->SetXY(24, $yy); $pdf->Cell(120, 6, self::t(fr_date($start)));
            $pdf->SetFont('Helvetica', '', 10); $pdf->SetXY(24, $yy + 6); $pdf->Cell(120, 5, self::t(trim(implode(', ', array_filter([(string)$ev['venue'], (string)($ev['address'] ?? ''), (string)$ev['city']])))));
            $pdf->SetFont('Helvetica', 'B', 13); $pdf->SetXY(24, $y + 62); $pdf->Cell(90, 7, self::t($t['rate_name']));
            $pdf->SetXY(110, $y + 62); $pdf->Cell(34, 7, self::t((int)$t['price'] ? money((int)$t['price']) : 'Exonéré'), 0, 0, 'R');
            $pdf->SetFont('Helvetica', '', 8); $pdf->SetTextColor(110, 110, 120); $pdf->SetXY(24, $y + 72);
            $pdf->Cell(120, 4, self::t(($t['seat'] ? Seating::label((string)$t['seat'], $map) : 'Placement libre') . ' · billet au porteur · une seule entrée'));
            $pdf->SetXY(24, $y + 77); $pdf->Cell(120, 4, self::t('N° ' . ($i + 1) . '/' . $n . ' · ' . $t['code']));
            $pdf->SetDrawColor(190, 190, 200); for ($d = $y + 3; $d < $y + 84; $d += 3) $pdf->Line(150, $d, 150, $d + 1.5);
            Qr::drawPdf($pdf, $t['code'], 155, $y + 18, 40);
            $pdf->SetFont('Courier', 'B', 8); $pdf->SetTextColor(17, 17, 20); $pdf->SetXY(152, $y + 60); $pdf->Cell(46, 4, $t['code'], 0, 0, 'C');
            if ($i % 3 !== 2 && $i < $n - 1) { $pdf->SetDrawColor(200, 200, 205); for ($d = 4; $d < 206; $d += 4) $pdf->Line($d, $y + 90, $d + 2, $y + 90); }
        }
        return $pdf->Output('S');
    }

    /** Cartes membres CR80. $cards : [name, sub, code, valid, kind] ; $a4 : planche de 10. */
    public static function cards(array $cards, array $org, bool $a4 = false): string
    {
        $pdf = $a4 ? new PdfDoc('P', 'mm', 'A4') : new PdfDoc('L', 'mm', [53.98, 85.6]);
        $pdf->SetAutoPageBreak(false); $pdf->SetMargins(0, 0, 0);
        $acc = self::rgb((string)(($org['brand_color'] ?? '') ?: setting('primary_color', '#0071e3')));
        $logo = !empty($org['logo']) && preg_match('~\.(jpe?g|png)$~i', (string)$org['logo']) && is_file(ROOT . '/' . $org['logo']) ? ROOT . '/' . $org['logo'] : null;
        foreach (array_values($cards) as $i => $c) {
            if (!$a4) { $pdf->AddPage(); $x = 0; $y = 0; }
            else { if ($i % 10 === 0) $pdf->AddPage(); $x = 15 + ($i % 2) * 95; $y = 12 + intdiv($i % 10, 2) * 56; $pdf->SetDrawColor(200, 200, 205); $pdf->SetLineWidth(0.1); $pdf->RoundedRect($x, $y, 85.6, 53.98, 3, 'D'); }
            $pdf->SetFillColor(...$acc); $pdf->RoundedRect($x, $y, 85.6, 16, $a4 ? 3 : 0, 'F'); if ($a4) $pdf->Rect($x, $y + 8, 85.6, 8, 'F');
            if ($logo) $pdf->Image($logo, $x + 4, $y + 3, 0, 10);
            $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetXY($x + ($logo ? 18 : 5), $y + 4); $pdf->Cell(62, 4, self::t(mb_strimwidth($org['name'], 0, 34, '…')));
            $pdf->SetFont('Helvetica', '', 6.5); $pdf->SetXY($x + ($logo ? 18 : 5), $y + 8.5); $pdf->Cell(62, 3, self::t(mb_strtoupper($c['kind'] ?? 'Carte membre')));
            $pdf->SetTextColor(17, 17, 20); $pdf->SetFont('Helvetica', 'B', 12); $pdf->SetXY($x + 5, $y + 21); $pdf->Cell(52, 5, self::t(mb_strimwidth((string)$c['name'], 0, 26, '…')));
            $pdf->SetFont('Helvetica', '', 7.5); $pdf->SetTextColor(90, 90, 100); $pdf->SetXY($x + 5, $y + 27.5); $pdf->MultiCell(52, 3.4, self::t(mb_strimwidth((string)$c['sub'], 0, 70, '…')));
            $pdf->SetFont('Helvetica', '', 6.5); $pdf->SetXY($x + 5, $y + 42); $pdf->Cell(52, 3, self::t((string)($c['valid'] ?? '')));
            $pdf->SetFont('Courier', 'B', 7.5); $pdf->SetTextColor(17, 17, 20); $pdf->SetXY($x + 5, $y + 46.5); $pdf->Cell(52, 3.5, (string)$c['code']);
            Qr::drawPdf($pdf, (string)$c['code'], $x + 59, $y + 21, 23);
        }
        return $pdf->Output('S');
    }
}
