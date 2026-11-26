<?php
declare(strict_types=1);

/* 3.0 : Entrevo Pay — espace pro cashless (sous-domaine pay.) : pilotage, porte-monnaie, bracelets, exploitants, matériel, équipe. */

require_once APP . '/controllers/bars.php';

/** Rubriques d'un événement : slug => [libellé, droit, groupe]. Les slugs des pages reprises de « Bars & caisses » sont identiques. */
const PAY_SECTIONS = [
    '' => ['En direct', 'live', 'Pilotage'],
    'analyse' => ['Analyse des ventes', 'rapport', 'Pilotage'],
    'previsions' => ['Prévisions de rupture', 'stocks', 'Pilotage'],
    'porte-monnaie' => ['Porte-monnaie', 'wallets', 'Festivaliers'],
    'remboursements' => ['Recharges & remboursements', 'refunds', 'Festivaliers'],
    'packs' => ['Crédits & préchargements', 'packs', 'Festivaliers'],
    'credits' => ['Crédits staff & artistes', 'credits', 'Festivaliers'],
    'ecocups' => ['Éco-cups & consignes', 'ecocups', 'Festivaliers'],
    'catalogue' => ['Produits', 'catalogue', 'Carte'],
    'consignes' => ['Types de consigne', 'consignes', 'Carte'],
    'remises' => ['Happy hours & remises', 'remises', 'Carte'],
    'appareils' => ['Points de vente & appareils', 'appareils', 'Terrain'],
    'caisses' => ['Caisses & clôtures', 'caisses', 'Terrain'],
    'stocks' => ['Stocks', 'stocks', 'Terrain'],
    'reassorts' => ['Réassorts & pertes', 'stocks', 'Terrain'],
    'materiel' => ['Parc de terminaux', 'materiel', 'Terrain'],
    'equipe' => ['Serveurs & droits', 'equipe', 'Terrain'],
    'bilan' => ['Bilan de l\'événement', 'rapport', 'Argent'],
    'exploitants' => ['Exploitants & reversements', 'exploitants', 'Argent'],
    'rapport' => ['Rapport de ventes', 'rapport', 'Argent'],
    'ventes' => ['Journal des ventes', 'ventes', 'Argent'],
    'exports' => ['Exports comptables', 'exports', 'Argent'],
    'portail' => ['Portail & cashless', 'reglages', 'Réglages'],
    'reglages' => ['Caisse', 'reglages', 'Réglages'],
    'journal' => ['Journal des actions', 'journal', 'Réglages'],
];
const PAY_REUSED = ['catalogue', 'consignes', 'remises', 'appareils', 'caisses', 'stocks', 'equipe', 'credits', 'rapport', 'ventes', 'reglages'];

function pay_require(?string $section = null): array
{
    $u = user();
    if (!$u) redirect(Pay::path('/connexion?suite=') . urlencode(route_path() . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '')));
    $m = Pay::member($u);
    if (!$m) {
        if (is_super()) redirect('/admin/organisateurs', ['info' => 'Choisis d\'abord un organisateur à consulter, puis ouvre Entrevo Pay.']);
        abort(403, 'Ce compte n\'a pas accès à Entrevo Pay. Demande à l\'organisateur de t\'ajouter à l\'équipe cashless.');
    }
    if ($section !== null && !Pay::can($m, $section)) abort(403, 'Ton rôle (' . Pay::ROLES[$m['role']][0] . ') ne donne pas accès à cette page.');
    $GLOBALS['pay_ctx'] = true;
    return $m;
}

function pay_event(array $m, string $id): array
{
    $ev = one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [(int)$id, $m['org']['id']]);
    if (!$ev) abort(404, 'Événement introuvable.');
    $_SESSION['pay_ev'] = (int)$ev['id'];
    return $ev;
}

function pay_page(array $m, ?array $ev, string $active, string $title, string $content, array $extra = []): string
{
    $events = all('SELECT id, name, starts_at, cashless_enabled FROM events WHERE organizer_id = ? ORDER BY starts_at DESC LIMIT 40', [$m['org']['id']]);
    return view('pay/layout', ['m' => $m, 'ev' => $ev, 'events' => $events, 'active' => $active, 'title' => $title, 'content' => $content] + $extra);
}

// ---------- connexion ----------
function pay_login(): string
{
    if (($u = user()) && Pay::member($u)) redirect(($u['role'] === 'operator') ? Pay::path('/exploitant') : Pay::path('/pro'));
    $error = null;
    if (is_post()) {
        $email = strtolower(trim((string)input('email')));
        $u = one('SELECT * FROM users WHERE email = ?', [$email]);
        if (too_many_attempts()) $error = 'Trop d\'essais : patiente 15 minutes.';
        elseif ($u && Security::lockedUntil($u)) $error = 'Compte verrouillé temporairement après trop d\'essais. Réinitialise ton mot de passe ou patiente.';
        elseif ($u && $u['active'] && password_verify((string)input('password'), $u['password_hash'])) {
            clear_attempts();
            $next = (string)input('suite');
            if ($next === '' || !str_starts_with($next, Pay::path('')) || str_starts_with($next, '//')) $next = $u['role'] === 'operator' ? Pay::path('/exploitant') : Pay::path('/pro');
            if ((int)$u['totp_enabled'] && $u['totp_secret']) {
                session_regenerate_id(true);
                $_SESSION['mfa'] = ['uid' => (int)$u['id'], 'next' => $next, 'at' => time(), 'tries' => 0];
                redirect('/connexion/code');
            }
            Security::log($u, $email, 'ok');
            login_user($u);
            redirect($next);
        } else {
            record_failed_attempt();
            if ($u) { Security::failed($u); Security::log($u, $email, 'fail'); }
            $error = 'E-mail ou mot de passe incorrect.';
        }
    }
    return view('pay/login', ['error' => $error, 'suite' => (string)input('suite')]);
}

function pay_logout(): never { logout_user(); redirect(Pay::path('/connexion'), ['success' => 'Tu es déconnecté.']); }

