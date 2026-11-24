<?php
declare(strict_types=1);

/* PC sécurité : écran de supervision plein écran, données en direct, messages aux contrôleurs. */

function pc_page(string $id): string
{
    require_org(['owner', 'manager']);
    $ev = org_event((int)$id);
    return view('pro/pc', ['ev' => $ev, 'snap' => Pc::snapshot($ev)]);
}

function pc_data(string $id): never
{
    require_org(['owner', 'manager']);
    $ev = org_event((int)$id);
    if (is_post() && in_array(input('action'), ['incident', 'resolve', 'reopen'], true)) {
        if (input('action') === 'incident') {
            $title = mb_substr(trim((string)input('title')), 0, 190);
            if ($title === '') json_out(['ok' => false]);
            insert('incidents', ['event_id' => $ev['id'], 'level' => in_array(input('level'), ['info', 'warn', 'alert'], true) ? (string)input('level') : 'info',
                'category' => array_key_exists((string)input('category'), PC_CATEGORIES) ? (string)input('category') : 'autre', 'zone' => mb_substr(trim((string)input('zone')), 0, 120) ?: null,
                'title' => $title, 'note' => mb_substr(trim((string)input('note')), 0, 2000) ?: null, 'author' => mb_substr((string)(user()['name'] ?? ''), 0, 120), 'status' => 'open', 'created_at' => now()]);
        } else {
            update('incidents', input('action') === 'resolve' ? ['status' => 'closed', 'resolved_at' => now()] : ['status' => 'open', 'resolved_at' => null], 'id = ? AND event_id = ?', [(int)input('id'), $ev['id']]);
        }
        json_out(['ok' => true]);
    }
    if (is_post()) {
        $body = mb_substr(trim((string)input('body')), 0, 280);
        if ($body !== '') insert('scan_messages', ['event_id' => $ev['id'], 'body' => $body, 'level' => in_array(input('level'), ['info', 'warn', 'alert'], true) ? (string)input('level') : 'info', 'user_id' => user()['id'], 'created_at' => now()]);
        json_out(['ok' => $body !== '']);
    }
    $snap = Pc::snapshot($ev);
    Pc::alerts($ev, $snap);
    $snap['messages'] = array_map(fn($m) => ['at' => substr($m['created_at'], 11, 5), 'body' => $m['body'], 'level' => $m['level']],
        all('SELECT * FROM scan_messages WHERE event_id = ? ORDER BY id DESC LIMIT 6', [$ev['id']]));
    $snap['incidents'] = array_map(fn($i) => ['id' => (int)$i['id'], 'at' => substr($i['created_at'], 11, 5), 'level' => $i['level'], 'cat' => PC_CATEGORIES[$i['category']] ?? $i['category'],
        'zone' => (string)$i['zone'], 'title' => $i['title'], 'note' => (string)$i['note'], 'author' => (string)$i['author'], 'open' => $i['status'] === 'open', 'closed_at' => $i['resolved_at'] ? substr($i['resolved_at'], 11, 5) : null],
        all("SELECT * FROM incidents WHERE event_id = ? ORDER BY status = 'open' DESC, id DESC LIMIT 30", [$ev['id']]));
    $snap['incidents_open'] = (int)val("SELECT COUNT(*) FROM incidents WHERE event_id = ? AND status = 'open'", [$ev['id']]);
    json_out($snap);
}

const PC_CATEGORIES = ['secours' => 'Secours', 'securite' => 'Sécurité', 'acces' => 'Accès', 'technique' => 'Technique', 'perdu' => 'Objet / enfant perdu', 'autre' => 'Autre'];

/** Main courante : export CSV de tous les incidents de l'événement. */
function pc_incidents_csv(string $id): never
{
    require_org(['owner', 'manager']);
    $ev = org_event((int)$id);
    $rows = [['Heure', 'Niveau', 'Catégorie', 'Zone', 'Titre', 'Détail', 'Saisi par', 'Statut', 'Clos à']];
    foreach (all('SELECT * FROM incidents WHERE event_id = ? ORDER BY id', [$ev['id']]) as $i)
        $rows[] = [$i['created_at'], $i['level'], strip_tags(PC_CATEGORIES[$i['category']] ?? $i['category']), $i['zone'], $i['title'], $i['note'], $i['author'], $i['status'] === 'open' ? 'en cours' : 'clos', $i['resolved_at']];
    csv_download('main-courante-' . $ev['slug'] . '.csv', $rows);
}

/** Menu « PC sécurité » : ouvre l'événement en cours ou le prochain. */
function pc_choose(): never
{
    $org = require_org(['owner', 'manager']);
    $ev = one("SELECT id FROM events WHERE organizer_id = ? AND COALESCE(ends_at, starts_at + INTERVAL 6 HOUR) >= NOW() ORDER BY starts_at LIMIT 1", [$org['id']]);
    if (!$ev) redirect('/pro/evenements', ['info' => 'Aucun événement à venir à superviser.']);
    redirect('/pro/evenements/' . $ev['id'] . '/pc');
}
