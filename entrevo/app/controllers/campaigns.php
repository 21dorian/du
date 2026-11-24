<?php
declare(strict_types=1);

/* Campagnes e-mail côté organisateur : liste, éditeur à blocs, audience, test, envoi, statistiques. */

require_once APP . '/controllers/pro.php';

function campaigns_index(): string
{
    $org = require_org();
    $oid = (int)$org['id'];
    $list = all('SELECT c.*, u.name author FROM campaigns c LEFT JOIN users u ON u.id = c.created_by WHERE c.organizer_id = ? ORDER BY COALESCE(c.sent_at, c.scheduled_at, c.updated_at, c.created_at) DESC LIMIT 200', [$oid]);
    foreach ($list as &$c) $c['stats'] = in_array($c['status'], ['sending', 'sent'], true) ? Campaigns::stats($c) : null;
    unset($c);
    $counts = Campaigns::counts($oid);
    $sentList = array_filter($list, fn($c) => $c['stats'] && $c['stats']['sent']);
    $tot = ['sent' => array_sum(array_map(fn($c) => $c['stats']['sent'], $sentList)), 'opened' => array_sum(array_map(fn($c) => $c['stats']['opened'], $sentList)),
        'clicked' => array_sum(array_map(fn($c) => $c['stats']['clicked'], $sentList)), 'revenue' => array_sum(array_map(fn($c) => $c['stats']['revenue'], $sentList))];
    $upcoming = all("SELECT id, name FROM events WHERE organizer_id = ? AND starts_at >= NOW() ORDER BY starts_at LIMIT 10", [$oid]);
    $templates = Campaigns::templates($upcoming);
    $auto = ['org' => current_org(), 'recovery' => Buyers::recoveryStats($oid),
        'reminders' => (int)val('SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND reminder_sent_at >= NOW() - INTERVAL 90 DAY', [$oid]),
        'reviews_sent' => (int)val('SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND review_sent_at >= NOW() - INTERVAL 90 DAY', [$oid]),
        'reviews' => (int)val('SELECT COUNT(*) FROM reviews WHERE organizer_id = ? AND created_at >= NOW() - INTERVAL 90 DAY', [$oid]),
        'referral' => Buyers::referralStats($oid)];
    return pro_page('Campagnes e-mail', 'campaigns/index', compact('list', 'counts', 'tot', 'templates', 'auto'), 'campagnes');
}

function campaign_new(): never
{
    $org = require_org();
    $upcoming = all("SELECT id, name FROM events WHERE organizer_id = ? AND starts_at >= NOW() ORDER BY starts_at LIMIT 10", [$org['id']]);
    $tpl = Campaigns::templates($upcoming)[(string)input('modele')] ?? Campaigns::templates($upcoming)['vide'];
    $aud = ['source' => input('source') === 'customers' ? 'customers' : 'optin', 'events' => array_filter([(int)input('ev')]), 'not_event' => 0,
        'segment' => array_key_exists((string)input('seg'), Campaigns::SEGMENTS) ? (string)input('seg') : ''];
    $id = insert('campaigns', ['organizer_id' => $org['id'], 'name' => $tpl[0] . ' — ' . date('d/m'), 'subject' => $tpl[1], 'blocks' => json_encode($tpl[2], JSON_UNESCAPED_UNICODE),
        'audience' => json_encode($aud), 'status' => 'draft', 'created_by' => user()['id'], 'created_at' => now(), 'updated_at' => now()]);
    redirect('/pro/campagnes/' . $id);
}

