<?php
declare(strict_types=1);

/**
 * 2.5 : bilans d'activité (dossiers de subvention, conseils d'administration, partenaires).
 * Les chiffres sont recalculés à chaque ouverture à partir des ventes, des scans, du bénévolat, des artistes et du budget ;
 * les textes (présentation, objectifs, actions…) sont rédigés par l'organisateur et enregistrés avec le bilan.
 */
final class Reports
{
    public const SECTIONS = [
        'presentation' => ['Présentation du projet', 'Ta structure, l\'événement, son histoire en quelques lignes.'],
        'objectifs' => ['Objectifs', 'Ce que tu voulais atteindre : publics, territoire, création, médiation…'],
        'actions' => ['Actions menées', 'Programmation, ateliers, actions vers les scolaires ou les publics éloignés…'],
        'publics' => ['Publics touchés', 'Ton analyse des chiffres ci-dessus : qui est venu, d\'où, et ce que ça dit.'],
        'partenaires' => ['Partenaires', 'Collectivités, mécènes, structures associées.'],
        'perspectives' => ['Bilan et perspectives', 'Ce qui a marché, ce qui est à améliorer, la suite.'],
    ];
    /** Tarifs considérés comme « accès facilité » (en plus des tarifs moins chers que le plein tarif). */
    private const REDUCED = '~r[ée]duit|[ée]tudiant|jeune|enfant|solidaire|demandeur|ch[ôo]meur|rsa|minima|senior|handicap|pmr|famille|scolaire|gratuit|adh[ée]rent|abonn[ée]~iu';

    public static function sections(array $r): array
    {
        $j = json_decode((string)($r['sections_json'] ?? ''), true);
        return is_array($j) ? $j : [];
    }

