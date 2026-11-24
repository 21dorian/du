<?php
declare(strict_types=1);

/* 2.2 : données personnelles — formulaire public, confirmation, traitement par le super-admin. */

require_once APP . '/controllers/public.php';

function gdpr_public(): string
{
    $errors = []; $sent = false;
    $v = ['email' => '', 'kind' => (string)input('type', 'access'), 'message' => ''];
    if (is_post()) {
        $v = ['email' => strtolower(trim((string)input('email'))), 'kind' => (string)input('kind'), 'message' => trim((string)input('message'))];
        if (!is_email($v['email'])) $errors[] = 'Ton adresse e-mail a l\'air incorrecte.';
        if (!isset(Gdpr::KINDS[$v['kind']])) $errors[] = 'Choisis le type de demande.';
        if (trim((string)input('website')) !== '' || !throttle_ok_pub('rgpd')) $errors[] = 'Trop de demandes d\'un coup : réessaie plus tard.';
        if (!$errors) { Gdpr::request($v['email'], $v['kind'], $v['message']); $sent = true; }
    }
    return pub('gdpr', compact('v', 'errors', 'sent'), 'Mes données personnelles');
}

function gdpr_confirm(string $token): string
{
    $r = strlen($token) >= 20 ? Gdpr::verify($token) : null;
    if (!$r) abort(404);
    return pub('message', ['title' => 'Demande confirmée', 'text' => 'Merci. Ta demande « ' . Gdpr::KINDS[$r['kind']] . ' » sera traitée au plus tard le ' . date('d/m/Y', strtotime((string)$r['due_on'])) . '. Tu recevras la réponse par e-mail.'], 'Demande confirmée');
}

function admin_gdpr(): string
{
    require_once APP . '/controllers/admin.php';
    $u = require_super();
    if (is_post()) {
        if (input('action') === 'settings') {
            set_setting('retention_years', (string)max(0, min(10, (int)input('retention_years'))));
            set_setting('dpo_email', trim((string)input('dpo_email')));
            back(['success' => 'Réglages enregistrés.']);
        }
        if (input('action') === 'lookup') {
            $email = strtolower(trim((string)input('email')));
            if (!is_email($email)) back(['error' => 'Adresse e-mail incorrecte.']);
            $id = insert('gdpr_requests', ['email' => $email, 'kind' => (string)input('kind', 'access'), 'message' => 'Demande saisie par ' . $u['name'] . ' (reçue par un autre canal).',
                'status' => 'pending', 'token' => token(24), 'created_at' => now(), 'verified_at' => now(), 'due_on' => date('Y-m-d', strtotime('+1 month'))]);
            redirect('/admin/rgpd/' . $id);
        }
    }
    $f = (string)input('f', 'a-traiter');
    $w = match ($f) { 'traitees' => "status IN ('done','rejected')", 'non-confirmees' => "status = 'unverified'", 'toutes' => '1', default => "status = 'pending'" };
    $list = all("SELECT * FROM gdpr_requests WHERE $w ORDER BY status = 'pending' DESC, due_on, id DESC LIMIT 300");
    $k = one("SELECT SUM(status = 'pending') pending, SUM(status = 'pending' AND due_on <= CURDATE() + INTERVAL 7 DAY) urgent, SUM(status = 'done' AND done_at >= NOW() - INTERVAL 12 MONTH) done12 FROM gdpr_requests") ?: [];
    $years = (int)setting('retention_years', '0');
    $toAnon = $years ? (int)val("SELECT COUNT(*) FROM orders o JOIN events e ON e.id = o.event_id WHERE o.first_name <> 'Anonyme' AND COALESCE(e.ends_at, e.starts_at) < NOW() - INTERVAL $years YEAR") : 0;
    return admin_page('Données personnelles (RGPD)', 'gdpr', compact('list', 'k', 'f', 'years', 'toAnon'), 'rgpd');
}

