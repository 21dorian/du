<?php
declare(strict_types=1);

/** Billets PDF (un billet par page A4, format à imprimer ou à présenter sur téléphone). */
final class TicketPdf
{
    private static function t(?string $s): string
    {
        $s = (string)$s;
        $s = str_replace(["\u{202F}", "\u{00A0}", '’', '«', '»', '–', '—'], [' ', ' ', "'", '"', '"', '-', '-'], $s);
        return (string)iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
    }

    /** @param array $tickets lignes de la table tickets */
    public static function build(array $order, array $event, array $org, array $tickets): string
    {
        $pdf = new PdfDoc('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetTitle(self::t('Billets ' . $event['name']));
        $pdf->SetAuthor(self::t((string)setting('platform_name')));
        [$r, $g, $b] = self::hex((string)setting('primary_color', '#ff5a4e'));
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
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetXY(120, 7);
            $pdf->Cell(75, 8, self::t('BILLET ' . ($i + 1) . ' / ' . $n), 0, 0, 'R');

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
            $pdf->Cell($cut - $tx2 - 6, 4, self::t(mb_strtoupper($org['name']) . ' PRÉSENTE'));
            $pdf->SetTextColor(17, 17, 17);
            $pdf->SetFont('Helvetica', 'B', 19);
            $pdf->SetXY($tx2, $y0 + 15);
            $pdf->MultiCell($cut - $tx2 - 6, 8, self::t(mb_strtoupper($event['name'])), 0, 'L');

            $gy = $y0 + 50;
            $full = $cut - $tx - 6;
            $cw = $full / 3;
            self::field($pdf, $tx, $gy, $cw - 3, 'DATE', date('d.m.Y', strtotime($event['starts_at'])));
            self::field($pdf, $tx + $cw, $gy, $cw - 3, 'HEURE', date('H:i', strtotime($event['starts_at'])));
            self::field($pdf, $tx + $cw * 2, $gy, $cw - 3, 'PRIX', (int)$t['price'] > 0 ? money((int)$t['price']) : 'Gratuit');
            self::field($pdf, $tx, $gy + 13, $full, 'TARIF', $t['rate_name']);
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

/** FPDF + rectangle à coins arrondis. */
class PdfDoc extends FPDF
{
    public function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = ''): void
    {
        $k = $this->k;
        $hp = $this->h;
        $op = $style === 'F' ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 'S');
        $arc = 4 / 3 * (M_SQRT2 - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->arc($xc + $r * $arc, $yc - $r, $xc + $r, $yc - $r * $arc, $xc + $r, $yc);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->arc($xc + $r, $yc + $r * $arc, $xc + $r * $arc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->arc($xc - $r * $arc, $yc + $r, $xc - $r, $yc + $r * $arc, $xc - $r, $yc);
        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->arc($xc - $r, $yc - $r * $arc, $xc - $r * $arc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    public function Circle(float $x, float $y, float $r, string $style = ''): void
    {
        $this->Ellipse($x, $y, $r, $r, $style);
    }

    public function Ellipse(float $x, float $y, float $rx, float $ry, string $style = ''): void
    {
        $op = $style === 'F' ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 'S');
        $lx = 4 / 3 * (M_SQRT2 - 1) * $rx;
        $ly = 4 / 3 * (M_SQRT2 - 1) * $ry;
        $k = $this->k; $h = $this->h;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c', ($x + $rx) * $k, ($h - $y) * $k, ($x + $rx) * $k, ($h - ($y - $ly)) * $k, ($x + $lx) * $k, ($h - ($y - $ry)) * $k, $x * $k, ($h - ($y - $ry)) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x - $lx) * $k, ($h - ($y - $ry)) * $k, ($x - $rx) * $k, ($h - ($y - $ly)) * $k, ($x - $rx) * $k, ($h - $y) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x - $rx) * $k, ($h - ($y + $ly)) * $k, ($x - $lx) * $k, ($h - ($y + $ry)) * $k, $x * $k, ($h - ($y + $ry)) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s', ($x + $lx) * $k, ($h - ($y + $ry)) * $k, ($x + $rx) * $k, ($h - ($y + $ly)) * $k, ($x + $rx) * $k, ($h - $y) * $k, $op));
    }

    private function arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $x1 * $this->k, ($h - $y1) * $this->k, $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
    }
}
