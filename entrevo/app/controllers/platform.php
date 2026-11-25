<?php
declare(strict_types=1);

/* Super-admin 1.4 : vue d'ensemble, reversements groupés (SEPA), santé de la plateforme, annonces. */

require_once APP . '/controllers/admin.php';

const REAL_ONLINE = "o.status = 'paid' AND o.method = 'online' AND o.provider IN ('stripe','paypal','sumup','mollie')";

function admin_home(): string
{
    require_super();
    if (is_post() && input('action') === 'announce') {
        set_setting('announce_text', mb_substr(trim((string)input('announce_text')), 0, 600));
        set_setting('announce_level', in_array(input('announce_level'), ['info', 'warn', 'success'], true) ? (string)input('announce_level') : 'info');
        set_setting('announce_until', preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('announce_until')) ? (string)input('announce_until') . ' 23:59:59' : '');
        back(['success' => trim((string)input('announce_text')) !== '' ? 'Annonce publiée dans l\'espace de tous les organisateurs.' : 'Annonce retirée.']);
    }
    $per = Insights::period((int)input('p', 30));
    $k = fn(string $from, string $to) => one("SELECT COUNT(*) n, COALESCE(SUM(o.total),0) gmv, COALESCE(SUM(o.commission),0) com,
            COALESCE(SUM((SELECT SUM(qty) FROM order_items i WHERE i.order_id = o.id)),0) tickets, COUNT(DISTINCT o.organizer_id) orgs
        FROM orders o WHERE " . REAL_ONLINE . ' AND o.paid_at BETWEEN ? AND ?', [$from, $to]);
    $cur = $k($per['from'], $per['to']); $prev = $k($per['pFrom'], $per['pTo']);
    $newOrgs = (int)val('SELECT COUNT(*) FROM organizers WHERE created_at BETWEEN ? AND ?', [$per['from'], $per['to']]);
    $newOrgsPrev = (int)val('SELECT COUNT(*) FROM organizers WHERE created_at BETWEEN ? AND ?', [$per['pFrom'], $per['pTo']]);
    $allTickets = (int)val("SELECT COUNT(*) FROM tickets WHERE status = 'valid'");
    // série journalière (ou mensuelle) des commissions et du volume
    $monthly = $per['days'] > 120; $fmt = $monthly ? '%Y-%m' : '%Y-%m-%d';
    $map = [];
    foreach (all("SELECT DATE_FORMAT(o.paid_at, '$fmt') d, SUM(o.commission) c, SUM(o.total) g FROM orders o WHERE " . REAL_ONLINE . ' AND o.paid_at BETWEEN ? AND ? GROUP BY d', [$per['from'], $per['to']]) as $r) $map[$r['d']] = $r;
    $labels = []; $com = []; $gmv = [];
    $mois = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    for ($d = strtotime(substr($per['from'], 0, 10)); $d <= strtotime($per['to']); $d = $monthly ? strtotime('+1 month', $d) : $d + 86400) {
        $key = date($monthly ? 'Y-m' : 'Y-m-d', $d);
        if (in_array($monthly ? $mois[(int)date('n', $d)] . ' ' . date('y', $d) : date('d/m', $d), $labels, true)) continue;
        $labels[] = $monthly ? $mois[(int)date('n', $d)] . ' ' . date('y', $d) : date('d/m', $d);
        $com[] = (int)($map[$key]['c'] ?? 0); $gmv[] = (int)($map[$key]['g'] ?? 0);
    }
    $top = all("SELECT g.id, g.name, COUNT(o.id) n, SUM(o.total) gmv, SUM(o.commission) com FROM orders o JOIN organizers g ON g.id = o.organizer_id
        WHERE " . REAL_ONLINE . ' AND o.paid_at BETWEEN ? AND ? GROUP BY g.id ORDER BY gmv DESC LIMIT 8', [$per['from'], $per['to']]);
    // à faire
    $todo = [];
    $n = (int)val("SELECT COUNT(*) FROM organizers WHERE status = 'pending'");
    if ($n) $todo[] = [ui_icon('users'), $n . ' organisateur(s) à valider', 'Ils attendent pour publier', '/admin/organisateurs?st=pending', 'hot'];
    $due = payouts_due();
    $sumDue = array_sum(array_column($due, 'balance'));
    if ($due) $todo[] = [ui_icon('euro'), money($sumDue) . ' à reverser', count($due) . ' organisateur(s) · ' . count(array_filter($due, fn($o) => !$o['iban_ok'])) . ' sans IBAN valide', '/admin/reversements', ''];
    $n = (int)val("SELECT COUNT(*) FROM resales WHERE refund_status = 'failed'");
    if ($n) $todo[] = [ui_icon('refund'), $n . ' remboursement(s) de revente échoué(s)', 'À traiter à la main chez le prestataire', '/admin/commandes', 'hot'];
    $cronLast = (int)setting('cron_last', 0);
    if (!$cronLast || $cronLast < time() - 3600) $todo[] = [ui_icon('clock'), 'Tâche planifiée (cron) ' . ($cronLast ? 'arrêtée depuis ' . ago(date('Y-m-d H:i:s', $cronLast)) : 'jamais lancée'), 'Campagnes, récaps et rappels en dépendent', '/admin/sante', 'hot'];
    if (array_keys(Payments::available()) === ['demo'] || !Payments::available()) $todo[] = [ui_icon('lock'), 'Paiement en mode démo uniquement', 'Branche Stripe, PayPal, SumUp ou Mollie', '/admin/reglages?tab=payments', 'hot'];
    // 2.2 : support, litiges, données personnelles, contrats qui finissent
    $n = (int)val("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'");
    if ($n) $todo[] = [ui_icon('help'), $n . ' demande(s) de support à traiter', 'La plus ancienne attend depuis ' . ago((string)val("SELECT MIN(updated_at) FROM support_tickets WHERE status = 'open'")), '/admin/support', (int)val("SELECT COUNT(*) FROM support_tickets WHERE status = 'open' AND priority IN ('high','urgent')") ? 'hot' : ''];
    $n = (int)val("SELECT COUNT(*) FROM disputes WHERE status = 'open'");
    if ($n) $todo[] = [ui_icon('shield'), $n . ' litige(s) de paiement à traiter', money((int)val("SELECT COALESCE(SUM(amount), 0) FROM disputes WHERE status = 'open'")) . ' contestés', '/admin/litiges', (int)val("SELECT COUNT(*) FROM disputes WHERE status = 'open' AND due_on <= CURDATE() + INTERVAL 7 DAY") ? 'hot' : ''];
    $n = (int)val("SELECT COUNT(*) FROM gdpr_requests WHERE status = 'pending'");
    if ($n) $todo[] = [ui_icon('lock'), $n . ' demande(s) RGPD', 'Délai légal : un mois', '/admin/rgpd', (int)val("SELECT COUNT(*) FROM gdpr_requests WHERE status = 'pending' AND due_on <= CURDATE() + INTERVAL 7 DAY") ? 'hot' : ''];
    $n = (int)val("SELECT COUNT(*) FROM contracts WHERE status = 'active' AND ends_on BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY");
    if ($n) $todo[] = [ui_icon('doc'), $n . ' contrat(s) finissent dans 30 jours', 'À renouveler ou laisser revenir à la formule', '/admin/contrats?f=fin', ''];
    if (setting('maintenance') === '1') $todo[] = [ui_icon('settings'), 'Site en maintenance', 'Le public voit la page d\'attente', '/admin/reglages?tab=plateforme', 'hot'];
    if (!setting('smtp_host')) $todo[] = [ui_icon('mail'), 'Pas de serveur SMTP configuré', 'Les e-mails partent via mail() : risque de spam', '/admin/reglages?tab=emails', ''];
    $recent = all('SELECT * FROM organizers ORDER BY created_at DESC LIMIT 6');
    $demo = (int)val("SELECT COUNT(*) FROM orders WHERE status = 'paid' AND provider = 'demo'");
    return admin_page('Vue d\'ensemble', 'home', compact('per', 'cur', 'prev', 'newOrgs', 'newOrgsPrev', 'allTickets', 'labels', 'com', 'gmv', 'top', 'todo', 'recent', 'demo', 'sumDue'), 'dash',
        ['actions' => view('pro/_period', ['per' => $per])]);
}

// ---------- Reversements ----------

/** Organisateurs à qui on doit de l'argent. */
function payouts_due(): array
{
    $out = [];
    foreach (all("SELECT o.*, (SELECT MAX(created_at) FROM payouts p WHERE p.organizer_id = o.id) last_payout FROM organizers o WHERE o.status <> 'suspended'") as $o) {
        $b = Tickets::balance((int)$o['id']);
        if ($b['balance'] <= 0) continue;
        $o['balance'] = $b['balance']; $o['iban_ok'] = iban_valid($o['iban']);
        $o['kyc_ok'] = !Kyc::required() || ($o['kyc_status'] ?? '') === 'verified';
        $out[] = $o;
    }
    usort($out, fn($a, $b) => $b['balance'] <=> $a['balance']);
    return $out;
}

function sepa_text(string $s, int $max): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = preg_replace("~[^A-Za-z0-9/\\-?:().,'+ ]~", ' ', $s);
    return mb_substr(trim(preg_replace('~\s+~', ' ', (string)$s)), 0, $max);
}

