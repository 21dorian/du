<?php
declare(strict_types=1);

/**
 * 4.1 : billet personnalisé (éditeur en glisser-déposer). Une maquette = un format en mm et des éléments positionnés :
 * texte (avec variables), QR code, affiche, logo, bloc de couleur, filet. Rendue en PDF, un billet par page A4.
 */
final class TicketDesign
{
    public const VARS = ['{evenement}' => 'Nom de l\'événement', '{date}' => 'Date', '{heure}' => 'Heure', '{lieu}' => 'Lieu', '{adresse}' => 'Adresse', '{nom}' => 'Nom du participant',
        '{tarif}' => 'Tarif', '{prix}' => 'Prix', '{siege}' => 'Place', '{code}' => 'Code du billet', '{ref}' => 'N° de commande', '{organisateur}' => 'Organisateur', '{type}' => 'Billet / Invitation', '{numero}' => 'N° du billet'];

    public static function get(array $ev): ?array
    {
        $j = json_decode((string)($ev['ticket_layout'] ?? ''), true);
        return is_array($j) && !empty($j['elements']) ? $j : null;
    }

    /** Modèles de départ. */
    public static function presets(array $ev): array
    {
        $c = (string)($ev['ticket_color'] ?: setting('primary_color', '#0071e3'));
        return [
            'classique' => ['name' => 'Classique', 'w' => 180, 'h' => 90, 'bg' => '#ffffff', 'elements' => [
                ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 180, 'h' => 16, 'bg' => $c],
                ['type' => 'text', 'x' => 6, 'y' => 3, 'w' => 120, 'h' => 10, 'text' => '{evenement}', 'size' => 15, 'bold' => 1, 'color' => '#ffffff'],
                ['type' => 'text', 'x' => 120, 'y' => 4, 'w' => 54, 'h' => 8, 'text' => '{type}', 'size' => 9, 'bold' => 1, 'color' => '#ffffff', 'align' => 'R'],
                ['type' => 'text', 'x' => 6, 'y' => 22, 'w' => 120, 'h' => 8, 'text' => '{date} · {heure}', 'size' => 12, 'bold' => 1, 'color' => '#1d1d1f'],
                ['type' => 'text', 'x' => 6, 'y' => 31, 'w' => 120, 'h' => 7, 'text' => '{lieu}', 'size' => 10, 'color' => '#55555f'],
                ['type' => 'line', 'x' => 6, 'y' => 42, 'w' => 120, 'h' => 0.4, 'bg' => '#e5e5ea'],
                ['type' => 'text', 'x' => 6, 'y' => 46, 'w' => 120, 'h' => 6, 'text' => 'PARTICIPANT', 'size' => 7, 'bold' => 1, 'color' => '#8e8e93'],
                ['type' => 'text', 'x' => 6, 'y' => 52, 'w' => 120, 'h' => 9, 'text' => '{nom}', 'size' => 16, 'bold' => 1, 'color' => '#1d1d1f'],
                ['type' => 'text', 'x' => 6, 'y' => 64, 'w' => 60, 'h' => 6, 'text' => 'TARIF', 'size' => 7, 'bold' => 1, 'color' => '#8e8e93'],
                ['type' => 'text', 'x' => 6, 'y' => 70, 'w' => 60, 'h' => 7, 'text' => '{tarif}', 'size' => 11, 'bold' => 1, 'color' => '#1d1d1f'],
                ['type' => 'text', 'x' => 70, 'y' => 64, 'w' => 56, 'h' => 6, 'text' => 'PLACE', 'size' => 7, 'bold' => 1, 'color' => '#8e8e93'],
                ['type' => 'text', 'x' => 70, 'y' => 70, 'w' => 56, 'h' => 7, 'text' => '{siege}', 'size' => 11, 'bold' => 1, 'color' => '#1d1d1f'],
                ['type' => 'qr', 'x' => 136, 'y' => 24, 'w' => 38, 'h' => 38],
                ['type' => 'text', 'x' => 132, 'y' => 64, 'w' => 46, 'h' => 6, 'text' => '{code}', 'size' => 9, 'bold' => 1, 'color' => '#1d1d1f', 'align' => 'C', 'mono' => 1],
                ['type' => 'text', 'x' => 6, 'y' => 82, 'w' => 168, 'h' => 5, 'text' => 'Commande {ref} · {organisateur}', 'size' => 7, 'color' => '#8e8e93'],
            ]],
            'affiche' => ['name' => 'Affiche', 'w' => 180, 'h' => 100, 'bg' => '#0f0f14', 'elements' => [
                ['type' => 'poster', 'x' => 0, 'y' => 0, 'w' => 70, 'h' => 100],
                ['type' => 'text', 'x' => 76, 'y' => 8, 'w' => 98, 'h' => 12, 'text' => '{evenement}', 'size' => 17, 'bold' => 1, 'color' => '#ffffff'],
                ['type' => 'text', 'x' => 76, 'y' => 22, 'w' => 98, 'h' => 7, 'text' => '{date} · {heure}', 'size' => 11, 'bold' => 1, 'color' => $c],
                ['type' => 'text', 'x' => 76, 'y' => 30, 'w' => 98, 'h' => 7, 'text' => '{lieu}', 'size' => 9.5, 'color' => '#c7c7cc'],
                ['type' => 'text', 'x' => 76, 'y' => 46, 'w' => 60, 'h' => 9, 'text' => '{nom}', 'size' => 14, 'bold' => 1, 'color' => '#ffffff'],
                ['type' => 'text', 'x' => 76, 'y' => 56, 'w' => 60, 'h' => 7, 'text' => '{tarif} · {siege}', 'size' => 10, 'color' => '#c7c7cc'],
                ['type' => 'rect', 'x' => 138, 'y' => 44, 'w' => 38, 'h' => 46, 'bg' => '#ffffff', 'radius' => 3],
                ['type' => 'qr', 'x' => 141, 'y' => 47, 'w' => 32, 'h' => 32],
                ['type' => 'text', 'x' => 138, 'y' => 81, 'w' => 38, 'h' => 6, 'text' => '{code}', 'size' => 8, 'bold' => 1, 'color' => '#0f0f14', 'align' => 'C', 'mono' => 1],
                ['type' => 'text', 'x' => 76, 'y' => 90, 'w' => 60, 'h' => 5, 'text' => '{type} · {ref}', 'size' => 7, 'color' => '#8e8e93'],
            ]],
            'minimal' => ['name' => 'Minimal', 'w' => 180, 'h' => 70, 'bg' => '#ffffff', 'elements' => [
                ['type' => 'text', 'x' => 8, 'y' => 8, 'w' => 120, 'h' => 10, 'text' => '{evenement}', 'size' => 16, 'bold' => 1, 'color' => '#1d1d1f'],
                ['type' => 'text', 'x' => 8, 'y' => 20, 'w' => 120, 'h' => 7, 'text' => '{date} à {heure} · {lieu}', 'size' => 10, 'color' => '#55555f'],
                ['type' => 'text', 'x' => 8, 'y' => 36, 'w' => 120, 'h' => 9, 'text' => '{nom}', 'size' => 14, 'bold' => 1, 'color' => $c],
                ['type' => 'text', 'x' => 8, 'y' => 46, 'w' => 120, 'h' => 7, 'text' => '{tarif}', 'size' => 10, 'color' => '#55555f'],
                ['type' => 'qr', 'x' => 140, 'y' => 8, 'w' => 34, 'h' => 34],
                ['type' => 'text', 'x' => 134, 'y' => 44, 'w' => 46, 'h' => 6, 'text' => '{code}', 'size' => 8, 'bold' => 1, 'color' => '#1d1d1f', 'align' => 'C', 'mono' => 1],
                ['type' => 'line', 'x' => 8, 'y' => 58, 'w' => 164, 'h' => 0.3, 'bg' => '#d2d2d7'],
                ['type' => 'text', 'x' => 8, 'y' => 61, 'w' => 164, 'h' => 5, 'text' => '{organisateur} · commande {ref}', 'size' => 7, 'color' => '#8e8e93'],
            ]],
        ];
    }

    /** Nettoie une maquette envoyée par l'éditeur. */
    public static function clean(array $in): ?array
    {
        $w = max(60, min(190, (float)($in['w'] ?? 180))); $h = max(40, min(260, (float)($in['h'] ?? 90)));
        $out = ['w' => $w, 'h' => $h, 'bg' => self::color($in['bg'] ?? '#ffffff', '#ffffff'), 'elements' => []];
        foreach (array_slice((array)($in['elements'] ?? []), 0, 60) as $e) {
            $t = (string)($e['type'] ?? '');
            if (!in_array($t, ['text', 'qr', 'poster', 'logo', 'rect', 'line'], true)) continue;
            $el = ['type' => $t, 'x' => round(max(0, min($w, (float)($e['x'] ?? 0))), 1), 'y' => round(max(0, min($h, (float)($e['y'] ?? 0))), 1),
                'w' => round(max(0.3, min($w, (float)($e['w'] ?? 20))), 1), 'h' => round(max(0.2, min($h, (float)($e['h'] ?? 10))), 1)];
            if ($t === 'text') $el += ['text' => mb_substr((string)($e['text'] ?? ''), 0, 300), 'size' => max(5, min(48, (float)($e['size'] ?? 10))), 'bold' => !empty($e['bold']) ? 1 : 0, 'mono' => !empty($e['mono']) ? 1 : 0,
                'color' => self::color($e['color'] ?? '#1d1d1f', '#1d1d1f'), 'align' => in_array($e['align'] ?? 'L', ['L', 'C', 'R'], true) ? ($e['align'] ?? 'L') : 'L'];
            if (in_array($t, ['rect', 'line'], true)) $el += ['bg' => self::color($e['bg'] ?? '#e5e5ea', '#e5e5ea'), 'radius' => max(0, min(20, (float)($e['radius'] ?? 0)))];
            $out['elements'][] = $el;
        }
        return $out['elements'] ? $out : null;
    }

    private static function color($v, string $def): string { return preg_match('~^#[0-9a-f]{6}$~i', (string)$v) ? strtolower((string)$v) : $def; }
    private static function rgb(string $hex): array { return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))]; }

    public static function vars(array $t, array $order, array $ev, array $org, int $i, int $n, ?string $start): array
    {
        $ts = strtotime((string)($start ?: $ev['starts_at']));
        $place = trim(implode(', ', array_filter([(string)$ev['venue'], (string)$ev['city']])));
        return ['{evenement}' => (string)$ev['name'], '{date}' => fr_date(date('Y-m-d H:i:s', $ts), false), '{heure}' => date('H\hi', $ts), '{lieu}' => $place, '{adresse}' => (string)($ev['address'] ?? ''),
            '{nom}' => trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')) ?: trim($order['first_name'] . ' ' . $order['last_name']), '{tarif}' => (string)($t['rate_name'] ?? ''),
            '{prix}' => (int)($t['price'] ?? 0) ? money((int)$t['price']) : 'Gratuit', '{siege}' => (string)($t['seat_label'] ?? $t['seat'] ?? '') ?: 'Placement libre', '{code}' => (string)$t['code'],
            '{ref}' => (string)$order['ref'], '{organisateur}' => (string)$org['name'], '{type}' => ['invitation' => 'INVITATION', 'accreditation' => 'ACCRÉDITATION'][$t['type'] ?? 'ticket'] ?? 'BILLET', '{numero}' => ($i + 1) . ' / ' . $n];
    }

    /** Dessine un billet de la maquette à la position ($ox, $oy) sur la page en cours. */
    public static function draw(PdfDoc $pdf, array $L, float $ox, float $oy, array $vars, ?string $poster, ?string $logo): void
    {
        $pdf->SetFillColor(...self::rgb($L['bg'])); $pdf->SetDrawColor(210, 210, 215); $pdf->SetLineWidth(0.2);
        $pdf->RoundedRect($ox, $oy, $L['w'], $L['h'], 4, 'DF');
        foreach ($L['elements'] as $e) {
            $x = $ox + $e['x']; $y = $oy + $e['y'];
            switch ($e['type']) {
                case 'rect': case 'line':
                    $pdf->SetFillColor(...self::rgb($e['bg']));
                    if (!empty($e['radius'])) $pdf->RoundedRect($x, $y, $e['w'], $e['h'], (float)$e['radius'], 'F'); else $pdf->Rect($x, $y, $e['w'], $e['h'], 'F');
                    break;
                case 'qr': Qr::drawPdf($pdf, $vars['{code}'], $x, $y, min($e['w'], $e['h'])); break;
                case 'poster': case 'logo':
                    $f = $e['type'] === 'poster' ? $poster : $logo;
                    if ($f) { try { $pdf->Image($f, $x, $y, $e['w'], $e['h']); } catch (Throwable $ex) { /* image illisible */ } }
                    else { $pdf->SetFillColor(235, 235, 240); $pdf->Rect($x, $y, $e['w'], $e['h'], 'F'); }
                    break;
                case 'text':
                    $txt = PdfDoc::t(strtr((string)$e['text'], $vars));
                    $pdf->SetFont($e['mono'] ? 'Courier' : 'Helvetica', $e['bold'] ? 'B' : '', (float)$e['size']);
                    $pdf->SetTextColor(...self::rgb($e['color']));
                    while ($txt !== '' && $pdf->GetStringWidth($txt) > $e['w'] && strlen($txt) > 3) $txt = substr($txt, 0, -4) . '...';
                    $pdf->SetXY($x, $y); $pdf->Cell($e['w'], $e['h'], $txt, 0, 0, $e['align']);
                    break;
            }
        }
    }

    /** PDF complet : un billet par page, la maquette en haut, les infos pratiques en dessous. */
    public static function build(array $L, array $order, array $ev, array $org, array $tickets): string
    {
        $pdf = new PdfDoc('P', 'mm', 'A4'); $pdf->SetAutoPageBreak(false); $pdf->SetTitle(PdfDoc::t('Billets ' . $ev['name']));
        $img = fn(?string $p) => $p && preg_match('~\.(jpe?g|png)$~i', $p) && is_file(ROOT . '/' . $p) ? ROOT . '/' . $p : null;
        $poster = $img($ev['image'] ?? null); $logo = $img($ev['ticket_logo'] ?? null) ?: $img($org['logo'] ?? null);
        $map = Seating::map($ev); $n = count($tickets);
        foreach (array_values($tickets) as $i => $t) {
            $pdf->AddPage();
            $start = !empty($t['session_id']) ? val('SELECT starts_at FROM sessions WHERE id = ?', [$t['session_id']]) : null;
            if (!empty($t['seat']) && $map) $t['seat_label'] = Seating::label((string)$t['seat'], $map);
            $ox = (210 - $L['w']) / 2;
            self::draw($pdf, $L, $ox, 18, self::vars($t, $order, $ev, $org, $i, $n, $start ?: null), TicketPdf::ratePoster($t) ?? $poster, $logo);
            $y = 18 + $L['h'] + 10;
            $pdf->SetTextColor(60, 60, 70); $pdf->SetFont('Helvetica', 'B', 10); $pdf->SetXY($ox, $y); $pdf->Cell($L['w'], 6, PdfDoc::t('Bon à savoir'));
            $pdf->SetFont('Helvetica', '', 9); $pdf->SetXY($ox, $y + 7);
            $info = trim((string)($ev['ticket_info'] ?? '')) ?: 'Présente ce billet (imprimé ou sur ton téléphone) à l\'entrée. Chaque QR code n\'est valable qu\'une seule fois : ne le partage pas.';
            $pdf->MultiCell($L['w'], 4.8, PdfDoc::t($info));
            $pdf->SetFont('Helvetica', '', 7.5); $pdf->SetTextColor(140, 140, 150); $pdf->SetXY($ox, 285);
            $pdf->Cell($L['w'], 5, PdfDoc::t('Billet ' . ($i + 1) . '/' . $n . ' · ' . $org['name'] . ' · ' . setting('platform_name')), 0, 0, 'C');
        }
        return $pdf->Output('S');
    }
}
