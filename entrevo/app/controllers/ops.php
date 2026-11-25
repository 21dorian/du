<?php
declare(strict_types=1);

/* 2.3 : super-admin › sauvegardes automatiques et alertes de supervision. */

require_once APP . '/controllers/admin.php';

function admin_backups(): string
{
    $u = require_super();
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'settings') {
            set_setting('backup_auto', checked('backup_auto'));
            set_setting('backup_hour', (string)max(0, min(23, (int)input('backup_hour'))));
            set_setting('backup_keep', (string)max(3, min(90, (int)input('backup_keep'))));
            $em = trim((string)input('backup_email'));
            if ($em !== '' && !is_email($em)) back(['error' => 'Adresse e-mail incorrecte.']);
            set_setting('backup_email', $em);
            back(['success' => 'Réglages enregistrés.']);
        }
        if ($a === 'now') {
            try { $n = Backups::run('manuelle'); set_setting('backup_error', ''); } catch (Throwable $e) { back(['error' => 'Sauvegarde impossible : ' . $e->getMessage()]); }
            Audit::log('backup.created', $n);
            back(['success' => 'Sauvegarde créée : ' . $n . '.']);
        }
        if ($a === 'delete') { try { unlink(Backups::path((string)input('name'))); } catch (Throwable $e) { back(['error' => $e->getMessage()]); } back(['success' => 'Sauvegarde supprimée.']); }
        if ($a === 'restore') {
            if (trim((string)input('confirm')) !== 'RESTAURER') back(['error' => 'Écris RESTAURER en majuscules pour confirmer.']);
            try { $r = Backups::restore((string)input('name')); } catch (Throwable $e) { log_line('sauvegardes', 'Restauration : ' . $e->getMessage()); back(['error' => 'Restauration interrompue : ' . $e->getMessage()]); }
            Audit::log('backup.restored', (string)input('name') . ' (état précédent gardé dans ' . $r['safety'] . ')');
            redirect('/admin/sauvegardes', ['success' => 'Base restaurée (' . $r['statements'] . ' instructions). L\'état d\'avant est gardé dans ' . $r['safety'] . '.']);
        }
    }
    if (input('dl')) {
        $f = Backups::path((string)input('dl'));
        Audit::log('backup.downloaded', basename($f));
        header('Content-Type: application/gzip'); header('Content-Disposition: attachment; filename="' . basename($f) . '"'); header('Content-Length: ' . filesize($f));
        readfile($f); exit;
    }
    $list = Backups::list();
    $free = function_exists('disk_free_space') ? @disk_free_space(ROOT) : false;
    return admin_page('Sauvegardes', 'backups', ['list' => $list, 'free' => $free, 'total' => array_sum(array_column($list, 'size'))], 'sauvegardes');
}

function admin_alerts(): string
{
    $u = require_super();
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'settings') {
            $em = implode(', ', array_filter(array_map('trim', preg_split('~[,;\s]+~', (string)input('alert_emails'))), 'is_email'));
            set_setting('alert_emails', $em);
            $hook = trim((string)input('alert_webhook'));
            if ($hook !== '' && !preg_match('~^https://~', $hook)) back(['error' => 'L\'adresse du webhook doit commencer par https://']);
            set_setting('alert_webhook', $hook);
            set_setting('alert_checks', implode(',', array_values(array_intersect(array_keys(Alerts::CHECKS), (array)($_POST['checks'] ?? [])))));
            set_setting('alert_cooldown', (string)max(1, min(48, (int)input('alert_cooldown'))));
            back(['success' => 'Réglages des alertes enregistrés.']);
        }
        if ($a === 'test') {
            $ch = Alerts::send('test', 'ok', 'Test des alertes', 'Si tu lis ce message, les alertes de ' . setting('platform_name') . ' arrivent bien ici.');
            back($ch ? ['success' => 'Test envoyé par : ' . implode(', ', $ch) . '.'] : ['error' => 'Aucun canal n\'a fonctionné : vérifie l\'adresse e-mail ou le webhook.']);
        }
        if ($a === 'check') { $n = Alerts::check(true); back(['success' => $n ? $n . ' alerte(s) envoyée(s).' : 'Vérification faite : rien d\'anormal (ou déjà signalé).']); }
    }
    $problems = Alerts::problems();
    $log = all('SELECT * FROM alerts_log ORDER BY id DESC LIMIT 60');
    return admin_page('Alertes', 'alerts', compact('problems', 'log'), 'alertes');
}