// ---------- accueil : choix de l'événement ----------
function pay_home(): string
{
    $m = pay_require();
    if ($m['role'] === 'operator') redirect(Pay::path('/exploitant'));
    $events = all('SELECT e.*, (SELECT COALESCE(SUM(amount), 0) FROM pos_sales s WHERE s.event_id = e.id AND s.undone = 0 AND s.kind = \'sale\') ttc,
        (SELECT COUNT(*) FROM wallets w WHERE w.event_id = e.id) wallets, (SELECT COUNT(*) FROM pos_devices d WHERE d.event_id = e.id AND d.revoked = 0 AND d.last_seen >= NOW() - INTERVAL 5 MINUTE) online
        FROM events e WHERE e.organizer_id = ? ORDER BY e.starts_at DESC', [$m['org']['id']]);
    if (!input('tous') && !empty($_SESSION['pay_ev']) && array_filter($events, fn($e) => (int)$e['id'] === (int)$_SESSION['pay_ev'])) redirect(Pay::path('/pro/e/') . (int)$_SESSION['pay_ev']);
    return pay_page($m, null, 'home', 'Tes événements', view('pay/home', compact('events', 'm')));
}

// ---------- tableau de bord en direct ----------
function pay_dashboard(string $id): string { return pay_section($id, ''); }

function pay_live_json(string $id): never
{
    $m = pay_require('live'); $ev = pay_event($m, $id);
    json_out(Pay::live($ev, $m['role'] === 'bar' ? $m['bars'] : []));
}

/** Une rubrique d'événement : pages propres à Pay, ou pages « Bars & caisses » reprises dans la coque Pay. */
function pay_section(string $id, string $section): string
{
    if (!array_key_exists($section, PAY_SECTIONS)) abort(404);
    [$label, $perm, $group] = PAY_SECTIONS[$section];
    $m = pay_require($perm); $ev = pay_event($m, $id);
    $base = Pay::path('/pro/e/') . $ev['id'];
    $GLOBALS['pay_bars'] = $m['role'] === 'bar' ? $m['bars'] : [];
    if (in_array($section, PAY_REUSED, true)) {
        $fn = 'bars_' . $section;
        $vars = $fn($ev, $m['org'], $base . '/' . $section);
        $content = view('pro/bars/' . $section, $vars + ['btab' => $section, 'bbase' => $base, 'ev' => $ev]);
        return pay_page($m, $ev, $section, $label, $content);
    }
    $fn = 'pay_s_' . str_replace('-', '_', $section ?: 'live');
    return $fn($m, $ev, $base, $label);
}

function pay_s_live(array $m, array $ev, string $base, string $label): string
{
    $L = Pay::live($ev, $m['role'] === 'bar' ? $m['bars'] : []);
    return pay_page($m, $ev, '', 'En direct', view('pay/live', compact('L', 'ev', 'base')), ['live' => true]);
}

// ---------- porte-monnaie ----------
function pay_s_porte_monnaie(array $m, array $ev, string $base, string $label): string
{
    if (is_post() && input('action') === 'virtual') {
        $email = strtolower(trim((string)input('email')));
        if ($email !== '' && !is_email($email)) back(['error' => 'E-mail invalide.']);
        $w = Pay::virtualCard($ev, $email, trim((string)input('name')), (string)input('profile'));
        if ($amt = parse_money((string)input('amount'))) Pay::credit($w, $amt, 'gift', 'Crédit initial', (int)user()['id']);
        if ($email && checked('send')) Pay::sendWalletLink(one('SELECT * FROM wallets WHERE id = ?', [$w['id']]), $ev);
        Audit::log('pay.virtual', 'Carte virtuelle ' . $w['code'] . ' créée', (int)$ev['id']);
        redirect($base . '/porte-monnaie/' . $w['id'], ['success' => 'Carte cashless ' . $w['code'] . ' créée.']);
    }
    $q = trim((string)input('q'));
    $filter = (string)input('f');
    $list = Pay::search((int)$ev['id'], $q, 80);
    if ($filter === 'blocked') $list = all("SELECT * FROM wallets WHERE event_id = ? AND status = 'blocked' ORDER BY id DESC", [$ev['id']]);
    if ($filter === 'refund') $list = all("SELECT * FROM wallets WHERE event_id = ? AND status = 'refund_req' ORDER BY id DESC", [$ev['id']]);
    if ($filter === 'positive') $list = all('SELECT * FROM wallets WHERE event_id = ? AND balance > 0 ORDER BY balance DESC LIMIT 200', [$ev['id']]);
    if (input('export') === 'csv') {
        $rows = [['Code', 'Titulaire', 'E-mail', 'Profil', 'Bracelet NFC', 'Carte virtuelle', 'Solde', 'Statut', 'Créé le']];
        foreach (all('SELECT * FROM wallets WHERE event_id = ? ORDER BY id', [$ev['id']]) as $w) $rows[] = [$w['code'], Pay::holder($w), $w['email'], Pay::PROFILES[$w['profile']] ?? $w['profile'], $w['nfc_uid'], $w['virtual'] ? 'oui' : '', money_input((int)$w['balance']), $w['status'], $w['created_at']];
        csv_download('porte-monnaie-' . $ev['slug'] . '.csv', $rows);
    }
    $k = Cashless::stats((int)$ev['id']);
    $counts = one("SELECT COUNT(*) n, COUNT(CASE WHEN status = 'blocked' THEN 1 END) blocked, COUNT(CASE WHEN status = 'refund_req' THEN 1 END) refund, COUNT(CASE WHEN balance > 0 THEN 1 END) positive, COUNT(CASE WHEN virtual = 1 THEN 1 END) virt, COUNT(CASE WHEN nfc_uid IS NOT NULL THEN 1 END) nfc FROM wallets WHERE event_id = ?", [$ev['id']]);
    return pay_page($m, $ev, 'porte-monnaie', $label, view('pay/wallets', compact('list', 'q', 'filter', 'k', 'counts', 'base', 'ev')));
}

function pay_wallet(string $id, string $wid): string
{
    $m = pay_require('wallets'); $ev = pay_event($m, $id);
    $base = Pay::path('/pro/e/') . $ev['id'];
    $w = one('SELECT * FROM wallets WHERE id = ? AND event_id = ?', [(int)$wid, $ev['id']]);
    if (!$w) abort(404);
    $uid = (int)user()['id'];
    if (is_post()) {
        $a = (string)input('action');
        try {
            if ($a === 'credit') {
                $amt = parse_money((string)input('amount')) * (input('sign') === '-' ? -1 : 1);
                $kind = input('kind') === 'gift' ? 'gift' : 'adjust';
                $note = trim((string)input('note'));
                if ($kind === 'adjust' && $note === '') throw new RuntimeException('Indique le motif de la correction.');
                $b = Pay::credit($w, $amt, $kind, $note ?: null, $uid);
                Audit::log('pay.credit', ($amt > 0 ? '+' : '') . money($amt) . ' sur ' . $w['code'] . ($note ? ' : ' . $note : ''), (int)$ev['id']);
                back(['success' => 'Nouveau solde : ' . money($b) . '.']);
            }
            if ($a === 'block') { Pay::block($w, trim((string)input('reason')) ?: 'Bloqué par l\'organisation'); Audit::log('pay.block', 'Porte-monnaie ' . $w['code'] . ' bloqué', (int)$ev['id']); back(['success' => 'Porte-monnaie bloqué : il ne peut plus payer.']); }
            if ($a === 'unblock') { Pay::unblock($w); Audit::log('pay.unblock', 'Porte-monnaie ' . $w['code'] . ' débloqué', (int)$ev['id']); back(['success' => 'Porte-monnaie débloqué.']); }
            if ($a === 'bracelet') { Pay::attachBracelet($w, (string)input('ref'), (int)$m['org']['id']); Audit::log('pay.bracelet', 'Bracelet associé à ' . $w['code'], (int)$ev['id']); back(['success' => 'Bracelet associé ; l\'ancien est désactivé.']); }
            if ($a === 'unlink') { update('wallets', ['nfc_uid' => null], 'id = ?', [$w['id']]); q("UPDATE pay_wristbands SET status = 'lost', wallet_id = NULL WHERE wallet_id = ?", [$w['id']]); back(['success' => 'Bracelet dissocié.']); }
            if ($a === 'transfer') {
                $to = Pay::transfer($w, (string)input('to'), parse_money((string)input('amount')), $uid);
                Audit::log('pay.transfer', 'Transfert ' . $w['code'] . ' → ' . $to['code'], (int)$ev['id']);
                back(['success' => 'Transfert effectué vers ' . $to['code'] . '.']);
            }
            if ($a === 'profile') {
                update('wallets', ['profile' => array_key_exists((string)input('profile'), Pay::PROFILES) ? (string)input('profile') : 'public', 'holder_name' => mb_substr(trim((string)input('holder_name')), 0, 120) ?: null,
                    'email' => is_email((string)input('email')) ? strtolower((string)input('email')) : $w['email'], 'phone' => mb_substr(trim((string)input('phone')), 0, 30) ?: null], 'id = ?', [$w['id']]);
                back(['success' => 'Fiche enregistrée.']);
            }
            if ($a === 'refund_done' && $w['status'] === 'refund_req') {
                $fee = min((int)$w['balance'], (int)Pay::settings($ev)['refund_fee']);
                insert('wallet_tx', ['wallet_id' => $w['id'], 'event_id' => $ev['id'], 'kind' => 'refund', 'amount' => -(int)$w['balance'], 'user_id' => $uid, 'items' => json_encode(['iban' => substr((string)$w['refund_iban'], -4), 'frais' => $fee]), 'created_at' => now()]);
                q("UPDATE wallets SET balance = 0, status = 'refunded', refunded_at = NOW() WHERE id = ?", [$w['id']]);
                back(['success' => 'Remboursement noté.']);
            }
            if ($a === 'send') { Pay::sendWalletLink($w, $ev); back(['success' => 'Lien envoyé à ' . $w['email'] . '.']); }
        } catch (RuntimeException $e) { back(['error' => $e->getMessage()]); }
        back();
    }
    $tx = all('SELECT t.*, b.name bar, u.name who FROM wallet_tx t LEFT JOIN bars b ON b.id = t.bar_id LEFT JOIN users u ON u.id = t.user_id WHERE t.wallet_id = ? ORDER BY t.id DESC LIMIT 200', [$w['id']]);
    $sales = all('SELECT s.*, b.name bar FROM pos_sales s JOIN bars b ON b.id = s.bar_id WHERE s.wallet_id = ? ORDER BY s.id DESC LIMIT 50', [$w['id']]);
    $bracelets = all('SELECT * FROM pay_wristbands WHERE wallet_id = ? OR (uid = ? AND organizer_id = ?)', [$w['id'], (string)$w['nfc_uid'], $m['org']['id']]);
    $ticket = $w['ticket_id'] ? one('SELECT t.*, o.ref FROM tickets t JOIN orders o ON o.id = t.order_id WHERE t.id = ?', [$w['ticket_id']]) : null;
    $totals = one("SELECT COALESCE(SUM(CASE WHEN amount > 0 AND kind IN ('topup','cash','card') THEN amount END), 0) loaded, COALESCE(-SUM(CASE WHEN kind = 'sale' THEN amount END), 0) spent FROM wallet_tx WHERE wallet_id = ? AND undone = 0", [$w['id']]);
    return pay_page($m, $ev, 'porte-monnaie', 'Porte-monnaie ' . $w['code'], view('pay/wallet', compact('w', 'tx', 'sales', 'bracelets', 'ticket', 'totals', 'base', 'ev')));
}

// ---------- recharges et remboursements ----------
function pay_s_remboursements(array $m, array $ev, string $base, string $label): string
{
    if (is_post() && input('action') === 'done_all') {
        $n = 0; $uid = (int)user()['id'];
        foreach (array_map('intval', (array)($_POST['ids'] ?? [])) as $wid) {
            $w = one("SELECT * FROM wallets WHERE id = ? AND event_id = ? AND status = 'refund_req'", [$wid, $ev['id']]);
            if (!$w || (int)$w['balance'] <= 0) continue;
            insert('wallet_tx', ['wallet_id' => $w['id'], 'event_id' => $ev['id'], 'kind' => 'refund', 'amount' => -(int)$w['balance'], 'user_id' => $uid, 'items' => json_encode(['iban' => substr((string)$w['refund_iban'], -4)]), 'created_at' => now()]);
            q("UPDATE wallets SET balance = 0, status = 'refunded', refunded_at = NOW() WHERE id = ?", [$w['id']]); $n++;
        }
        back(['success' => $n . ' remboursement(s) marqué(s) comme virés.']);
    }
    $fee = (int)Pay::settings($ev)['refund_fee'];
    $requests = all("SELECT * FROM wallets WHERE event_id = ? AND status = 'refund_req' ORDER BY id", [$ev['id']]);
    if (input('export') === 'sepa') {
        $rows = [['Bénéficiaire', 'IBAN', 'Montant', 'Référence']];
        foreach ($requests as $w) $rows[] = [Pay::holder($w) ?: $w['email'], $w['refund_iban'], money_input(max(0, (int)$w['balance'] - $fee)), 'Remboursement cashless ' . $w['code']];
        csv_download('remboursements-' . $ev['slug'] . '.csv', $rows);
    }
    $topups = all("SELECT t.*, w.code, w.email FROM wallet_tx t JOIN wallets w ON w.id = t.wallet_id WHERE t.event_id = ? AND t.kind IN ('topup','cash','card','gift') AND t.undone = 0 ORDER BY t.id DESC LIMIT 100", [$ev['id']]);
    $refunded = all("SELECT t.*, w.code, w.email FROM wallet_tx t JOIN wallets w ON w.id = t.wallet_id WHERE t.event_id = ? AND t.kind = 'refund' ORDER BY t.id DESC LIMIT 60", [$ev['id']]);
    $k = Cashless::stats((int)$ev['id']);
    $sum = one("SELECT COALESCE(SUM(CASE WHEN kind = 'topup' THEN amount END), 0) online, COALESCE(SUM(CASE WHEN kind = 'cash' AND undone = 0 THEN amount END), 0) cash, COALESCE(SUM(CASE WHEN kind = 'card' AND undone = 0 THEN amount END), 0) card, COALESCE(SUM(CASE WHEN kind = 'gift' THEN amount END), 0) gift FROM wallet_tx WHERE event_id = ?", [$ev['id']]);
    return pay_page($m, $ev, 'remboursements', $label, view('pay/refunds', compact('requests', 'topups', 'refunded', 'k', 'sum', 'fee', 'base', 'ev')));
}

// ---------- crédits et préchargements (packs) ----------
function pay_s_packs(array $m, array $ev, string $base, string $label): string
{
    if (is_post() && input('action') === 'bulk') {
        $amt = parse_money((string)input('amount'));
        if ($amt <= 0) back(['error' => 'Indique un montant.']);
        $note = trim((string)input('note')) ?: 'Crédit offert';
        $targets = [];
        $profile = (string)input('profile');
        if ($profile !== '' && array_key_exists($profile, Pay::PROFILES)) $targets = all('SELECT * FROM wallets WHERE event_id = ? AND profile = ? AND status = \'active\'', [$ev['id'], $profile]);
        $refs = preg_split('~[\s,;]+~', trim((string)input('refs')), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($refs as $r) if ($w = Pay::find((int)$ev['id'], $r)) $targets[] = $w;
        $seen = []; $n = 0; $uid = (int)user()['id'];
        foreach ($targets as $w) { if (isset($seen[$w['id']])) continue; $seen[$w['id']] = 1; Pay::credit($w, $amt, 'gift', $note, $uid); $n++; }
        Audit::log('pay.bulk', $n . ' porte-monnaie crédités de ' . money($amt) . ' (' . $note . ')', (int)$ev['id']);
        back(['success' => $n . ' porte-monnaie crédité(s) de ' . money($amt) . '.']);
    }
    if (is_post() && input('action') === 'cards') {
        // cartes virtuelles en série (invités, partenaires) avec un crédit
        $n = max(1, min(500, (int)input('count'))); $amt = parse_money((string)input('amount')); $profile = (string)input('profile'); $uid = (int)user()['id']; $codes = [['Code', 'Lien', 'Crédit']];
        for ($i = 0; $i < $n; $i++) { $w = Pay::virtualCard($ev, '', trim((string)input('label')) ?: 'Invité', $profile); if ($amt) Pay::credit($w, $amt, 'gift', trim((string)input('label')) ?: 'Carte offerte', $uid); $codes[] = [$w['code'], Pay::absUrl('/w/' . $w['token']), money_input($amt)]; }
        csv_download('cartes-' . $ev['slug'] . '.csv', $codes);
    }
    $gifts = all("SELECT t.*, w.code, w.profile, u.name who FROM wallet_tx t JOIN wallets w ON w.id = t.wallet_id LEFT JOIN users u ON u.id = t.user_id WHERE t.event_id = ? AND t.kind = 'gift' ORDER BY t.id DESC LIMIT 80", [$ev['id']]);
    $profiles = []; foreach (all('SELECT profile, COUNT(*) n, SUM(balance) b FROM wallets WHERE event_id = ? GROUP BY profile', [$ev['id']]) as $r) $profiles[$r['profile']] = $r;
    $giftTotal = (int)val("SELECT COALESCE(SUM(amount), 0) FROM wallet_tx WHERE event_id = ? AND kind = 'gift'", [$ev['id']]);
    $allow = all('SELECT * FROM pos_allowances WHERE event_id = ? AND active = 1', [$ev['id']]);
    return pay_page($m, $ev, 'packs', $label, view('pay/packs', compact('gifts', 'profiles', 'giftTotal', 'allow', 'base', 'ev')));
}

// ---------- éco-cups ----------
function pay_s_ecocups(array $m, array $ev, string $base, string $label): string
{
    $E = Pay::ecocups($ev);
    return pay_page($m, $ev, 'ecocups', $label, view('pay/ecocups', compact('E', 'base', 'ev')));
}

// ---------- parc de terminaux ----------
function pay_s_materiel(array $m, array $ev, string $base, string $label): string
{
    $devices = all('SELECT d.*, b.name bar_name, (SELECT COUNT(*) FROM pos_sales s WHERE s.device_id = d.id AND s.undone = 0) sales, (SELECT COALESCE(SUM(amount), 0) FROM pos_sales s WHERE s.device_id = d.id AND s.undone = 0 AND s.kind = \'sale\') ttc,
        (SELECT MAX(created_at) FROM pos_sales s WHERE s.device_id = d.id) last_sale, (SELECT COUNT(*) FROM pos_sales s WHERE s.device_id = d.id AND s.offline = 1) offline_sales
        FROM pos_devices d LEFT JOIN bars b ON b.id = d.bar_id WHERE d.event_id = ? AND d.revoked = 0 ORDER BY d.last_seen IS NULL, d.last_seen DESC', [$ev['id']]);
    if ($m['role'] === 'bar' && $m['bars']) $devices = array_values(array_filter($devices, fn($d) => in_array((int)$d['bar_id'], $m['bars'], true)));
    $latest = trim((string)@file_get_contents(ROOT . '/VERSION'));
    return pay_page($m, $ev, 'materiel', $label, view('pay/fleet', compact('devices', 'latest', 'base', 'ev')));
}

// ---------- exploitants (relevés de l'événement) ----------
function pay_s_exploitants(array $m, array $ev, string $base, string $label): string
{
    $ops = all('SELECT * FROM pay_operators WHERE organizer_id = ? ORDER BY active DESC, name', [$m['org']['id']]);
    if (is_post() && input('action') === 'assign') {
        foreach ((array)($_POST['bar_op'] ?? []) as $bid => $oid) update('bars', ['operator_id' => (int)$oid ?: null], 'id = ? AND event_id = ?', [(int)$bid, $ev['id']]);
        back(['success' => 'Rattachement des points de vente enregistré.']);
    }
    $bars = all('SELECT * FROM bars WHERE event_id = ? ORDER BY id', [$ev['id']]);
    $st = []; foreach ($ops as $o) $st[(int)$o['id']] = Pay::statement($ev, $o);
    $own = Pay::statement($ev, ['id' => 0, 'commission_pct' => 0, 'fixed_fee' => 0, 'keeps_cash' => 0, 'keeps_card' => 0]);
    return pay_page($m, $ev, 'exploitants', $label, view('pay/operators_event', compact('ops', 'bars', 'st', 'own', 'base', 'ev')));
}

function pay_operator_statement(string $id, string $oid): string
{
    $m = pay_require('exploitants'); $ev = pay_event($m, $id);
    $op = one('SELECT * FROM pay_operators WHERE id = ? AND organizer_id = ?', [(int)$oid, $m['org']['id']]);
    if (!$op) abort(404);
    if (is_post() && input('action') === 'payout') {
        $amt = parse_money((string)input('amount'));
        if (!$amt) back(['error' => 'Indique le montant viré.']);
        insert('pay_payouts', ['organizer_id' => $m['org']['id'], 'operator_id' => $op['id'], 'event_id' => $ev['id'], 'amount' => $amt, 'note' => mb_substr(trim((string)input('note')), 0, 255) ?: null, 'paid_at' => input('paid_at') ?: date('Y-m-d'), 'created_by' => user()['id'], 'created_at' => now()]);
        Audit::log('pay.payout', 'Reversement ' . money($amt) . ' à ' . $op['name'], (int)$ev['id']);
        back(['success' => 'Reversement enregistré.']);
    }
    $S = Pay::statement($ev, $op);
    if (input('pdf')) pay_statement_pdf($m['org'], $ev, $op, $S);
    return pay_page($m, $ev, 'exploitants', 'Relevé · ' . $op['name'], view('pay/operator_statement', compact('op', 'S', 'ev') + ['base' => Pay::path('/pro/e/') . $ev['id']]));
}

function pay_statement_pdf(array $org, array $ev, array $op, array $S): never
{
    $t = fn($s) => PdfDoc::t((string)$s);
    $pdf = new PdfDoc('P', 'mm', 'A4'); $pdf->SetMargins(18, 18, 18); $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 20); $pdf->Cell(0, 10, $t('Relevé exploitant'), 0, 1);
    $pdf->SetFont('Helvetica', '', 10); $pdf->SetTextColor(110, 110, 115);
    $pdf->MultiCell(0, 5, $t(($org['legal_name'] ?: $org['name']) . "\n" . $ev['name'] . ' · ' . fr_date($ev['starts_at'], false) . "\nExploitant : " . ($op['legal_name'] ?: $op['name']) . ($op['siret'] ? ' · SIRET ' . $op['siret'] : '') . "\nÉdité le " . date('d/m/Y H:i')));
    $pdf->Ln(4); $pdf->SetTextColor(29, 29, 31);
    $row = function (string $l, string $v, bool $b = false) use ($pdf, $t) { $pdf->SetFont('Helvetica', $b ? 'B' : '', 11); $pdf->Cell(120, 7.5, $t($l), 'B'); $pdf->Cell(54, 7.5, $t($v), 'B', 1, 'R'); };
    foreach ($S['per_bar'] as $b) $row($b['name'] . ' (' . $b['n'] . ' ventes)', money($b['ttc']));
    $pdf->Ln(3);
    $row('Ventes cashless', money($S['cashless'])); $row('Ventes carte', money($S['card'])); $row('Ventes espèces', money($S['cash']));
    $row('Chiffre d\'affaires TTC', money($S['ttc']), true); $row('dont HT', money($S['ht']));
    $pdf->Ln(3);
    $row('Encaissé par l\'organisateur', money($S['collected_by_org'])); $row('Encaissé directement par l\'exploitant', money($S['kept_by_operator']));
    $row('Commission (' . rtrim(rtrim(number_format((float)$op['commission_pct'], 2, ',', ''), '0'), ',') . ' %' . ((int)$op['fixed_fee'] ? ' + ' . money((int)$op['fixed_fee']) : '') . ')', '- ' . money($S['commission']));
    $row('Montant dû à l\'exploitant', money($S['due']), true);
    foreach ($S['payouts'] as $p) $row('Virement du ' . date('d/m/Y', strtotime($p['paid_at'])) . ($p['note'] ? ' · ' . $p['note'] : ''), '- ' . money((int)$p['amount']));
    $row($S['left'] >= 0 ? 'Reste à verser' : 'L\'exploitant doit', money(abs($S['left'])), true);
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="releve-' . preg_replace('~[^a-z0-9]+~', '-', strtolower($op['name'])) . '.pdf"');
    echo $pdf->Output('S'); exit;
}

// ---------- exports comptables ----------
function pay_s_exports(array $m, array $ev, string $base, string $label): string
{
    $x = (string)input('x');
    if ($x === 'journal') {
        $rows = [['Date', 'Ticket', 'Point de vente', 'Serveur', 'Type', 'Moyen', 'Montant TTC', 'HT', 'TVA', 'Offert', 'Remise', 'Porte-monnaie']];
        foreach (all('SELECT s.*, b.name bar, w.code wcode FROM pos_sales s JOIN bars b ON b.id = s.bar_id LEFT JOIN wallets w ON w.id = s.wallet_id WHERE s.event_id = ? AND s.undone = 0 ORDER BY s.created_at', [$ev['id']]) as $s) {
            $ht = 0; foreach (json_decode((string)$s['vat_json'], true) ?: [] as $rate => $v) $ht += (int)round($v / (1 + (float)$rate / 100));
            $paid = !in_array($s['method'], ['allowance', 'offered'], true) && ($s['kind'] ?? 'sale') === 'sale';
            $rows[] = [$s['created_at'], $s['ticket_no'], $s['bar'], $s['staff'], $s['kind'], Till::METHODS[$s['method']] ?? $s['method'], money_input((int)$s['amount']), $paid ? money_input($ht) : '', $paid ? money_input((int)$s['amount'] - $ht) : '', money_input((int)$s['offered_value']), money_input((int)$s['discount']), $s['wcode']];
        }
        csv_download('journal-caisse-' . $ev['slug'] . '.csv', $rows);
    }
    if ($x === 'wallet') {
        $rows = [['Date', 'Porte-monnaie', 'Opération', 'Montant', 'Point de vente', 'Par']];
        foreach (all('SELECT t.*, w.code, b.name bar, u.name who FROM wallet_tx t JOIN wallets w ON w.id = t.wallet_id LEFT JOIN bars b ON b.id = t.bar_id LEFT JOIN users u ON u.id = t.user_id WHERE t.event_id = ? AND t.undone = 0 ORDER BY t.id', [$ev['id']]) as $t)
            $rows[] = [$t['created_at'], $t['code'], Pay::TX[$t['kind']] ?? $t['kind'], money_input((int)$t['amount']), $t['bar'], $t['who']];
        csv_download('mouvements-cashless-' . $ev['slug'] . '.csv', $rows);
    }
    if ($x === 'ecritures') {
        // écritures par jour : ventes HT par taux (706/707), TVA collectée (44571), encaissements (531 espèces, 5112 CB, 4191 cashless)
        $acc = ['cash' => '531000', 'card' => '511200', 'cashless' => '419100'];
        $rows = [['Journal', 'Date', 'Compte', 'Libellé', 'Débit', 'Crédit']];
        $days = [];
        foreach (all("SELECT * FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind IN ('sale','refund') ORDER BY created_at", [$ev['id']]) as $s) {
            if (in_array($s['method'], ['allowance', 'offered'], true)) continue;
            $d = substr($s['created_at'], 0, 10);
            $parts = $s['method'] === 'split' ? (json_decode((string)$s['payments_json'], true) ?: []) : [['method' => $s['method'], 'amount' => (int)$s['amount']]];
            foreach ($parts as $p) $days[$d]['pay'][$p['method']] = ($days[$d]['pay'][$p['method']] ?? 0) + (int)$p['amount'];
            foreach (json_decode((string)$s['vat_json'], true) ?: [] as $rate => $v) { $ht = (int)round($v / (1 + (float)$rate / 100)); $days[$d]['ht'][$rate] = ($days[$d]['ht'][$rate] ?? 0) + $ht; $days[$d]['tva'] = ($days[$d]['tva'] ?? 0) + (int)$v - $ht; }
            foreach (json_decode((string)$s['items'], true) ?: [] as $l) if (in_array($l['kind'] ?? '', ['deposit', 'return'], true)) $days[$d]['dep'] = ($days[$d]['dep'] ?? 0) + (int)$l['total'];
        }
        $fmt = fn($c) => $c ? number_format($c / 100, 2, ',', '') : '';
        foreach ($days as $d => $x2) {
            $lib = 'Ventes ' . $ev['name'] . ' ' . date('d/m', strtotime($d));
            foreach ($x2['pay'] ?? [] as $mth => $a) $rows[] = ['VT', $d, $acc[$mth] ?? '580000', $lib . ' · ' . (Till::METHODS[$mth] ?? $mth), $fmt(max(0, $a)), $fmt(max(0, -$a))];
            foreach ($x2['ht'] ?? [] as $rate => $a) $rows[] = ['VT', $d, (float)$rate >= 20 ? '707100' : ((float)$rate >= 10 ? '707200' : '707300'), $lib . ' · HT ' . rtrim(rtrim((string)$rate, '0'), '.') . ' %', $fmt(max(0, -$a)), $fmt(max(0, $a))];
            if (!empty($x2['tva'])) $rows[] = ['VT', $d, '445710', $lib . ' · TVA collectée', $fmt(max(0, -$x2['tva'])), $fmt(max(0, $x2['tva']))];
            if (!empty($x2['dep'])) $rows[] = ['VT', $d, '419800', $lib . ' · Consignes', $fmt(max(0, -$x2['dep'])), $fmt(max(0, $x2['dep']))];
        }
        csv_download('ecritures-' . $ev['slug'] . '.csv', $rows);
    }
    if ($x === 'balances') {
        $rows = [['Porte-monnaie', 'Titulaire', 'E-mail', 'Solde', 'Statut']];
        foreach (all('SELECT * FROM wallets WHERE event_id = ? AND balance <> 0 ORDER BY balance DESC', [$ev['id']]) as $w) $rows[] = [$w['code'], Pay::holder($w), $w['email'], money_input((int)$w['balance']), $w['status']];
        csv_download('soldes-' . $ev['slug'] . '.csv', $rows);
    }
    $k = Cashless::stats((int)$ev['id']);
    return pay_page($m, $ev, 'exports', $label, view('pay/exports', compact('k', 'base', 'ev')));
}

// ---------- portail et réglages cashless ----------
function pay_s_portail(array $m, array $ev, string $base, string $label): string
{
    if (is_post()) {
        $s = Pay::settings($ev);
        foreach (['transfer', 'virtual', 'self_block'] as $k) $s[$k] = checked($k);
        $s['refund_fee'] = parse_money((string)input('refund_fee'));
        $s['max_balance'] = max(1000, parse_money((string)input('max_balance')));
        $s['topups'] = implode(',', array_filter(array_map(fn($v) => (string)parse_money(trim($v)), explode(',', (string)input('topups'))), fn($v) => (int)$v > 0));
        $s['message'] = mb_substr(trim((string)input('message')), 0, 600);
        $s['brand_color'] = preg_match('~^#[0-9a-f]{6}$~i', (string)input('brand_color')) ? (string)input('brand_color') : '#5e5ce6';
        update('events', ['pay_json' => json_encode($s, JSON_UNESCAPED_UNICODE), 'cashless_enabled' => checked('cashless_enabled'), 'cashless_min_topup' => max(100, parse_money((string)input('cashless_min_topup'))), 'cashless_refund_until' => input('cashless_refund_until') ?: null], 'id = ?', [$ev['id']]);
        back(['success' => 'Réglages enregistrés.']);
    }
    $s = Pay::settings($ev);
    return pay_page($m, $ev, 'portail', $label, view('pay/portal_settings', compact('s', 'base', 'ev')));
}

// ---------- organisation : bracelets, exploitants, équipe ----------
function pay_bracelets(): string
{
    $m = pay_require('bracelets'); $org = $m['org'];
    $events = all('SELECT id, name FROM events WHERE organizer_id = ? ORDER BY starts_at DESC', [$org['id']]);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'import') {
            $batch = mb_substr(trim((string)input('batch')) ?: 'Lot du ' . date('d/m/Y'), 0, 60); $profile = array_key_exists((string)input('profile'), Pay::PROFILES) ? (string)input('profile') : 'public';
            $ev = (int)input('event_id') ?: null; $n = 0; $skip = 0;
            foreach (preg_split('~\R~', trim((string)input('lines'))) as $line) {
                $c = array_map('trim', preg_split('~[;,\t]~', $line)); if (!$c[0]) continue;
                // « numéro;UID » ou « UID » seul
                [$num, $uid] = count($c) > 1 ? [$c[0], $c[1]] : [null, $c[0]];
                $uid = Pos::nfc($uid);
                if (strlen($uid) < 8 || val('SELECT id FROM pay_wristbands WHERE organizer_id = ? AND uid = ?', [$org['id'], $uid])) { $skip++; continue; }
                insert('pay_wristbands', ['organizer_id' => $org['id'], 'event_id' => $ev, 'number' => $num ? mb_substr($num, 0, 30) : null, 'uid' => $uid, 'batch' => $batch, 'profile' => $profile, 'status' => 'stock', 'created_at' => now()]); $n++;
            }
            back(['success' => $n . ' bracelet(s) ajouté(s) au stock' . ($skip ? ', ' . $skip . ' ignoré(s) (doublon ou identifiant illisible)' : '') . '.']);
        }
        if ($a === 'status') {
            $b = one('SELECT * FROM pay_wristbands WHERE id = ? AND organizer_id = ?', [(int)input('id'), $org['id']]);
            $st = (string)input('status');
            if ($b && in_array($st, ['stock', 'blocked', 'lost', 'destroyed'], true)) {
                update('pay_wristbands', ['status' => $st], 'id = ?', [$b['id']]);
                if (in_array($st, ['blocked', 'lost'], true) && $b['wallet_id']) { $w = one('SELECT * FROM wallets WHERE id = ?', [$b['wallet_id']]); if ($w && $w['nfc_uid'] === $b['uid']) Pay::block($w, 'Bracelet ' . ($st === 'lost' ? 'perdu' : 'bloqué')); }
            }
            back(['success' => 'Bracelet mis à jour.']);
        }
        back();
    }
    $q = trim((string)input('q')); $f = (string)input('f');
    $w = ['b.organizer_id = ?']; $p = [$org['id']];
    if ($q !== '') { $w[] = '(b.number = ? OR b.uid = ? OR b.batch LIKE ?)'; array_push($p, $q, Pos::nfc($q), '%' . $q . '%'); }
    if ($f !== '') { $w[] = 'b.status = ?'; $p[] = $f; }
    $list = all('SELECT b.*, w.code wcode, w.balance, w.event_id wev FROM pay_wristbands b LEFT JOIN wallets w ON w.id = b.wallet_id WHERE ' . implode(' AND ', $w) . ' ORDER BY b.id DESC LIMIT 200', $p);
    $counts = []; foreach (all('SELECT status, COUNT(*) n FROM pay_wristbands WHERE organizer_id = ? GROUP BY status', [$org['id']]) as $r) $counts[$r['status']] = (int)$r['n'];
    $batches = all('SELECT batch, profile, COUNT(*) n, COUNT(CASE WHEN status = \'active\' THEN 1 END) used FROM pay_wristbands WHERE organizer_id = ? GROUP BY batch, profile ORDER BY MAX(id) DESC', [$org['id']]);
    $ev = !empty($_SESSION['pay_ev']) ? one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [$_SESSION['pay_ev'], $org['id']]) : null;
    return pay_page($m, $ev, 'org-bracelets', 'Stock de bracelets', view('pay/bracelets', compact('list', 'counts', 'batches', 'q', 'f', 'events')));
}

