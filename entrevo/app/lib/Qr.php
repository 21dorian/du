<?php
declare(strict_types=1);

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;

/** Génération de QR codes (matrice, SVG, dessin PDF). */
final class Qr
{
    /** @return bool[][] matrice avec marge */
    public static function matrix(string $text): array
    {
        $opt = new QROptions(['eccLevel' => EccLevel::M, 'addQuietzone' => true, 'quietzoneSize' => 2]);
        $qr = new QRCode($opt);
        $qr->addByteSegment($text);
        return $qr->getQRMatrix()->getMatrix(true);
    }

    public static function svg(string $text, int $size = 200): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $path = '';
        foreach ($m as $y => $row) {
            $x = 0;
            while ($x < $n) {
                if ($row[$x]) {
                    $start = $x;
                    while ($x < $n && $row[$x]) $x++;
                    $path .= 'M' . $start . ' ' . $y . 'h' . ($x - $start) . 'v1h-' . ($x - $start) . 'z';
                } else {
                    $x++;
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $n . ' ' . $n . '" width="' . $size . '" height="' . $size . '" shape-rendering="crispEdges" role="img" aria-label="QR code">'
            . '<rect width="100%" height="100%" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
    }

    public static function drawPdf(FPDF $pdf, string $text, float $x, float $y, float $size): void
    {
        $m = self::matrix($text);
        $n = count($m);
        $c = $size / $n;
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $size, $size, 'F');
        $pdf->SetFillColor(0, 0, 0);
        foreach ($m as $row => $cols) {
            $col = 0;
            while ($col < $n) {
                if ($cols[$col]) {
                    $s = $col;
                    while ($col < $n && $cols[$col]) $col++;
                    $pdf->Rect($x + $s * $c, $y + $row * $c, ($col - $s) * $c + 0.01, $c + 0.01, 'F');
                } else {
                    $col++;
                }
            }
        }
    }
}
