<?php
declare(strict_types=1);

/* 2.5 : super-admin › marque blanche (domaines personnalisés des organisateurs). */

require_once APP . '/controllers/admin.php';

function admin_whitelabel(): string
{
    require_super();
    if (is_post()) {
        $a = (string)input('action');
        $org = one('SELECT * FROM organizers WHERE id = ?', [(int)input('organizer_id')]);
        if (!$org) back(['error' => 'Choisis un organisateur.']);
        if ($a === 'save') {
            $domain = WhiteLabel::normalize((string)input('domain'));
            if ($domain === '') back(['error' => 'Ce nom de domaine n\'est pas valide (exemple : billetterie.monfestival.fr).']);
            if ($domain === WhiteLabel::platformHost()) back(['error' => 'C\'est le domaine de la plateforme.']);
            if (val('SELECT id FROM organizers WHERE custom_domain = ? AND id <> ?', [$domain, $org['id']])) back(['error' => 'Ce domaine est déjà utilisé par un autre organisateur.']);
            $s = WhiteLabel::settings($org);
            $s['hide_credit'] = checked('hide_credit'); $s['mail_brand'] = checked('mail_brand'); $s['mail_name'] = mb_substr(trim((string)input('mail_name')), 0, 120);
            $changed = $domain !== (string)$org['custom_domain'];
            update('organizers', ['custom_domain' => $domain, 'white_label' => 1, 'wl_json' => json_encode($s, JSON_UNESCAPED_UNICODE), 'domain_status' => $changed ? 'pending' : ($org['domain_status'] ?: 'pending')], 'id = ?', [$org['id']]);
            Audit::log('wl.saved', $org['name'] . ' · ' . $domain, null, null, (int)$org['id']);
            $a = $changed ? 'check' : '';
            if (!$a) redirect('/admin/marque-blanche?o=' . $org['id'], ['success' => 'Réglages enregistrés.']);
            $org = one('SELECT * FROM organizers WHERE id = ?', [$org['id']]);
        }
        if ($a === 'check' && $org['custom_domain']) {
            $c = WhiteLabel::check((string)$org['custom_domain']);
            update('organizers', ['domain_status' => $c['ok'] ? 'active' : 'pending', 'domain_checked_at' => now()], 'id = ?', [$org['id']]);
            redirect('/admin/marque-blanche?o=' . $org['id'], [$c['ok'] ? 'success' : 'warning' => $c['message'] . ($c['ok'] ? ' Marque blanche active.' : ' Vérifie la configuration ci-dessous, puis relance la vérification.')]);
        }
        if ($a === 'force') { update('organizers', ['domain_status' => 'active', 'domain_checked_at' => now()], 'id = ?', [$org['id']]); Audit::log('wl.forced', $org['name'], null, null, (int)$org['id']); redirect('/admin/marque-blanche?o=' . $org['id'], ['success' => 'Domaine activé sans vérification DNS.']); }
        if ($a === 'disable') { update('organizers', ['white_label' => 0, 'domain_status' => 'off'], 'id = ?', [$org['id']]); Audit::log('wl.off', $org['name'], null, null, (int)$org['id']); redirect('/admin/marque-blanche', ['success' => 'Marque blanche coupée : le domaine ne sert plus la billetterie.']); }
        back();
    }
    $list = all("SELECT o.*, (SELECT COALESCE(SUM(total), 0) FROM orders x WHERE x.organizer_id = o.id AND x.status = 'paid' AND x.paid_at >= NOW() - INTERVAL 30 DAY) sales30 FROM organizers o WHERE o.white_label = 1 OR o.custom_domain IS NOT NULL ORDER BY o.white_label DESC, o.name");
    $sel = (int)input('o') ? one('SELECT * FROM organizers WHERE id = ?', [(int)input('o')]) : (input('o') === 'new' ? [] : null);
    $orgs = all("SELECT id, name FROM organizers WHERE status = 'active' ORDER BY name");
    return admin_page('Marque blanche', 'whitelabel', ['list' => $list, 'sel' => $sel, 'orgs' => $orgs, 'host' => WhiteLabel::platformHost(), 'ip' => (string)(($_SERVER['SERVER_ADDR'] ?? '') ?: (@gethostbyname(WhiteLabel::platformHost()) ?: ''))], 'marque-blanche');
}
