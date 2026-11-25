<?php
declare(strict_types=1);

/* 2.3 : artistes & planning — fiches, passages par scène, feuille de route, espace artiste. */

require_once APP . '/controllers/pro.php';

const ARTIST_STATUS = ['option' => ['En option', 'orange'], 'confirmed' => ['Confirmé', 'green'], 'cancelled' => ['Annulé', 'grey']];
const FEE_STATUS = ['todo' => 'À payer', 'deposit' => 'Acompte versé', 'paid' => 'Payé', 'none' => 'Sans cachet'];

function artists_choose(): never
{
    $org = require_org();
    require_module('artistes');
    $ev = one("SELECT id FROM events WHERE organizer_id = ? AND COALESCE(ends_at, starts_at) >= NOW() ORDER BY starts_at LIMIT 1", [$org['id']])
        ?: one('SELECT id FROM events WHERE organizer_id = ? ORDER BY starts_at DESC LIMIT 1', [$org['id']]);
    if (!$ev) redirect('/pro/evenements', ['info' => 'Crée d\'abord un événement.']);
    redirect('/pro/evenements/' . $ev['id'] . '/artistes');
}

function artists_tab(string $id): string
{
    $org = require_org();
    require_module('artistes');
    $ev = org_event((int)$id);
    if (is_post()) {
        $a = (string)input('action');
        $aid = (int)input('artist_id');
        $art = $aid ? one('SELECT * FROM artists WHERE id = ? AND event_id = ?', [$aid, $ev['id']]) : null;
        if ($a === 'delete_perf') { q('DELETE p FROM performances p WHERE p.id = ? AND p.event_id = ?', [(int)input('perf_id'), $ev['id']]); back(['success' => 'Passage supprimé.']); }
        if ($a === 'delete' && $art) { q('DELETE FROM performances WHERE artist_id = ?', [$art['id']]); q('DELETE FROM artists WHERE id = ?', [$art['id']]); back(['success' => 'Artiste supprimé.']); }
        if ($a === 'send' && $art) {
            if (!is_email((string)$art['email'])) back(['error' => 'Ajoute d\'abord l\'e-mail de l\'artiste (ou de son tourneur).']);
            try { Mailer::send((string)$art['email'], 'Ta feuille de route · ' . $ev['name'], '<p>Bonjour,</p><p>Voici toutes les infos pour <b>' . e($art['name']) . '</b> à <b>' . e($ev['name']) . '</b> : horaires d\'arrivée, de balance et de passage, accès, accueil.</p>'
                . '<p><a href="' . e(abs_url('/artiste/' . $art['token'])) . '" style="display:inline-block;background:#1d1d1f;color:#fff;padding:12px 22px;border-radius:980px;text-decoration:none">Ouvrir ma feuille de route</a></p><p>Elle se met à jour toute seule si un horaire change.</p>', [], $org['email'] ?? null); }
            catch (Throwable $e) { back(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
            back(['success' => 'Feuille de route envoyée à ' . $art['email'] . '.']);
        }
        if ($a === 'publish') {
            $prog = [];
            foreach (all("SELECT p.*, a.name, a.kind FROM performances p JOIN artists a ON a.id = p.artist_id WHERE p.event_id = ? AND a.status = 'confirmed' ORDER BY p.starts_at", [$ev['id']]) as $p)
                $prog[] = ['when' => date('d/m H\hi', strtotime($p['starts_at'])), 'title' => $p['name'], 'text' => trim($p['stage'] . ($p['kind'] ? ' · ' . $p['kind'] : ''))];
            update('events', ['program' => $prog ? json_encode($prog, JSON_UNESCAPED_UNICODE) : null], 'id = ?', [$ev['id']]);
            back(['success' => count($prog) . ' passage(s) publiés dans le programme de la page publique.']);
        }
        if ($a === 'perf') {
            $s = dt_from_input(input('starts_at')); $en = dt_from_input(input('ends_at'));
            if (!$art || !$s || !$en || $en <= $s) back(['error' => 'Indique un début et une fin de passage cohérents.']);
            $stage = mb_substr(trim((string)input('stage')) ?: 'Scène principale', 0, 80);
            $clash = one('SELECT p.*, a.name FROM performances p JOIN artists a ON a.id = p.artist_id WHERE p.event_id = ? AND p.stage = ? AND p.starts_at < ? AND p.ends_at > ? AND p.id <> ? AND a.status <> \'cancelled\' LIMIT 1', [$ev['id'], $stage, $en, $s, (int)input('perf_id')]);
            $d = ['artist_id' => $art['id'], 'event_id' => $ev['id'], 'stage' => $stage, 'starts_at' => $s, 'ends_at' => $en, 'arrival_at' => dt_from_input(input('arrival_at')), 'soundcheck_at' => dt_from_input(input('soundcheck_at')), 'notes' => mb_substr(trim((string)input('notes')), 0, 500) ?: null];
            if ((int)input('perf_id')) update('performances', $d, 'id = ? AND event_id = ?', [(int)input('perf_id'), $ev['id']]); else insert('performances', $d);
            back($clash ? ['warning' => 'Passage enregistré, mais il chevauche « ' . $clash['name'] . ' » sur ' . $stage . ' (' . date('H\hi', strtotime($clash['starts_at'])) . '–' . date('H\hi', strtotime($clash['ends_at'])) . ').'] : ['success' => 'Passage enregistré.']);
        }
        // fiche artiste
        $d = ['name' => mb_substr(trim((string)input('name')), 0, 190), 'kind' => mb_substr(trim((string)input('kind')), 0, 40) ?: null, 'contact_name' => trim((string)input('contact_name')) ?: null,
            'email' => is_email(strtolower(trim((string)input('email')))) ? strtolower(trim((string)input('email'))) : null, 'phone' => trim((string)input('phone')) ?: null,
            'fee' => parse_money(input('fee')), 'fee_status' => array_key_exists((string)input('fee_status'), FEE_STATUS) ? (string)input('fee_status') : 'todo', 'people' => max(1, min(200, (int)input('people', 1))),
            'rider' => trim((string)input('rider')) ?: null, 'hospitality' => trim((string)input('hospitality')) ?: null, 'notes' => trim((string)input('notes')) ?: null,
            'status' => array_key_exists((string)input('status'), ARTIST_STATUS) ? (string)input('status') : 'option'];
        if ($d['name'] === '') back(['error' => 'Donne un nom à l\'artiste.']);
        if ($art) update('artists', $d, 'id = ?', [$art['id']]);
        else { $aid = insert('artists', $d + ['organizer_id' => $org['id'], 'event_id' => $ev['id'], 'token' => token(24), 'created_at' => now()]); }
        redirect('/pro/evenements/' . $ev['id'] . '/artistes?edit=' . ($art['id'] ?? $aid) . '#fiche', ['success' => 'Fiche enregistrée.']);
    }
    if (input('pdf')) { header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="feuille-de-route-' . $ev['slug'] . '.pdf"'); echo artists_runsheet_pdf($ev, $org); exit; }
    $artists = all('SELECT a.*, (SELECT MIN(starts_at) FROM performances p WHERE p.artist_id = a.id) first_set FROM artists a WHERE a.event_id = ? ORDER BY a.status = \'cancelled\', first_set IS NULL, first_set, a.name', [$ev['id']]);
    $perfs = all("SELECT p.*, a.name, a.status FROM performances p JOIN artists a ON a.id = p.artist_id WHERE p.event_id = ? ORDER BY p.stage, p.starts_at", [$ev['id']]);
    $edit = input('edit') === 'new' ? [] : (input('edit') ? one('SELECT * FROM artists WHERE id = ? AND event_id = ?', [(int)input('edit'), $ev['id']]) : null);
    $k = one("SELECT SUM(status = 'confirmed') confirmed, SUM(status = 'option') options, COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN fee END), 0) fees,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' AND fee_status = 'paid' THEN fee END), 0) paid, COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN people END), 0) people FROM artists WHERE event_id = ?", [$ev['id']]);
    return ev_page($ev, 'artists', 'Artistes & planning', 'artists', compact('artists', 'perfs', 'edit', 'k', 'org'));
}

/** Feuille de route de l'événement (tous les passages, par scène). */
function artists_runsheet_pdf(array $ev, array $org, ?int $onlyArtist = null): string
{
    $t = fn($s) => PdfDoc::t((string)$s);
    $rows = all("SELECT p.*, a.name, a.people, a.contact_name, a.phone FROM performances p JOIN artists a ON a.id = p.artist_id WHERE p.event_id = ? AND a.status <> 'cancelled'" . ($onlyArtist ? ' AND a.id = ' . (int)$onlyArtist : '') . ' ORDER BY p.stage, p.starts_at', [$ev['id']]);
    $pdf = new PdfDoc('P', 'mm', 'A4'); $pdf->SetMargins(16, 16, 16); $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 18); $pdf->Cell(0, 9, $t('Feuille de route'), 0, 1);
    $pdf->SetFont('Helvetica', '', 10); $pdf->SetTextColor(110, 110, 115);
    $pdf->Cell(0, 5, $t($ev['name'] . ' - ' . fr_date($ev['starts_at']) . ($ev['venue'] ? ' - ' . $ev['venue'] : '') . ($ev['address'] ?? '' ? ', ' . $ev['address'] : '')), 0, 1);
    $pdf->Cell(0, 5, $t('Contact organisation : ' . $org['name'] . ($org['phone'] ? ' - ' . $org['phone'] : '') . ' - édité le ' . date('d/m/Y H:i')), 0, 1);
    $pdf->SetTextColor(29, 29, 31); $stage = null;
    $w = [24, 24, 30, 60, 44];
    foreach ($rows as $r) {
        if ($r['stage'] !== $stage) {
            $stage = $r['stage']; $pdf->Ln(5); $pdf->SetFont('Helvetica', 'B', 12); $pdf->Cell(0, 7, $t($stage), 0, 1);
            $pdf->SetFillColor(29, 29, 31); $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 8.5);
            foreach (['Arrivée', 'Balance', 'Passage', 'Artiste', 'Contact'] as $i => $h) $pdf->Cell($w[$i], 6.5, $t($h), 0, 0, 'L', true);
            $pdf->Ln(); $pdf->SetTextColor(29, 29, 31);
        }
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell($w[0], 7, $r['arrival_at'] ? date('H\hi', strtotime($r['arrival_at'])) : '-', 'B');
        $pdf->Cell($w[1], 7, $r['soundcheck_at'] ? date('H\hi', strtotime($r['soundcheck_at'])) : '-', 'B');
        $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell($w[2], 7, date('H\hi', strtotime($r['starts_at'])) . ' - ' . date('H\hi', strtotime($r['ends_at'])), 'B');
        $pdf->Cell($w[3], 7, $t(mb_strimwidth($r['name'] . ((int)$r['people'] > 1 ? ' (' . (int)$r['people'] . ' pers.)' : ''), 0, 38, '…')), 'B');
        $pdf->SetFont('Helvetica', '', 8.5); $pdf->Cell($w[4], 7, $t(mb_strimwidth(trim(($r['contact_name'] ?? '') . ' ' . ($r['phone'] ?? '')), 0, 30, '…')), 'B', 1);
        if ($r['notes']) { $pdf->SetFont('Helvetica', 'I', 8); $pdf->SetTextColor(110, 110, 115); $pdf->Cell(0, 5, $t('   ' . $r['notes']), 0, 1); $pdf->SetTextColor(29, 29, 31); }
    }
    if (!$rows) { $pdf->Ln(6); $pdf->Cell(0, 6, $t('Aucun passage programmé pour l\'instant.'), 0, 1); }
    return $pdf->Output('S');
}

/** Espace artiste (lien secret). */
function artist_portal(string $token): string
{
    $a = one('SELECT * FROM artists WHERE token = ?', [$token]);
    if (!$a || strlen($token) < 20 || $a['status'] === 'cancelled') abort(404);
    $ev = one('SELECT * FROM events WHERE id = ?', [$a['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$a['organizer_id']]);
    if (input('pdf')) { header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="feuille-de-route.pdf"'); echo artists_runsheet_pdf($ev, $org, (int)$a['id']); exit; }
    $perfs = all('SELECT * FROM performances WHERE artist_id = ? ORDER BY starts_at', [$a['id']]);
    return pub('artist_portal', compact('a', 'ev', 'org', 'perfs'), $a['name'] . ' · ' . $ev['name']);
}