function campaign_payload(): array
{
    $blocks = [];
    foreach ((array)(json_decode((string)input('blocks'), true) ?: []) as $b) {
        $t = (string)($b['type'] ?? '');
        if (!in_array($t, ['heading', 'text', 'button', 'event', 'image', 'divider'], true)) continue;
        $x = ['type' => $t];
        foreach (['text' => 5000, 'label' => 80, 'url' => 500] as $k => $len) if (isset($b[$k])) $x[$k] = mb_substr(trim((string)$b[$k]), 0, $len);
        if (isset($x['url']) && $x['url'] !== '' && !preg_match('~^https?://~i', $x['url'])) $x['url'] = 'https://' . ltrim($x['url'], '/');
        if ($t === 'event') $x['event_id'] = (int)($b['event_id'] ?? 0);
        $blocks[] = $x;
        if (count($blocks) >= 30) break;
    }
    $aud = ['source' => input('source') === 'customers' ? 'customers' : 'optin', 'events' => array_values(array_filter(array_map('intval', (array)($_POST['events'] ?? [])))),
        'not_event' => (int)input('not_event'), 'segment' => array_key_exists((string)input('segment'), Campaigns::SEGMENTS) ? (string)input('segment') : ''];
    return ['name' => mb_substr(trim((string)input('name')), 0, 160) ?: 'Campagne sans nom', 'subject' => mb_substr(trim((string)input('subject')), 0, 190),
        'preheader' => mb_substr(trim((string)input('preheader')), 0, 190) ?: null, 'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE), 'audience' => json_encode($aud), 'updated_at' => now()];
}

function campaign_edit(string $id): string
{
    $org = require_org();
    $c = Campaigns::get((int)$org['id'], (int)$id);
    if (!$c) abort(404);
    $editable = in_array($c['status'], ['draft', 'scheduled'], true);
    if (is_post()) {
        $a = (string)input('action');
        if ($editable && in_array($a, ['save', 'test', 'send', 'schedule'], true)) { update('campaigns', campaign_payload(), 'id = ?', [$c['id']]); $c = Campaigns::get((int)$org['id'], (int)$id); }
        switch ($a) {
            case 'save': back(['success' => '💾 Enregistré.']);
            case 'test':
                $to = trim((string)input('test_email')) ?: user()['email'];
                if (!is_email($to)) back(['error' => 'Adresse de test invalide.']);
                try { Campaigns::sendOne($c + ['subject' => '[TEST] ' . $c['subject']], $org, ['email' => $to, 'first_name' => explode(' ', user()['name'])[0], 'token' => 'test' . token(8)]); }
                catch (Throwable $e) { back(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
                back(['success' => '📬 Test envoyé à ' . $to . '. Les liens de suivi ne comptent pas pour le test.']);
            case 'send':
            case 'schedule':
                if (trim((string)$c['subject']) === '') back(['error' => 'Il manque l\'objet de l\'e-mail ✍️']);
                if (!Campaigns::blocks($c)) back(['error' => 'Ton e-mail est vide 🙈']);
                $n = count(Campaigns::recipients((int)$org['id'], Campaigns::audience($c)));
                if (!$n) back(['error' => 'Personne dans cette audience. Élargis-la (ou attends tes premiers inscrits à la newsletter).']);
                if ($a === 'schedule') {
                    $at = strtotime((string)input('scheduled_at'));
                    if (!$at || $at < time() + 60) back(['error' => 'Choisis une date d\'envoi dans le futur.']);
                    update('campaigns', ['status' => 'scheduled', 'scheduled_at' => date('Y-m-d H:i:s', $at)], 'id = ?', [$c['id']]);
                    Audit::log('message.sent', 'Campagne programmée : ' . $c['name'] . ' (' . $n . ' destinataires)');
                    back(['success' => '⏰ Programmée pour le ' . fr_date(date('Y-m-d H:i:s', $at)) . ' (' . $n . ' destinataires environ).']);
                }
                $n = Campaigns::launch($c);
                Campaigns::process(Campaigns::BATCH);
                Audit::log('message.sent', 'Campagne : ' . $c['name'] . ' (' . $n . ' destinataires)');
                redirect('/pro/campagnes/' . $c['id'], ['success' => '🚀 C\'est parti ! ' . $n . ' e-mails en cours d\'envoi, par petits lots pour ne pas être pris pour du spam.']);
            case 'cancel':
                if ($c['status'] === 'scheduled') update('campaigns', ['status' => 'draft', 'scheduled_at' => null], 'id = ?', [$c['id']]);
                elseif ($c['status'] === 'sending') update('campaigns', ['status' => 'cancelled'], 'id = ?', [$c['id']]);
                back(['success' => 'Envoi arrêté.']);
            case 'duplicate':
                $copy = $c; unset($copy['id']);
                $nid = insert('campaigns', array_merge($copy, ['name' => $c['name'] . ' (copie)', 'status' => 'draft', 'scheduled_at' => null, 'started_at' => null, 'sent_at' => null, 'recipients' => 0, 'created_by' => user()['id'], 'created_at' => now(), 'updated_at' => now()]));
                redirect('/pro/campagnes/' . $nid, ['success' => '📑 Copie créée.']);
            case 'delete':
                if ($c['status'] !== 'draft') back(['error' => 'Seul un brouillon peut être supprimé.']);
                q('DELETE FROM campaigns WHERE id = ?', [$c['id']]);
                redirect('/pro/campagnes', ['success' => '🗑️ Brouillon supprimé.']);
        }
        back();
    }
    $events = all('SELECT id, name, starts_at, image FROM events WHERE organizer_id = ? ORDER BY starts_at DESC', [$org['id']]);
    $counts = Campaigns::counts((int)$org['id']);
    $audienceCount = count(Campaigns::recipients((int)$org['id'], Campaigns::audience($c)));
    $stats = in_array($c['status'], ['sending', 'sent', 'cancelled'], true) ? Campaigns::stats($c) : null;
    $timeline = $stats ? all("SELECT DATE_FORMAT(opened_at, '%Y-%m-%d %H:00') h, COUNT(*) n FROM campaign_recipients WHERE campaign_id = ? AND opened_at IS NOT NULL GROUP BY h ORDER BY h LIMIT 72", [$c['id']]) : [];
    $orders = $stats ? all("SELECT o.id, o.ref, o.first_name, o.last_name, o.total, o.paid_at, e.name ev FROM orders o JOIN events e ON e.id = o.event_id WHERE o.organizer_id = ? AND o.status = 'paid' AND o.utm_campaign = ? ORDER BY o.paid_at DESC LIMIT 20", [$org['id'], 'c' . $c['id']]) : [];
    return view('layout_back', ['title' => $c['name'], 'area' => 'pro', 'active' => 'campagnes', 'crumbs' => [['Campagnes e-mail', '/pro/campagnes']],
        'content' => view('pro/campaigns/' . ($editable ? 'edit' : 'report'), compact('c', 'org', 'events', 'counts', 'audienceCount', 'stats', 'timeline', 'orders'))]);
}

/** Aperçu (POST : blocs en cours d'édition, sans enregistrer) et compteur d'audience. */
function campaign_preview(string $id): never
{
    $org = require_org();
    $c = Campaigns::get((int)$org['id'], (int)$id);
    if (!$c) abort(404);
    if (is_post()) $c = array_merge($c, campaign_payload());
    if (input('count')) json_out(['count' => count(Campaigns::recipients((int)$org['id'], Campaigns::audience($c)))]);
    header('Content-Type: text/html; charset=utf-8');
    echo Campaigns::render($c, $org, null);
    exit;
}
