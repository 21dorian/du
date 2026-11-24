<?php
declare(strict_types=1);

/* 2.0 : liens de suivi (par canal) et ambassadeurs (par personne), avec statistiques. */

require_once APP . '/controllers/pro.php';
require_once APP . '/controllers/public.php';

function link_stats(int $eventId): array
{
    $out = [];
    foreach (all("SELECT link_id, COUNT(*) n, COALESCE(SUM(total), 0) revenue,
            COALESCE(SUM((SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id AND t.status = 'valid')), 0) tickets
            FROM orders o WHERE event_id = ? AND status = 'paid' AND link_id IS NOT NULL GROUP BY link_id", [$eventId]) as $r) $out[(int)$r['link_id']] = $r;
    return $out;
}

function links_tab(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $lid = (int)input('link_id');
        $a = (string)input('action');
        if ($a === 'toggle') { q('UPDATE tracking_links SET active = 1 - active WHERE id = ? AND event_id = ?', [$lid, $ev['id']]); back(); }
        if ($a === 'delete') {
            if (val("SELECT id FROM orders WHERE link_id = ? AND status = 'paid' LIMIT 1", [$lid])) back(['error' => 'Ce lien a déjà généré des ventes : désactive-le plutôt.']);
            q('DELETE FROM tracking_links WHERE id = ? AND event_id = ?', [$lid, $ev['id']]);
            back(['success' => 'Lien supprimé.']);
        }
        $name = mb_substr(trim((string)input('name')), 0, 120);
        if ($name === '') back(['error' => 'Donne un nom à ce lien (ex. : Story Instagram, Affiche Bar le Rex, Lucie).']);
        $kind = input('kind') === 'ambassadeur' ? 'ambassadeur' : 'canal';
        $email = strtolower(trim((string)input('email')));
        if ($email !== '' && !is_email($email)) back(['error' => 'L\'adresse e-mail de l\'ambassadeur a l\'air incorrecte.']);
        $code = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', (string)input('code'))) ?: strtoupper(substr(preg_replace('~[^A-Za-z0-9]~', '', iconv('UTF-8', 'ASCII//TRANSLIT', $name)), 0, 10));
        if ($code === '' || val('SELECT id FROM tracking_links WHERE event_id = ? AND code = ?', [$ev['id'], $code])) $code = substr($code, 0, 8) . random_code(4);
        $promo = strtoupper(trim((string)input('promo_code')));
        if ($promo !== '' && !val('SELECT id FROM promos WHERE event_id = ? AND code = ?', [$ev['id'], $promo])) back(['error' => 'Ce code promo n\'existe pas pour cet événement : crée-le d\'abord dans « Codes promo ».']);
        $token = token(20);
        insert('tracking_links', ['organizer_id' => $org['id'], 'event_id' => $ev['id'], 'name' => $name, 'code' => $code, 'kind' => $kind, 'email' => $email ?: null,
            'promo_code' => $promo ?: null, 'reward' => mb_substr(trim((string)input('reward')), 0, 190) ?: null, 'token' => $token, 'created_at' => now()]);
        if ($kind === 'ambassadeur' && $email && input('notify')) {
            $url = abs_url('/e/' . $ev['slug'], ['l' => $code]);
            try {
                Mailer::send($email, 'Ton lien ambassadeur pour ' . $ev['name'],
                    '<p>Bonjour ' . e($name) . ',</p><p><b>' . e($org['name']) . '</b> fait de toi un ambassadeur de <b>' . e($ev['name']) . '</b>. Partage ce lien autour de toi :</p>'
                    . '<p style="font-size:16px"><a href="' . e($url) . '">' . e($url) . '</a></p>'
                    . ($promo ? '<p>Les personnes qui passent par ton lien profitent automatiquement du code <b>' . e($promo) . '</b>.</p>' : '')
                    . (input('reward') ? '<p>Ta récompense : ' . e((string)input('reward')) . '</p>' : '')
                    . '<p><a href="' . e(abs_url('/ambassadeur/' . $token)) . '" style="display:inline-block;background:#1d1d1f;color:#fff;padding:12px 20px;border-radius:980px;text-decoration:none">Suivre mes résultats</a></p>', [], $org['email'] ?? null);
            } catch (Throwable $e) { log_line('emails', 'Ambassadeur : ' . $e->getMessage()); }
        }
        back(['success' => 'Lien « ' . $name . ' » créé.']);
    }
    $links = all('SELECT * FROM tracking_links WHERE event_id = ? ORDER BY active DESC, id DESC', [$ev['id']]);
    $stats = link_stats((int)$ev['id']);
    $direct = one("SELECT COUNT(*) n, COALESCE(SUM(total), 0) revenue FROM orders WHERE event_id = ? AND status = 'paid' AND link_id IS NULL AND (channel IS NULL OR channel <> 'systeme')", [$ev['id']]);
    $promos = array_column(all('SELECT code FROM promos WHERE event_id = ? AND active = 1 AND batch IS NULL ORDER BY code', [$ev['id']]), 'code');
    return ev_page($ev, 'links', 'Liens & ambassadeurs', 'links', compact('links', 'stats', 'direct', 'promos'));
}

/** Page publique d'un ambassadeur : ses résultats, sans accès au compte de l'organisateur. */
function ambassador_page(string $token): string
{
    $lk = one('SELECT * FROM tracking_links WHERE token = ?', [$token]);
    if (!$lk || strlen($token) < 20) abort(404);
    $ev = one('SELECT * FROM events WHERE id = ?', [$lk['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$lk['organizer_id']]);
    $st = link_stats((int)$ev['id'])[(int)$lk['id']] ?? ['n' => 0, 'revenue' => 0, 'tickets' => 0];
    $rank = null;
    if ($lk['kind'] === 'ambassadeur') {
        $all = all("SELECT l.id, (SELECT COALESCE(SUM((SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id AND t.status = 'valid')), 0) FROM orders o WHERE o.link_id = l.id AND o.status = 'paid') t
            FROM tracking_links l WHERE l.event_id = ? AND l.kind = 'ambassadeur' ORDER BY t DESC, l.id", [$ev['id']]);
        foreach ($all as $i => $r) if ((int)$r['id'] === (int)$lk['id']) $rank = [$i + 1, count($all)];
    }
    $url = abs_url('/e/' . $ev['slug'], ['l' => $lk['code']]);
    return pub('ambassador', compact('lk', 'ev', 'org', 'st', 'rank', 'url'), 'Mes résultats · ' . $ev['name']);
}

/** Menu « Liens & ambassadeurs » : ouvre l'événement à venir le plus proche. */
function links_choose(): never
{
    $org = require_org();
    $ev = one("SELECT id FROM events WHERE organizer_id = ? AND COALESCE(ends_at, starts_at) >= NOW() ORDER BY starts_at LIMIT 1", [$org['id']])
        ?: one('SELECT id FROM events WHERE organizer_id = ? ORDER BY starts_at DESC LIMIT 1', [$org['id']]);
    if (!$ev) redirect('/pro/evenements', ['info' => 'Crée d\'abord un événement.']);
    redirect('/pro/evenements/' . $ev['id'] . '/liens');
}

/** Contenu enrichi de la page publique : programme horaire, infos pratiques, questions fréquentes. */
function page_tab(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $clean = fn($v, int $n) => mb_substr(trim((string)$v), 0, $n);
        $prog = [];
        foreach ((array)($_POST['p_when'] ?? []) as $i => $w) {
            $t = $clean($_POST['p_title'][$i] ?? '', 160);
            if ($t !== '') $prog[] = ['when' => $clean($w, 40), 'title' => $t, 'text' => $clean($_POST['p_text'][$i] ?? '', 500)];
        }
        $faq = [];
        foreach ((array)($_POST['f_q'] ?? []) as $i => $q) {
            $q = $clean($q, 200); $a = $clean($_POST['f_a'][$i] ?? '', 1500);
            if ($q !== '' && $a !== '') $faq[] = ['q' => $q, 'a' => $a];
        }
        $prac = [];
        foreach (['acces', 'parking', 'pmr', 'regles', 'food', 'contact'] as $k) if (($v = $clean($_POST['prac'][$k] ?? '', 800)) !== '') $prac[$k] = $v;
        update('events', ['program' => $prog ? json_encode($prog, JSON_UNESCAPED_UNICODE) : null, 'faq' => $faq ? json_encode($faq, JSON_UNESCAPED_UNICODE) : null,
            'practical' => $prac ? json_encode($prac, JSON_UNESCAPED_UNICODE) : null], 'id = ?', [$ev['id']]);
        Audit::log('event.page', 'Page publique mise à jour', (int)$ev['id']);
        back(['success' => 'Page mise à jour. Elle est déjà en ligne.']);
    }
    $prog = json_decode((string)$ev['program'], true) ?: [];
    $faq = json_decode((string)$ev['faq'], true) ?: [];
    $prac = json_decode((string)$ev['practical'], true) ?: [];
    return ev_page($ev, 'page', 'Page de l\'événement', 'event_page', compact('prog', 'faq', 'prac'));
}
