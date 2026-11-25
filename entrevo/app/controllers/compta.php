<?php
declare(strict_types=1);

/* 2.5 : organisateur › Factures & compta — registre des factures et avoirs, facturation en lot, plan comptable. */

require_once APP . '/controllers/pro.php';

function pro_compta(): string
{
    $org = require_org(['owner']);
    require_module('compta');
    $month = preg_match('~^\d{4}-\d{2}$~', (string)input('mois')) ? (string)input('mois') : date('Y-m');
    $from = $month . '-01'; $to = date('Y-m-t', strtotime($from));
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'invoice_all') {
            $n = Accounting::invoicePeriod((int)$org['id'], $from, $to);
            Audit::log('compta.batch', $n . ' facture(s) émise(s) pour ' . month_fr($month));
            back(['success' => $n ? $n . ' facture(s) émise(s) pour ' . month_fr($month) . '.' : 'Toutes les ventes du mois sont déjà facturées.']);
        }
        if ($a === 'auto') {
            $s = Accounting::settings($org);
            $on = checked('auto_invoice');
            if ($on && !(int)$org['auto_invoice']) $s['auto_from'] = date('Y-m-d');
            update('organizers', ['auto_invoice' => $on, 'accounting_json' => json_encode($s)], 'id = ?', [$org['id']]);
            if ($on) Accounting::autoInvoice((int)$org['id']);
            back(['success' => $on ? 'Chaque vente payée reçoit maintenant sa facture automatiquement.' : 'Facturation automatique désactivée : les factures sont émises à la demande.']);
        }
        if ($a === 'accounts') {
            $s = Accounting::settings($org);
            foreach (array_keys(Accounting::ACCOUNTS) as $k) { $v = preg_replace('~\D~', '', (string)($_POST['acc'][$k] ?? '')); $s['accounts'][$k] = $v !== '' ? substr($v, 0, 10) : null; }
            foreach (array_keys(Accounting::JOURNALS) as $k) { $v = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', (string)($_POST['jr'][$k] ?? ''))); $s['journals'][$k] = $v !== '' ? substr($v, 0, 6) : null; }
            update('organizers', ['accounting_json' => json_encode($s)], 'id = ?', [$org['id']]);
            back(['success' => 'Plan comptable enregistré : il est utilisé par l\'export FEC.']);
        }
        back();
    }
    Accounting::syncCredits((int)$org['id']);
    if (input('export') === 'zip') {
        $data = Accounting::zip($org, $month);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="factures-' . $month . '.zip"');
        echo $data; exit;
    }
    $kind = in_array(input('type'), ['facture', 'avoir'], true) ? (string)input('type') : '';
    $reg = Accounting::register((int)$org['id'], $from, $to, $kind);
    if (input('export') === 'csv') {
        $rows = [['Type', 'Numéro', 'Date', 'Client', 'E-mail', 'Commande', 'Événement', 'Montant TTC']];
        foreach (array_reverse($reg) as $r) $rows[] = [$r['kind'], $r['number'], substr((string)$r['at'], 0, 10), $r['invoice_company'] ?: trim($r['first_name'] . ' ' . $r['last_name']), $r['email'], $r['ref'], (string)$r['ev'], money_input((int)$r['amount'])];
        csv_download('registre-factures-' . $month . '.csv', $rows);
    }
    $k = ['inv' => 0, 'inv_t' => 0, 'av' => 0, 'av_t' => 0];
    foreach (Accounting::register((int)$org['id'], $from, $to) as $r) { if ($r['kind'] === 'facture') { $k['inv']++; $k['inv_t'] += (int)$r['amount']; } else { $k['av']++; $k['av_t'] += (int)$r['amount']; } }
    $k['todo'] = (int)val("SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND invoice_number IS NULL AND status IN ('paid','refunded') AND total > 0 AND paid_at >= ? AND paid_at < ? + INTERVAL 1 DAY", [$org['id'], $from, $to]);
    $k['todo_t'] = (int)val("SELECT COALESCE(SUM(total), 0) FROM orders WHERE organizer_id = ? AND invoice_number IS NULL AND status IN ('paid','refunded') AND total > 0 AND paid_at >= ? AND paid_at < ? + INTERVAL 1 DAY", [$org['id'], $from, $to]);
    $missing = array_keys(array_filter(['raison sociale' => $org['legal_name'] ?? '', 'adresse' => $org['address'] ?? '', 'SIRET' => $org['siret'] ?? ''], fn($v) => trim((string)$v) === ''));
    $commission = all('SELECT * FROM commission_invoices WHERE organizer_id = ? ORDER BY period DESC LIMIT 6', [$org['id']]);
    $settings = Accounting::settings($org);
    $months = []; for ($i = 0; $i < 18; $i++) $months[] = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
    return pro_page('Factures & compta', 'compta', compact('org', 'month', 'from', 'to', 'kind', 'reg', 'k', 'missing', 'commission', 'settings', 'months'), 'factures');
}

function pro_invoice_doc(string $id): never
{
    $org = require_org(['owner', 'manager']);
    $o = one('SELECT * FROM orders WHERE id = ? AND organizer_id = ? AND invoice_number IS NOT NULL', [(int)$id, $org['id']]);
    if (!$o) abort(404);
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="facture-' . $o['invoice_number'] . '.pdf"');
    echo Quotes::invoice($o); exit;
}

function pro_credit_doc(string $id): never
{
    $org = require_org(['owner', 'manager']);
    $c = one('SELECT * FROM credit_notes WHERE id = ? AND organizer_id = ?', [(int)$id, $org['id']]);
    if (!$c) abort(404);
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="avoir-' . $c['number'] . '.pdf"');
    echo Accounting::creditPdf($c); exit;
}

/** Acheteur : son avoir, depuis la page de commande. */
function order_credit(string $ref, string $id): never
{
    $o = order_by_ref($ref);
    $c = one('SELECT * FROM credit_notes WHERE id = ? AND order_id = ?', [(int)$id, $o['id']]);
    if (!$c) abort(404);
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="avoir-' . $c['number'] . '.pdf"');
    echo Accounting::creditPdf($c); exit;
}
