<?php
declare(strict_types=1);

/* 2.3 : objets trouvés — registre de l'organisation, déclarations de perte publiques, rapprochement, restitution. */

require_once APP . '/controllers/public.php';

const LOST_CATS = ['telephone' => 'Téléphone', 'papiers' => 'Papiers, cartes', 'portefeuille' => 'Portefeuille, sac', 'cles' => 'Clés', 'vetement' => 'Vêtement', 'lunettes' => 'Lunettes', 'bijou' => 'Bijou, montre', 'electronique' => 'Électronique', 'autre' => 'Autre'];
const LOST_STATUS = ['found' => ['En stock', 'blue'], 'claimed' => ['Propriétaire trouvé', 'orange'], 'returned' => ['Rendu', 'green'], 'discarded' => ['Donné / détruit', 'grey']];

/** Déclarations qui ressemblent à un objet (même catégorie, même événement ou proche, mots en commun). */
function lost_matches(array $item, array $reports): array
{
    $words = fn($s) => array_filter(array_unique(preg_split('~[^\p{L}\p{N}]+~u', mb_strtolower((string)$s))), fn($w) => mb_strlen($w) >= 4);
    $iw = $words($item['title'] . ' ' . $item['description']);
    $out = [];
    foreach ($reports as $r) {
        if ($r['status'] !== 'open') continue;
        $s = 0;
        if ($r['category'] === $item['category']) $s += 3;
        if ($r['event_id'] && (int)$r['event_id'] === (int)$item['event_id']) $s += 2;
        $s += count(array_intersect($iw, $words($r['description'])));
        if ($s >= 4) $out[] = $r + ['score' => $s];
    }
    usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($out, 0, 3);
}

function pro_lost(): string
{
    require_once APP . '/controllers/pro.php';
    $org = require_org();
    $u = user();
    if (is_post()) {
        $a = (string)input('action');
        $item = (int)input('item_id') ? one('SELECT * FROM lost_items WHERE id = ? AND organizer_id = ?', [(int)input('item_id'), $org['id']]) : null;
        if ($a === 'settings') { update('organizers', ['lost_enabled' => checked('lost_enabled')], 'id = ?', [$org['id']]); back(['success' => 'Réglage enregistré.']); }
        if ($a === 'match' && $item) {
            $r = one('SELECT * FROM lost_reports WHERE id = ? AND organizer_id = ?', [(int)input('report_id'), $org['id']]);
            if (!$r) back();
            update('lost_items', ['status' => 'claimed', 'claimant' => $r['name'], 'claimant_email' => $r['email']], 'id = ?', [$item['id']]);
            update('lost_reports', ['status' => 'matched', 'item_id' => $item['id']], 'id = ?', [$r['id']]);
            try { Mailer::send($r['email'], 'Bonne nouvelle : on a peut-être retrouvé ton objet', '<p>Bonjour ' . e(strtok($r['name'], ' ')) . ',</p><p><b>' . e($org['name']) . '</b> a retrouvé un objet qui ressemble à ce que tu as déclaré : <b>' . e($item['title']) . '</b> (référence ' . e($item['ref']) . ').</p><p>' . nl2br(e(trim((string)input('how')) ?: 'Réponds à cet e-mail pour convenir de la restitution. Pense à décrire l\'objet précisément (couleur, contenu, signe distinctif) pour prouver qu\'il est à toi.')) . '</p>', [], $org['email'] ?? null); }
            catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            back(['success' => $r['name'] . ' est prévenu(e) par e-mail.']);
        }
        if ($a === 'status' && $item) {
            $st = array_key_exists((string)input('status'), LOST_STATUS) ? (string)input('status') : 'found';
            update('lost_items', ['status' => $st, 'returned_at' => $st === 'returned' ? now() : null, 'claimant' => trim((string)input('claimant')) ?: $item['claimant']], 'id = ?', [$item['id']]);
            if ($st === 'returned') q("UPDATE lost_reports SET status = 'closed' WHERE item_id = ?", [$item['id']]);
            back(['success' => 'Objet mis à jour.']);
        }
        if ($a === 'close_report') { q("UPDATE lost_reports SET status = 'closed' WHERE id = ? AND organizer_id = ?", [(int)input('report_id'), $org['id']]); back(); }
        // nouvel objet
        $title = mb_substr(trim((string)input('title')), 0, 190);
        if ($title === '') back(['error' => 'Décris l\'objet en quelques mots.']);
        $ev = (int)input('event_id') ? one('SELECT id FROM events WHERE id = ? AND organizer_id = ?', [(int)input('event_id'), $org['id']]) : null;
        $d = ['organizer_id' => $org['id'], 'event_id' => $ev['id'] ?? null, 'ref' => 'OT' . strtoupper(random_code(5)), 'title' => $title, 'category' => array_key_exists((string)input('category'), LOST_CATS) ? (string)input('category') : 'autre',
            'description' => mb_substr(trim((string)input('description')), 0, 1000) ?: null, 'found_where' => mb_substr(trim((string)input('found_where')), 0, 190) ?: null,
            'found_at' => dt_from_input(input('found_at')) ?: now(), 'status' => 'found', 'created_by' => $u['id'], 'created_at' => now()];
        try { if ($img = upload_image('photo')) $d['photo'] = $img; } catch (RuntimeException $e) { back(['error' => $e->getMessage()]); }
        insert('lost_items', $d);
        back(['success' => 'Objet enregistré sous la référence ' . $d['ref'] . '. Note-la sur l\'étiquette.']);
    }
    $f = (string)input('f', 'found');
    $where = array_key_exists($f, LOST_STATUS) ? ' AND i.status = ' . db()->quote($f) : '';
    $items = all("SELECT i.*, e.name ev_name FROM lost_items i LEFT JOIN events e ON e.id = i.event_id WHERE i.organizer_id = ?$where ORDER BY i.found_at DESC LIMIT 300", [$org['id']]);
    $reports = all("SELECT r.*, e.name ev_name FROM lost_reports r LEFT JOIN events e ON e.id = r.event_id WHERE r.organizer_id = ? AND r.status IN ('open','matched') ORDER BY r.id DESC LIMIT 200", [$org['id']]);
    foreach ($items as &$i) $i['matches'] = $i['status'] === 'found' ? lost_matches($i, $reports) : [];
    unset($i);
    $k = one("SELECT SUM(status = 'found') stock, SUM(status = 'claimed') claimed, SUM(status = 'returned') returned, COUNT(*) n FROM lost_items WHERE organizer_id = ?", [$org['id']]);
    $k['reports'] = count(array_filter($reports, fn($r) => $r['status'] === 'open'));
    $events = all('SELECT id, name, starts_at FROM events WHERE organizer_id = ? ORDER BY starts_at DESC LIMIT 30', [$org['id']]);
    return pro_page('Objets trouvés', 'lost', compact('org', 'items', 'reports', 'k', 'events', 'f'), 'objets-trouves');
}