function pay_operators(): string
{
    $m = pay_require('exploitants'); $org = $m['org'];
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'save') {
            $name = mb_substr(trim((string)input('name')), 0, 120);
            if ($name === '') back(['error' => 'Donne un nom à l\'exploitant.']);
            $iban = strtoupper(preg_replace('~\s+~', '', (string)input('iban')));
            if ($iban !== '' && !iban_valid($iban)) back(['error' => 'IBAN invalide.']);
            $d = ['name' => $name, 'legal_name' => mb_substr(trim((string)input('legal_name')), 0, 190) ?: null, 'siret' => preg_replace('~\D~', '', (string)input('siret')) ?: null, 'email' => is_email((string)input('email')) ? strtolower((string)input('email')) : null,
                'phone' => mb_substr(trim((string)input('phone')), 0, 30) ?: null, 'iban' => $iban ?: null, 'commission_pct' => max(0, min(100, (float)str_replace(',', '.', (string)input('commission_pct')))), 'fixed_fee' => parse_money((string)input('fixed_fee')),
                'keeps_cash' => checked('keeps_cash'), 'keeps_card' => checked('keeps_card'), 'notes' => trim((string)input('notes')) ?: null];
            if ((int)input('id')) update('pay_operators', $d, 'id = ? AND organizer_id = ?', [(int)input('id'), $org['id']]);
            else insert('pay_operators', $d + ['organizer_id' => $org['id'], 'active' => 1, 'created_at' => now()]);
            redirect(Pay::path('/pro/exploitants'), ['success' => 'Exploitant enregistré.']);
        }
        if ($a === 'toggle') { q('UPDATE pay_operators SET active = 1 - active WHERE id = ? AND organizer_id = ?', [(int)input('id'), $org['id']]); back(); }
        back();
    }
    $ops = all('SELECT o.*, (SELECT COUNT(*) FROM bars b WHERE b.operator_id = o.id) bars, (SELECT COUNT(*) FROM pay_members pm WHERE pm.operator_id = o.id) users FROM pay_operators o WHERE o.organizer_id = ? ORDER BY o.active DESC, o.name', [$org['id']]);
    $edit = (int)input('edit') ? one('SELECT * FROM pay_operators WHERE id = ? AND organizer_id = ?', [(int)input('edit'), $org['id']]) : null;
    $ev = !empty($_SESSION['pay_ev']) ? one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [$_SESSION['pay_ev'], $org['id']]) : null;
    return pay_page($m, $ev, 'org-exploitants', 'Exploitants', view('pay/operators', compact('ops', 'edit') + ['new' => input('edit') === 'new']));
}

