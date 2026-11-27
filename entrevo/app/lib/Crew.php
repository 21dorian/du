<?php
declare(strict_types=1);

/**
 * 4.2 : compléments « Crew / Pay / Access » :
 *  - click & collect : le festivalier commande au bar depuis sa carte cashless, paie avec son solde, et récupère au comptoir avec un numéro ;
 *  - dotations des équipes (repas, hébergement, matériel), documents à signer, messages par poste, planning individuel PDF ;
 *  - statistiques du contrôle d'accès par opérateur.
 */
final class Crew
{
    // ---------- click & collect ----------
    public static function collectBars(array $ev): array
    {
        if (!(int)($ev['click_collect'] ?? 0)) return [];
        $ids = array_filter(array_map('intval', explode(',', (string)($ev['click_collect_bars'] ?? ''))));
        $rows = all('SELECT * FROM bars WHERE event_id = ? ORDER BY name', [$ev['id']]);
        return array_values(array_filter($rows, fn($b) => !$ids || in_array((int)$b['id'], $ids, true)));
    }

    public static function menu(array $ev, int $barId): array
    {
        return all('SELECT p.id, p.name, p.price, p.deposit, c.name cat FROM bar_products p LEFT JOIN pos_categories c ON c.id = p.category_id WHERE p.event_id = ? AND p.active = 1 AND p.kind = \'item\' AND p.price > 0
            ORDER BY c.sort, c.name, p.sort, p.name LIMIT 200', [$ev['id']]);
    }

    /** Crée la commande : débit du porte-monnaie, ticket de préparation numéroté. */
    public static function collectOrder(array $w, array $ev, int $barId, array $qty): array
    {
        if (!in_array($barId, array_map(fn($b) => (int)$b['id'], self::collectBars($ev)), true)) throw new RuntimeException('Ce bar ne prend pas les commandes à distance.');
        if ($w['status'] !== 'active') throw new RuntimeException('Ta carte est bloquée.');
        $items = [];
        foreach ($qty as $pid => $n) if ((int)$n > 0) $items[] = ['id' => (int)$pid, 'qty' => min(20, (int)$n)];
        if (!$items) throw new RuntimeException('Choisis au moins un article.');
        $pr = Till::price($ev, $barId, ['items' => $items], time());
        if (!$pr['lines'] || $pr['total'] <= 0) throw new RuntimeException('Panier vide.');
        if ((int)val('SELECT COUNT(*) FROM pos_sales WHERE wallet_id = ? AND source = \'collect\' AND prep_status = \'new\'', [$w['id']]) >= 3) throw new RuntimeException('Tu as déjà 3 commandes en préparation : récupère-les d\'abord.');
        $lines = array_map(fn($l) => ($l['kind'] ?? '') === 'product' ? $l + ['prep' => 1] : $l, $pr['lines']);
        db()->beginTransaction();
        try {
            $w = one('SELECT * FROM wallets WHERE id = ? FOR UPDATE', [$w['id']]);
            if ((int)$w['balance'] < $pr['total']) throw new RuntimeException('Solde insuffisant (' . money((int)$w['balance']) . ') : recharge ta carte.');
            $r = Cashless::charge($w, $pr['total'], $barId, $lines, 'click&collect');
            $id = insert('pos_sales', ['event_id' => $ev['id'], 'bar_id' => $barId, 'device_id' => null, 'method' => 'cashless', 'amount' => $pr['total'], 'items' => json_encode($lines, JSON_UNESCAPED_UNICODE),
                'wallet_id' => $w['id'], 'wallet_tx_id' => $r['tx'], 'created_at' => now(), 'received_at' => now(), 'subtotal' => $pr['subtotal'], 'discount' => $pr['discount'], 'offered_value' => 0,
                'vat_json' => $pr['vat'] ? json_encode($pr['vat']) : null, 'receipt_token' => bin2hex(random_bytes(12)), 'ticket_no' => Till::nextTicket((int)$ev['id']), 'order_no' => Till::nextOrder($barId),
                'prep_status' => 'new', 'staff' => 'Click & collect', 'source' => 'collect', 'offline' => 0, 'undone' => 0]);
            db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        return one('SELECT * FROM pos_sales WHERE id = ?', [$id]);
    }

    public static function myOrders(array $w): array
    {
        return all("SELECT s.*, b.name bar FROM pos_sales s JOIN bars b ON b.id = s.bar_id WHERE s.wallet_id = ? AND s.source = 'collect' AND s.undone = 0 AND s.created_at >= NOW() - INTERVAL 12 HOUR ORDER BY s.id DESC LIMIT 10", [$w['id']]);
    }

    // ---------- équipes ----------
    public static function eventMembers(int $evId): array
    {
        return all("SELECT m.*, GROUP_CONCAT(DISTINCT s.title ORDER BY s.starts_at SEPARATOR ', ') posts, COUNT(DISTINCT s.id) nshifts, MIN(s.starts_at) first_at, MAX(s.ends_at) last_at,
                SUM(TIMESTAMPDIFF(MINUTE, s.starts_at, s.ends_at)) minutes, p.meals, p.meals_used, p.lodging, p.items, p.given_at
            FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id JOIN staff_members m ON m.id = a.member_id
            LEFT JOIN staff_provisions p ON p.event_id = s.event_id AND p.member_id = m.id
            WHERE s.event_id = ? AND a.status = 'confirmed' GROUP BY m.id ORDER BY m.last_name, m.first_name", [$evId]);
    }

    /** Dotation suggérée : 1 repas par créneau de 4 h ou plus (min. 1), selon le planning confirmé. */
    public static function autoMeals(int $evId): int
    {
        $n = 0;
        foreach (all("SELECT a.member_id, SUM(GREATEST(1, FLOOR(TIMESTAMPDIFF(MINUTE, s.starts_at, s.ends_at) / 240))) meals FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id
            WHERE s.event_id = ? AND a.status = 'confirmed' GROUP BY a.member_id", [$evId]) as $r) {
            q('INSERT INTO staff_provisions (event_id, member_id, meals, meals_used) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE meals = VALUES(meals)', [$evId, $r['member_id'], (int)$r['meals']]);
            $n++;
        }
        return $n;
    }

    public static function docsFor(array $m): array
    {
        return all("SELECT d.*, e.name ev_name, g.signed_at, g.name signed_name FROM staff_docs d LEFT JOIN events e ON e.id = d.event_id
            LEFT JOIN staff_signatures g ON g.doc_id = d.id AND g.member_id = ?
            WHERE d.organizer_id = ? AND (d.team IS NULL OR d.team = '' OR d.team = ?) AND (d.event_id IS NULL OR EXISTS (SELECT 1 FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id WHERE a.member_id = ? AND s.event_id = d.event_id AND a.status IN ('confirmed','proposed','requested')))
            ORDER BY d.id DESC", [$m['id'], $m['organizer_id'], (string)$m['team'], $m['id']]);
    }

    public static function planningPdf(array $m, ?int $evId = null): string
    {
        $org = one('SELECT * FROM organizers WHERE id = ?', [$m['organizer_id']]);
        $rows = all("SELECT s.*, a.status, e.name ev_name FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id JOIN events e ON e.id = s.event_id
            WHERE a.member_id = ? AND a.status IN ('confirmed','proposed')" . ($evId ? ' AND s.event_id = ' . (int)$evId : ' AND s.ends_at >= NOW() - INTERVAL 1 DAY') . ' ORDER BY s.starts_at', [$m['id']]);
        $pdf = new PdfDoc('P', 'mm', 'A4'); $pdf->SetAutoPageBreak(true, 18); $pdf->AddPage();
        $t = fn($s) => PdfDoc::t((string)$s);
        $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetTextColor(110, 110, 120); $pdf->Cell(0, 5, $t(mb_strtoupper($org['name']) . ' · PLANNING INDIVIDUEL'), 0, 1);
        $pdf->SetFont('Helvetica', 'B', 20); $pdf->SetTextColor(17, 17, 20); $pdf->Cell(0, 11, $t($m['first_name'] . ' ' . $m['last_name']), 0, 1);
        $pdf->SetFont('Helvetica', '', 10); $pdf->SetTextColor(90, 90, 100); $pdf->Cell(0, 5, $t(trim(($m['team'] ? 'Équipe ' . $m['team'] : '') . ($m['phone'] ? ' · ' . $m['phone'] : ''), ' ·')), 0, 1);
        Qr::drawPdf($pdf, 'STAFF:' . $m['token'], 170, 12, 26);
        $pdf->Ln(8); $min = 0; $day = '';
        foreach ($rows as $r) {
            $d = fr_date($r['starts_at'], false);
            if ($d !== $day) { $day = $d; $pdf->Ln(2); $pdf->SetFont('Helvetica', 'B', 11); $pdf->SetTextColor(17, 17, 20); $pdf->Cell(0, 7, $t(ucfirst($d) . ' · ' . $r['ev_name']), 'B', 1); }
            $pdf->SetFont('Helvetica', 'B', 10); $pdf->Cell(30, 7, date('H:i', strtotime($r['starts_at'])) . ' - ' . date('H:i', strtotime($r['ends_at'])));
            $pdf->Cell(95, 7, $t($r['title'] . ($r['status'] === 'proposed' ? ' (à confirmer)' : '')));
            $pdf->SetFont('Helvetica', '', 9); $pdf->Cell(0, 7, $t((string)$r['location']), 0, 1);
            if ($r['description']) { $pdf->SetTextColor(90, 90, 100); $pdf->SetX(40); $pdf->MultiCell(150, 4.5, $t($r['description'])); $pdf->SetTextColor(17, 17, 20); }
            $min += Staff::minutes($r)[0];
        }
        if (!$rows) { $pdf->SetFont('Helvetica', '', 11); $pdf->Cell(0, 8, $t('Aucun créneau confirmé pour l\'instant.'), 0, 1); }
        $prov = $evId ? one('SELECT * FROM staff_provisions WHERE event_id = ? AND member_id = ?', [$evId, $m['id']]) : null;
        $pdf->Ln(4); $pdf->SetFont('Helvetica', 'B', 10); $pdf->Cell(0, 6, $t('Total : ' . Staff::hm($min)), 0, 1);
        if ($prov) { $pdf->SetFont('Helvetica', '', 10); $pdf->MultiCell(0, 5.5, $t('Dotation : ' . (int)$prov['meals'] . ' repas' . ($prov['lodging'] ? ' · hébergement : ' . $prov['lodging'] : '') . ($prov['items'] ? ' · ' . $prov['items'] : ''))); }
        $pdf->Ln(4); $pdf->SetFont('Helvetica', '', 8.5); $pdf->SetTextColor(120, 120, 130); $pdf->MultiCell(0, 4.5, $t('Présente le QR code ci-dessus à l\'accueil équipe pour pointer. Ton espace : ' . Staff::portalUrl($m)));
        return $pdf->Output('S');
    }

    // ---------- contrôle d'accès ----------
    public static function operatorStats(int $evId): array
    {
        return all("SELECT COALESCE(u.name, NULLIF(s.device, ''), 'Appareil inconnu') who, COUNT(*) n, SUM(s.result = 'ok') ok, SUM(s.result = 'exit') exits, SUM(s.result NOT IN ('ok','exit')) refused,
                SUM(s.offline) offline, MIN(s.scanned_at) first_at, MAX(s.scanned_at) last_at, COUNT(DISTINCT s.access_point_id) points,
                GROUP_CONCAT(DISTINCT ap.name ORDER BY ap.name SEPARATOR ', ') point_names
            FROM scans s LEFT JOIN users u ON u.id = s.user_id LEFT JOIN access_points ap ON ap.id = s.access_point_id
            WHERE s.event_id = ? AND s.undone = 0 GROUP BY who ORDER BY n DESC", [$evId]);
    }
}
