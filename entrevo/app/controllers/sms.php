<?php
declare(strict_types=1);

/* 2.1 : campagnes SMS côté organisateur. */

require_once APP . '/controllers/pro.php';

function sms_index(): string
{
    $org = require_org();
    $oid = (int)$org['id'];
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'settings') {
            $s = preg_replace('~[^A-Za-z0-9]~', '', (string)input('sms_sender'));
            if ($s !== '' && (strlen($s) < 3 || !preg_match('~^[A-Za-z]~', $s))) back(['error' => 'Le nom d\'expéditeur doit faire 3 à 11 lettres ou chiffres et commencer par une lettre.']);
            update('organizers', ['sms_sender' => $s !== '' ? substr($s, 0, 11) : null], 'id = ?', [$oid]);
            back(['success' => 'Expéditeur enregistré.']);
        }
        if ($a === 'optout') {
            $n = 0;
            foreach (preg_split('~[\n,;]+~', (string)input('phones')) as $p) if (trim($p) !== '' && Sms::addOptout($oid, $p)) $n++;
            back(['success' => $n . ' numéro' . ($n > 1 ? 's' : '') . ' ajouté' . ($n > 1 ? 's' : '') . ' à la liste STOP.']);
        }
        if ($a === 'optout_remove') { q('DELETE FROM sms_optouts WHERE organizer_id = ? AND phone = ?', [$oid, (string)input('phone')]); back(['success' => 'Numéro retiré de la liste STOP.']); }
        if ($a === 'cancel') {
            $id = (int)input('campaign_id');
            q("UPDATE sms_campaigns SET status = 'cancelled' WHERE id = ? AND organizer_id = ? AND status IN ('scheduled','sending')", [$id, $oid]);
            q("UPDATE sms_messages SET status = 'skipped', error = 'Campagne annulée' WHERE campaign_id = ? AND status = 'queued'", [$id]);
            back(['success' => 'Campagne arrêtée. Les SMS déjà partis ne peuvent pas être rappelés.']);
        }
        $body = trim(str_replace("\r", '', (string)input('body')));
        $ev = (int)input('event_id') ? one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [(int)input('event_id'), $oid]) : null;
        if ($body === '') back(['error' => 'Écris ton message.']);
        if (str_contains($body, '{lien}') && !$ev) back(['error' => 'Choisis l\'événement vers lequel pointe {lien}.']);
        [$len, $seg] = Sms::measure(Sms::text($body, 'Alexandra', Sms::link($ev, 99999)));
        if ($seg > 4) back(['error' => 'Message trop long (' . $seg . ' SMS) : 4 SMS maximum.']);
        if ($a === 'test') {
            $to = Sms::normalize((string)input('test_phone'));
            if (!$to) back(['error' => 'Indique un numéro de mobile pour le test.']);
            try { $st = Sms::send($to, Sms::text($body, (string)strtok((string)(user()['name'] ?? ''), ' '), Sms::link($ev, 0)), Sms::sender($org), 'test'); }
            catch (Throwable $e) { back(['error' => $e->getMessage()]); }
            back(['success' => $st === 'test' ? 'Mode test : le SMS a été écrit dans le journal, rien n\'est parti (le service SMS n\'est pas encore branché).' : 'SMS de test envoyé au ' . Sms::pretty($to) . '.']);
        }
        if ($a === 'send') {
            $aud = ['events' => array_values(array_filter(array_map('intval', (array)($_POST['events'] ?? [])))), 'not_event' => (int)input('not_event'),
                'segment' => array_key_exists((string)input('segment'), Campaigns::SEGMENTS) ? (string)input('segment') : ''];
            if (!Sms::recipients($oid, $aud)) back(['error' => 'Personne à qui écrire avec ces critères.']);
            $when = input('when') === 'later' ? dt_from_input(input('scheduled_at')) : null;
            if (input('when') === 'later' && (!$when || strtotime($when) < time())) back(['error' => 'Choisis une date d\'envoi à venir.']);
            if (!checked('confirm')) back(['error' => 'Coche la case de confirmation avant d\'envoyer.']);
            $id = insert('sms_campaigns', ['organizer_id' => $oid, 'name' => mb_substr(trim((string)input('name')) ?: 'SMS du ' . date('d/m/Y'), 0, 190), 'body' => mb_substr($body, 0, 640),
                'audience' => json_encode($aud), 'status' => 'scheduled', 'segments' => $seg, 'event_id' => $ev['id'] ?? null, 'scheduled_at' => $when,
                'created_by' => user()['id'] ?? null, 'created_at' => now()]);
            Audit::log('sms.created', 'Campagne SMS « ' . mb_substr($body, 0, 60) . ' »', $ev['id'] ?? null, null, $oid);
            if (!$when) { $c = one('SELECT * FROM sms_campaigns WHERE id = ?', [$id]); $n = Sms::launch($c); Sms::process(); }
            redirect('/pro/sms/' . $id, ['success' => $when ? 'Campagne programmée.' : 'C\'est parti : ' . ($n ?? 0) . ' SMS en route.']);
        }
        back();
    }
    $list = all('SELECT * FROM sms_campaigns WHERE organizer_id = ? ORDER BY id DESC LIMIT 100', [$oid]);
    $reach = count(Sms::recipients($oid, ['events' => [], 'not_event' => 0, 'segment' => '']));
    $optouts = all('SELECT * FROM sms_optouts WHERE organizer_id = ? ORDER BY created_at DESC LIMIT 200', [$oid]);
    $events = all('SELECT id, name, slug, starts_at FROM events WHERE organizer_id = ? ORDER BY starts_at DESC LIMIT 60', [$oid]);
    $kpi = one("SELECT COALESCE(SUM(sent), 0) sent, COALESCE(SUM(cost), 0) cost FROM sms_campaigns WHERE organizer_id = ?", [$oid]);
    $kpi['revenue'] = (int)val("SELECT COALESCE(SUM(total), 0) FROM orders WHERE organizer_id = ? AND status = 'paid' AND utm_source = 'sms'", [$oid]);
    return pro_page('Campagnes SMS', 'sms/index', compact('org', 'list', 'reach', 'optouts', 'events', 'kpi'), 'sms');
}