function pay_team(): string
{
    $m = pay_require('team'); $org = $m['org'];
    if (!in_array('*', Pay::ROLES[$m['role']][1], true)) abort(403);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'add') {
            $email = strtolower(trim((string)input('email'))); $role = (string)input('role');
            if (!is_email($email) || !array_key_exists($role, Pay::ROLES)) back(['error' => 'E-mail ou rôle invalide.']);
            $opId = $role === 'operator' ? ((int)input('operator_id') ?: null) : null;
            if ($role === 'operator' && !$opId) back(['error' => 'Choisis l\'exploitant de ce compte.']);
            $bars = $role === 'bar' ? implode(',', array_map('intval', (array)($_POST['bar_ids'] ?? []))) : null;
            $u = one('SELECT * FROM users WHERE email = ?', [$email]);
            if ($u && in_array($u['role'], ['owner', 'manager', 'superadmin'], true)) back(['error' => 'Ce compte a déjà accès à tout (organisateur).']);
            if ($u && $u['role'] === 'scanner') back(['error' => 'Ce compte est un compte de contrôle d\'accès : utilise une autre adresse.']);
            $created = null;
            if (!$u) {
                $tok = token(24);
                $uid = insert('users', ['organizer_id' => $org['id'], 'email' => $email, 'name' => mb_substr(trim((string)input('name')) ?: $email, 0, 120), 'password_hash' => password_hash(token(16), PASSWORD_DEFAULT),
                    'role' => $role === 'operator' ? 'operator' : 'cashless', 'active' => 1, 'reset_token' => $tok, 'reset_expires' => date('Y-m-d H:i:s', time() + 7 * 86400), 'created_at' => now()]);
                $u = one('SELECT * FROM users WHERE id = ?', [$uid]); $created = $tok;
            }
            q('INSERT INTO pay_members (organizer_id, user_id, role, operator_id, bar_ids, created_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE role = VALUES(role), operator_id = VALUES(operator_id), bar_ids = VALUES(bar_ids)', [$org['id'], $u['id'], $role, $opId, $bars]);
            $link = $created ? abs_url('/mot-de-passe/' . $created) : Pay::absUrl('/connexion');
            try { Mailer::send($email, 'Ton accès à Entrevo Pay · ' . $org['name'], '<p>' . e($org['name']) . ' t\'a ajouté à son équipe cashless sur <b>Entrevo Pay</b>, en tant que <b>' . e(Pay::ROLES[$role][0]) . '</b>.</p><p><a href="' . e($link) . '">' . ($created ? 'Choisir mon mot de passe' : 'Me connecter') . '</a></p><p style="color:#86868b">Ensuite, connecte-toi sur ' . e(Pay::absUrl('/connexion')) . '</p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            Audit::log('pay.team', $email . ' ajouté (' . Pay::ROLES[$role][0] . ')');
            back(['success' => $email . ' ajouté. Un e-mail ' . ($created ? 'pour choisir son mot de passe' : 'de connexion') . ' lui a été envoyé.'] + ($created ? ['info' => 'Lien d\'activation (valable 7 jours) : ' . $link] : []));
        }
        if ($a === 'remove') {
            $pm = one('SELECT * FROM pay_members WHERE id = ? AND organizer_id = ?', [(int)input('id'), $org['id']]);
            if ($pm) { q('DELETE FROM pay_members WHERE id = ?', [$pm['id']]); if (!val('SELECT id FROM pay_members WHERE user_id = ?', [$pm['user_id']])) q("UPDATE users SET active = 0 WHERE id = ? AND role IN ('cashless','operator')", [$pm['user_id']]); }
            back(['success' => 'Accès retiré.']);
        }
        back();
    }
    $members = all('SELECT pm.*, u.name, u.email, u.last_login, u.active, o.name operator FROM pay_members pm JOIN users u ON u.id = pm.user_id LEFT JOIN pay_operators o ON o.id = pm.operator_id WHERE pm.organizer_id = ? ORDER BY pm.role, u.name', [$org['id']]);
    $admins = all("SELECT name, email, role, last_login FROM users WHERE organizer_id = ? AND role IN ('owner','manager') AND active = 1", [$org['id']]);
    $ops = all('SELECT id, name FROM pay_operators WHERE organizer_id = ? AND active = 1 ORDER BY name', [$org['id']]);
    $bars = all('SELECT b.id, b.name, e.name ev FROM bars b JOIN events e ON e.id = b.event_id WHERE e.organizer_id = ? ORDER BY e.starts_at DESC, b.id LIMIT 80', [$org['id']]);
    $ev = !empty($_SESSION['pay_ev']) ? one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [$_SESSION['pay_ev'], $org['id']]) : null;
    return pay_page($m, $ev, 'org-equipe', 'Équipe cashless', view('pay/team', compact('members', 'admins', 'ops', 'bars')));
}

