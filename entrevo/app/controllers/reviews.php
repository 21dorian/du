<?php
declare(strict_types=1);

/* 1.8 : avis des participants et réglages des envois automatiques (organisateur). */

require_once APP . '/controllers/pro.php';

function auto_settings(): never
{
    $org = require_org();
    update('organizers', ['auto_recovery' => checked('auto_recovery'), 'auto_reminder' => checked('auto_reminder'), 'auto_review' => checked('auto_review')], 'id = ?', [$org['id']]);
    Audit::log('org.auto', 'Envois automatiques modifiés', null, null, (int)$org['id']);
    redirect('/pro/campagnes', ['success' => '✅ Envois automatiques enregistrés.']);
}

function reviews_page(): string
{
    $org = require_org();
    $oid = (int)$org['id'];
    if (is_post()) {
        $id = (int)input('review_id');
        if (input('action') === 'toggle') q('UPDATE reviews SET visible = 1 - visible WHERE id = ? AND organizer_id = ?', [$id, $oid]);
        back(['success' => 'C\'est noté 👍']);
    }
    $evId = (int)input('ev');
    $note = (int)input('note');
    $w = 'r.organizer_id = ?'; $p = [$oid];
    if ($evId) { $w .= ' AND r.event_id = ?'; $p[] = $evId; }
    if ($note >= 1 && $note <= 5) { $w .= ' AND r.rating = ?'; $p[] = $note; }
    $list = all("SELECT r.*, e.name ev_name, o.ref, o.first_name, o.last_name, o.email FROM reviews r JOIN events e ON e.id = r.event_id JOIN orders o ON o.id = r.order_id WHERE $w ORDER BY r.created_at DESC LIMIT 300", $p);
    $kpi = one('SELECT COUNT(*) n, AVG(rating) avg, SUM(comment IS NOT NULL) comments, SUM(verified) verified FROM reviews WHERE organizer_id = ?' . ($evId ? ' AND event_id = ?' : ''), $evId ? [$oid, $evId] : [$oid]);
    $dist = array_fill(1, 5, 0);
    foreach (all('SELECT rating, COUNT(*) n FROM reviews WHERE organizer_id = ?' . ($evId ? ' AND event_id = ?' : '') . ' GROUP BY rating', $evId ? [$oid, $evId] : [$oid]) as $d) $dist[(int)$d['rating']] = (int)$d['n'];
    $asked = (int)val('SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND review_sent_at IS NOT NULL' . ($evId ? ' AND event_id = ?' : ''), $evId ? [$oid, $evId] : [$oid]);
    $byEvent = all('SELECT e.id, e.name, e.starts_at, COUNT(*) n, AVG(r.rating) avg FROM reviews r JOIN events e ON e.id = r.event_id WHERE r.organizer_id = ? GROUP BY e.id ORDER BY e.starts_at DESC LIMIT 20', [$oid]);
    $events = all('SELECT DISTINCT e.id, e.name FROM reviews r JOIN events e ON e.id = r.event_id WHERE r.organizer_id = ? ORDER BY e.starts_at DESC', [$oid]);
    return pro_page('Avis des participants', 'reviews', compact('org', 'list', 'kpi', 'dist', 'asked', 'byEvent', 'events', 'evId', 'note'), 'avis');
}
