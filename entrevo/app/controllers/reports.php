<?php
declare(strict_types=1);

/* 2.5 : organisateur › Bilans & subventions. */

require_once APP . '/controllers/pro.php';

function pro_reports(): string
{
    $org = require_org(['owner', 'manager']);
    require_module('bilans');
    if (is_post()) {
        $title = mb_substr(trim((string)input('title')), 0, 190);
        $ids = array_map('intval', (array)($_POST['events'] ?? []));
        $from = (string)input('from'); $to = (string)input('to');
        if (!$ids && preg_match('~^\d{4}-\d{2}-\d{2}$~', $from) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $to)) $ids = Reports::eventsBetween((int)$org['id'], $from, $to);
        $ids = array_map('intval', array_column(all('SELECT id FROM events WHERE organizer_id = ? AND id IN (' . (implode(',', $ids ?: [0])) . ')', [$org['id']]), 'id'));
        if (!$ids) back(['error' => 'Choisis au moins un événement (ou une période qui en contient).']);
        if ($title === '') $title = count($ids) === 1 ? 'Bilan · ' . val('SELECT name FROM events WHERE id = ?', [$ids[0]]) : 'Bilan de saison ' . date('Y');
        $id = insert('reports', ['organizer_id' => $org['id'], 'title' => $title, 'event_ids' => implode(',', $ids), 'date_from' => $from ?: null, 'date_to' => $to ?: null, 'sections_json' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        redirect('/pro/bilans/' . $id, ['success' => 'Bilan créé : les chiffres sont prêts, ajoute tes textes.']);
    }
    $list = all('SELECT * FROM reports WHERE organizer_id = ? ORDER BY updated_at DESC', [$org['id']]);
    foreach ($list as &$r) $r['n'] = count(Reports::eventIds($r));
    unset($r);
    $events = all("SELECT id, name, starts_at FROM events WHERE organizer_id = ? AND status <> 'draft' ORDER BY starts_at DESC LIMIT 200", [$org['id']]);
    return pro_page('Bilans & subventions', 'reports', compact('list', 'events'), 'subventions');
}

function pro_report(string $id): string
{
    $org = require_org(['owner', 'manager']);
    require_module('bilans');
    $r = one('SELECT * FROM reports WHERE id = ? AND organizer_id = ?', [(int)$id, $org['id']]);
    if (!$r) abort(404);
    if (is_post()) {
        if (input('action') === 'delete') { q('DELETE FROM reports WHERE id = ?', [$r['id']]); redirect('/pro/bilans', ['success' => 'Bilan supprimé.']); }
        $sec = [];
        foreach (array_keys(Reports::SECTIONS) as $k) $sec[$k] = mb_substr(trim((string)($_POST['s'][$k] ?? '')), 0, 8000);
        $ids = array_map('intval', array_column(all('SELECT id FROM events WHERE organizer_id = ? AND id IN (' . implode(',', array_map('intval', (array)($_POST['events'] ?? [])) ?: [0]) . ')', [$org['id']]), 'id'));
        update('reports', ['title' => mb_substr(trim((string)input('title')), 0, 190) ?: $r['title'], 'sections_json' => json_encode($sec, JSON_UNESCAPED_UNICODE), 'event_ids' => $ids ? implode(',', $ids) : $r['event_ids'], 'updated_at' => now()], 'id = ?', [$r['id']]);
        if (input('ajax')) json_out(['ok' => true, 'at' => date('H:i')]);
        back(['success' => 'Bilan enregistré.']);
    }
    $d = Reports::compute((int)$org['id'], Reports::eventIds($r));
    if (input('format') === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="bilan-' . (int)$r['id'] . '.pdf"');
        echo Reports::pdf($org, $r, $d); exit;
    }
    if (input('format') === 'csv') Reports::csv($r, $d);
    $events = all("SELECT id, name, starts_at FROM events WHERE organizer_id = ? AND status <> 'draft' ORDER BY starts_at DESC LIMIT 200", [$org['id']]);
    return pro_page($r['title'], 'report', ['r' => $r, 'd' => $d, 'sec' => Reports::sections($r), 'events' => $events, 'sel' => Reports::eventIds($r)], 'subventions');
}
