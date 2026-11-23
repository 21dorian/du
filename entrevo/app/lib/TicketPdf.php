<?php
declare(strict_types=1);

/** Billets PDF (un billet par page A4, format à imprimer ou à présenter sur téléphone). */
final class TicketPdf
{
    private static function t(?string $s): string { return PdfDoc::t($s); }

    /** @param array $tickets lignes de la table tickets */
    public static function build(array $order, array $event, array $org, array $tickets): string
    {
        $pdf = new PdfDoc('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetTitle(self::t('Billets ' . $event['name']));
        $pdf->SetAuthor(self::t((string)setting('platform_name')));
        [$r, $g, $b] = self::hex((string)($event['ticket_color'] ?: setting('primary_color', '#ff5a4e')));
        $map = Seating::map($event);
        $sessions = [];
        foreach ($tickets as $tk) if ($tk['session_id'] && !isset($sessions[$tk['session_id']])) $sessions[$tk['session_id']] = one('SELECT * FROM sessions WHERE id = ?', [$tk['session_id']]);
        $logo = !empty($event['ticket_logo']) && preg_match('~\.(jpe?g|png)$~i', $event['ticket_logo']) && is_file(ROOT . '/' . $event['ticket_logo']) ? ROOT . '/' . $event['ticket_logo'] : null;
        $poster = null;
        if (!empty($event['image']) && preg_match('~\.(jpe?g|png)$~i', $event['image']) && is_file(ROOT . '/' . $event['image'])) $poster = ROOT . '/' . $event['image'];
        $n = count($tickets);
        foreach (array_values($tickets) as $i => $t) {
            $pdf->AddPage();
            // en-tête
            $pdf->SetFillColor(17, 17, 17);
            $pdf->Rect(0, 0, 210, 22, 'F');
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect(15, 7, 8, 8, 'F');
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 15);
            $pdf->SetXY(26, 7);
            $pdf->Cell(100, 8, self::t(mb_strtoupper((string)setting('platform_name'))));
            if ($logo) $pdf->Image($logo, 150, 3, 0, 16);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetXY(120, 7);
            $kind = ['invitation' => 'INVITATION', 'accreditation' => 'ACCREDITATION', 'external' => 'BILLET'][$t['type'] ?? 'ticket'] ?? 'BILLET';
            if (!$logo) $pdf->Cell(75, 8, self::t($kind . ' ' . ($i + 1) . ' / ' . $n), 0, 0, 'R');

            // ticket papier : partie principale + souche
            $y0 = 34; $h = 110; $x0 = 15; $w = 180; $stub = 62; $cut = $x0 + $w - $stub;
            $pdf->SetFillColor(243, 237, 225);
            $pdf->Rect($x0, $y0, $w, $h, 'F');
            // encoches de la perforation
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Circle($cut, $y0, 4, 'F');
            $pdf->Circle($cut, $y0 + $h, 4, 'F');
            $pdf->SetDrawColor(140, 132, 118);
            $pdf->SetLineWidth(0.5);
            for ($yy = $y0 + 6; $yy < $y0 + $h - 6; $yy += 3.2) $pdf->Line($cut, $yy, $cut, $yy + 1.6);
            // bande d'accent
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect($x0, $y0, 4, $h, 'F');

            $tx = $x0 + 12;
            if ($poster) { $pdf->Image($poster, $tx, $y0 + 10, 34, 34); $tx2 = $tx + 40; } else { $tx2 = $tx; }
            $pdf->SetTextColor(109, 103, 92);
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->SetXY($tx2, $y0 + 10);
            $pdf->Cell($cut - $tx2 - 6, 4, self::t(mb_strtoupper($org['name']) . ' PRÉSENTE' . (($t['type'] ?? '') === 'invitation' ? ' - INVITATION' : '')));
            $pdf->SetTextColor(17, 17, 17);
            $pdf->SetFont('Helvetica', 'B', 19);
            $pdf->SetXY($tx2, $y0 + 15);
            $pdf->MultiCell($cut - $tx2 - 6, 8, self::t(mb_strtoupper($event['name'])), 0, 'L');

            $gy = $y0 + 50;
            $full = $cut - $tx - 6;
            $cw = $full / 3;
            $start = !empty($t['session_id']) && !empty($sessions[$t['session_id']]) ? $sessions[$t['session_id']]['starts_at'] : $event['starts_at'];
            self::field($pdf, $tx, $gy, $cw - 3, 'DATE', date('d.m.Y', strtotime($start)));
            self::field($pdf, $tx + $cw, $gy, $cw - 3, 'HEURE', date('H:i', strtotime($start)));
            self::field($pdf, $tx + $cw * 2, $gy, $cw - 3, 'PRIX', (int)$t['price'] > 0 ? money((int)$t['price']) : 'Gratuit');
            $extra = (int)($t['max_entries'] ?? 1) > 1 ? ' - ' . (int)$t['max_entries'] . ' entrées' : (!empty($t['per_day']) ? ' - 1 entrée/jour' : '');
            self::field($pdf, $tx, $gy + 13, $full, !empty($t['seat']) ? 'TARIF / PLACE' : 'TARIF', $t['rate_name'] . $extra . (!empty($t['seat']) ? ' - ' . Seating::label($t['seat'], $map) : ''));
            self::field($pdf, $tx, $gy + 26, $full, 'LIEU', trim($event['venue'] . ($event['city'] ? ', ' . $event['city'] : ''), ', ') ?: '-');
            self::field($pdf, $tx, $gy + 39, $full, 'PARTICIPANT', trim($t['first_name'] . ' ' . $t['last_name']) ?: '-');

            // souche
            Qr::drawPdf($pdf, $t['code'], $cut + 8, $y0 + 18, $stub - 16);
            $pdf->SetTextColor(17, 17, 17);
            $pdf->SetFont('Courier', 'B', 10);
            $pdf->SetXY($cut, $y0 + 18 + $stub - 14);
            $pdf->Cell($stub, 5, $t['code'], 0, 0, 'C');
            $pdf->SetFont('Helvetica', 'B', 7.5);
            $pdf->SetTextColor(109, 103, 92);
            $pdf->SetXY($cut, $y0 + 18 + $stub - 8);
            $pdf->Cell($stub, 4, self::t('COMMANDE ' . $order['ref']), 0, 0, 'C');
            $pdf->SetXY($cut, $y0 + 18 + $stub - 4);
            $pdf->Cell($stub, 4, self::t('BILLET ' . ($i + 1) . '/' . $n), 0, 0, 'C');

            // infos
            $pdf->SetTextColor(17, 17, 17);
            $pdf->SetXY(15, 156);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->Cell(180, 6, self::t('À LIRE AVANT DE VENIR'));
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetXY(15, 164);
            $info = "Un QR code = une entrée. Montre-le à l'entrée, sur ton téléphone ou imprimé. "
                . "Le premier scan gagne : une copie sera refusée. Bref, garde-le pour toi.\n";
            if (!empty($event['ticket_info'])) $info .= "\n" . $event['ticket_info'] . "\n";
            $info .= "\nAcheteur : " . trim($order['first_name'] . ' ' . $order['last_name']) . ' - ' . $order['email'];
            $info .= "\nOrganisateur : " . $org['name'] . ($org['email'] ? ' - ' . $org['email'] : '');
            $pdf->MultiCell(180, 5.2, self::t($info), 0, 'L');

            $pdf->SetXY(15, 282);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->Cell(180, 5, self::t(setting('platform_name') . ' - ' . abs_url('/')), 0, 0, 'C');
        }
        return $pdf->Output('S');
    }

    private static function field(PdfDoc $pdf, float $x, float $y, float $w, string $label, string $value): void
    {
        $pdf->SetTextColor(109, 103, 92);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, 4, self::t($label));
        $pdf->SetTextColor(17, 17, 17);
        $pdf->SetFont('Helvetica', 'B', 11.5);
        $pdf->SetXY($x, $y + 4.5);
        $txt = self::t(mb_strtoupper($value));
        if ($pdf->GetStringWidth($txt) > $w) {
            while (strlen($txt) > 1 && $pdf->GetStringWidth($txt . '...') > $w) $txt = substr($txt, 0, -1);
            $txt = rtrim($txt) . '...';
        }
        $pdf->Cell($w, 6, $txt);
    }

    private static function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return [255, 90, 78];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}