/** Décompte en direct pour le formulaire (audience + longueur). */
function sms_estimate(): never
{
    $org = require_org();
    $aud = ['events' => array_values(array_filter(array_map('intval', (array)($_POST['events'] ?? [])))), 'not_event' => (int)input('not_event'), 'segment' => (string)input('segment')];
    $ev = (int)input('event_id') ? one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [(int)input('event_id'), $org['id']]) : null;
    $text = Sms::text(trim(str_replace("\r", '', (string)input('body'))), 'Alexandra', Sms::link($ev, 99999));
    [$len, $seg, $uni] = Sms::measure($text);
    $n = count(Sms::recipients((int)$org['id'], $aud));
    json_out(['recipients' => $n, 'chars' => $len, 'segments' => $seg, 'unicode' => $uni, 'cost' => money($n * $seg * Sms::price()), 'preview' => $text]);
}

function sms_report(string $id): string
{
    $org = require_org();
    $c = one('SELECT * FROM sms_campaigns WHERE id = ? AND organizer_id = ?', [(int)$id, $org['id']]);
    if (!$c) abort(404);
    if ($c['status'] === 'sending') { Sms::refresh((int)$c['id']); $c = one('SELECT * FROM sms_campaigns WHERE id = ?', [$c['id']]); }
    $ev = $c['event_id'] ? one('SELECT * FROM events WHERE id = ?', [$c['event_id']]) : null;
    $msgs = all('SELECT * FROM sms_messages WHERE campaign_id = ? ORDER BY id LIMIT 1000', [$c['id']]);
    $sales = one("SELECT COUNT(*) n, COALESCE(SUM(total), 0) revenue FROM orders WHERE organizer_id = ? AND status = 'paid' AND utm_source = 'sms' AND utm_campaign = ?", [$org['id'], 's' . $c['id']]);
    $preview = Sms::text((string)$c['body'], 'Alexandra', Sms::link($ev, (int)$c['id']));
    return pro_page($c['name'], 'sms/report', compact('org', 'c', 'ev', 'msgs', 'sales', 'preview'), 'sms', ['crumbs' => [['Campagnes SMS', '/pro/sms']]]);
}
