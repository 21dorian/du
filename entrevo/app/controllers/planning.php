<?php
declare(strict_types=1);

/* Organisation d'événement : budget prévisionnel, tâches / rétroplanning, documents & contrats. */

require_once APP . '/controllers/pro.php';

const BUDGET_EXPENSES = ['Artistes & cachets', 'Technique (son, lumière, scène)', 'Lieu & location', 'Sécurité & secours', 'Communication', 'Restauration & bar', 'Personnel', 'Assurance', 'Droits (SACEM, SACD…)', 'Transport & hébergement', 'Décoration', 'Divers'];
const BUDGET_INCOMES = ['Subventions', 'Partenaires & sponsors', 'Bar & restauration', 'Merchandising', 'Mécénat & dons', 'Autres recettes'];
const DOC_CATEGORIES = ['contrat' => '🎤 Contrat artiste', 'devis' => '🧾 Devis / facture fournisseur', 'assurance' => '☂️ Assurance', 'autorisation' => '🏛️ Autorisation (mairie, préfecture)', 'droits' => '🎼 SACEM / droits', 'technique' => '🔧 Fiche technique / plan', 'securite' => '🦺 Sécurité', 'autre' => '📎 Autre'];

// ---------- Budget ----------
/** Recettes de billetterie (part organisateur) d'un événement : ventes en ligne nettes + guichet. */
function budget_ticketing(int $eventId): array
{
    $r = one("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN method IN ('online','partner') THEN organizer_amount ELSE total - refunded_amount END),0) net,
        COALESCE(SUM((SELECT SUM(qty) FROM order_items i WHERE i.order_id = orders.id AND i.kind <> 'product')),0) tickets FROM orders WHERE event_id = ? AND status = 'paid' AND (channel IS NULL OR channel <> 'systeme')", [$eventId]);
    return ['net' => (int)$r['net'], 'tickets' => (int)$r['tickets'], 'avg' => $r['tickets'] ? intdiv((int)$r['net'], (int)$r['tickets']) : 0];
}

function budget_summary(array $ev): array
{
    $lines = all('SELECT * FROM budget_lines WHERE event_id = ? ORDER BY kind, sort, id', [$ev['id']]);
    $t = ['exp_p' => 0, 'exp_a' => 0, 'inc_p' => 0, 'inc_a' => 0];
    foreach ($lines as $l) {
        $k = $l['kind'] === 'income' ? 'inc' : 'exp';
        $t[$k . '_p'] += (int)$l['planned'];
        $t[$k . '_a'] += $l['actual'] !== null ? (int)$l['actual'] : (int)$l['planned'];
    }
    $tk = budget_ticketing((int)$ev['id']);
    $avg = $tk['avg'] ?: (int)val('SELECT COALESCE(AVG(price),0) FROM rates WHERE event_id = ? AND active = 1 AND price > 0', [$ev['id']]);
    $toCover = max(0, $t['exp_a'] - $t['inc_a']);
    $breakEven = $avg > 0 ? (int)ceil($toCover / $avg) : null;
    return $t + ['lines' => $lines, 'ticketing' => $tk, 'avg' => $avg, 'break_even' => $breakEven,
        'result_planned' => $t['inc_p'] - $t['exp_p'], 'result' => $t['inc_a'] + $tk['net'] - $t['exp_a']];
}

function budget_tab(string $id): string
{
    $org = require_org(['owner', 'manager']);
    $ev = org_event((int)$id);
    if (is_post()) {
        $a = (string)input('action');
        $line = input('line_id') ? one('SELECT * FROM budget_lines WHERE id = ? AND event_id = ?', [(int)input('line_id'), $ev['id']]) : null;
        if ($a === 'save') {
            $kind = input('kind') === 'income' ? 'income' : 'expense';
            $d = ['kind' => $kind, 'category' => mb_substr(trim((string)input('category')), 0, 60) ?: 'Divers', 'label' => mb_substr(trim((string)input('label')), 0, 160),
                'planned' => max(0, parse_money((string)input('planned'))), 'actual' => trim((string)input('actual')) === '' ? null : max(0, parse_money((string)input('actual'))),
                'paid' => checked('paid'), 'supplier' => mb_substr(trim((string)input('supplier')), 0, 120) ?: null, 'note' => mb_substr(trim((string)input('note')), 0, 500) ?: null];
            if ($d['label'] === '') back(['error' => 'Donne un intitulé à la ligne.']);
            if ($line) update('budget_lines', $d, 'id = ?', [$line['id']]);
            else insert('budget_lines', $d + ['event_id' => $ev['id'], 'created_at' => now(), 'sort' => (int)val('SELECT COALESCE(MAX(sort),0)+1 FROM budget_lines WHERE event_id = ?', [$ev['id']])]);
            back(['success' => $line ? '✅ Ligne modifiée.' : '➕ Ligne ajoutée.']);
        }
        if ($a === 'delete' && $line) { q('DELETE FROM budget_lines WHERE id = ?', [$line['id']]); back(['success' => '🗑️ Ligne supprimée.']); }
        if ($a === 'template') {
            $tpl = ['Artistes & cachets' => 'Cachet tête d\'affiche', 'Technique (son, lumière, scène)' => 'Location son et lumière', 'Lieu & location' => 'Location de la salle', 'Sécurité & secours' => 'Agents de sécurité',
                'Communication' => 'Affiches et réseaux sociaux', 'Assurance' => 'Responsabilité civile organisateur', 'Droits (SACEM, SACD…)' => 'SACEM'];
            $i = 0; foreach ($tpl as $cat => $label) insert('budget_lines', ['event_id' => $ev['id'], 'kind' => 'expense', 'category' => $cat, 'label' => $label, 'planned' => 0, 'sort' => ++$i, 'created_at' => now()]);
            back(['success' => '🧩 Postes de base ajoutés : renseigne les montants.']);
        }
        back();
    }
    if (input('csv')) {
        $s = budget_summary($ev);
        $rows = [['Type', 'Catégorie', 'Intitulé', 'Fournisseur', 'Prévu', 'Réel', 'Payé', 'Note']];
        foreach ($s['lines'] as $l) $rows[] = [$l['kind'] === 'income' ? 'Recette' : 'Dépense', $l['category'], $l['label'], $l['supplier'], money_input((int)$l['planned']), $l['actual'] !== null ? money_input((int)$l['actual']) : '', $l['paid'] ? 'Oui' : 'Non', $l['note']];
        $rows[] = ['Recette', 'Billetterie', 'Ventes (part organisateur)', '', '', money_input($s['ticketing']['net']), '', ''];
        csv_download('budget-' . $ev['slug'] . '.csv', $rows);
    }
    $s = budget_summary($ev);
    $edit = input('modifier') ? one('SELECT * FROM budget_lines WHERE id = ? AND event_id = ?', [(int)input('modifier'), $ev['id']]) : null;
    $cap = (int)$ev['capacity'];
    return ev_page($ev, 'budget', 'Budget', 'budget', compact('s', 'edit', 'cap'));
}

function budget_overview(): string
{
    $org = require_org(['owner', 'manager']);
    $events = all('SELECT * FROM events WHERE organizer_id = ? ORDER BY starts_at DESC LIMIT 60', [$org['id']]);
    $rows = [];
    foreach ($events as $ev) { $s = budget_summary($ev); if ($s['lines'] || $s['ticketing']['net']) $rows[] = ['ev' => $ev, 's' => $s]; }
    return pro_page('Budget', 'budget_overview', compact('rows', 'events'), 'budget');
}

// ---------- Tâches ----------
const TASK_STATUS = ['todo' => '📝 À faire', 'doing' => '⏳ En cours', 'done' => '✅ Fait'];

/** Modèles de rétroplanning : [jours avant l'événement, intitulé]. */
function task_templates(): array
{
    return [
        'concert' => ['🎸 Concert / soirée', [[-60, 'Confirmer la date et signer le contrat de salle'], [-55, 'Contrats artistes et fiches techniques'], [-50, 'Déclarer l\'événement (SACEM)'], [-45, 'Ouvrir la billetterie'], [-40, 'Affiche et visuels réseaux sociaux'],
            [-30, 'Campagne e-mail aux anciens participants'], [-21, 'Réserver son, lumière et backline'], [-14, 'Prévoir la sécurité et les secours'], [-10, 'Brief bénévoles et planning du jour J'], [-7, 'Campagne « dernières places »'],
            [-3, 'Créer les comptes contrôleurs et tester le scan'], [-1, 'Imprimer la liste de contrôle de secours'], [0, 'Jour J : ouverture des portes'], [2, 'Payer les prestataires et faire le bilan']]],
        'festival' => ['🎪 Festival', [[-180, 'Valider le budget prévisionnel'], [-150, 'Demander les autorisations (mairie, préfecture)'], [-120, 'Programmation et contrats artistes'], [-100, 'Dossiers de subventions'], [-90, 'Ouverture des préventes (early bird)'],
            [-75, 'Recherche de partenaires et sponsors'], [-60, 'Assurance de l\'événement'], [-45, 'Recrutement des bénévoles'], [-30, 'Plan du site, signalétique, sécurité'], [-21, 'Commande bar, food-trucks et merch'],
            [-14, 'Planning des bénévoles et des équipes'], [-7, 'Test du contrôle d\'accès et des terminaux de paiement'], [-1, 'Montage et briefing général'], [0, 'Jour J'], [7, 'Bilan financier et bilan pour les financeurs']]],
        'conference' => ['🎤 Conférence / salon', [[-90, 'Réserver le lieu'], [-75, 'Confirmer les intervenants'], [-60, 'Ouvrir les inscriptions'], [-45, 'Programme détaillé en ligne'], [-30, 'Relance des inscrits et des partenaires'],
            [-14, 'Badges et signalétique'], [-7, 'Rappel aux participants'], [-1, 'Installation et tests techniques'], [0, 'Jour J : accueil'], [3, 'Questionnaire de satisfaction']]],
    ];
}

function tasks_page(?string $eventId = null): string
{
    $org = require_org(['owner', 'manager']);
    $oid = (int)$org['id'];
    $ev = $eventId !== null ? org_event((int)$eventId) : null;
    if (is_post()) {
        $a = (string)input('action');
        $t = input('task_id') ? one('SELECT * FROM tasks WHERE id = ? AND organizer_id = ?', [(int)input('task_id'), $oid]) : null;
        if ($a === 'move' && $t) {
            $st = (string)input('status');
            if (isset(TASK_STATUS[$st])) update('tasks', ['status' => $st, 'done_at' => $st === 'done' ? now() : null], 'id = ?', [$t['id']]);
            if (input('ajax')) json_out(['ok' => true]);
            back();
        }
        if ($a === 'save') {
            $evId = (int)input('event_id') ?: null;
            if ($evId && !val('SELECT id FROM events WHERE id = ? AND organizer_id = ?', [$evId, $oid])) $evId = null;
            $assignee = (int)input('assignee_id') ?: null;
            if ($assignee && !val('SELECT id FROM users WHERE id = ? AND organizer_id = ?', [$assignee, $oid])) $assignee = null;
            $d = ['title' => mb_substr(trim((string)input('title')), 0, 190), 'description' => trim((string)input('description')) ?: null, 'event_id' => $evId, 'assignee_id' => $assignee,
                'due_date' => preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('due_date')) ? (string)input('due_date') : null, 'priority' => checked('priority')];
            if ($d['title'] === '') back(['error' => 'Il faut un intitulé.']);
            if ($t) update('tasks', $d, 'id = ?', [$t['id']]);
            else {
                insert('tasks', $d + ['organizer_id' => $oid, 'status' => 'todo', 'created_by' => user()['id'], 'created_at' => now()]);
                if ($assignee && $assignee !== (int)user()['id'] && ($u = one('SELECT * FROM users WHERE id = ?', [$assignee])) && is_email((string)$u['email'])) {
                    try { Mailer::send($u['email'], '✅ Nouvelle tâche : ' . $d['title'], '<p>' . e(user()['name']) . ' t\'a confié une tâche : <b>' . e($d['title']) . '</b>' . ($d['due_date'] ? ' pour le ' . e(date('d/m/Y', strtotime($d['due_date']))) : '') . '.</p><p><a href="' . e(abs_url('/pro/taches')) . '">Voir mes tâches</a></p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
                }
            }
            back(['success' => $t ? '✅ Tâche modifiée.' : '➕ Tâche ajoutée.']);
        }
        if ($a === 'delete' && $t) { q('DELETE FROM tasks WHERE id = ?', [$t['id']]); back(['success' => '🗑️ Tâche supprimée.']); }
        if ($a === 'template') {
            $tpl = task_templates()[(string)input('template')] ?? null;
            $target = one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [(int)input('event_id'), $oid]);
            if (!$tpl || !$target) back(['error' => 'Choisis un modèle et un événement.']);
            $start = strtotime(substr($target['starts_at'], 0, 10));
            foreach ($tpl[1] as $i => [$days, $title]) insert('tasks', ['organizer_id' => $oid, 'event_id' => $target['id'], 'title' => $title, 'due_date' => date('Y-m-d', $start + $days * 86400),
                'status' => 'todo', 'sort' => $i, 'created_by' => user()['id'], 'created_at' => now()]);
            back(['success' => '🗓️ Rétroplanning « ' . $tpl[0] . ' » ajouté : ' . count($tpl[1]) . ' tâches calées sur la date de l\'événement.']);
        }
        back();
    }
    $f = ['ev' => $ev ? (int)$ev['id'] : (int)input('ev'), 'who' => (string)input('qui'), 'view' => input('vue') === 'liste' ? 'liste' : 'tableau'];
    $w = 't.organizer_id = ?'; $p = [$oid];
    if ($f['ev']) { $w .= ' AND t.event_id = ?'; $p[] = $f['ev']; }
    if ($f['who'] === 'moi') { $w .= ' AND t.assignee_id = ?'; $p[] = user()['id']; }
    $tasks = all("SELECT t.*, e.name ev_name, u.name assignee FROM tasks t LEFT JOIN events e ON e.id = t.event_id LEFT JOIN users u ON u.id = t.assignee_id WHERE $w
        ORDER BY t.status = 'done', t.due_date IS NULL, t.due_date, t.priority DESC, t.sort, t.id LIMIT 1000", $p);
    $events = all('SELECT id, name, starts_at FROM events WHERE organizer_id = ? ORDER BY starts_at DESC', [$oid]);
    $users = all('SELECT id, name FROM users WHERE organizer_id = ? AND active = 1 ORDER BY name', [$oid]);
    $edit = input('modifier') ? one('SELECT * FROM tasks WHERE id = ? AND organizer_id = ?', [(int)input('modifier'), $oid]) : null;
    $vars = compact('tasks', 'events', 'users', 'f', 'edit', 'ev');
    return $ev ? ev_page($ev, 'tasks', 'Tâches', 'tasks', $vars) : pro_page('Tâches & rétroplanning', 'tasks', $vars, 'taches');
}

function tasks_event(string $id): string { return tasks_page($id); }

// ---------- Documents ----------
function documents_page(?string $eventId = null): string
{
    $org = require_org(['owner', 'manager']);
    $oid = (int)$org['id'];
    $ev = $eventId !== null ? org_event((int)$eventId) : null;
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'upload') {
            $evId = (int)input('event_id') ?: null;
            if ($evId && !val('SELECT id FROM events WHERE id = ? AND organizer_id = ?', [$evId, $oid])) $evId = null;
            try { $file = store_private_upload('file', 'docs/' . $oid, 20); } catch (RuntimeException $e) { back(['error' => $e->getMessage()]); }
            insert('documents', ['organizer_id' => $oid, 'event_id' => $evId, 'title' => mb_substr(trim((string)input('title')), 0, 190) ?: pathinfo($file['original'], PATHINFO_FILENAME),
                'category' => isset(DOC_CATEGORIES[(string)input('category')]) ? (string)input('category') : 'autre', 'file' => $file['file'], 'original_name' => $file['original'], 'mime' => $file['mime'], 'size' => $file['size'],
                'expires_at' => preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('expires_at')) ? (string)input('expires_at') : null, 'note' => mb_substr(trim((string)input('note')), 0, 500) ?: null,
                'uploaded_by' => user()['id'], 'created_at' => now()]);
            back(['success' => '📎 Document ajouté.']);
        }
        $d = one('SELECT * FROM documents WHERE id = ? AND organizer_id = ?', [(int)input('doc_id'), $oid]);
        if ($a === 'delete' && $d) { delete_private_file($d['file']); q('DELETE FROM documents WHERE id = ?', [$d['id']]); back(['success' => '🗑️ Document supprimé.']); }
        back();
    }
    $f = ['ev' => $ev ? (int)$ev['id'] : (int)input('ev'), 'cat' => isset(DOC_CATEGORIES[(string)input('cat')]) ? (string)input('cat') : '', 'q' => trim((string)input('q'))];
    $w = 'd.organizer_id = ?'; $p = [$oid];
    if ($f['ev']) { $w .= ' AND d.event_id = ?'; $p[] = $f['ev']; }
    if ($f['cat']) { $w .= ' AND d.category = ?'; $p[] = $f['cat']; }
    if ($f['q'] !== '') { $w .= ' AND (d.title LIKE ? OR d.original_name LIKE ? OR d.note LIKE ?)'; array_push($p, '%' . $f['q'] . '%', '%' . $f['q'] . '%', '%' . $f['q'] . '%'); }
    $docs = all("SELECT d.*, e.name ev_name, u.name uploader FROM documents d LEFT JOIN events e ON e.id = d.event_id LEFT JOIN users u ON u.id = d.uploaded_by WHERE $w ORDER BY d.expires_at IS NULL, d.expires_at, d.created_at DESC LIMIT 500", $p);
    $events = all('SELECT id, name FROM events WHERE organizer_id = ? ORDER BY starts_at DESC', [$oid]);
    $usage = (int)val('SELECT COALESCE(SUM(size),0) FROM documents WHERE organizer_id = ?', [$oid]);
    $vars = compact('docs', 'events', 'f', 'ev', 'usage');
    return $ev ? ev_page($ev, 'docs', 'Documents', 'documents', $vars) : pro_page('Documents & contrats', 'documents', $vars, 'documents');
}

function documents_event(string $id): string { return documents_page($id); }

function document_file(string $id): never
{
    $org = require_org(['owner', 'manager']);
    $d = one('SELECT * FROM documents WHERE id = ? AND organizer_id = ?', [(int)$id, $org['id']]) ?: abort(404);
    send_private_file($d['file'], $d['original_name'] ?: 'document', $d['mime'] ?: 'application/octet-stream', !input('dl'));
}
