<?php
declare(strict_types=1);

/* 2.2 : super-admin › formules des organisateurs, contrats & tarifs négociés, retenues. */

require_once APP . '/controllers/admin.php';

function plan_data(): array
{
    $opt = fn(string $k) => trim((string)input($k)) === '' ? null : $k;
    $mods = array_values(array_intersect(array_keys(Billing::MODULES), (array)($_POST['modules'] ?? [])));
    return [
        'name' => mb_substr(trim((string)input('name')), 0, 120), 'description' => mb_substr(trim((string)input('description')), 0, 500) ?: null,
        'fee_percent' => $opt('fee_percent') ? max(0, min(50, (float)str_replace(',', '.', (string)input('fee_percent')))) : null,
        'fee_fixed' => $opt('fee_fixed') ? parse_money(input('fee_fixed')) : null,
        'fee_cap' => $opt('fee_cap') ? parse_money(input('fee_cap')) : null,
        'monthly_price' => parse_money(input('monthly_price')),
        'sms_price' => $opt('sms_price') ? parse_money(input('sms_price')) : null,
        'modules' => count($mods) === count(Billing::MODULES) ? null : implode(',', $mods),
        'public' => checked('public'), 'active' => checked('active'), 'sort' => (int)input('sort'),
    ];
}

function admin_plans(): string
{
    $u = require_super();
    if (is_post()) {
        $a = (string)input('action');
        $id = (int)input('plan_id');
        if ($a === 'default') { q('UPDATE plans SET is_default = (id = ?)', [$id]); Audit::log('plan.default', 'Formule par défaut #' . $id); back(['success' => 'Formule par défaut changée : elle s\'applique à tous les organisateurs sans formule.']); }
        if ($a === 'delete') {
            if (val('SELECT id FROM organizers WHERE plan_id = ? LIMIT 1', [$id])) back(['error' => 'Des organisateurs ont cette formule : change-les d\'abord ou désactive-la.']);
            q('DELETE FROM plans WHERE id = ?', [$id]); back(['success' => 'Formule supprimée.']);
        }
        $d = plan_data();
        if ($d['name'] === '') back(['error' => 'Donne un nom à la formule.']);
        if ($id) update('plans', $d, 'id = ?', [$id]);
        else { $d['is_default'] = val('SELECT id FROM plans LIMIT 1') ? 0 : 1; $id = insert('plans', $d + ['created_at' => now()]); }
        Audit::log('plan.saved', 'Formule « ' . $d['name'] . ' »');
        redirect('/admin/formules', ['success' => 'Formule « ' . $d['name'] . ' » enregistrée.']);
    }
    $plans = Billing::plans();
    $edit = input('edit') === 'new' ? [] : (input('edit') ? one('SELECT * FROM plans WHERE id = ?', [(int)input('edit')]) : null);
    $noPlan = (int)val("SELECT COUNT(*) FROM organizers WHERE plan_id IS NULL AND status <> 'suspended'");
    [$gp, $gf] = [(float)setting('fee_percent'), (int)setting('fee_fixed')];
    return admin_page('Formules', 'plans', ['plans' => $plans, 'edit' => $edit, 'noPlan' => $noPlan, 'mrr' => Billing::mrr(), 'global' => [$gp, $gf]], 'formules');
}

