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
    if (is_post()) {
        $body = mb_substr(trim((string)input('body')), 0, 280);
        if ($body !== '') insert('scan_messages', ['event_id' => $ev['id'], 'body' => $body, 'level' => in_array(input('level'), ['info', 'warn', 'alert'], true) ? (string)input('level') : 'info', 'user_id' => user()['id'], 'created_at' => now()]);
        json_out(['ok' => $body !== '']);
    }
    $snap = Pc::snapshot($ev);
    Pc::alerts($ev, $snap);
    $snap['messages'] = array_map(fn($m) => ['at' => substr($m['created_at'], 11, 5), 'body' => $m['body'], 'level' => $m['level']],
        all('SELECT * FROM scan_messages WHERE event_id = ? ORDER BY id DESC LIMIT 6', [$ev['id']]));
    json_out($snap);
}

/** Menu « PC sécurité » : ouvre l'événement en cours ou le prochain. */
function pc_choose(): never
{
    $org = require_org(['owner', 'manager']);
    $ev = one("SELECT id FROM events WHERE organizer_id = ? AND COALESCE(ends_at, starts_at + INTERVAL 6 HOUR) >= NOW() ORDER BY starts_at LIMIT 1", [$org['id']]);
    if (!$ev) redirect('/pro/evenements', ['info' => 'Aucun événement à venir à superviser.']);
    redirect('/pro/evenements/' . $ev['id'] . '/pc');
}
