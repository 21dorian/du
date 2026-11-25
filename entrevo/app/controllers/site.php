<?php
declare(strict_types=1);

/* 2.5 : mini-site de l'organisateur — gestion (pro) et pages publiques. */

require_once APP . '/controllers/pro.php';
require_once APP . '/controllers/public.php';

const SITE_TABS = ['pages' => 'Pages', 'actualites' => 'Actualités', 'messages' => 'Messages', 'abonnes' => 'Abonnés', 'reglages' => 'Réglages'];

function pro_site(): string
{
    $org = require_org(['owner', 'manager']);
    require_module('minisite');
    $tab = array_key_exists((string)input('tab'), SITE_TABS) ? (string)input('tab') : 'pages';
    $kind = $tab === 'actualites' ? 'news' : 'page';
    if (is_post()) {
        $a = (string)input('action');
        $p = (int)input('page_id') ? one('SELECT * FROM site_pages WHERE id = ? AND organizer_id = ?', [(int)input('page_id'), $org['id']]) : null;
        if ($a === 'settings') {
            $s = Site::settings($org);
            foreach (['enabled', 'contact', 'newsletter', 'news_home'] as $k) $s[$k] = checked($k);
            foreach (['intro' => 4000, 'footer' => 1000, 'newsletter_text' => 300] as $k => $max) $s[$k] = mb_substr(trim((string)input($k)), 0, $max);
            $s['contact_email'] = is_email(strtolower(trim((string)input('contact_email')))) ? strtolower(trim((string)input('contact_email'))) : '';
            update('organizers', ['site_json' => json_encode($s, JSON_UNESCAPED_UNICODE)], 'id = ?', [$org['id']]);
            redirect('/pro/mini-site?tab=reglages', ['success' => $s['enabled'] ? 'Mini-site en ligne.' : 'Réglages enregistrés (mini-site hors ligne).']);
        }
        if ($a === 'save') {
            $k = $p['kind'] ?? (input('kind') === 'news' ? 'news' : 'page');
            $title = mb_substr(trim((string)input('title')), 0, 190);
            if ($title === '') back(['error' => 'Donne un titre.']);
            $d = ['title' => $title, 'slug' => Site::uniqueSlug((int)$org['id'], $k, trim((string)input('slug')) ?: $title, (int)($p['id'] ?? 0)), 'excerpt' => mb_substr(trim((string)input('excerpt')), 0, 500) ?: null,
                'body' => mb_substr((string)input('body'), 0, 60000), 'in_menu' => checked('in_menu'), 'published' => checked('published'), 'updated_at' => now(),
                'published_at' => $k === 'news' ? (input('published_at') ? date('Y-m-d H:i:s', strtotime((string)input('published_at'))) : now()) : null];
            try { if ($img = upload_image('image')) $d['image'] = $img; } catch (Throwable $e) { back(['error' => $e->getMessage()]); }
            if (checked('remove_image')) $d['image'] = null;
            if ($p) update('site_pages', $d, 'id = ?', [$p['id']]);
            else { $d += ['organizer_id' => $org['id'], 'kind' => $k, 'sort' => (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM site_pages WHERE organizer_id = ? AND kind = ?', [$org['id'], $k]), 'created_at' => now()]; $p = ['id' => insert('site_pages', $d)]; }
            redirect('/pro/mini-site?tab=' . ($k === 'news' ? 'actualites' : 'pages') . '&edit=' . $p['id'], ['success' => $k === 'news' ? 'Actualité enregistrée.' : 'Page enregistrée.']);
        }
        if ($a === 'delete' && $p) { q('DELETE FROM site_pages WHERE id = ?', [$p['id']]); redirect('/pro/mini-site?tab=' . ($p['kind'] === 'news' ? 'actualites' : 'pages'), ['success' => 'Supprimé.']); }
        if ($a === 'move' && $p) {
            $dir = input('dir') === 'up' ? -1 : 1;
            $list = all('SELECT id FROM site_pages WHERE organizer_id = ? AND kind = ? ORDER BY sort, id', [$org['id'], $p['kind']]);
            $ids = array_map('intval', array_column($list, 'id')); $i = array_search((int)$p['id'], $ids, true); $j = $i + $dir;
            if ($i !== false && isset($ids[$j])) { [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; foreach ($ids as $n => $id) q('UPDATE site_pages SET sort = ? WHERE id = ?', [$n, $id]); }
            back();
        }
        if ($a === 'read') { q('UPDATE site_messages SET read_at = COALESCE(read_at, NOW()) WHERE organizer_id = ? AND id = ?', [$org['id'], (int)input('message_id')]); back(); }
        if ($a === 'delete_message') { q('DELETE FROM site_messages WHERE organizer_id = ? AND id = ?', [$org['id'], (int)input('message_id')]); back(['success' => 'Message supprimé.']); }
        if ($a === 'add_contacts') {
            $n = 0;
            foreach (preg_split('~[\s,;]+~', (string)input('emails')) as $em) if (Site::subscribe((int)$org['id'], $em, null, 'ajout')) $n++;
            back(['success' => $n . ' abonné(s) ajouté(s). Assure-toi d\'avoir leur accord pour leur écrire.']);
        }
        if ($a === 'unsub') { q('UPDATE site_contacts SET unsub_at = NOW() WHERE organizer_id = ? AND id = ?', [$org['id'], (int)input('contact_id')]); back(['success' => 'Abonné retiré.']); }
        back();
    }
    if ($tab === 'abonnes' && input('export') === 'csv') {
        $rows = [['E-mail', 'Nom', 'Origine', 'Inscrit le', 'Désinscrit le']];
        foreach (all('SELECT * FROM site_contacts WHERE organizer_id = ? ORDER BY created_at DESC', [$org['id']]) as $c) $rows[] = [$c['email'], (string)$c['name'], (string)$c['source'], $c['created_at'], (string)$c['unsub_at']];
        csv_download('abonnes-' . $org['slug'] . '.csv', $rows);
    }
    $settings = Site::settings($org);
    $edit = null;
    if (in_array($tab, ['pages', 'actualites'], true) && input('edit') !== null) $edit = input('edit') === 'new' ? [] : one('SELECT * FROM site_pages WHERE id = ? AND organizer_id = ?', [(int)input('edit'), $org['id']]);
    $items = in_array($tab, ['pages', 'actualites'], true) ? all('SELECT * FROM site_pages WHERE organizer_id = ? AND kind = ? ORDER BY ' . ($kind === 'news' ? 'COALESCE(published_at, created_at) DESC' : 'sort, id'), [$org['id'], $kind]) : [];
    $messages = $tab === 'messages' ? all('SELECT * FROM site_messages WHERE organizer_id = ? ORDER BY created_at DESC LIMIT 200', [$org['id']]) : [];
    $contacts = $tab === 'abonnes' ? all('SELECT * FROM site_contacts WHERE organizer_id = ? ORDER BY unsub_at IS NULL DESC, created_at DESC LIMIT 500', [$org['id']]) : [];
    $k = ['unread' => (int)val('SELECT COUNT(*) FROM site_messages WHERE organizer_id = ? AND read_at IS NULL', [$org['id']]),
        'subs' => (int)val('SELECT COUNT(*) FROM site_contacts WHERE organizer_id = ? AND unsub_at IS NULL', [$org['id']]),
        'subs30' => (int)val('SELECT COUNT(*) FROM site_contacts WHERE organizer_id = ? AND unsub_at IS NULL AND created_at >= NOW() - INTERVAL 30 DAY', [$org['id']])];
    return pro_page('Mini-site', 'site', compact('org', 'tab', 'kind', 'settings', 'edit', 'items', 'messages', 'contacts', 'k'), 'mini-site');
}

// ---------- Public ----------
function site_org(string $slug): array
{
    $org = one("SELECT * FROM organizers WHERE slug = ? AND status = 'active'", [$slug]);
    if (!$org || !Site::on($org)) abort(404);
    return $org;
}

function site_pub(array $org, string $view, array $vars, string $title, array $extra = []): string
{
    return pub($view, $vars + ['org' => $org], $title, $extra + ['site' => $org, 'org_tracking' => $org]);
}

function site_page(string $slug, string $page): string
{
    $org = site_org($slug);
    $p = one("SELECT * FROM site_pages WHERE organizer_id = ? AND kind = 'page' AND slug = ? AND published = 1", [$org['id'], $page]);
    if (!$p) abort(404);
    return site_pub($org, 'site_page', ['p' => $p], $p['title'] . ' · ' . $org['name'], ['description' => $p['excerpt'] ?: mb_substr(strip_tags(rich_text((string)$p['body'])), 0, 160)]);
}

function site_news(string $slug): string
{
    $org = site_org($slug);
    return site_pub($org, 'site_news', ['news' => Site::news((int)$org['id'], 60)], 'Actualités · ' . $org['name']);
}

function site_news_post(string $slug, string $post): string
{
    $org = site_org($slug);
    $p = one("SELECT * FROM site_pages WHERE organizer_id = ? AND kind = 'news' AND slug = ? AND published = 1 AND (published_at IS NULL OR published_at <= NOW())", [$org['id'], $post]);
    if (!$p) abort(404);
    $more = all("SELECT * FROM site_pages WHERE organizer_id = ? AND kind = 'news' AND published = 1 AND id <> ? AND (published_at IS NULL OR published_at <= NOW()) ORDER BY COALESCE(published_at, created_at) DESC LIMIT 3", [$org['id'], $p['id']]);
    return site_pub($org, 'site_page', ['p' => $p, 'more' => $more], $p['title'] . ' · ' . $org['name'],
        ['description' => $p['excerpt'] ?: mb_substr(strip_tags(rich_text((string)$p['body'])), 0, 160), 'og_image' => $p['image'] ? abs_url('/' . ltrim($p['image'], '/')) : null]);
}

function site_contact(string $slug): string
{
    $org = site_org($slug);
    $s = Site::settings($org);
    if (!$s['contact']) abort(404);
    if (is_post()) {
        if (trim((string)input('website')) !== '') redirect('/o/' . $slug . '/contact', ['success' => 'Message envoyé.']); // pot de miel
        $d = ['name' => mb_substr(trim((string)input('name')), 0, 190), 'email' => strtolower(trim((string)input('email'))), 'subject' => mb_substr(trim((string)input('subject')), 0, 190) ?: null, 'body' => mb_substr(trim((string)input('body')), 0, 5000)];
        if ($d['name'] === '' || !is_email($d['email']) || mb_strlen($d['body']) < 5) { flash('error', 'Indique ton nom, ton e-mail et ton message.'); return site_pub($org, 'site_contact', ['s' => $s, 'old' => $d], 'Contact · ' . $org['name']); }
        if (!throttle_ok_pub('contact-' . $org['id'])) redirect('/o/' . $slug . '/contact', ['error' => 'Trop de messages envoyés, réessaie dans quelques minutes.']);
        insert('site_messages', $d + ['organizer_id' => $org['id'], 'ip' => mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), 'created_at' => now()]);
        if (checked('newsletter') && $s['newsletter']) Site::subscribe((int)$org['id'], $d['email'], $d['name'], 'contact');
        $to = $s['contact_email'] ?: (string)($org['email'] ?? '');
        if (is_email($to)) {
            try { Mailer::send($to, 'Nouveau message : ' . ($d['subject'] ?: 'depuis ton site'), '<p><b>' . e($d['name']) . '</b> (' . e($d['email']) . ') t\'a écrit depuis ton mini-site :</p><blockquote style="border-left:3px solid #d2d2d7;margin:0;padding:4px 14px;color:#1d1d1f">' . nl2br(e($d['body'])) . '</blockquote><p>Réponds simplement à cet e-mail.</p>', [], $d['email']); }
            catch (Throwable $e) { log_line('mail', 'Contact mini-site : ' . $e->getMessage()); }
        }
        redirect('/o/' . $slug . '/contact', ['success' => 'Merci, ton message est bien parti. Réponse par e-mail.']);
    }
    return site_pub($org, 'site_contact', ['s' => $s, 'old' => []], 'Contact · ' . $org['name']);
}

function site_newsletter(string $slug): never
{
    $org = site_org($slug);
    $back = (string)input('back');
    $back = str_starts_with($back, '/o/' . $slug) ? $back : '/o/' . $slug;
    if (trim((string)input('website')) !== '' || !Site::settings($org)['newsletter']) redirect($back);
    if (!throttle_ok_pub('nl-' . $org['id'])) redirect($back, ['error' => 'Trop d\'inscriptions depuis ton appareil, réessaie plus tard.']);
    Site::subscribe((int)$org['id'], (string)input('email'), null, 'site')
        ? redirect($back, ['success' => 'C\'est noté : tu recevras nos prochaines annonces. Un lien de désinscription figure dans chaque e-mail.'])
        : redirect($back, ['error' => 'Cette adresse e-mail a l\'air bizarre.']);
}
