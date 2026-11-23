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
        [$r, $g, $b] = self::hex((string)setting('primary_color', '#5b3df5'));
        $n = count($tickets);
        foreach (array_values($tickets) as $i => $t) {
            $pdf->AddPage();
            // bandeau
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect(0, 0, 210, 34, 'F');
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 20);
            $pdf->SetXY(15, 9);
            $pdf->Cell(130, 9, self::t((string)setting('platform_name')));
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetXY(15, 19);
            $pdf->Cell(130, 6, self::t('Billet électronique - Organisé par ' . $org['name']));
            $pdf->SetXY(150, 12);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->Cell(45, 6, self::t('Billet ' . ($i + 1) . ' / ' . $n), 0, 0, 'R');

            // cadre billet
            $pdf->SetDrawColor(220, 222, 230);
            $pdf->SetLineWidth(0.4);
            $pdf->RoundedRect(15, 44, 180, 112, 4);

            $pdf->SetTextColor(20, 20, 30);
            $pdf->SetFont('Helvetica', 'B', 18);
            $pdf->SetXY(22, 52);
            $pdf->MultiCell(105, 8, self::t($event['name']));
            $y = $pdf->GetY() + 3;
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->SetTextColor(70, 70, 85);
            $lines = [fr_date($event['starts_at'])];
            if ($event['venue']) $lines[] = $event['venue'];
            if ($event['address'] || $event['city']) $lines[] = trim($event['address'] . ' ' . $event['city']);
            foreach ($lines as $l) { $pdf->SetXY(22, $y); $pdf->MultiCell(105, 6, self::t($l)); $y = $pdf->GetY(); }

            $y += 6;
            $pdf->SetXY(22, $y);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(120, 120, 135);
            $pdf->Cell(105, 5, self::t('PARTICIPANT'));
            $pdf->SetXY(22, $y + 5);
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->SetTextColor(20, 20, 30);
            $pdf->Cell(105, 7, self::t(trim($t['first_name'] . ' ' . $t['last_name']) ?: '-'));
            $pdf->SetXY(22, $y + 15);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(120, 120, 135);
            $pdf->Cell(105, 5, self::t('TARIF'));
            $pdf->SetXY(22, $y + 20);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetTextColor(20, 20, 30);
            $pdf->Cell(105, 6, self::t($t['rate_name'] . ' - ' . ($t['price'] > 0 ? money((int)$t['price']) : 'Gratuit')));

            Qr::drawPdf($pdf, $t['code'], 133, 54, 56);
            $pdf->SetFont('Courier', 'B', 11);
            $pdf->SetXY(133, 112);
            $pdf->Cell(56, 6, $t['code'], 0, 0, 'C');
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(120, 120, 135);
            $pdf->SetXY(133, 118);
            $pdf->Cell(56, 5, self::t('Commande ' . $order['ref']), 0, 0, 'C');

            // pointillés
            $pdf->SetDrawColor(200, 200, 210);
            for ($x = 15; $x < 195; $x += 3) $pdf->Line($x, 166, $x + 1.5, 166);

            // informations
            $pdf->SetTextColor(40, 40, 55);
            $pdf->SetXY(15, 174);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->Cell(180, 6, self::t('Informations importantes'));
            $pdf->SetFont('Helvetica', '', 9.5);
            $pdf->SetXY(15, 182);
            $info = "Ce billet est valable pour une seule entrée. Présentez-le à l'entrée, imprimé ou sur votre téléphone : "
                . "le QR code sera scanné. Toute copie sera refusée après le premier passage.\n";
            if (!empty($event['ticket_info'])) $info .= "\n" . $event['ticket_info'] . "\n";
            $info .= "\nAcheteur : " . trim($order['first_name'] . ' ' . $order['last_name']) . ' - ' . $order['email'];
            $info .= "\nOrganisateur : " . $org['name'] . ($org['email'] ? ' - ' . $org['email'] : '');
            $pdf->MultiCell(180, 5, self::t($info));

            $pdf->SetXY(15, 282);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(150, 150, 160);
            $pdf->Cell(180, 5, self::t('Billetterie ' . setting('platform_name') . ' - ' . abs_url('/')), 0, 0, 'C');
        }
        return $pdf->Output('S');
    }

    private static function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return [91, 61, 245];
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

    private function arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $x1 * $this->k, ($h - $y1) * $this->k, $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
    }
}