// ---------- espace exploitant ----------
function pay_op_home(): string
{
    $m = pay_require();
    if ($m['role'] !== 'operator' && !Pay::can($m, 'exploitants')) abort(403);
    $op = $m['role'] === 'operator' ? one('SELECT * FROM pay_operators WHERE id = ?', [$m['operator_id']]) : one('SELECT * FROM pay_operators WHERE id = ? AND organizer_id = ?', [(int)input('op'), $m['org']['id']]);
    if (!$op) abort(404, 'Exploitant introuvable.');
    $events = all('SELECT DISTINCT e.* FROM events e JOIN bars b ON b.event_id = e.id WHERE b.operator_id = ? ORDER BY e.starts_at DESC', [$op['id']]);
    $evId = (int)input('e') ?: (int)($events[0]['id'] ?? 0);
    $ev = $evId ? one('SELECT e.* FROM events e JOIN bars b ON b.event_id = e.id WHERE e.id = ? AND b.operator_id = ? LIMIT 1', [$evId, $op['id']]) : null;
    $S = $ev ? Pay::statement($ev, $op) : null;
    if ($ev && input('pdf')) pay_statement_pdf($m['org'], $ev, $op, $S);
    $sales = $ev && $S['bars'] ? all('SELECT s.*, b.name bar FROM pos_sales s JOIN bars b ON b.id = s.bar_id WHERE s.event_id = ? AND s.undone = 0 AND s.bar_id IN (' . implode(',', array_map(fn($b) => (int)$b['id'], $S['bars'])) . ') ORDER BY s.id DESC LIMIT 60', [$ev['id']]) : [];
    $stock = $ev && $S['bars'] ? all('SELECT p.name, b.name bar, s.qty FROM pos_stock s JOIN bar_products p ON p.id = s.product_id JOIN bars b ON b.id = s.bar_id WHERE s.bar_id IN (' . implode(',', array_map(fn($b) => (int)$b['id'], $S['bars'])) . ') AND p.track_stock = 1 ORDER BY b.name, p.name', []) : [];
    $prods = [];
    foreach ($sales as $s) foreach (json_decode((string)$s['items'], true) ?: [] as $l) if (($l['kind'] ?? '') === 'product') { $prods[$l['name']]['q'] = ($prods[$l['name']]['q'] ?? 0) + (int)$l['qty']; $prods[$l['name']]['t'] = ($prods[$l['name']]['t'] ?? 0) + (int)$l['total']; }
    uasort($prods, fn($a, $b) => $b['t'] <=> $a['t']);
    return view('pay/operator_home', compact('m', 'op', 'events', 'ev', 'S', 'sales', 'stock', 'prods'));
}

