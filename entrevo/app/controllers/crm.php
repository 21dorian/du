<?php
declare(strict_types=1);

/* 2.3 : super-admin › prospection — pipeline des organisateurs à convaincre. */

require_once APP . '/controllers/admin.php';

const CRM_STAGES = ['lead' => 'À contacter', 'contacted' => 'Contacté', 'demo' => 'Démo faite', 'proposal' => 'Proposition', 'won' => 'Gagné', 'lost' => 'Perdu'];
const CRM_KINDS = ['festival' => 'Festival', 'salle' => 'Salle / théâtre', 'asso' => 'Association', 'sport' => 'Sport', 'pro' => 'Salon / pro', 'autre' => 'Autre'];

function admin_crm(): string
{
    $u = require_super();
    if (is_post()) {
        $a = (string)input('action');
        $p = (int)input('prospect_id') ? one('SELECT * FROM prospects WHERE id = ?', [(int)input('prospect_id')]) : null;
        if ($a === 'move' && $p) {
            $st = array_key_exists((string)input('stage'), CRM_STAGES) ? (string)input('stage') : $p['stage'];
            update('prospects', ['stage' => $st, 'updated_at' => now(), 'lost_reason' => $st === 'lost' ? (trim((string)input('lost_reason')) ?: $p['lost_reason']) : null], 'id = ?', [$p['id']]);
            insert('prospect_notes', ['prospect_id' => $p['id'], 'user_id' => $u['id'], 'kind' => 'stage', 'body' => CRM_STAGES[$p['stage']] . ' → ' . CRM_STAGES[$st], 'created_at' => now()]);
            if (input('ajax')) json_out(['ok' => true]);
            back();
        }
        if ($a === 'note' && $p) {
            $b = trim((string)input('body'));
            if ($b !== '') insert('prospect_notes', ['prospect_id' => $p['id'], 'user_id' => $u['id'], 'kind' => in_array(input('kind'), ['call', 'mail', 'meeting', 'note'], true) ? (string)input('kind') : 'note', 'body' => mb_substr($b, 0, 3000), 'created_at' => now()]);
            update('prospects', ['next_action' => trim((string)input('next_action')) ?: null, 'next_action_on' => input('next_action_on') ?: null, 'updated_at' => now()], 'id = ?', [$p['id']]);
            back(['success' => 'Suivi enregistré.']);
        }
        if ($a === 'link' && $p) {
            $oid = (int)input('organizer_id');
            if ($oid && val('SELECT id FROM organizers WHERE id = ?', [$oid])) { update('prospects', ['organizer_id' => $oid, 'stage' => 'won', 'updated_at' => now()], 'id = ?', [$p['id']]); back(['success' => 'Prospect relié à son compte organisateur : gagné !']); }
            back();
        }
        if ($a === 'invite' && $p) {
            if (!is_email((string)$p['email'])) back(['error' => 'Ajoute l\'e-mail du contact.']);
            try { Mailer::send((string)$p['email'], 'Ta billetterie sur ' . setting('platform_name'), '<p>Bonjour ' . e(strtok((string)($p['contact_name'] ?: $p['name']), ' ')) . ',</p><p>' . nl2br(e(trim((string)input('body')) ?: 'Comme convenu, voici le lien pour créer ton espace et ouvrir ta billetterie en quelques minutes.')) . '</p>'
                . '<p><a href="' . e(abs_url('/inscription', ['ref' => 'p' . $p['id']])) . '" style="display:inline-block;background:#1d1d1f;color:#fff;padding:12px 22px;border-radius:980px;text-decoration:none">Créer mon espace organisateur</a></p>', [], (string)($u['email'] ?? '')); }
            catch (Throwable $e) { back(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
            insert('prospect_notes', ['prospect_id' => $p['id'], 'user_id' => $u['id'], 'kind' => 'mail', 'body' => 'Invitation à créer son espace envoyée.', 'created_at' => now()]);
            if ($p['stage'] === 'lead') update('prospects', ['stage' => 'contacted', 'updated_at' => now()], 'id = ?', [$p['id']]);
            back(['success' => 'Invitation envoyée à ' . $p['email'] . '.']);
        }
        if ($a === 'delete' && $p) { q('DELETE FROM prospect_notes WHERE prospect_id = ?', [$p['id']]); q('DELETE FROM prospects WHERE id = ?', [$p['id']]); redirect('/admin/prospection', ['success' => 'Prospect supprimé.']); }
        if ($a === 'import') {
            $n = 0;
            foreach (preg_split('~\R~', (string)input('csv')) as $line) {
                $c = str_getcsv(str_replace("\t", ';', $line), ';');
                if (count($c) < 1 || trim($c[0]) === '' || mb_strtolower(trim($c[0])) === 'nom') continue;
                insert('prospects', ['name' => mb_substr(trim($c[0]), 0, 190), 'contact_name' => trim($c[1] ?? '') ?: null, 'email' => is_email(trim($c[2] ?? '')) ? strtolower(trim($c[2])) : null, 'phone' => trim($c[3] ?? '') ?: null,
                    'city' => trim($c[4] ?? '') ?: null, 'source' => 'import', 'stage' => 'lead', 'owner_id' => $u['id'], 'created_at' => now(), 'updated_at' => now()]); $n++;
            }
            back(['success' => $n . ' prospect(s) importé(s).']);
        }
        // création / modification
        $d = ['name' => mb_substr(trim((string)input('name')), 0, 190), 'kind' => array_key_exists((string)input('kind'), CRM_KINDS) ? (string)input('kind') : null, 'contact_name' => trim((string)input('contact_name')) ?: null,
            'email' => is_email(strtolower(trim((string)input('email')))) ? strtolower(trim((string)input('email'))) : null, 'phone' => trim((string)input('phone')) ?: null, 'city' => trim((string)input('city')) ?: null,
            'website' => trim((string)input('website_url')) ?: null, 'source' => trim((string)input('source')) ?: null, 'est_volume' => parse_money(input('est_volume')),
            'next_action' => trim((string)input('next_action')) ?: null, 'next_action_on' => input('next_action_on') ?: null, 'owner_id' => (int)input('owner_id') ?: $u['id'], 'updated_at' => now()];
        if ($d['name'] === '') back(['error' => 'Donne un nom au prospect.']);
        if ($p) update('prospects', $d, 'id = ?', [$p['id']]); else $p = ['id' => insert('prospects', $d + ['stage' => 'lead', 'created_at' => now()])];
        redirect('/admin/prospection?p=' . $p['id'], ['success' => 'Prospect enregistré.']);
    }
    $list = all('SELECT p.*, u.name owner, (SELECT MAX(created_at) FROM prospect_notes n WHERE n.prospect_id = p.id) last_touch FROM prospects p LEFT JOIN users u ON u.id = p.owner_id ORDER BY p.next_action_on IS NULL, p.next_action_on, p.updated_at DESC');
    $cols = array_fill_keys(array_keys(CRM_STAGES), []);
    foreach ($list as $p) $cols[$p['stage']][] = $p;
    $sel = input('p') === 'new' ? [] : (input('p') ? one('SELECT * FROM prospects WHERE id = ?', [(int)input('p')]) : null);
    $notes = $sel ? all('SELECT n.*, u.name FROM prospect_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.prospect_id = ? ORDER BY n.id DESC', [$sel['id']]) : [];
    $today = array_values(array_filter($list, fn($p) => $p['next_action_on'] && $p['next_action_on'] <= date('Y-m-d') && !in_array($p['stage'], ['won', 'lost'], true)));
    $k = ['open' => count(array_filter($list, fn($p) => !in_array($p['stage'], ['won', 'lost'], true))), 'won' => count($cols['won']), 'lost' => count($cols['lost']),
        'pipe' => array_sum(array_map(fn($p) => (int)$p['est_volume'], array_filter($list, fn($p) => in_array($p['stage'], ['demo', 'proposal'], true))))];
    $agents = all("SELECT id, name FROM users WHERE role = 'superadmin' AND active = 1");
    $orgs = all('SELECT id, name FROM organizers ORDER BY created_at DESC LIMIT 100');
    return admin_page('Prospection', 'crm', compact('cols', 'sel', 'notes', 'today', 'k', 'agents', 'orgs'), 'prospection');
}
