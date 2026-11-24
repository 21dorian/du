<?php
declare(strict_types=1);

/**
 * Factures de commission : une par organisateur et par mois, numérotation continue par année
 * (PRÉFIXE-AAAA-0001), montant = commissions TTC des ventes en ligne réelles payées dans le mois.
 * Émetteur : réglages invoice_* du super-admin.
 */
final class Invoices
{
    public static function vatRate(): float { return (float)str_replace(',', '.', (string)setting('invoice_vat_rate', '20')); }

    /** Commissions du mois pour un organisateur (ou tous). */
    public static function due(string $period, ?int $orgId = null): array
    {
        $from = $period . '-01 00:00:00'; $to = date('Y-m-d 00:00:00', strtotime($from . ' +1 month'));
        return all("SELECT o.organizer_id, COUNT(*) n, SUM(o.commission) com FROM orders o WHERE o.status IN ('paid','refunded') AND o.method IN ('online','partner')
            AND o.provider IN ('stripe','paypal','sumup','mollie') AND o.paid_at >= ? AND o.paid_at < ?" . ($orgId ? ' AND o.organizer_id = ' . (int)$orgId : '') . '
            GROUP BY o.organizer_id HAVING com > 0', [$from, $to]);
    }

    /** Crée les factures manquantes du mois ; renvoie les nouvelles. */
    public static function generate(string $period, bool $send = true): array
    {
        if (!preg_match('~^\d{4}-\d{2}$~', $period) || $period >= date('Y-m')) throw new RuntimeException('Choisis un mois terminé.');
        $made = [];
        foreach (self::due($period) as $r) {
            if (val('SELECT id FROM commission_invoices WHERE organizer_id = ? AND period = ?', [$r['organizer_id'], $period])) continue;
            $ttc = (int)$r['com']; $rate = self::vatRate();
            $ht = (int)round($ttc / (1 + $rate / 100));
            db()->beginTransaction();
            try {
                $year = substr($period, 0, 4);
                $prefix = preg_replace('~[^A-Z0-9]~', '', strtoupper((string)setting('invoice_prefix', 'COM'))) ?: 'COM';
                $last = (int)val("SELECT COUNT(*) FROM commission_invoices WHERE number LIKE ? FOR UPDATE", [$prefix . '-' . $year . '-%']);
                $num = $prefix . '-' . $year . '-' . str_pad((string)($last + 1), 4, '0', STR_PAD_LEFT);
                $id = insert('commission_invoices', ['organizer_id' => $r['organizer_id'], 'number' => $num, 'period' => $period, 'orders_count' => (int)$r['n'],
                    'amount_ht' => $ht, 'vat_rate' => $rate, 'vat' => $ttc - $ht, 'amount_ttc' => $ttc, 'created_at' => now()]);
                db()->commit();
            } catch (Throwable $e) { db()->rollBack(); throw $e; }
            $made[] = $id;
            if ($send) { try { self::send($id); } catch (Throwable $e) { log_line('emails', 'Facture ' . $num . ' : ' . $e->getMessage()); } }
        }
        return $made;
    }

    public static function send(int $id): void
    {
        $inv = one('SELECT * FROM commission_invoices WHERE id = ?', [$id]);
        $org = one('SELECT * FROM organizers WHERE id = ?', [$inv['organizer_id']]);
        if (!is_email((string)$org['email'])) return;
        Mailer::send((string)$org['email'], 'Facture ' . $inv['number'] . ' — commissions ' . self::monthLabel($inv['period']),
            '<p>Bonjour,</p><p>Tu trouveras ci-joint la facture des commissions ' . e(setting('platform_name')) . ' pour <b>' . e(self::monthLabel($inv['period'])) . '</b> : <b>' . e(money((int)$inv['amount_ttc'])) . ' TTC</b>.</p>'
            . '<p>Rien à payer : ces commissions ont déjà été déduites de tes reversements.</p><p>Toutes tes factures sont aussi dans Finances.</p>',
            [['data' => self::pdf($inv, $org), 'name' => $inv['number'] . '.pdf']]);
        update('commission_invoices', ['sent_at' => now()], 'id = ?', [$id]);
    }

    public static function monthLabel(string $period): string
    {
        $m = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        return $m[(int)substr($period, 5, 2)] . ' ' . substr($period, 0, 4);
    }

    public static function pdf(array $inv, array $org): string
    {
        $t = fn($s) => PdfDoc::t((string)$s);
        $pdf = new PdfDoc('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 22); $pdf->SetTextColor(0, 50, 250);
        $pdf->Cell(110, 10, $t((string)setting('invoice_name') ?: setting('platform_name')), 0, 0);
        $pdf->SetTextColor(15, 15, 20); $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, $t('FACTURE'), 0, 1, 'R');
        $pdf->SetFont('Helvetica', '', 9.5);
        $y = $pdf->GetY();
        $pdf->MultiCell(100, 5, $t(trim((string)setting('invoice_address')) . "\n" . (setting('invoice_siret') ? 'SIRET ' . setting('invoice_siret') . "\n" : '') . (setting('invoice_vat_number') ? 'TVA ' . setting('invoice_vat_number') : '')), 0, 'L');
        $pdf->SetXY(120, $y);
        $pdf->MultiCell(0, 5, $t('N° ' . $inv['number'] . "\nDate : " . date('d/m/Y', strtotime($inv['created_at'])) . "\nPériode : " . self::monthLabel($inv['period'])), 0, 'R');
        $pdf->Ln(10);
        $pdf->SetFillColor(240, 245, 250); $pdf->RoundedRect(110, $pdf->GetY(), 90, 34, 3, 'F');
        $pdf->SetXY(115, $pdf->GetY() + 4); $pdf->SetFont('Helvetica', 'B', 10); $pdf->Cell(0, 5, $t('Facturé à'), 0, 2);
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->MultiCell(80, 5, $t(($org['legal_name'] ?: $org['name']) . "\n" . ($org['address'] ?: '') . ($org['siret'] ? "\nSIRET " . $org['siret'] : '') . ($org['vat_number'] ? "\nTVA " . $org['vat_number'] : '')), 0, 'L');
        $pdf->SetY(max($pdf->GetY(), 100) + 6);
        $pdf->SetFillColor(15, 15, 20); $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->Cell(110, 8, $t('Désignation'), 0, 0, 'L', true); $pdf->Cell(20, 8, $t('Qté'), 0, 0, 'R', true); $pdf->Cell(30, 8, $t('Montant HT'), 0, 0, 'R', true); $pdf->Cell(0, 8, $t('TVA'), 0, 1, 'R', true);
        $pdf->SetTextColor(15, 15, 20); $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->Cell(110, 9, $t('Commissions de billetterie - ' . self::monthLabel($inv['period'])), 'B');
        $pdf->Cell(20, 9, (string)$inv['orders_count'], 'B', 0, 'R');
        $pdf->Cell(30, 9, $t(money((int)$inv['amount_ht'])), 'B', 0, 'R');
        $pdf->Cell(0, 9, $t(rtrim(rtrim(number_format((float)$inv['vat_rate'], 2, ',', ''), '0'), ',') . ' %'), 'B', 1, 'R');
        $pdf->Ln(4);
        foreach ([['Total HT', $inv['amount_ht']], ['TVA ' . rtrim(rtrim(number_format((float)$inv['vat_rate'], 2, ',', ''), '0'), ',') . ' %', $inv['vat']], ['Total TTC', $inv['amount_ttc']]] as $i => [$l, $v]) {
            $pdf->SetFont('Helvetica', $i === 2 ? 'B' : '', $i === 2 ? 12 : 10);
            $pdf->Cell(150, 7, $t($l), 0, 0, 'R'); $pdf->Cell(0, 7, $t(money((int)$v)), 0, 1, 'R');
        }
        $pdf->Ln(8);
        $pdf->SetFont('Helvetica', '', 9); $pdf->SetTextColor(90, 90, 100);
        $pdf->MultiCell(0, 5, $t('Facture acquittée : le montant a été prélevé sur les ventes encaissées pour votre compte (' . $inv['orders_count'] . ' commande(s)). Aucun paiement n\'est attendu.'
            . ((float)$inv['vat_rate'] === 0.0 ? "\nTVA non applicable, article 293 B du CGI." : '') . "\n" . trim((string)setting('invoice_footer'))), 0, 'L');
        return $pdf->Output('S');
    }
}
