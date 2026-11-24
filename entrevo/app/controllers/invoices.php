<?php
declare(strict_types=1);

/* Factures de commission (super-admin) et téléchargement côté organisateur. */

function admin_invoices(): string
{
    require_login(['superadmin']);
    if (is_post()) {
        if (input('action') === 'generate') {
            if (trim((string)setting('invoice_name')) === '' || trim((string)setting('invoice_address')) === '') back(['error' => 'Renseigne d\'abord tes informations d\'émetteur (Réglages › Commissions).']);
            try { $ids = Invoices::generate((string)input('period'), (bool)input('send')); } catch (Throwable $e) { back(['error' => $e->getMessage()]); }
            back(['success' => $ids ? '' . count($ids) . ' facture(s) créée(s)' . (input('send') ? ' et envoyée(s).' : '.') : 'Aucune nouvelle facture : tout est déjà facturé pour ce mois (ou pas de commission).']);
        }
        if (input('action') === 'resend') {
            try { Invoices::send((int)input('id')); back(['success' => 'Facture renvoyée.']); } catch (Throwable $e) { back(['error' => $e->getMessage()]); }
        }
        back();
    }
    $period = preg_match('~^\d{4}-\d{2}$~', (string)input('mois')) ? (string)input('mois') : '';
    $list = all('SELECT i.*, o.name org_name FROM commission_invoices i JOIN organizers o ON o.id = i.organizer_id' . ($period ? ' WHERE i.period = ?' : '') . ' ORDER BY i.id DESC LIMIT 500', $period ? [$period] : []);
    $prev = date('Y-m', strtotime('first day of last month'));
    $pending = count(array_filter(Invoices::due($prev), fn($r) => !val('SELECT id FROM commission_invoices WHERE organizer_id = ? AND period = ?', [$r['organizer_id'], $prev])));
    $totals = one('SELECT COALESCE(SUM(amount_ht),0) ht, COALESCE(SUM(vat),0) vat, COALESCE(SUM(amount_ttc),0) ttc FROM commission_invoices' . ($period ? ' WHERE period = ?' : ' WHERE period LIKE ?'), [$period ?: date('Y') . '%']);
    return view('layout_back', ['title' => 'Factures de commission', 'area' => 'admin', 'active' => 'factures-commission',
        'content' => view('admin/invoices', compact('list', 'period', 'prev', 'pending', 'totals'))]);
}

function admin_invoice_pdf(string $id): never
{
    require_login(['superadmin']);
    $inv = one('SELECT * FROM commission_invoices WHERE id = ?', [(int)$id]) ?: abort(404);
    invoice_output($inv);
}

function pro_invoice_pdf(string $id): never
{
    $org = require_org(['owner', 'manager']);
    $inv = one('SELECT * FROM commission_invoices WHERE id = ? AND organizer_id = ?', [(int)$id, $org['id']]) ?: abort(404);
    invoice_output($inv);
}

function invoice_output(array $inv): never
{
    $org = one('SELECT * FROM organizers WHERE id = ?', [$inv['organizer_id']]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $inv['number'] . '.pdf"');
    echo Invoices::pdf($inv, $org);
    exit;
}