// ---------- 3.1 : réassorts et pertes ----------
function pay_s_reassorts(array $m, array $ev, string $base, string $label): string
{
    $mine = $m['role'] === 'bar' && $m['bars'] ? ' AND r.bar_id IN (' . implode(',', array_map('intval', $m['bars'])) . ')' : '';
    if (is_post()) {
        $r = one("SELECT r.*, p.name pname, b.name bname FROM pos_stock_requests r JOIN bar_products p ON p.id = r.product_id JOIN bars b ON b.id = r.bar_id WHERE r.id = ? AND r.event_id = ? AND r.status = 'open'$mine", [(int)input('id'), $ev['id']]);
        if (!$r) back(['error' => 'Demande introuvable ou déjà traitée.']);
        $uid = (int)user()['id'];
        if (input('action') === 'refuse') { update('pos_stock_requests', ['status' => 'refused', 'done_by' => $uid, 'done_at' => now()], 'id = ?', [$r['id']]); back(['success' => 'Demande refusée.']); }
        $q = max(1, (int)input('qty')); $src = (string)input('source');
        if (!(int)val('SELECT track_stock FROM bar_products WHERE id = ?', [$r['product_id']])) update('bar_products', ['track_stock' => 1], 'id = ?', [$r['product_id']]);
        if ($src !== '' && $src !== 'reserve') {
            $from = (int)$src;
            if (!val('SELECT id FROM bars WHERE id = ? AND event_id = ?', [$from, $ev['id']]) || $from === (int)$r['bar_id']) back(['error' => 'Choisis la provenance.']);
            Till::stockMove((int)$ev['id'], (int)$r['product_id'], $from, -$q, 'transfer', null, $uid, 'Réassort vers ' . $r['bname']);
            Till::stockMove((int)$ev['id'], (int)$r['product_id'], (int)$r['bar_id'], $q, 'transfer', null, $uid, 'Réassort depuis le bar ' . $from);
        } else Till::stockMove((int)$ev['id'], (int)$r['product_id'], (int)$r['bar_id'], $q, 'in', null, $uid, 'Réassort (réserve)' . ($r['staff'] ? ' demandé par ' . $r['staff'] : ''));
        update('pos_stock_requests', ['status' => 'done', 'done_qty' => $q, 'done_by' => $uid, 'done_at' => now()], 'id = ?', [$r['id']]);
        Audit::log('pay.restock', $q . ' × ' . $r['pname'] . ' livrés à ' . $r['bname'], (int)$ev['id']);
        back(['success' => $q . ' × ' . $r['pname'] . ' livrés à ' . $r['bname'] . '.']);
    }
    $open = all("SELECT r.*, p.name pname, b.name bname, COALESCE(s.qty, 0) cur FROM pos_stock_requests r JOIN bar_products p ON p.id = r.product_id JOIN bars b ON b.id = r.bar_id LEFT JOIN pos_stock s ON s.product_id = r.product_id AND s.bar_id = r.bar_id WHERE r.event_id = ? AND r.status = 'open'$mine ORDER BY r.id", [$ev['id']]);
    $done = all("SELECT r.*, p.name pname, b.name bname, u.name who FROM pos_stock_requests r JOIN bar_products p ON p.id = r.product_id JOIN bars b ON b.id = r.bar_id LEFT JOIN users u ON u.id = r.done_by WHERE r.event_id = ? AND r.status <> 'open'$mine ORDER BY r.id DESC LIMIT 30", [$ev['id']]);
    $bars = all('SELECT id, name FROM bars WHERE event_id = ? ORDER BY id', [$ev['id']]);
    $stockBy = []; foreach (all('SELECT s.product_id, s.bar_id, s.qty FROM pos_stock s JOIN bar_products p ON p.id = s.product_id WHERE p.event_id = ?', [$ev['id']]) as $x) $stockBy[(int)$x['product_id']][(int)$x['bar_id']] = (int)$x['qty'];
    $lossW = $m['role'] === 'bar' && $m['bars'] ? ' AND m.bar_id IN (' . implode(',', array_map('intval', $m['bars'])) . ')' : '';
    $losses = all("SELECT m.*, p.name pname, p.cost, b.name bname FROM pos_stock_moves m JOIN bar_products p ON p.id = m.product_id JOIN bars b ON b.id = m.bar_id WHERE m.event_id = ? AND (m.kind = 'loss' OR (m.kind = 'in' AND m.delta < 0))$lossW ORDER BY m.id DESC LIMIT 200", [$ev['id']]);
    $byReason = []; $lossValue = 0;
    foreach ($losses as $l) { $r = $l['kind'] === 'loss' ? explode(' · ', (string)$l['note'])[0] : 'casse'; $v = -(int)$l['delta'] * (int)($l['cost'] ?? 0); $byReason[$r]['q'] = ($byReason[$r]['q'] ?? 0) - (int)$l['delta']; $byReason[$r]['v'] = ($byReason[$r]['v'] ?? 0) + $v; $lossValue += $v; }
    $value = (int)val('SELECT COALESCE(SUM(s.qty * COALESCE(p.cost, 0)), 0) FROM pos_stock s JOIN bar_products p ON p.id = s.product_id WHERE p.event_id = ? AND s.qty > 0', [$ev['id']]);
    return pay_page($m, $ev, 'reassorts', $label, view('pay/restock', compact('open', 'done', 'bars', 'stockBy', 'losses', 'byReason', 'lossValue', 'value', 'base', 'ev')));
}

