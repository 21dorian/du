<?php
declare(strict_types=1);

/* 4.2 : bordereau de recettes (déclarations SACEM, SACD, CNV / taxe sur les spectacles) — billetterie hors frais, par tarif, avec la TVA. */

require_once APP . '/controllers/pro.php';

function receipts_data(array $ev, array $org): array
{
    $def = $org['vat_rate'] !== null && $org['vat_rate'] !== '' ? (float)$org['vat_rate'] : 0.0;
    $rows = [];
    foreach (all("SELECT r.id, r.name, r.vat_rate, i.unit_price, SUM(i.qty * GREATEST(1, r.pack_size)) n, SUM(i.qty * i.unit_price) gross,
            SUM(CASE WHEN o.subtotal > 0 THEN ROUND(o.discount * i.qty * i.unit_price / o.subtotal) ELSE 0 END) disc, SUM(CASE WHEN o.total > 0 THEN ROUND(o.refunded_amount * i.qty * i.unit_price / o.total) ELSE 0 END) refd,
            o.method m FROM order_items i JOIN orders o ON o.id = i.order_id JOIN rates r ON r.id = i.rate_id
            WHERE o.event_id = ? AND o.status IN ('paid','refunded') AND i.kind = 'rate' GROUP BY r.id, i.unit_price, (o.method IN ('invitation','partner','import','pass')) ORDER BY r.sort, r.id, i.unit_price DESC", [$ev['id']]) as $x) {
        $vat = $x['vat_rate'] !== null && $x['vat_rate'] !== '' ? (float)$x['vat_rate'] : $def;
        $net = (int)$x['gross'] - (int)$x['disc'] - (int)$x['refd'];
        $free = (int)$x['unit_price'] === 0 || in_array($x['m'], ['invitation', 'partner', 'import', 'pass'], true);
        $rows[] = ['name' => $x['name'], 'unit' => (int)$x['unit_price'], 'n' => (int)$x['n'], 'free' => $free, 'gross' => (int)$x['gross'], 'disc' => (int)$x['disc'], 'refd' => (int)$x['refd'], 'net' => $net, 'vat' => $vat, 'ht' => (int)round($net / (1 + $vat / 100))];
    }
    $fees = (int)val("SELECT COALESCE(SUM(i.qty * i.unit_price), 0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.event_id = ? AND o.status = 'paid' AND i.kind = 'fee'", [$ev['id']]);
    $other = one("SELECT COALESCE(SUM(fees), 0) svc, COALESCE(SUM(donation), 0) don FROM orders WHERE event_id = ? AND status = 'paid'", [$ev['id']]);
    $prod = (int)val("SELECT COALESCE(SUM(i.qty * i.unit_price), 0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.event_id = ? AND o.status = 'paid' AND i.kind = 'product'", [$ev['id']]);
    $t = ['paid' => 0, 'free' => 0, 'net' => 0, 'ht' => 0, 'vat' => 0];
    foreach ($rows as $r) { if ($r['free']) $t['free'] += $r['n']; else { $t['paid'] += $r['n']; $t['net'] += $r['net']; $t['ht'] += $r['ht']; $t['vat'] += $r['net'] - $r['ht']; } }
    $came = (int)val("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status = 'valid' AND entries > 0", [$ev['id']]);
    return compact('rows', 'fees', 'other', 'prod', 't', 'came');
}

function receipts_statement(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    $D = receipts_data($ev, $org);
    if (input('format') === 'csv') {
        $out = [['Tarif', 'Prix unitaire TTC', 'Billets', 'Exonéré', 'Recette brute TTC', 'Remises', 'Remboursements', 'Recette nette TTC', 'TVA %', 'Recette HT']];
        foreach ($D['rows'] as $r) $out[] = [$r['name'], money_input($r['unit']), $r['n'], $r['free'] ? 'oui' : 'non', money_input($r['gross']), money_input($r['disc']), money_input($r['refd']), money_input($r['net']), $r['vat'], money_input($r['ht'])];
        $out[] = ['Total billetterie', '', $D['t']['paid'] + $D['t']['free'], '', '', '', '', money_input($D['t']['net']), '', money_input($D['t']['ht'])];
        $out[] = ['Frais par billet (hors billetterie)', '', '', '', '', '', '', money_input($D['fees']), '', ''];
        $out[] = ['Frais de service plateforme', '', '', '', '', '', '', money_input((int)$D['other']['svc']), '', ''];
        csv_download('bordereau-recettes-' . $ev['slug'] . '.csv', $out);
    }
    if (input('format') === 'pdf') receipts_pdf($ev, $org, $D);
    return ev_page($ev, 'receipts', 'Bordereau de recettes', 'receipts', ['D' => $D]);
}

function receipts_pdf(array $ev, array $org, array $D): never
{
    $t = fn($s) => PdfDoc::t((string)$s);
    $pdf = new PdfDoc('L', 'mm', 'A4'); $pdf->SetMargins(14, 14, 14); $pdf->SetAutoPageBreak(true, 14); $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 17); $pdf->Cell(0, 9, $t('Bordereau de recettes'), 0, 1);
    $pdf->SetFont('Helvetica', '', 10); $pdf->SetTextColor(90, 90, 100);
    $pdf->MultiCell(0, 5, $t(($org['legal_name'] ?: $org['name']) . ($org['siret'] ? ' · SIRET ' . $org['siret'] : '') . "\n" . $ev['name'] . ' · ' . fr_date($ev['starts_at']) . ($ev['venue'] ? ' · ' . $ev['venue'] . ($ev['city'] ? ', ' . $ev['city'] : '') : '') . "\nÉdité le " . date('d/m/Y H:i')));
    $pdf->Ln(3); $pdf->SetTextColor(20, 20, 25);
    $w = [70, 26, 18, 18, 28, 22, 26, 30, 14, 26]; $h = ['Tarif', 'Prix TTC', 'Billets', 'Exonéré', 'Brut TTC', 'Remises', 'Rembours.', 'Net TTC', 'TVA', 'Net HT'];
    $pdf->SetFont('Helvetica', 'B', 8.5); $pdf->SetFillColor(240, 240, 244);
    foreach ($h as $i => $x) $pdf->Cell($w[$i], 7, $t($x), 0, 0, $i ? 'R' : 'L', true); $pdf->Ln();
    $pdf->SetFont('Helvetica', '', 9);
    foreach ($D['rows'] as $r) {
        foreach ([$r['name'], money($r['unit']), $r['n'], $r['free'] ? 'oui' : '', money($r['gross']), money($r['disc']), money($r['refd']), money($r['net']), rtrim(rtrim(number_format($r['vat'], 2, ',', ''), '0'), ',') . ' %', money($r['ht'])] as $i => $c) $pdf->Cell($w[$i], 6.5, $t(mb_strimwidth((string)$c, 0, $i ? 20 : 42, '...')), 'B', 0, $i ? 'R' : 'L');
        $pdf->Ln();
    }
    $pdf->SetFont('Helvetica', 'B', 9.5);
    foreach (['Total', '', $D['t']['paid'] + $D['t']['free'], $D['t']['free'], '', '', '', money($D['t']['net']), '', money($D['t']['ht'])] as $i => $c) $pdf->Cell($w[$i], 7, $t((string)$c), 0, 0, $i ? 'R' : 'L');
    $pdf->Ln(12);
    $pdf->SetFont('Helvetica', 'B', 11); $pdf->Cell(0, 7, $t('Synthèse pour les déclarations'), 0, 1); $pdf->SetFont('Helvetica', '', 10);
    foreach ([['Entrées payantes', (string)$D['t']['paid']], ['Entrées exonérées (invitations, partenaires…)', (string)$D['t']['free']], ['Présents (billets scannés)', (string)$D['came']],
        ['Recette billetterie nette TTC (hors frais)', money($D['t']['net'])], ['dont TVA', money($D['t']['vat'])], ['Recette billetterie nette HT', money($D['t']['ht'])],
        ['Taxe sur les spectacles (CNV / CNM, variétés 3,5 % du HT) — indicatif', money((int)round($D['t']['ht'] * 0.035))],
        ['Frais par billet encaissés (hors assiette billetterie)', money($D['fees'])], ['Frais de service de la plateforme', money((int)$D['other']['svc'])], ['Dons', money((int)$D['other']['don'])], ['Options et produits', money($D['prod'])]] as [$l, $v]) {
        $pdf->Cell(150, 6, $t($l)); $pdf->Cell(40, 6, $t($v), 0, 1, 'R');
    }
    $pdf->Ln(4); $pdf->SetFont('Helvetica', '', 8); $pdf->SetTextColor(120, 120, 130);
    $pdf->MultiCell(0, 4.2, $t('Les remises et remboursements sont répartis au prorata des billets de chaque commande. Les frais par billet et les frais de service ne font pas partie de la recette de billetterie déclarée. Le taux de la taxe sur les spectacles dépend du genre (variétés, théâtre…) : vérifie-le auprès de l\'organisme concerné.'));
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="bordereau-' . $ev['slug'] . '.pdf"');
    echo $pdf->Output('S'); exit;
}

/** 4.2 : pièce jointe d'un formulaire d'inscription (réservée à l'organisateur de la commande). */
function form_file(string $tok): never
{
    $org = require_org();
    if (!preg_match('~^[a-f0-9]{24}\.(jpe?g|png|gif|pdf|docx?)$~', $tok)) abort(404);
    $like = '%' . $tok . '%';
    $ok = val('SELECT o.id FROM orders o WHERE o.organizer_id = ? AND (o.answers LIKE ? OR EXISTS (SELECT 1 FROM tickets t WHERE t.order_id = o.id AND t.answers LIKE ?)) LIMIT 1', [$org['id'], $like, $like]);
    $f = STORAGE . '/formulaires/' . $tok;
    if (!$ok || !is_file($f)) abort(404);
    $mime = ['pdf' => 'application/pdf', 'png' => 'image/png', 'gif' => 'image/gif', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'][strtolower(pathinfo($tok, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime); header('Content-Disposition: inline; filename="' . $tok . '"'); header('X-Content-Type-Options: nosniff');
    readfile($f); exit;
}