    public static function eventIds(array $r): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string)$r['event_ids']))));
    }

    /** Événements d'une période (pour préremplir un bilan de saison). */
    public static function eventsBetween(int $orgId, string $from, string $to): array
    {
        return array_map('intval', array_column(all("SELECT id FROM events WHERE organizer_id = ? AND status <> 'draft' AND starts_at >= ? AND starts_at < ? + INTERVAL 1 DAY ORDER BY starts_at", [$orgId, $from, $to]), 'id'));
    }

    public static function compute(int $orgId, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $out = ['events' => [], 'k' => [], 'rates' => [], 'channels' => [], 'ages' => [], 'places' => [], 'budget' => [], 'staff' => [], 'artists' => [], 'months' => []];
        if (!$ids) return $out;
        $in = implode(',', $ids);
        $out['events'] = all("SELECT e.id, e.name, e.starts_at, e.ends_at, e.venue, e.city, e.capacity,
                (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid') issued,
                (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid' AND t.checked_in_at IS NOT NULL) present,
                (SELECT COALESCE(SUM(capacity), 0) FROM sessions s WHERE s.event_id = e.id) sess_cap, (SELECT COUNT(*) FROM sessions s WHERE s.event_id = e.id) sess_n
            FROM events e WHERE e.organizer_id = ? AND e.id IN ($in) ORDER BY e.starts_at", [$orgId]);
        $ids = array_map('intval', array_column($out['events'], 'id'));
        if (!$ids) return $out;
        $in = implode(',', $ids);

        // fréquentation
        $t = one("SELECT COUNT(*) issued, SUM(t.checked_in_at IS NOT NULL) present, SUM(o.method = 'invitation' OR o.method = 'accreditation') invited,
                SUM(t.price = 0 AND o.method NOT IN ('invitation','accreditation')) free_paid, COUNT(DISTINCT LOWER(o.email)) buyers
            FROM tickets t JOIN orders o ON o.id = t.order_id WHERE t.event_id IN ($in) AND t.status = 'valid'");
        $cap = 0; $sessions = 0;
        foreach ($out['events'] as $e) { $cap += (int)$e['sess_n'] ? (int)$e['sess_cap'] : (int)$e['capacity']; $sessions += max(1, (int)$e['sess_n']); }

        // recettes
        $m = one("SELECT COUNT(*) n, COALESCE(SUM(total), 0) gross, COALESCE(SUM(refunded_amount), 0) refunded, COALESCE(SUM(donation), 0) donations, COALESCE(SUM(CASE WHEN fee_mode = 'buyer' THEN fees ELSE 0 END), 0) buyer_fees,
                COALESCE(SUM(CASE WHEN fee_mode <> 'buyer' THEN commission ELSE 0 END), 0) org_fees, COALESCE(SUM(discount), 0) discounts
            FROM orders WHERE event_id IN ($in) AND status IN ('paid','refunded') AND organizer_id = ?", [$orgId]);
        $net = (int)$m['gross'] - (int)$m['refunded'] - (int)$m['buyer_fees'] - (int)$m['org_fees'];
        $paidTickets = max(1, (int)$t['issued'] - (int)$t['invited']);

        // tarifs (accès facilité : tarif moins cher que le plein tarif de l'événement, ou intitulé explicite)
        // prix de référence : le tarif nommé « plein », « normal » ou « standard » ; sinon seul l'intitulé du tarif compte
        $max = [];
        foreach (all("SELECT event_id, MAX(price) p FROM rates WHERE event_id IN ($in) AND name REGEXP 'plein|normal|standard|tarif unique' AND pack_size <= 1 GROUP BY event_id") as $x) $max[(int)$x['event_id']] = (int)$x['p'];
        $reduced = 0;
        foreach (all("SELECT t.event_id, t.rate_name name, t.price, o.method, COUNT(*) n, SUM(t.price) amount FROM tickets t JOIN orders o ON o.id = t.order_id
                WHERE t.event_id IN ($in) AND t.status = 'valid' GROUP BY t.event_id, t.rate_name, t.price, o.method ORDER BY n DESC") as $x) {
            $inv = in_array($x['method'], ['invitation', 'accreditation'], true);
            $key = $inv ? 'Invitations' : (string)$x['name'];
            $isRed = !$inv && ((int)$x['price'] < ($max[(int)$x['event_id']] ?? 0) || preg_match(self::REDUCED, (string)$x['name']));
            $out['rates'][$key] ??= ['name' => $key, 'n' => 0, 'amount' => 0, 'reduced' => $isRed, 'invite' => $inv];
            $out['rates'][$key]['n'] += (int)$x['n']; $out['rates'][$key]['amount'] += (int)$x['amount'];
            if ($isRed) $reduced += (int)$x['n'];
        }
        uasort($out['rates'], fn($a, $b) => $b['n'] <=> $a['n']);
        $out['rates'] = array_values($out['rates']);

        // canaux de vente
        $labels = ['online' => 'Billetterie en ligne', 'guichet' => 'Guichet', 'partner' => 'Partenaires', 'invitation' => 'Invitations', 'accreditation' => 'Accréditations', 'import' => 'Import', 'pass' => 'Abonnements'];
        foreach (all("SELECT o.method, o.channel, COUNT(*) n FROM tickets t JOIN orders o ON o.id = t.order_id WHERE t.event_id IN ($in) AND t.status = 'valid' GROUP BY o.method, o.channel") as $x) {
            $k = $x['channel'] === 'revendeur' ? 'Revendeurs' : ($labels[$x['method']] ?? ucfirst((string)$x['method']));
            $out['channels'][$k] = ($out['channels'][$k] ?? 0) + (int)$x['n'];
        }
        arsort($out['channels']);

        // nouveaux / fidèles : déjà acheteur chez cet organisateur avant le premier événement du bilan
        $first = (string)($out['events'][0]['starts_at'] ?? date('Y-m-d'));
        $emails = array_column(all("SELECT DISTINCT LOWER(email) e FROM orders WHERE event_id IN ($in) AND status = 'paid' AND email <> ''"), 'e');
        $loyal = 0;
        foreach (array_chunk($emails, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $loyal += (int)val("SELECT COUNT(DISTINCT LOWER(email)) FROM orders WHERE organizer_id = ? AND status = 'paid' AND created_at < ? AND event_id NOT IN ($in) AND LOWER(email) IN ($ph)", array_merge([$orgId, $first], $chunk));
        }

        // âges (date de naissance demandée pour les événements avec restriction d'âge)
        $brackets = ['Moins de 18 ans' => [0, 17], '18-25 ans' => [18, 25], '26-35 ans' => [26, 35], '36-50 ans' => [36, 50], '51-65 ans' => [51, 65], 'Plus de 65 ans' => [66, 200]];
        foreach (all("SELECT TIMESTAMPDIFF(YEAR, o.birth_date, e.starts_at) age FROM orders o JOIN events e ON e.id = o.event_id WHERE o.event_id IN ($in) AND o.status = 'paid' AND o.birth_date IS NOT NULL") as $x)
            foreach ($brackets as $l => [$a, $b]) if ((int)$x['age'] >= $a && (int)$x['age'] <= $b) { $out['ages'][$l] = ($out['ages'][$l] ?? 0) + 1; break; }

        // provenance : questions « code postal », « ville » ou « commune » du formulaire
        $fields = all("SELECT id, per_ticket FROM form_fields WHERE event_id IN ($in) AND label REGEXP 'postal|ville|commune|provenance'");
        if ($fields) {
            $byId = array_column($fields, 'per_ticket', 'id');
            foreach (all("SELECT o.answers oa, t.answers ta FROM tickets t JOIN orders o ON o.id = t.order_id WHERE t.event_id IN ($in) AND t.status = 'valid'") as $x) {
                $vals = [];
                foreach ([json_decode((string)$x['ta'], true), json_decode((string)$x['oa'], true)['order'] ?? []] as $set)
                    foreach ((array)$set as $fid => $v) if (isset($byId[$fid]) && is_scalar($v) && trim((string)$v) !== '') $vals[] = trim((string)$v);
                if (!$vals) continue;
                $v = $vals[0];
                $key = preg_match('~^\d{5}$~', $v) ? substr($v, 0, 2) === '97' ? 'Outre-mer (' . substr($v, 0, 3) . ')' : 'Département ' . substr($v, 0, 2) : mb_convert_case(mb_strtolower($v), MB_CASE_TITLE);
                $out['places'][$key] = ($out['places'][$key] ?? 0) + 1;
            }
            arsort($out['places']);
            $out['places'] = array_slice($out['places'], 0, 12, true);
        }

        // bénévolat
        // durée réelle si l'arrivée et le départ ont été pointés de façon plausible, sinon durée du créneau
        $people = []; $shifts = 0; $minutes = 0;
        foreach (all("SELECT a.member_id, a.checked_in_at ci, a.checked_out_at co, s.starts_at, s.ends_at FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id
                WHERE s.event_id IN ($in) AND a.status IN ('confirmed','present','done')") as $x) {
            $plan = max(0, (strtotime($x['ends_at']) - strtotime($x['starts_at'])) / 60);
            $real = $x['ci'] && $x['co'] ? (strtotime($x['co']) - strtotime($x['ci'])) / 60 : -1;
            $minutes += ($real > 0 && $real <= 16 * 60) ? $real : min($plan, 16 * 60);
            $people[$x['member_id']] = true; $shifts++;
        }
        $s = ['people' => count($people), 'shifts' => $shifts, 'minutes' => $minutes];
        $out['staff'] = ['people' => (int)$s['people'], 'shifts' => (int)$s['shifts'], 'hours' => round((int)$s['minutes'] / 60, 1)];

        // artistes
        $a = one("SELECT COUNT(DISTINCT a.id) n, COALESCE(SUM(a.fee), 0) fees, COALESCE(SUM(a.people), 0) people FROM artists a WHERE a.organizer_id = ? AND a.event_id IN ($in) AND a.status <> 'cancelled'", [$orgId]);
        $out['artists'] = ['n' => (int)$a['n'], 'fees' => (int)$a['fees'], 'people' => (int)$a['people'], 'shows' => (int)val("SELECT COUNT(*) FROM performances WHERE event_id IN ($in)")];

        // budget prévu / réalisé
        foreach (all("SELECT kind, category, SUM(planned) planned, SUM(COALESCE(actual, 0)) actual, SUM(actual IS NOT NULL) has_actual FROM budget_lines WHERE event_id IN ($in) GROUP BY kind, category ORDER BY kind, category") as $x)
            $out['budget'][$x['kind'] === 'income' ? 'income' : 'expense'][] = ['category' => $x['category'], 'planned' => (int)$x['planned'], 'actual' => (int)$x['has_actual'] ? (int)$x['actual'] : null];

        // ventes par mois (rythme)
        foreach (all("SELECT DATE_FORMAT(o.paid_at, '%Y-%m') m, COUNT(*) n FROM tickets t JOIN orders o ON o.id = t.order_id WHERE t.event_id IN ($in) AND t.status = 'valid' AND o.paid_at IS NOT NULL GROUP BY m ORDER BY m") as $x) $out['months'][$x['m']] = (int)$x['n'];

        $out['k'] = [
            'events' => count($out['events']), 'sessions' => $sessions, 'capacity' => $cap,
            'issued' => (int)$t['issued'], 'present' => (int)$t['present'], 'invited' => (int)$t['invited'],
            'fill' => $cap ? round((int)$t['issued'] / $cap * 100) : null, 'show' => (int)$t['issued'] ? round((int)$t['present'] / (int)$t['issued'] * 100) : null,
            'reduced' => $reduced, 'reduced_pct' => (int)$t['issued'] ? round($reduced / (int)$t['issued'] * 100) : 0,
            'buyers' => (int)$t['buyers'], 'loyal' => $loyal, 'new' => max(0, count($emails) - $loyal),
            'gross' => (int)$m['gross'], 'refunded' => (int)$m['refunded'], 'net' => $net, 'donations' => (int)$m['donations'], 'fees' => (int)$m['buyer_fees'] + (int)$m['org_fees'],
            'avg_price' => (int)round(($net - (int)$m['donations']) / $paidTickets), 'orders' => (int)$m['n'],
        ];
        return $out;
    }

    public static function csv(array $r, array $d): void
    {
        $rows = [['Bilan', $r['title']], [], ['Indicateur', 'Valeur']];
        $k = $d['k'];
        foreach (['Événements' => $k['events'], 'Représentations / dates' => $k['sessions'], 'Jauge totale' => $k['capacity'], 'Billets émis' => $k['issued'], 'Entrées contrôlées' => $k['present'],
            'Invitations' => $k['invited'], 'Taux de remplissage (%)' => $k['fill'], 'Taux de présence (%)' => $k['show'], 'Billets à tarif réduit' => $k['reduced'], 'Acheteurs' => $k['buyers'],
            'Nouveaux acheteurs' => $k['new'], 'Acheteurs fidèles' => $k['loyal'], 'Recettes nettes' => money_input($k['net']), 'Dons' => money_input($k['donations']), 'Prix moyen payé' => money_input($k['avg_price']),
            'Bénévoles' => $d['staff']['people'], 'Heures de bénévolat' => $d['staff']['hours'], 'Artistes' => $d['artists']['n'], 'Cachets' => money_input($d['artists']['fees'])] as $l => $v) $rows[] = [$l, (string)$v];
        $rows[] = []; $rows[] = ['Tarif', 'Billets', 'Montant', 'Accès facilité'];
        foreach ($d['rates'] as $x) $rows[] = [$x['name'], $x['n'], money_input($x['amount']), $x['reduced'] ? 'oui' : ''];
        $rows[] = []; $rows[] = ['Événement', 'Date', 'Lieu', 'Jauge', 'Billets', 'Présents'];
        foreach ($d['events'] as $e) $rows[] = [$e['name'], substr($e['starts_at'], 0, 10), trim($e['venue'] . ' ' . $e['city']), (int)$e['sess_n'] ? $e['sess_cap'] : $e['capacity'], $e['issued'], $e['present']];
        csv_download('bilan-' . preg_replace('~[^a-z0-9]+~', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $r['title']) ?: 'bilan')) . '.csv', $rows);
    }

    // ---------- PDF ----------
    public static function pdf(array $org, array $r, array $d): string
    {
        $t = fn($s) => PdfDoc::t((string)$s);
        $p = new PdfDoc('P', 'mm', 'A4');
        $p->SetMargins(18, 18, 18); $p->SetAutoPageBreak(true, 18); $p->AliasNbPages();
        $k = $d['k'];
        // couverture
        $p->AddPage();
        $p->SetFillColor(29, 29, 31); $p->Rect(0, 0, 210, 120, 'F');
        $p->SetTextColor(255, 255, 255); $p->SetXY(18, 40);
        $p->SetFont('Helvetica', '', 11); $p->Cell(0, 6, $t(mb_strtoupper('Bilan d\'activité')), 0, 1);
        $p->SetX(18); $p->SetFont('Helvetica', 'B', 26); $p->MultiCell(174, 11, $t($r['title']));
        $p->SetX(18); $p->SetFont('Helvetica', '', 12); $p->SetTextColor(200, 200, 205);
        $period = $d['events'] ? (count($d['events']) === 1 ? fr_date($d['events'][0]['starts_at']) : 'Du ' . date('d/m/Y', strtotime($d['events'][0]['starts_at'])) . ' au ' . date('d/m/Y', strtotime((string)(end($d['events'])['ends_at'] ?: end($d['events'])['starts_at'])))) : '';
        $p->Cell(0, 7, $t($org['legal_name'] ?: $org['name']), 0, 1); $p->SetX(18); $p->Cell(0, 7, $t($period), 0, 1);
        // chiffres clés
        $p->SetY(134); $p->SetTextColor(29, 29, 31);
        $tiles = [[number_format($k['issued'], 0, ',', ' '), 'billets émis'], [number_format($k['present'], 0, ',', ' '), 'entrées contrôlées'], [$k['fill'] !== null ? $k['fill'] . ' %' : '-', 'de remplissage'],
            [number_format($k['buyers'], 0, ',', ' '), 'acheteurs'], [$k['reduced_pct'] . ' %', 'billets à tarif réduit'], [money($k['net']), 'de recettes nettes'],
            [(string)$k['events'], $k['events'] > 1 ? 'événements' : 'événement'], [(string)$k['sessions'], 'représentation(s)'], [str_replace('.', ',', (string)$d['staff']['hours']), 'heures de bénévolat']];
        foreach ($tiles as $i => [$v, $l]) {
            $x = 18 + ($i % 3) * 59; $y = 134 + intdiv($i, 3) * 32;
            $p->SetFillColor(245, 245, 247); $p->RoundedRect($x, $y, 55, 27, 4, 'F');
            $p->SetXY($x + 5, $y + 5); $p->SetFont('Helvetica', 'B', 17); $p->Cell(45, 9, $t($v), 0, 2);
            $p->SetFont('Helvetica', '', 9); $p->SetTextColor(110, 110, 115); $p->Cell(45, 6, $t($l), 0, 0); $p->SetTextColor(29, 29, 31);
        }
        $p->SetY(240); $p->SetFont('Helvetica', '', 8.5); $p->SetTextColor(110, 110, 115);
        $p->MultiCell(0, 4.5, $t('Chiffres issus de la billetterie au ' . date('d/m/Y') . '. Billets émis = billets valides (payants et invitations). Entrées contrôlées = billets scannés à l\'entrée. Recettes nettes = ventes encaissées, remboursements et frais de billetterie déduits.'));

        $h2 = function (string $s) use ($p, $t) { if ($p->GetY() > 250) $p->AddPage(); $p->Ln(4); $p->SetTextColor(29, 29, 31); $p->SetFont('Helvetica', 'B', 14); $p->Cell(0, 9, $t($s), 0, 1); $p->SetDrawColor(29, 29, 31); $p->Line(18, $p->GetY(), 192, $p->GetY()); $p->Ln(3); };
        $table = function (array $head, array $rows, array $w) use ($p, $t) {
            $p->SetFont('Helvetica', 'B', 8.5); $p->SetTextColor(110, 110, 115);
            foreach ($head as $i => $h) $p->Cell($w[$i], 6, $t($h), 'B', 0, $i ? 'R' : 'L');
            $p->Ln(); $p->SetFont('Helvetica', '', 9.5); $p->SetTextColor(29, 29, 31); $p->SetDrawColor(225, 225, 230);
            foreach ($rows as $row) { if ($p->GetY() > 270) $p->AddPage(); foreach ($row as $i => $c) $p->Cell($w[$i], 6.5, $t(mb_strimwidth((string)$c, 0, $i ? 30 : 70, '…')), 'B', 0, $i ? 'R' : 'L'); $p->Ln(); }
            $p->Ln(2);
        };
        $bars = function (array $data) use ($p, $t) {
            $max = max(1, max($data ?: [1])); $tot = max(1, array_sum($data));
            foreach ($data as $l => $v) {
                if ($p->GetY() > 272) $p->AddPage();
                $y = $p->GetY(); $p->SetFont('Helvetica', '', 9); $p->SetTextColor(29, 29, 31); $p->Cell(52, 6, $t(mb_strimwidth((string)$l, 0, 34, '…')), 0, 0);
                $p->SetFillColor(29, 29, 31); $p->RoundedRect(72, $y + 1.5, max(1.5, 95 * $v / $max), 3.2, 1.2, 'F');
                $p->SetX(170); $p->Cell(22, 6, $t($v . ' (' . round($v / $tot * 100) . ' %)'), 0, 1, 'R');
            }
            $p->Ln(2);
        };
        $text = function (string $key) use ($p, $t, $r, $h2) {
            $s = trim((string)(self::sections($r)[$key] ?? ''));
            if ($s === '') return;
            $h2(self::SECTIONS[$key][0]); $p->SetFont('Helvetica', '', 10); $p->SetTextColor(29, 29, 31); $p->MultiCell(0, 5.2, $t($s));
        };

        $p->AddPage();
        $text('presentation'); $text('objectifs');
        $h2('Événements');
        $table(['Événement', 'Date', 'Jauge', 'Billets', 'Présents'], array_map(fn($e) => [$e['name'], date('d/m/Y', strtotime($e['starts_at'])), (int)$e['sess_n'] ? $e['sess_cap'] : ($e['capacity'] ?: '-'), $e['issued'], $e['present']], $d['events']), [86, 24, 20, 22, 22]);
        $text('actions');
        $h2('Fréquentation et publics');
        $p->SetFont('Helvetica', '', 10);
        $p->MultiCell(0, 5.2, $t(sprintf('%s billets émis dont %s invitations, pour une jauge de %s places%s. %s entrées contrôlées%s. %s acheteurs différents : %s nouveaux et %s qui avaient déjà acheté un billet. %s billets (%s %%) à tarif réduit ou solidaire.',
            number_format($k['issued'], 0, ',', ' '), $k['invited'], number_format($k['capacity'], 0, ',', ' '), $k['fill'] !== null ? ' (' . $k['fill'] . ' % de remplissage)' : '', number_format($k['present'], 0, ',', ' '), $k['show'] !== null ? ' (' . $k['show'] . ' % des billets)' : '',
            $k['buyers'], $k['new'], $k['loyal'], $k['reduced'], $k['reduced_pct'])));
        $p->Ln(2);
        if ($d['rates']) { $p->SetFont('Helvetica', 'B', 10); $p->Cell(0, 7, $t('Répartition par tarif'), 0, 1); $bars(array_column($d['rates'], 'n', 'name')); }
        if ($d['channels']) { $p->SetFont('Helvetica', 'B', 10); $p->Cell(0, 7, $t('Canaux de vente'), 0, 1); $bars($d['channels']); }
        if ($d['ages']) { $p->SetFont('Helvetica', 'B', 10); $p->Cell(0, 7, $t('Âge des acheteurs'), 0, 1); $bars($d['ages']); }
        if ($d['places']) { $p->SetFont('Helvetica', 'B', 10); $p->Cell(0, 7, $t('Provenance'), 0, 1); $bars($d['places']); }
        $text('publics');
        $h2('Recettes');
        $table(['', 'Montant'], array_filter([['Ventes encaissées (TTC)', money($k['gross'])], $k['refunded'] ? ['Remboursements', '- ' . money($k['refunded'])] : null, ['Frais de billetterie', '- ' . money($k['fees'])],
            ['Recettes nettes', money($k['net'])], $k['donations'] ? ['dont dons', money($k['donations'])] : null, ['Prix moyen payé par billet', money($k['avg_price'])]]), [130, 44]);
        if (!empty($d['budget'])) {
            $h2('Budget : prévu et réalisé');
            foreach (['income' => 'Recettes', 'expense' => 'Dépenses'] as $kind => $label) {
                if (empty($d['budget'][$kind])) continue;
                $rows = array_map(fn($b) => [$b['category'], money($b['planned']), $b['actual'] === null ? '-' : money($b['actual'])], $d['budget'][$kind]);
                $rows[] = ['Total ' . mb_strtolower($label), money(array_sum(array_column($d['budget'][$kind], 'planned'))), money(array_sum(array_map(fn($b) => (int)$b['actual'], $d['budget'][$kind])))];
                $table([$label, 'Prévu', 'Réalisé'], $rows, [102, 36, 36]);
            }
        }
        if ($d['staff']['people'] || $d['artists']['n']) {
            $h2('Équipe et artistes');
            $table(['', ''], array_filter([$d['staff']['people'] ? ['Bénévoles mobilisés', $d['staff']['people']] : null, $d['staff']['people'] ? ['Créneaux tenus', $d['staff']['shifts']] : null, $d['staff']['people'] ? ['Heures de bénévolat', str_replace('.', ',', (string)$d['staff']['hours']) . ' h'] : null,
                $d['artists']['n'] ? ['Artistes et compagnies', $d['artists']['n']] : null, $d['artists']['n'] ? ['Personnes accueillies (équipes artistiques)', $d['artists']['people']] : null, $d['artists']['shows'] ? ['Passages programmés', $d['artists']['shows']] : null,
                $d['artists']['fees'] ? ['Cachets et contrats', money($d['artists']['fees'])] : null]), [130, 44]);
        }
        $text('partenaires'); $text('perspectives');
        return $p->Output('S');
    }
}
