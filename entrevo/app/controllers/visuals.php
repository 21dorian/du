<?php
declare(strict_types=1);

/* 2.4 : visuels réseaux sociaux — composés dans le navigateur (canvas), exportés en PNG. */

require_once APP . '/controllers/pro.php';

function visuals_tab(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    $min = val("SELECT MIN(price) FROM rates WHERE event_id = ? AND active = 1 AND hidden = 0", [$ev['id']]);
    $sold = (int)val("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status = 'valid'", [$ev['id']]);
    $cap = (int)$ev['capacity'];
    $url = abs_url('/e/' . $ev['slug'], ['utm_source' => 'reseaux', 'utm_medium' => 'visuel']);
    $data = ['name' => $ev['name'], 'date' => fr_date($ev['starts_at']), 'venue' => trim(($ev['venue'] ?? '') . ($ev['city'] ? ', ' . $ev['city'] : ''), ', '),
        'price' => $min === null ? '' : ((int)$min === 0 ? 'Gratuit' : 'Dès ' . money((int)$min)), 'image' => $ev['image'] ? url($ev['image']) : '',
        'url' => preg_replace('~^https?://~', '', abs_url('/e/' . $ev['slug'])), 'org' => (string)current_org()['name'],
        'fill' => $cap ? round($sold / $cap * 100) : null, 'left' => $cap ? max(0, $cap - $sold) : null,
        'qr' => 'data:image/svg+xml;base64,' . base64_encode(Qr::svg($url, 600)), 'accent' => (string)(current_org()['accent'] ?? '') ?: '#1d1d1f'];
    return ev_page($ev, 'visuals', 'Visuels réseaux sociaux', 'visuals', ['data' => $data]);
}

function visuals_choose(): never
{
    $org = require_org();
    $ev = one("SELECT id FROM events WHERE organizer_id = ? AND COALESCE(ends_at, starts_at) >= NOW() ORDER BY starts_at LIMIT 1", [$org['id']])
        ?: one('SELECT id FROM events WHERE organizer_id = ? ORDER BY starts_at DESC LIMIT 1', [$org['id']]);
    if (!$ev) redirect('/pro/evenements', ['info' => 'Crée d\'abord un événement.']);
    redirect('/pro/evenements/' . $ev['id'] . '/visuels');
}