function admin_contracts(): string
{
    $u = require_super();
    if (is_post()) {
        $a = (string)input('action');
        $id = (int)input('contract_id');
        if ($a === 'end') { q("UPDATE contracts SET status = 'ended', ends_on = LEAST(COALESCE(ends_on, CURDATE()), CURDATE()) WHERE id = ?", [$id]); Billing::forget(); back(['success' => 'Contrat terminé : la commission revient à la formule de l\'organisateur.']); }
        $org = one('SELECT * FROM organizers WHERE id = ?', [(int)input('organizer_id')]);
        if (!$org) back(['error' => 'Choisis un organisateur.']);
        $start = (string)input('starts_on') ?: date('Y-m-d');
        $end = (string)input('ends_on') ?: null;
        if ($end && $end < $start) back(['error' => 'La fin du contrat est avant son début.']);
        $num = fn($k) => trim((string)input($k)) === '' ? null : max(0, min(50, (float)str_replace(',', '.', (string)input($k))));
        $d = ['organizer_id' => $org['id'], 'label' => mb_substr(trim((string)input('label')) ?: 'Contrat ' . $org['name'], 0, 190),
            'fee_percent' => $num('fee_percent'), 'fee_fixed' => trim((string)input('fee_fixed')) === '' ? null : parse_money(input('fee_fixed')),
            'fee_cap' => trim((string)input('fee_cap')) === '' ? null : parse_money(input('fee_cap')),
            'threshold' => trim((string)input('threshold')) === '' ? null : parse_money(input('threshold')), 'fee_percent_after' => $num('fee_percent_after'),
            'starts_on' => $start, 'ends_on' => $end, 'notes' => trim((string)input('notes')) ?: null];
        if (($d['threshold'] === null) !== ($d['fee_percent_after'] === null)) back(['error' => 'Pour un palier, indique à la fois le volume et la commission au-delà.']);
        if (!empty($_FILES['document']['name'])) {
            $f = $_FILES['document'];
            if ($f['error'] !== UPLOAD_ERR_OK || strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'pdf' || $f['size'] > 10 * 1024 * 1024) back(['error' => 'Le contrat signé doit être un PDF de 10 Mo maximum.']);
            $dir = STORAGE . '/contrats'; if (!is_dir($dir)) mkdir($dir, 0775, true);
            $name = 'contrat-' . $org['id'] . '-' . token(10) . '.pdf';
            move_uploaded_file($f['tmp_name'], $dir . '/' . $name);
            $d['document'] = $name;
        }
        if ($id) update('contracts', $d, 'id = ?', [$id]);
        else $id = insert('contracts', $d + ['status' => 'active', 'created_by' => $u['id'], 'created_at' => now()]);
        Billing::forget();
        Audit::log('contract.saved', $d['label'] . ' · ' . $org['name'], null, null, (int)$org['id']);
        redirect('/admin/contrats', ['success' => 'Contrat enregistré pour ' . $org['name'] . '.']);
    }
    if (input('doc')) {
        $c = one('SELECT * FROM contracts WHERE id = ?', [(int)input('doc')]);
        $f = $c && $c['document'] ? STORAGE . '/contrats/' . basename($c['document']) : '';
        if (!$f || !is_file($f)) abort(404);
        header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . basename($f) . '"'); readfile($f); exit;
    }
    $f = (string)input('f', 'actifs');
    $where = match ($f) {
        'fin' => "c.status = 'active' AND c.ends_on IS NOT NULL AND c.ends_on BETWEEN CURDATE() AND CURDATE() + INTERVAL 45 DAY",
        'termines' => "(c.status <> 'active' OR c.ends_on < CURDATE())",
        'tous' => '1',
        default => "c.status = 'active' AND (c.ends_on IS NULL OR c.ends_on >= CURDATE())",
    };
    $list = all("SELECT c.*, o.name org_name FROM contracts c JOIN organizers o ON o.id = c.organizer_id WHERE $where ORDER BY c.starts_on DESC, c.id DESC LIMIT 300");
    foreach ($list as &$c) {
        $c['volume'] = Billing::contractVolume($c);
        $c['commission'] = (int)val("SELECT COALESCE(SUM(commission), 0) FROM orders WHERE organizer_id = ? AND status = 'paid' AND paid_at >= ?" . ($c['ends_on'] ? ' AND paid_at < ? + INTERVAL 1 DAY' : ''),
            $c['ends_on'] ? [$c['organizer_id'], $c['starts_on'], $c['ends_on']] : [$c['organizer_id'], $c['starts_on']]);
    }
    unset($c);
    $counts = one("SELECT SUM(status = 'active' AND (ends_on IS NULL OR ends_on >= CURDATE())) actifs,
        SUM(status = 'active' AND ends_on IS NOT NULL AND ends_on BETWEEN CURDATE() AND CURDATE() + INTERVAL 45 DAY) fin FROM contracts");
    $orgs = all("SELECT id, name FROM organizers WHERE status <> 'suspended' ORDER BY name");
    $edit = input('edit') ? one('SELECT * FROM contracts WHERE id = ?', [(int)input('edit')]) : null;
    $preOrg = (int)input('org');
    return admin_page('Contrats & tarifs', 'contracts', compact('list', 'counts', 'orgs', 'edit', 'f', 'preOrg'), 'contrats');
}

/** Retenues d'un organisateur (ajustement manuel, avoir) depuis sa fiche. */
function admin_org_charge(string $id): never
{
    $u = require_super();
    $org = one('SELECT * FROM organizers WHERE id = ?', [(int)$id]);
    if (!$org) abort(404);
    if (input('action') === 'delete') {
        q("DELETE FROM org_charges WHERE id = ? AND organizer_id = ? AND kind IN ('ajustement','avoir')", [(int)input('charge_id'), $org['id']]);
        back(['success' => 'Ligne supprimée.']);
    }
    $kind = input('kind') === 'avoir' ? 'avoir' : 'ajustement';
    $amount = parse_money(input('amount'));
    if ($amount <= 0 || trim((string)input('label')) === '') back(['error' => 'Indique un libellé et un montant.']);
    Billing::charge((int)$org['id'], $kind, trim((string)input('label')), $kind === 'avoir' ? -$amount : $amount, null, (int)$u['id']);
    Audit::log('charge.added', ($kind === 'avoir' ? 'Avoir ' : 'Retenue ') . money($amount) . ' : ' . input('label'), null, null, (int)$org['id']);
    back(['success' => ($kind === 'avoir' ? 'Avoir' : 'Retenue') . ' de ' . money($amount) . ' enregistré(e).']);
}