// ---------- 3.1 : bilan de fin d'événement ----------
function pay_bilan_data(array $m, array $ev): array
{
    $R = bars_report_data($ev, null, null);
    $k = Cashless::stats((int)$ev['id']);
    $tx = []; foreach (all('SELECT kind, COUNT(*) n, SUM(amount) a FROM wallet_tx WHERE event_id = ? AND undone = 0 GROUP BY kind', [$ev['id']]) as $r) $tx[$r['kind']] = ['n' => (int)$r['n'], 'a' => (int)$r['a']];
    $txSum = (int)val('SELECT COALESCE(SUM(t.amount), 0) FROM wallet_tx t JOIN wallets w ON w.id = t.wallet_id WHERE t.event_id = ?', [$ev['id']]);
    $balances = (int)val('SELECT COALESCE(SUM(balance), 0) FROM wallets WHERE event_id = ?', [$ev['id']]);
    $w = one("SELECT COUNT(*) n, COUNT(CASE WHEN balance > 0 THEN 1 END) pos, COUNT(CASE WHEN status = 'refund_req' THEN 1 END) req, COUNT(CASE WHEN nfc_uid IS NOT NULL THEN 1 END) nfc, COUNT(CASE WHEN virtual = 1 THEN 1 END) virt FROM wallets WHERE event_id = ?", [$ev['id']]);
    $ops = []; foreach (all('SELECT * FROM pay_operators WHERE organizer_id = ?', [$m['org']['id']]) as $o) { $st = Pay::statement($ev, $o); if ($st['bars']) $ops[] = ['name' => $o['name']] + $st; }
    $z = one('SELECT COUNT(*) n, COUNT(CASE WHEN closed_at IS NOT NULL THEN 1 END) closed, COUNT(CASE WHEN validated_at IS NOT NULL THEN 1 END) valid, COALESCE(SUM(CASE WHEN counted_cash IS NOT NULL THEN counted_cash - expected_cash END), 0) gap FROM pos_sessions WHERE event_id = ?', [$ev['id']]);
    $cups = Pay::ecocups($ev);
    $loss = (int)val("SELECT COALESCE(SUM(-m.delta * COALESCE(p.cost, 0)), 0) FROM pos_stock_moves m JOIN bar_products p ON p.id = m.product_id WHERE m.event_id = ? AND (m.kind = 'loss' OR (m.kind = 'in' AND m.delta < 0))", [$ev['id']]);
    $costed = array_sum(array_map(fn($x) => (int)($x['cht'] ?? 0), $R['prods']));
    return ['R' => $R, 'k' => $k, 'tx' => $tx, 'tx_sum' => $txSum, 'balances' => $balances, 'w' => $w, 'ops' => $ops, 'z' => $z, 'cups' => $cups, 'loss' => $loss, 'costed' => $costed];
}