function admin_gdpr_request(string $id): string
{
    require_once APP . '/controllers/admin.php';
    $u = require_super();
    $r = one('SELECT * FROM gdpr_requests WHERE id = ?', [(int)$id]);
    if (!$r) abort(404);
    if (is_post()) {
        $a = (string)input('action');
        $note = fn(string $t) => update('gdpr_requests', ['notes' => trim(($r['notes'] ? $r['notes'] . "\n" : '') . date('d/m/Y H:i') . ' · ' . $u['name'] . ' : ' . $t)], 'id = ?', [$r['id']]);
        if ($a === 'export') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="donnees-' . preg_replace('~[^a-z0-9]+~', '-', $r['email']) . '.json"');
            echo Gdpr::exportJson($r['email']); exit;
        }
        if ($a === 'send_export') {
            try {
                Mailer::send($r['email'], 'Copie de tes données personnelles', '<p>Bonjour,</p><p>Comme demandé, voici en pièce jointe la copie de toutes les données que ' . e(setting('platform_name')) . ' détient sur toi (format JSON, lisible avec un éditeur de texte).</p>',
                    [['data' => Gdpr::exportJson($r['email']), 'name' => 'mes-donnees.json']]);
            } catch (Throwable $ex) { back(['error' => 'Envoi impossible : ' . $ex->getMessage()]); }
            $note('copie des données envoyée par e-mail');
            back(['success' => 'Copie envoyée à ' . $r['email'] . '.']);
        }
        if ($a === 'anonymize') {
            if (input('confirm_email') !== $r['email']) back(['error' => 'Recopie l\'adresse e-mail exacte pour confirmer l\'effacement.']);
            $n = Gdpr::anonymize($r['email']);
            $note('données anonymisées (' . $n . ' lignes)');
            back(['success' => 'Données anonymisées : ' . $n . ' ligne(s). Les montants des commandes sont conservés pour la comptabilité.']);
        }
        if ($a === 'oppose') {
            $orgs = array_column(all('SELECT DISTINCT organizer_id FROM orders WHERE LOWER(email) = ?', [$r['email']]), 'organizer_id');
            foreach ($orgs as $oid) q('INSERT IGNORE INTO email_unsubscribes (organizer_id, email, created_at) VALUES (?, ?, ?)', [$oid, $r['email'], now()]);
            q('UPDATE orders SET marketing_optin = 0 WHERE LOWER(email) = ?', [$r['email']]);
            foreach (all('SELECT DISTINCT organizer_id, phone FROM orders WHERE LOWER(email) = ? AND phone IS NOT NULL', [$r['email']]) as $p) if ($n = Sms::normalize((string)$p['phone'])) q('INSERT IGNORE INTO sms_optouts (organizer_id, phone, created_at) VALUES (?, ?, ?)', [$p['organizer_id'], $n, now()]);
            $note('opposition appliquée : plus aucun e-mail ni SMS de ' . count($orgs) . ' organisateur(s)');
            back(['success' => 'Opposition appliquée chez ' . count($orgs) . ' organisateur(s).']);
        }
        if ($a === 'close') {
            $st = input('status') === 'rejected' ? 'rejected' : 'done';
            $reply = trim((string)input('reply'));
            update('gdpr_requests', ['status' => $st, 'done_at' => now(), 'handled_by' => $u['id']], 'id = ?', [$r['id']]);
            if ($reply !== '') { try { Mailer::send($r['email'], 'Ta demande sur tes données personnelles', '<p>' . nl2br(e($reply)) . '</p>'); } catch (Throwable $ex) { log_line('emails', 'RGPD : ' . $ex->getMessage()); } }
            $note(($st === 'done' ? 'demande clôturée' : 'demande refusée') . ($reply !== '' ? ' (réponse envoyée)' : ''));
            redirect('/admin/rgpd', ['success' => 'Demande clôturée.']);
        }
        if ($a === 'note') { $note(trim((string)input('note'))); back(); }
    }
    $inv = Gdpr::inventory($r['email']);
    return admin_page('Demande RGPD · ' . $r['email'], 'gdpr_request', compact('r', 'inv'), 'rgpd', ['crumbs' => [['Données personnelles', '/admin/rgpd']]]);
}
