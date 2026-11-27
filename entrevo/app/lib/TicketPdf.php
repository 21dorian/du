<?php
declare(strict_types=1);

/**
 * Billets PDF (1.8) : un billet par page A4, lisible imprimé comme sur téléphone.
 * Haut du billet aux couleurs de l'événement (affiche, titre, date, lieu), ligne de découpe,
 * puis un grand QR code et le nom du participant en gros.
 */
final class TicketPdf
{
    private const JOURS = ['DIM.', 'LUN.', 'MAR.', 'MER.', 'JEU.', 'VEN.', 'SAM.'];
    private const MOIS = ['', 'JANV.', 'FÉVR.', 'MARS', 'AVR.', 'MAI', 'JUIN', 'JUIL.', 'AOÛT', 'SEPT.', 'OCT.', 'NOV.', 'DÉC.'];

    private static function t(?string $s): string { return PdfDoc::t($s); }

    /** 4.2 : visuel propre au tarif (remplace l'affiche de l'événement sur le billet). */
    public static function ratePoster(array $t): ?string
    {
        static $c = [];
        $rid = (int)($t['rate_id'] ?? 0);
        if (!$rid) return null;
        if (!array_key_exists($rid, $c)) { $p = (string)val('SELECT ticket_image FROM rates WHERE id = ?', [$rid]); $c[$rid] = $p && preg_match('~\.(jpe?g|png)$~i', $p) && is_file(ROOT . '/' . $p) ? ROOT . '/' . $p : null; }
        return $c[$rid];
    }