function pay_s_bilan(array $m, array $ev, string $base, string $label): string
{
    $B = pay_bilan_data($m, $ev);
    if (input('pdf')) pay_bilan_pdf($m['org'], $ev, $B);
    return pay_page($m, $ev, 'bilan', $label, view('pay/bilan', compact('B', 'base', 'ev')));
}

function pay_bilan_pdf(array $org, array $ev, array $B): never
{
    $t = fn($s) => PdfDoc::t((string)$s); $T = $B['R']['tot']; $k = $B['k'];
    $pdf = new PdfDoc('P', 'mm', 'A4'); $pdf->SetMargins(18, 18, 18); $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 20); $pdf->Cell(0, 10, $t('Bilan cashless & bars'), 0, 1);
    $pdf->SetFont('Helvetica', '', 10); $pdf->SetTextColor(110, 110, 115);
    $pdf->MultiCell(0, 5, $t(($org['legal_name'] ?: $org['name']) . "\n" . $ev['name'] . ' · ' . fr_date($ev['starts_at'], false) . "\nÉdité le " . date('d/m/Y H:i') . ' · Entrevo Pay'));
    $pdf->SetTextColor(29, 29, 31);
    $h = function (string $x) use ($pdf, $t) { $pdf->Ln(4); $pdf->SetFont('Helvetica', 'B', 13); $pdf->Cell(0, 8, $t($x), 0, 1); };
    $row = function (string $l, string $v, bool $b = false) use ($pdf, $t) { $pdf->SetFont('Helvetica', $b ? 'B' : '', 10.5); $pdf->Cell(120, 7, $t($l), 'B'); $pdf->Cell(54, 7, $t($v), 'B', 1, 'R'); };
    $h('Ventes'); $row('Chiffre d\'affaires TTC', money($T['ttc']), true); $row('Hors taxes', money($T['ht'])); $row('TVA collectée', money($T['tva']));
    if ($T['cost']) $row('Marge brute (produits chiffrés)', money($B['costed'] - $T['cost']));
    $row('Tickets', (string)$T['n']); $row('Cashless · carte · espèces', money($T['cashless']) . ' · ' . money($T['card']) . ' · ' . money($T['cash']));
    $row('Offert · remises', money($T['offered']) . ' · ' . money($T['discount'])); $row('Remboursements de tickets', money($T['refund'])); $row('Pourboires', money($T['tip']));
    $h('Cashless'); $row('Rechargé (en ligne · sur place)', money((int)$k['loaded']) . ' (' . money((int)$k['online']) . ' · ' . money((int)$k['onsite']) . ')'); $row('Crédits offerts', money($B['tx']['gift']['a'] ?? 0));
    $row('Dépensé aux bars', money((int)$k['spent'])); $row('Soldes remboursés', money((int)$k['refunded'])); $row('Soldes restants', money($B['balances']), true);
    $row('Contrôle : mouvements = soldes', $B['tx_sum'] === $B['balances'] ? 'OK' : 'écart ' . money($B['tx_sum'] - $B['balances']));
    $h('Caisses'); $row('Clôtures (validées / total)', $B['z']['valid'] . ' / ' . $B['z']['closed']); $row('Écart de caisse cumulé', money((int)$B['z']['gap']));
    $h('Consignes & stocks'); $cin = array_sum(array_column($B['cups']['types'], 'value_in')); $cout = array_sum(array_column($B['cups']['types'], 'value_out'));
    $row('Consignes encaissées · rendues', money($cin) . ' · ' . money($cout)); $row('Consignes conservées', money($cin - $cout)); $row('Pertes valorisées (prix d\'achat)', money($B['loss']));
    if ($B['ops']) { $h('Exploitants'); foreach ($B['ops'] as $o) $row($o['name'] . ' · CA ' . money($o['ttc']) . ' · commission ' . money($o['commission']), ($o['left'] >= 0 ? 'à verser ' : 'doit ') . money(abs($o['left']))); }
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="bilan-' . $ev['slug'] . '.pdf"');
    echo $pdf->Output('S'); exit;
}

// ---------- 3.1 : journal des actions sensibles ----------
function pay_s_journal(array $m, array $ev, string $base, string $label): string
{
    $f = (string)input('f');
    $w = 'a.organizer_id = ? AND (a.event_id = ? OR (a.event_id IS NULL AND a.action LIKE \'pay.%\')) AND (a.action LIKE \'pay.%\' OR a.action LIKE \'pos.%\')'; $p = [$m['org']['id'], $ev['id']];
    if ($f !== '') { $w .= ' AND a.action = ?'; $p[] = $f; }
    $rows = all("SELECT a.*, u.name who, u.email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE $w ORDER BY a.id DESC LIMIT 300", $p);
    $kinds = array_column(all("SELECT DISTINCT action FROM audit_log WHERE organizer_id = ? AND (action LIKE 'pay.%' OR action LIKE 'pos.%') ORDER BY action", [$m['org']['id']]), 'action');
    return pay_page($m, $ev, 'journal', $label, view('pay/journal', compact('rows', 'kinds', 'f', 'base', 'ev')));
}

// ---------- 3.2 : prévisions de rupture ----------
function pay_s_previsions(array $m, array $ev, string $base, string $label): string
{
    $mine = $m['role'] === 'bar' ? $m['bars'] : [];
    $win = in_array((int)input('w'), [30, 60, 120], true) ? (int)input('w') : 60;
    $cover = in_array((int)input('c'), [60, 120, 180, 240], true) ? (int)input('c') : 120;
    if (is_post() && input('action') === 'deliver') {
        $pid = (int)input('product_id'); $bid = (int)input('bar_id'); $q = max(1, (int)input('qty'));
        if (!val('SELECT id FROM bar_products WHERE id = ? AND event_id = ?', [$pid, $ev['id']]) || !val('SELECT id FROM bars WHERE id = ? AND event_id = ?', [$bid, $ev['id']]) || ($mine && !in_array($bid, $mine, true))) back(['error' => 'Produit ou point de vente invalide.']);
        Till::stockMove((int)$ev['id'], $pid, $bid, $q, 'in', null, (int)user()['id'], 'Réassort suggéré (prévision)');
        q("UPDATE pos_stock_requests SET status = 'done', done_qty = ?, done_by = ?, done_at = NOW() WHERE event_id = ? AND product_id = ? AND bar_id = ? AND status = 'open'", [$q, user()['id'], $ev['id'], $pid, $bid]);
        Audit::log('pay.restock', $q . ' livrés (prévision) au point de vente ' . $bid, (int)$ev['id']);
        back(['success' => $q . ' livré(s).']);
    }
    $F = Pay::forecast($ev, $mine, $win, $cover);
    return pay_page($m, $ev, 'previsions', $label, view('pay/forecast', compact('F', 'win', 'cover', 'base', 'ev')));
}

// ---------- 3.2 : analyse des ventes ----------
function pay_s_analyse(array $m, array $ev, string $base, string $label): string
{
    $day = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('jour')) ? (string)input('jour') : null;
    $A = Pay::analyse($ev, $day, $m['role'] === 'bar' ? $m['bars'] : []);
    $others = all('SELECT id, name, starts_at FROM events WHERE organizer_id = ? AND id <> ? ORDER BY starts_at DESC LIMIT 20', [$m['org']['id'], $ev['id']]);
    $other = (int)input('vs') ? one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [(int)input('vs'), $m['org']['id']]) : null;
    $CMP = $other ? Pay::compare($ev, $other) : null;
    return pay_page($m, $ev, 'analyse', $label, view('pay/analyse', compact('A', 'day', 'others', 'other', 'CMP', 'base', 'ev')));
}

