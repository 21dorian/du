<?php
declare(strict_types=1);

/* 2.3 : pages éditables (FAQ, à propos…) et habillage des e-mails. */

require_once APP . '/controllers/public.php';

function content_page(string $slug): string
{
    $p = one('SELECT * FROM pages WHERE slug = ? AND published = 1', [$slug]);
    if (!$p && !is_super()) abort(404);
    $p ??= one('SELECT * FROM pages WHERE slug = ?', [$slug]) ?: abort(404);
    return pub('page', ['p' => $p], $p['title']);
}

function admin_contents(): string
{
    require_once APP . '/controllers/admin.php';
    $u = require_super();
    $tab = input('tab') === 'emails' ? 'emails' : 'pages';
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'email') {
            set_setting('email_header', in_array(input('email_header'), ['light', 'dark', 'color'], true) ? (string)input('email_header') : 'light');
            if (preg_match('~^#[0-9a-fA-F]{6}$~', (string)input('email_accent'))) set_setting('email_accent', (string)input('email_accent'));
            set_setting('email_signature', mb_substr(trim((string)input('email_signature')), 0, 300));
            set_setting('email_footer', mb_substr(trim((string)input('email_footer')), 0, 400));
            if (is_email((string)input('test_to'))) {
                try { Mailer::send((string)input('test_to'), 'Aperçu de tes e-mails', email_sample()); } catch (Throwable $e) { redirect('/admin/contenus?tab=emails', ['error' => 'Envoi impossible : ' . $e->getMessage()]); }
                redirect('/admin/contenus?tab=emails', ['success' => 'Enregistré, et aperçu envoyé à ' . input('test_to') . '.']);
            }
            redirect('/admin/contenus?tab=emails', ['success' => 'Habillage des e-mails enregistré.']);
        }
        if ($a === 'delete') { q('DELETE FROM pages WHERE id = ?', [(int)input('page_id')]); redirect('/admin/contenus', ['success' => 'Page supprimée.']); }
        if ($a === 'page') {
            $id = (int)input('page_id');
            $title = mb_substr(trim((string)input('title')), 0, 190);
            if ($title === '') back(['error' => 'Donne un titre à la page.']);
            $slug = trim(preg_replace('~[^a-z0-9]+~', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', (string)(input('slug') ?: $title)))), '-');
            if ($slug === '' || val('SELECT id FROM pages WHERE slug = ? AND id <> ?', [$slug, $id])) back(['error' => 'Cette adresse est déjà prise par une autre page.']);
            $d = ['title' => $title, 'slug' => $slug, 'body' => (string)input('body'), 'in_footer' => checked('in_footer'), 'published' => checked('published'), 'sort' => (int)input('sort'), 'updated_at' => now()];
            if ($id) update('pages', $d, 'id = ?', [$id]); else $id = insert('pages', $d);
            Audit::log('page.saved', $title);
            redirect('/admin/contenus?edit=' . $id, ['success' => 'Page enregistrée. Elle est en ligne à /page/' . $slug . '.']);
        }
    }
    $pages = all('SELECT * FROM pages ORDER BY sort, title');
    $edit = input('edit') === 'new' ? [] : (input('edit') ? one('SELECT * FROM pages WHERE id = ?', [(int)input('edit')]) : null);
    return admin_page('Contenus & e-mails', 'contents', compact('tab', 'pages', 'edit'), 'contenus');
}

/** Exemple d'e-mail pour l'aperçu. */
function email_sample(): string
{
    return '<p>Bonjour Camille,</p><p>Merci pour ta commande ! Tes <b>2 billets</b> pour <b>Festival Lumières d\'Été</b> sont en pièce jointe.</p>'
        . '<p style="margin:22px 0"><a href="#" style="display:inline-block;background:#1d1d1f;color:#fff;padding:12px 22px;border-radius:980px;text-decoration:none">Voir ma commande</a></p>'
        . '<p style="color:#86868b;font-size:13px">Présente le QR code à l\'entrée, sur ton téléphone ou imprimé.</p>';
}

/** Aperçu brut (iframe). */
function admin_email_preview(): never
{
    require_once APP . '/controllers/admin.php';
    require_super();
    foreach (['email_header', 'email_accent', 'email_signature', 'email_footer'] as $k) if (isset($_GET[$k])) $GLOBALS['__setting_override'][$k] = (string)$_GET[$k];
    header('Content-Type: text/html; charset=utf-8');
    echo Mailer::wrap('Aperçu', email_sample());
    exit;
}