    /** @param array $tickets lignes de la table tickets */
    public static function build(array $order, array $event, array $org, array $tickets): string
    {
        if (I18n::en()) { $event = I18n::localize($event, ['name', 'ticket_info']); foreach ($tickets as &$tk0) { if (!empty($tk0['rate_id']) && ($n0 = val('SELECT name_en FROM rates WHERE id = ?', [$tk0['rate_id']]))) $tk0['rate_name'] = $n0; } unset($tk0); } // 4.2
        if (class_exists('TicketDesign') && ($L = TicketDesign::get($event))) return TicketDesign::build($L, $order, $event, $org, $tickets); // 4.1 : maquette perso
        $pdf = new PdfDoc('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetTitle(self::t('Billets ' . $event['name']));
        $pdf->SetAuthor(self::t((string)setting('platform_name')));
        $accent = self::hex((string)($event['ticket_color'] ?: setting('primary_color', '#0071e3')));
        $light = self::luma($accent) > 0.6; // couleur claire : texte foncé par-dessus
        $ink = $light ? [17, 17, 20] : [255, 255, 255];
        $soft = $light ? [60, 60, 70] : self::mix($accent, [255, 255, 255], 0.78);
        $map = Seating::map($event);
        $sessions = [];
        foreach ($tickets as $tk) if (!empty($tk['session_id']) && !isset($sessions[$tk['session_id']])) $sessions[$tk['session_id']] = one('SELECT * FROM sessions WHERE id = ?', [$tk['session_id']]);
        $img = fn(?string $p) => $p && preg_match('~\.(jpe?g|png)$~i', $p) && is_file(ROOT . '/' . $p) ? ROOT . '/' . $p : null;
        $logo = $img($event['ticket_logo'] ?? null);
        $poster = $img($event['image'] ?? null);
        $place = trim(implode(', ', array_filter([(string)$event['venue'], (string)($event['address'] ?? ''), (string)$event['city']])));
        $n = count($tickets);

        foreach (array_values($tickets) as $i => $t) {
            $pdf->AddPage();
            $poster = self::ratePoster($t) ?? $img($event['image'] ?? null); // 4.2 : visuel par tarif
            $start = !empty($t['session_id']) && !empty($sessions[$t['session_id']]) ? $sessions[$t['session_id']]['starts_at'] : $event['starts_at'];
            $ts = strtotime((string)$start);
            $kind = ['invitation' => 'INVITATION', 'accreditation' => 'ACCRÉDITATION', 'external' => 'BILLET'][$t['type'] ?? 'ticket'] ?? 'BILLET';

            // ---------- bandeau plateforme ----------
            $pdf->SetFillColor(...$accent);
            $pdf->RoundedRect(15, 12, 6, 6, 1.5, 'F');
            $pdf->SetTextColor(17, 17, 20);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetXY(23.5, 12);
            $pdf->Cell(90, 6, self::t(mb_strtoupper((string)setting('platform_name'))));
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $label = $kind . '  ' . ($i + 1) . ' / ' . $n;
            $lw = $pdf->GetStringWidth(self::t($label)) + 8;
            $pdf->SetFillColor(17, 17, 20);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->RoundedRect(195 - $lw, 12, $lw, 6.5, 3.2, 'F');
            $pdf->SetXY(195 - $lw, 12);
            $pdf->Cell($lw, 6.5, self::t($label), 0, 0, 'C');

            // ---------- billet ----------
            $x = 15; $w = 180; $y = 24; $top = 86; $bottom = 104; $h = $top + $bottom; $r = 6;
            // ombre légère
            $pdf->SetFillColor(232, 233, 238);
            $pdf->RoundedRect($x + 0.8, $y + 1.6, $w, $h, $r, 'F');
            // partie basse (blanche) puis partie haute (couleur)
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(225, 227, 234);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($x, $y, $w, $h, $r, 'FD');
            $pdf->SetFillColor(...$accent);
            $pdf->RoundedRect($x, $y, $w, $top + $r, $r, 'F');
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($x, $y + $top - 0.2, $w, $r + 0.6, 'F');
            $pdf->SetDrawColor(225, 227, 234);
            $pdf->Line($x, $y + $top, $x, $y + $top + $r);
            $pdf->Line($x + $w, $y + $top, $x + $w, $y + $top + $r);

            // haut : affiche + organisateur + titre + date + lieu
            $tx = $x + 10;
            if ($poster) {
                $pdf->SetFillColor(255, 255, 255);
                $pdf->RoundedRect($tx - 1, $y + 9, 60, 60, 4, 'F');
                $pdf->Image($poster, $tx, $y + 10, 58, 58);
                $tx += 68;
            }
            $tw = $x + $w - 10 - $tx;
            $pdf->SetTextColor(...$soft);
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->SetXY($tx, $y + 11);
            $pdf->Cell($tw, 4, self::fit($pdf, mb_strtoupper($org['name']) . ' PRÉSENTE', $tw));
            $pdf->SetTextColor(...$ink);
            $size = mb_strlen((string)$event['name']) > 34 ? 19 : (mb_strlen((string)$event['name']) > 22 ? 23 : 27);
            $pdf->SetFont('Helvetica', 'B', $size);
            $pdf->SetXY($tx, $y + 17);
            $lh = $size * 0.42;
            $pdf->MultiCell($tw, $lh, self::t(mb_strtoupper((string)$event['name'])), 0, 'L');
            $after = min($pdf->GetY(), $y + 17 + 3 * $lh);
            // date en gros, lieu dessous
            $pdf->SetFont('Helvetica', 'B', 13);
            $pdf->SetXY($tx, max($after + 4, $y + 50));
            $pdf->Cell($tw, 7, self::t(self::JOURS[(int)date('w', $ts)] . ' ' . date('j', $ts) . ' ' . self::MOIS[(int)date('n', $ts)] . ' ' . date('Y', $ts) . '  ·  ' . date('H\hi', $ts)));
            $pdf->SetFont('Helvetica', '', 10.5);
            $pdf->SetTextColor(...$soft);
            $pdf->SetXY($tx, $pdf->GetY() + 7.5);
            $pdf->MultiCell($tw, 5, self::t($place ?: 'Lieu communiqué par l\'organisateur'), 0, 'L');
            if ($logo) $pdf->Image($logo, $x + $w - 34, $y + $top - 20, 0, 13);

            // ligne de découpe : encoches + pointillés
            $cy = $y + $top;
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Circle($x, $cy, 5, 'F');
            $pdf->Circle($x + $w, $cy, 5, 'F');
            $pdf->SetDrawColor(200, 203, 214);
            $pdf->SetLineWidth(0.5);
            for ($xx = $x + 9; $xx < $x + $w - 9; $xx += 3.4) $pdf->Line($xx, $cy, $xx + 1.7, $cy);

            // bas : QR code géant + participant
            $qs = 74; $qx = $x + 10; $qy = $cy + 12;
            $pdf->SetDrawColor(230, 232, 238);
            $pdf->SetLineWidth(0.4);
            $pdf->RoundedRect($qx - 3, $qy - 3, $qs + 6, $qs + 6, 4, 'D');
            Qr::drawPdf($pdf, $t['code'], $qx, $qy, $qs);
            $pdf->SetTextColor(17, 17, 20);
            $pdf->SetFont('Courier', 'B', 13);
            $pdf->SetXY($qx - 3, $qy + $qs + 5);
            $pdf->Cell($qs + 6, 6, $t['code'], 0, 0, 'C');

            $bx = $qx + $qs + 14; $bw = $x + $w - 10 - $bx;
            self::label($pdf, $bx, $cy + 12, $bw, 'PARTICIPANT');
            $who = trim($t['first_name'] . ' ' . $t['last_name']) ?: trim($order['first_name'] . ' ' . $order['last_name']);
            $pdf->SetTextColor(17, 17, 20);
            $pdf->SetFont('Helvetica', 'B', mb_strlen($who) > 22 ? 15 : 19);
            $pdf->SetXY($bx, $cy + 17);
            $pdf->MultiCell($bw, 8, self::t(mb_strtoupper($who)), 0, 'L');
            $fy = max($pdf->GetY() + 5, $cy + 36);
            // tarif en pastille de couleur
            $rate = $t['rate_name'] . ((int)($t['max_entries'] ?? 1) > 1 ? ' · ' . (int)$t['max_entries'] . ' entrées' : (!empty($t['per_day']) ? ' · 1 entrée/jour' : ''));
            $pdf->SetFont('Helvetica', 'B', 10.5);
            $rw = min($bw, $pdf->GetStringWidth(self::t($rate)) + 10);
            $pdf->SetFillColor(...self::mix($accent, [255, 255, 255], 0.86));
            $pdf->SetTextColor(...self::mix($accent, [0, 0, 0], $light ? 0.55 : 0.15));
            $pdf->RoundedRect($bx, $fy, $rw, 8, 4, 'F');
            $pdf->SetXY($bx, $fy);
            $pdf->Cell($rw, 8, self::fit($pdf, $rate, $rw - 6), 0, 0, 'C');
            static $rateAge = [];
            $rateAge[$t['rate_id']] ??= (int)val('SELECT min_age FROM rates WHERE id = ?', [$t['rate_id']]);
            $age = max((int)($event['min_age'] ?? 0), $rateAge[$t['rate_id']]);
            if ($age && $rw + 34 <= $bw) {
                $pdf->SetFillColor(253, 232, 230); $pdf->SetTextColor(196, 40, 28);
                $pdf->RoundedRect($bx + $rw + 3, $fy, 30, 8, 4, 'F');
                $pdf->SetXY($bx + $rw + 3, $fy);
                $pdf->Cell(30, 8, self::t($age . ' ANS ET +'), 0, 0, 'C');
            }
            $fy += 14;
            $half = ($bw - 4) / 2;
            if (!empty($t['seat'])) { self::field($pdf, $bx, $fy, $bw, 'PLACE', Seating::label($t['seat'], $map), 15); $fy += 15; }
            self::field($pdf, $bx, $fy, $half, 'PRIX', (int)$t['price'] > 0 ? money((int)$t['price']) : ($kind === 'INVITATION' ? 'Invitation' : 'Gratuit'));
            self::field($pdf, $bx + $half + 4, $fy, $half, 'COMMANDE', (string)$order['ref']);
            $fy += 15;
            self::field($pdf, $bx, $fy, $bw, 'DÉBUT', date('d/m/Y', $ts) . ' à ' . date('H\hi', $ts));
            // mention en bas du billet
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(130, 134, 150);
            $pdf->SetXY($bx, $y + $h - 11);
            $pdf->MultiCell($bw, 3.8, self::t('Billet personnel. Le premier scan gagne : une copie sera refusée à l\'entrée.'), 0, 'L');

            // ---------- à lire avant de venir ----------
            $iy = $y + $h + 12;
            $pdf->SetTextColor(17, 17, 20);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetXY(15, $iy);
            $pdf->Cell(180, 6, self::t('À lire avant de venir'));
            $tips = [
                ['1', 'Un QR code = une entrée', 'Montre-le sur ton téléphone ou imprimé. Garde-le pour toi.'],
                ['2', 'Luminosité à fond', 'Le scan est plus rapide avec l\'écran bien lumineux.'],
                ['3', 'Réseau saturé ?', 'Pas de souci : ce PDF fonctionne sans connexion.'],
            ];
            $cw = 180 / 3;
            foreach ($tips as $k => [$num, $title, $txt]) {
                $cx = 15 + $k * $cw;
                $pdf->SetFillColor(...$accent);
                $pdf->Circle($cx + 3.5, $iy + 13.5, 3.5, 'F');
                $pdf->SetTextColor(...($light ? [17, 17, 20] : [255, 255, 255]));
                $pdf->SetFont('Helvetica', 'B', 9);
                $pdf->SetXY($cx, $iy + 11);
                $pdf->Cell(7, 5, $num, 0, 0, 'C');
                $pdf->SetTextColor(17, 17, 20);
                $pdf->SetFont('Helvetica', 'B', 9.5);
                $pdf->SetXY($cx + 9, $iy + 10.5);
                $pdf->Cell($cw - 12, 5, self::t($title));
                $pdf->SetFont('Helvetica', '', 8.5);
                $pdf->SetTextColor(90, 94, 110);
                $pdf->SetXY($cx + 9, $iy + 15.5);
                $pdf->MultiCell($cw - 12, 4, self::t($txt), 0, 'L');
            }
            $ny = $iy + 30;
            if (!empty($event['ticket_info'])) {
                $pdf->SetFillColor(246, 244, 239);
                $pdf->SetFont('Helvetica', '', 9.5);
                $lines = max(1, (int)ceil($pdf->GetStringWidth(self::t((string)$event['ticket_info'])) / 164) + substr_count((string)$event['ticket_info'], "\n"));
                $bh = min(40, 10 + $lines * 4.6);
                $pdf->Rect(15, $ny, 180, $bh, 'F');
                $pdf->SetFillColor(...$accent);
                $pdf->Rect(15, $ny, 1.5, $bh, 'F');
                $pdf->SetTextColor(17, 17, 20);
                $pdf->SetFont('Helvetica', 'B', 8);
                $pdf->SetXY(21, $ny + 3);
                $pdf->Cell(170, 4, self::t('LE MOT DE L\'ORGANISATEUR'));
                $pdf->SetFont('Helvetica', '', 9.5);
                $pdf->SetXY(21, $ny + 8);
                $pdf->MultiCell(170, 4.6, self::t((string)$event['ticket_info']), 0, 'L');
                $ny += $bh + 6;
            }
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->SetTextColor(110, 114, 130);
            $by = max($ny, 258);
            $pdf->SetXY(15, $by);
            $pdf->MultiCell(180, 4.2, self::t('Acheteur : ' . trim($order['first_name'] . ' ' . $order['last_name']) . ($order['email'] ? ' (' . $order['email'] . ')' : '')
                . "\nOrganisateur : " . $org['name'] . (!empty($org['email']) ? ' - ' . $org['email'] : '') . "\nUne question sur l'événement ? Écris directement à l'organisateur."), 0, 'L');
            $pdf->SetXY(15, max(284, $by + 15));
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(160, 163, 175);
            $pdf->Cell(180, 4, self::t(setting('platform_name') . ' · ' . abs_url('/')), 0, 0, 'C');
        }
        return $pdf->Output('S');
    }

    private static function label(PdfDoc $pdf, float $x, float $y, float $w, string $label): void
    {
        $pdf->SetTextColor(130, 134, 150);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, 4, self::t($label));
    }

    private static function field(PdfDoc $pdf, float $x, float $y, float $w, string $label, string $value, float $h = 13): void
    {
        self::label($pdf, $x, $y, $w, $label);
        $pdf->SetTextColor(17, 17, 20);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetXY($x, $y + 4.5);
        $pdf->Cell($w, 6, self::fit($pdf, $value, $w));
    }

    /** Coupe proprement un texte trop long pour la largeur donnée (police déjà choisie). */
    private static function fit(PdfDoc $pdf, string $value, float $w): string
    {
        $txt = self::t($value);
        if ($pdf->GetStringWidth($txt) <= $w) return $txt;
        while (strlen($txt) > 1 && $pdf->GetStringWidth($txt . '...') > $w) $txt = substr($txt, 0, -1);
        return rtrim($txt) . '...';
    }

    private static function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return [0, 50, 250];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function luma(array $c): float { return (0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]) / 255; }

    private static function mix(array $a, array $b, float $k): array
    {
        return [(int)round($a[0] + ($b[0] - $a[0]) * $k), (int)round($a[1] + ($b[1] - $a[1]) * $k), (int)round($a[2] + ($b[2] - $a[2]) * $k)];
    }
}