function lost_public(string $slug): string
{
    $org = one("SELECT * FROM organizers WHERE slug = ? AND status = 'active'", [$slug]);
    if (!$org || !(int)($org['lost_enabled'] ?? 1)) abort(404);
    $errors = []; $done = false;
    $events = all("SELECT id, name, starts_at FROM events WHERE organizer_id = ? AND status <> 'draft' AND starts_at BETWEEN NOW() - INTERVAL 60 DAY AND NOW() + INTERVAL 1 DAY ORDER BY starts_at DESC", [$org['id']]);
    if (is_post()) {
        $d = ['name' => trim((string)input('name')), 'email' => strtolower(trim((string)input('email'))), 'phone' => trim((string)input('phone')) ?: null, 'description' => mb_substr(trim((string)input('description')), 0, 1000),
            'category' => array_key_exists((string)input('category'), LOST_CATS) ? (string)input('category') : 'autre', 'event_id' => in_array((int)input('event_id'), array_map('intval', array_column($events, 'id')), true) ? (int)input('event_id') : null];
        if ($d['name'] === '' || !is_email($d['email']) || mb_strlen($d['description']) < 5) $errors[] = 'Indique ton nom, ton e-mail et décris l\'objet.';
        if (trim((string)input('website')) !== '' || !throttle_ok_pub('perdu')) $errors[] = 'Requête refusée.';
        if (!$errors) {
            insert('lost_reports', $d + ['organizer_id' => $org['id'], 'status' => 'open', 'created_at' => now()]);
            try { Mailer::send((string)$org['email'], 'Objet perdu déclaré : ' . (LOST_CATS[$d['category']] ?? ''), '<p>' . e($d['name']) . ' a perdu : ' . nl2br(e($d['description'])) . '</p><p><a href="' . e(abs_url('/pro/objets-trouves')) . '">Voir les objets trouvés</a></p>', [], $d['email']); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            $done = true;
        }
    }
    $found = all("SELECT i.title, i.category, i.found_at, e.name ev_name FROM lost_items i LEFT JOIN events e ON e.id = i.event_id WHERE i.organizer_id = ? AND i.status = 'found' AND i.found_at >= NOW() - INTERVAL 90 DAY ORDER BY i.found_at DESC LIMIT 60", [$org['id']]);
    return pub('lost_public', compact('org', 'events', 'errors', 'done', 'found'), 'Objets trouvés · ' . $org['name']);
}
