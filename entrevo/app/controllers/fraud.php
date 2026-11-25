<?php
declare(strict_types=1);

/* 2.3 : super-admin › fraude & risques. */

require_once APP . '/controllers/admin.php';

function admin_fraud(): string
{
    $u = require_super();
    if (is_post()) {
        $a = (string)input('action');
        $o = (int)input('order_id') ? one('SELECT * FROM orders WHERE id = ?', [(int)input('order_id')]) : null;
        if ($a === 'ok' && $o) { update('orders', ['risk_status' => 'ok'], 'id = ?', [$o['id']]); Audit::log('risk.ok', 'Commande ' . $o['ref'] . ' vérifiée', null, (int)$o['id'], (int)$o['organizer_id']); back(['success' => 'Commande ' . $o['ref'] . ' marquée comme vérifiée.']); }
        if ($a === 'block_order' && $o) {
            Risk::block('email', (string)$o['email'], 'Commande ' . $o['ref'], (int)$u['id']);
            if ($o['ip'] && input('with_ip')) Risk::block('ip', (string)$o['ip'], 'Commande ' . $o['ref'], (int)$u['id'], 30);
            update('orders', ['risk_status' => 'blocked'], 'id = ?', [$o['id']]);
            Audit::log('risk.blocked', $o['email'] . ($o['ip'] && input('with_ip') ? ' + IP ' . $o['ip'] : ''), null, (int)$o['id'], (int)$o['organizer_id']);
            redirect('/admin/commandes/' . $o['id'], ['success' => 'Acheteur bloqué. Rembourse ou annule la commande ci-dessous si besoin.']);
        }
        if ($a === 'block') {
            $kind = (string)input('kind'); $v = trim((string)input('value'));
            if (!isset(Risk::KINDS[$kind]) || $v === '') back(['error' => 'Indique ce qu\'il faut bloquer.']);
            if ($kind === 'ip' && !filter_var($v, FILTER_VALIDATE_IP)) back(['error' => 'Adresse IP invalide.']);
            if ($kind === 'email' && !is_email($v)) back(['error' => 'Adresse e-mail invalide.']);
            Risk::block($kind, ltrim($v, '@'), trim((string)input('reason')) ?: null, (int)$u['id'], (int)input('days') ?: null);
            Audit::log('risk.blocked', Risk::KINDS[$kind] . ' ' . $v);
            back(['success' => 'Ajouté à la liste de blocage.']);
        }
        if ($a === 'unblock') { q('DELETE FROM risk_blocks WHERE id = ?', [(int)input('block_id')]); back(['success' => 'Retiré de la liste.']); }
        if ($a === 'settings') {
            set_setting('risk_threshold', (string)max(10, min(100, (int)input('risk_threshold'))));
            set_setting('risk_ip_hour', (string)max(2, min(100, (int)input('risk_ip_hour'))));
            set_setting('risk_amount', (string)max(1000, parse_money(input('risk_amount'))));
            set_setting('risk_block_disposable', checked('risk_block_disposable'));
            back(['success' => 'Règles enregistrées.']);
        }
    }
    $queue = all("SELECT o.*, e.name ev_name, g.name org_name FROM orders o LEFT JOIN events e ON e.id = o.event_id JOIN organizers g ON g.id = o.organizer_id WHERE o.risk_status = 'review' ORDER BY o.risk_score DESC, o.id DESC LIMIT 200");
    $recent = all("SELECT o.id, o.ref, o.email, o.total, o.risk_score, o.risk_flags, o.risk_status, o.status, o.created_at FROM orders o WHERE o.risk_score >= 25 AND o.created_at >= NOW() - INTERVAL 30 DAY AND (o.risk_status IS NULL OR o.risk_status <> 'review') ORDER BY o.id DESC LIMIT 30");
    $blocks = all('SELECT b.*, u.name by_name FROM risk_blocks b LEFT JOIN users u ON u.id = b.created_by ORDER BY b.id DESC LIMIT 300');
    $k = one("SELECT SUM(risk_status = 'review') review, SUM(risk_status = 'blocked') blocked, SUM(risk_score >= 25 AND created_at >= NOW() - INTERVAL 30 DAY) flagged30,
        COALESCE(SUM(CASE WHEN risk_status = 'review' AND status = 'paid' THEN total END), 0) at_stake FROM orders") ?: [];
    $topIps = all("SELECT ip, COUNT(*) n, COUNT(DISTINCT LOWER(email)) emails, SUM(status = 'paid') paid, MAX(created_at) last FROM orders WHERE ip IS NOT NULL AND created_at >= NOW() - INTERVAL 7 DAY GROUP BY ip HAVING emails >= 3 ORDER BY emails DESC LIMIT 8");
    return admin_page('Fraude & risques', 'fraud', compact('queue', 'recent', 'blocks', 'k', 'topIps'), 'fraude');
}