/** Fichier de virements groupés SEPA (pain.001.001.03), accepté par les banques françaises et européennes. */
function sepa_xml(array $lines, string $batch): string
{
    $x = fn($s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $sum = number_format(array_sum(array_column($lines, 'amount')) / 100, 2, '.', '');
    $n = count($lines);
    $bic = strtoupper(trim((string)setting('sepa_bic')));
    $txs = '';
    foreach ($lines as $i => $l) {
        $txs .= '<CdtTrfTxInf><PmtId><EndToEndId>' . $x(sepa_text($batch . '-' . ($i + 1), 35)) . '</EndToEndId></PmtId>'
            . '<Amt><InstdAmt Ccy="EUR">' . number_format($l['amount'] / 100, 2, '.', '') . '</InstdAmt></Amt>'
            . ($l['bic'] ? '<CdtrAgt><FinInstnId><BIC>' . $x(strtoupper($l['bic'])) . '</BIC></FinInstnId></CdtrAgt>' : '')
            . '<Cdtr><Nm>' . $x(sepa_text($l['name'], 70)) . '</Nm></Cdtr><CdtrAcct><Id><IBAN>' . $x($l['iban']) . '</IBAN></Id></CdtrAcct>'
            . '<RmtInf><Ustrd>' . $x(sepa_text($l['label'], 140)) . '</Ustrd></RmtInf></CdtTrfTxInf>';
    }
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><CstmrCdtTrfInitn>'
        . '<GrpHdr><MsgId>' . $x($batch) . '</MsgId><CreDtTm>' . date('Y-m-d\TH:i:s') . '</CreDtTm><NbOfTxs>' . $n . '</NbOfTxs><CtrlSum>' . $sum . '</CtrlSum>'
        . '<InitgPty><Nm>' . $x(sepa_text((string)setting('sepa_name'), 70)) . '</Nm></InitgPty></GrpHdr>'
        . '<PmtInf><PmtInfId>' . $x($batch) . '</PmtInfId><PmtMtd>TRF</PmtMtd><BtchBookg>true</BtchBookg><NbOfTxs>' . $n . '</NbOfTxs><CtrlSum>' . $sum . '</CtrlSum>'
        . '<PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl></PmtTpInf><ReqdExctnDt>' . date('Y-m-d') . '</ReqdExctnDt>'
        . '<Dbtr><Nm>' . $x(sepa_text((string)setting('sepa_name'), 70)) . '</Nm></Dbtr><DbtrAcct><Id><IBAN>' . $x(strtoupper(preg_replace('~\s+~', '', (string)setting('sepa_iban')))) . '</IBAN></Id></DbtrAcct>'
        . '<DbtrAgt><FinInstnId>' . ($bic ? '<BIC>' . $x($bic) . '</BIC>' : '<Othr><Id>NOTPROVIDED</Id></Othr>') . '</FinInstnId></DbtrAgt><ChrgBr>SLEV</ChrgBr>'
        . $txs . '</PmtInf></CstmrCdtTrfInitn></Document>';
}

function payouts_page(): string
{
    $u = require_super();
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'sepa_settings') {
            $iban = strtoupper(preg_replace('~\s+~', '', (string)input('sepa_iban')));
            if ($iban !== '' && !iban_valid($iban)) back(['error' => 'Cet IBAN n\'est pas valide (clé de contrôle).']);
            set_setting('sepa_name', mb_substr(trim((string)input('sepa_name')), 0, 70));
            set_setting('sepa_iban', $iban);
            set_setting('sepa_bic', strtoupper(trim((string)input('sepa_bic'))));
            back(['success' => 'Compte émetteur enregistré.']);
        }
        if (in_array($a, ['sepa', 'mark'], true)) {
            $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
            if (!$ids) back(['error' => 'Coche au moins un organisateur.']);
            $due = array_column(payouts_due(), null, 'id');
            $lines = []; $skipped = [];
            foreach ($ids as $id) {
                if (!isset($due[$id])) continue;
                $o = $due[$id];
                $amount = min((int)$o['balance'], max(0, parse_money((string)($_POST['amount'][$id] ?? ''))) ?: (int)$o['balance']);
                if ($amount <= 0) continue;
                if ($a === 'sepa' && !$o['iban_ok']) { $skipped[] = $o['name'] . ' (IBAN)'; continue; }
                if (!$o['kyc_ok']) { $skipped[] = $o['name'] . ' (KYC non vérifié)'; continue; }
                $lines[] = ['org' => $o, 'amount' => $amount, 'iban' => strtoupper(preg_replace('~\s+~', '', (string)$o['iban'])), 'bic' => (string)$o['bic'],
                    'name' => $o['legal_name'] ?: $o['name'], 'label' => setting('platform_name') . ' reversement ' . date('m/Y') . ' ' . $o['name']];
            }
            if (!$lines) back(['error' => 'Rien à reverser dans la sélection' . ($skipped ? ' (bloqués : ' . implode(', ', $skipped) . ')' : '') . '.']);
            if ($a === 'sepa' && (!iban_valid((string)setting('sepa_iban')) || trim((string)setting('sepa_name')) === '')) back(['error' => 'Renseigne d\'abord le compte émetteur (nom et IBAN de la plateforme) en bas de la page.']);
            $batch = 'ENT' . date('ymdHis');
            if ($a === 'mark' || input('record')) {
                foreach ($lines as $l) insert('payouts', ['organizer_id' => $l['org']['id'], 'amount' => $l['amount'], 'reference' => $batch, 'note' => $a === 'sepa' ? 'Virement groupé SEPA' : 'Reversement groupé',
                    'batch' => $batch, 'created_by' => $u['id'], 'created_at' => now()]);
            }
            if ($a === 'mark') back(['success' => '' . count($lines) . ' reversement(s) enregistré(s), lot ' . $batch . '.']);
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="virements-' . $batch . '.xml"');
            echo sepa_xml($lines, $batch);
            exit;
        }
        if ($a === 'cancel_batch') {
            q('DELETE FROM payouts WHERE batch = ?', [(string)input('batch')]);
            back(['success' => 'Lot annulé : les soldes sont revenus.']);
        }
        back();
    }
    $due = payouts_due();
    $batches = all('SELECT batch, COUNT(*) n, SUM(amount) total, MIN(created_at) at, MAX(note) note FROM payouts WHERE batch IS NOT NULL GROUP BY batch ORDER BY at DESC LIMIT 20');
    $sepaOk = iban_valid((string)setting('sepa_iban')) && trim((string)setting('sepa_name')) !== '';
    return admin_page('Reversements', 'payouts', compact('due', 'batches', 'sepaOk'), 'payouts');
}

// ---------- Santé ----------
function tail_log(string $file, int $n = 15): array
{
    $f = STORAGE . '/logs/' . $file . '.log';
    if (!is_file($f)) return [];
    $size = filesize($f); $h = fopen($f, 'rb');
    fseek($h, max(0, $size - 40000)); $txt = (string)stream_get_contents($h); fclose($h);
    $entries = preg_split('~\n(?=\[\d{4}-\d{2}-\d{2} )~', trim($txt));
    return array_reverse(array_slice(array_filter($entries), -$n));
}

function dir_size(string $dir): int
{
    if (!is_dir($dir)) return 0;
    $t = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) $t += $f->getSize();
    return $t;
}

function health_page(): string
{
    require_super();
    if (is_post() && input('action') === 'run_jobs') { $r = Jobs::run(true); back(['success' => 'Tâches lancées : ' . json_encode($r, JSON_UNESCAPED_UNICODE)]); }
    $checks = [];
    $add = function (string $group, string $label, string $level, string $detail = '', string $fix = '') use (&$checks) { $checks[$group][] = compact('label', 'level', 'detail', 'fix'); };
    // serveur
    $add('Serveur', 'PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'bad', 'Minimum 8.1');
    foreach (['pdo_mysql', 'mbstring', 'gd', 'openssl', 'curl', 'zip', 'iconv'] as $ext) $add('Serveur', 'Extension ' . $ext, extension_loaded($ext) ? 'ok' : ($ext === 'zip' || $ext === 'curl' ? 'warn' : 'bad'), extension_loaded($ext) ? 'chargée' : 'manquante');
    $add('Serveur', 'Base de données', 'ok', (string)val('SELECT VERSION()') . ' · schéma v' . setting('db_version'));
    $free = @disk_free_space(ROOT);
    $add('Serveur', 'Espace disque libre', $free === false ? 'warn' : ($free < 500 * 1048576 ? 'warn' : 'ok'), $free === false ? 'inconnu' : number_format($free / 1073741824, 1, ',', ' ') . ' Go');
    $add('Serveur', 'Dossiers storage/ et uploads/', is_writable(STORAGE) && is_writable(ROOT . '/uploads') ? 'ok' : 'bad', 'uploads : ' . number_format(dir_size(ROOT . '/uploads') / 1048576, 1, ',', ' ') . ' Mo');
    $add('Serveur', 'HTTPS', (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'ok' : 'warn', 'Obligatoire pour la caméra du scanner et les paiements', 'Active le certificat SSL gratuit dans cPanel');
    // tâches
    $cron = (int)setting('cron_last', 0);
    $add('Tâches de fond', 'Tâche planifiée (cron)', $cron > time() - 900 ? 'ok' : ($cron > time() - 3600 ? 'warn' : 'bad'), $cron ? 'dernier passage ' . ago(date('Y-m-d H:i:s', $cron)) : 'jamais lancée', 'cPanel › Tâches Cron, toutes les 5 min : wget -q -O /dev/null "' . abs_url('/cron', ['cle' => Jobs::cronKey()]) . '"');
    $rep = json_decode((string)setting('jobs_report'), true) ?: [];
    $add('Tâches de fond', 'Dernier tour', $rep ? (in_array('erreur', (array)($rep['r'] ?? []), true) ? 'warn' : 'ok') : 'warn', $rep ? $rep['at'] . ' · ' . implode(', ', array_map(fn($k, $v) => $k . ' ' . (is_scalar($v) ? (string)$v : '·'), array_keys($rep['r'] ?? []), $rep['r'] ?? [])) : 'aucun');
    $sending = (int)val("SELECT COUNT(*) FROM campaigns WHERE status = 'sending'");
    $queued = (int)val("SELECT COUNT(*) FROM campaign_recipients r JOIN campaigns c ON c.id = r.campaign_id WHERE c.status = 'sending' AND r.sent_at IS NULL AND r.error IS NULL");
    $add('Tâches de fond', 'Campagnes en cours', $queued > 2000 ? 'warn' : 'ok', $sending . ' campagne(s), ' . $queued . ' e-mail(s) en file');
    $stuck = (int)val("SELECT COUNT(*) FROM orders WHERE status = 'pending' AND created_at < NOW() - INTERVAL 2 HOUR AND created_at > NOW() - INTERVAL 3 DAY");
    $add('Tâches de fond', 'Paiements en attente depuis plus de 2 h', $stuck > 5 ? 'warn' : 'ok', (string)$stuck);
    // e-mails
    $mails = []; for ($i = 6; $i >= 0; $i--) $mails[date('d/m', strtotime("-$i days"))] = (int)setting('mails_' . date('Ymd', strtotime("-$i days")), 0);
    $add('E-mails', 'Envoi', setting('smtp_host') ? 'ok' : 'warn', setting('smtp_host') ? 'SMTP ' . setting('smtp_host') : 'fonction mail() de l\'hébergement', setting('smtp_host') ? '' : 'Configure un SMTP (Réglages › E-mails) pour éviter les spams');
    $add('E-mails', 'Envoyés aujourd\'hui / 7 jours', 'ok', end($mails) . ' / ' . array_sum($mails));
    // paiements
    $prov = Payments::available();
    $add('Paiements', 'Prestataires actifs', !$prov || array_keys($prov) === ['demo'] ? 'warn' : 'ok', $prov ? implode(', ', array_map('provider_label', array_keys($prov))) : 'aucun');
    $hooks = [];
    try { $hooks = all('SELECT * FROM webhook_log ORDER BY id DESC LIMIT 10'); } catch (Throwable $e) {}
    $errors = tail_log('erreurs', 12); $mailErrors = tail_log('emails', 8); $taskErrors = tail_log('taches', 8);
    return admin_page('Santé de la plateforme', 'health', compact('checks', 'mails', 'hooks', 'errors', 'mailErrors', 'taskErrors'), 'health');
}

/** Statistiques de la plateforme : 12 mois glissants (ou 30/90 jours), démo incluse sur demande. */
function admin_stats(): string
{
    require_login(['superadmin']);
    $demo = input('demo') === '1';
    $w = $demo ? "o.status = 'paid' AND o.method = 'online' AND o.provider IS NOT NULL" : REAL_ONLINE;
    $months = in_array((int)input('m'), [3, 6, 12, 24], true) ? (int)input('m') : 12;
    $from = date('Y-m-01 00:00:00', strtotime('-' . ($months - 1) . ' months'));
    $labels = []; $keys = [];
    for ($i = $months - 1; $i >= 0; $i--) { $t = strtotime(date('Y-m-01') . " -$i months"); $keys[] = date('Y-m', $t); $labels[] = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int)date('n', $t)] . ' ' . date('y', $t); }
    $by = fn(array $rows, string $col) => array_map(fn($k) => (int)($rows[$k][$col] ?? 0), $keys);
    $monthly = array_column(all("SELECT DATE_FORMAT(o.paid_at, '%Y-%m') m, SUM(o.total) gmv, SUM(o.commission) com, COUNT(*) n,
        SUM((SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id)) tk FROM orders o WHERE $w AND o.paid_at >= ? GROUP BY m", [$from]), null, 'm');
    $orgsM = array_column(all("SELECT DATE_FORMAT(created_at, '%Y-%m') m, COUNT(*) n FROM organizers WHERE created_at >= ? GROUP BY m", [$from]), null, 'm');
    $tot = one("SELECT COUNT(*) n, COALESCE(SUM(o.total), 0) gmv, COALESCE(SUM(o.commission), 0) com, COALESCE(SUM(o.refunded_amount), 0) ref,
        COUNT(DISTINCT LOWER(o.email)) buyers, COUNT(DISTINCT o.organizer_id) orgs, COUNT(DISTINCT o.event_id) events FROM orders o WHERE $w AND o.paid_at >= ?", [$from]);
    $tot['tickets'] = array_sum($by($monthly, 'tk'));
    $prov = all("SELECT o.provider p, COUNT(*) n, SUM(o.total) gmv FROM orders o WHERE $w AND o.paid_at >= ? GROUP BY o.provider ORDER BY gmv DESC", [$from]);
    $src = all("SELECT CASE WHEN o.link_id IS NOT NULL THEN 'Liens & ambassadeurs' WHEN o.referrer_order_id IS NOT NULL THEN 'Invité par un ami' WHEN o.recovered_from IS NOT NULL THEN 'Relance panier'
        WHEN o.channel = 'widget' THEN 'Widget' WHEN o.utm_source IS NOT NULL THEN CONCAT('UTM : ', o.utm_source) ELSE 'Direct / site' END s, COUNT(*) n, SUM(o.total) gmv
        FROM orders o WHERE $w AND o.paid_at >= ? GROUP BY s ORDER BY gmv DESC LIMIT 8", [$from]);
    $topEv = all("SELECT e.id, e.name, g.name org, g.id org_id, COUNT(*) n, SUM(o.total) gmv, SUM(o.commission) com FROM orders o JOIN events e ON e.id = o.event_id JOIN organizers g ON g.id = o.organizer_id
        WHERE $w AND o.paid_at >= ? GROUP BY e.id ORDER BY gmv DESC LIMIT 10", [$from]);
    $repeat = (int)val("SELECT COUNT(*) FROM (SELECT LOWER(o.email) e FROM orders o WHERE $w AND o.paid_at >= ? GROUP BY LOWER(o.email) HAVING COUNT(DISTINCT o.event_id) >= 2) x", [$from]);
    $series = ['labels' => $labels, 'gmv' => $by($monthly, 'gmv'), 'com' => $by($monthly, 'com'), 'tickets' => $by($monthly, 'tk'), 'orgs' => array_map(fn($k) => (int)($orgsM[$k]['n'] ?? 0), $keys)];
    return admin_page('Statistiques', 'stats', compact('demo', 'months', 'tot', 'series', 'prov', 'src', 'topEv', 'repeat'), 'stats-plateforme');
}
