<?php
declare(strict_types=1);

/* 2.7 : événement › Jour J › Bars & caisses — l'espace de gestion de la caisse événementielle. */

require_once APP . '/controllers/pro.php';

const BARS_TABS = ['' => 'En direct', 'catalogue' => 'Produits', 'consignes' => 'Consignes', 'remises' => 'Happy hours & remises', 'appareils' => 'Points de vente & appareils',
    'caisses' => 'Caisses & clôtures', 'stocks' => 'Stocks', 'equipe' => 'Serveurs & droits', 'credits' => 'Crédits offerts', 'rapport' => 'Rapport', 'ventes' => 'Journal des ventes', 'reglages' => 'Réglages'];
/** 2.9 : six rubriques au lieu de douze onglets ; chaque rubrique garde ses pages. */
const BARS_GROUPS = ['' => ['En direct', ['']], 'catalogue' => ['Carte', ['catalogue', 'consignes', 'remises']], 'appareils' => ['Points de vente', ['appareils', 'caisses', 'stocks']],
    'equipe' => ['Équipe', ['equipe', 'credits']], 'rapport' => ['Ventes', ['rapport', 'ventes']], 'reglages' => ['Réglages', ['reglages']]];

function bars_page(string $id, string $tab = ''): string
{
    $org = require_org(['owner', 'manager']);
    require_module('cashless');
    $ev = org_event((int)$id);
    if (!array_key_exists($tab, BARS_TABS)) abort(404);
    // 3.0 : tout le cashless se gère dans Entrevo Pay (sous-domaine dédié)
    if (empty($GLOBALS['pay_ctx'])) redirect(Pay::path('/pro/e/' . $ev['id'] . ($tab ? '/' . $tab : '')));
    $base = '/pro/evenements/' . $ev['id'] . '/bars' . ($tab ? '/' . $tab : '');
    $fn = 'bars_' . ($tab ?: 'dashboard');
    $vars = $fn($ev, $org, $base);
    return ev_page($ev, 'bars', BARS_TABS[$tab] . ' · Bars & caisses', 'bars/' . ($tab ?: 'dashboard'), $vars + ['btab' => $tab, 'bbase' => '/pro/evenements/' . $ev['id'] . '/bars']);
}

function bars_ids(string $field): string { return implode(',', array_values(array_unique(array_filter(array_map('intval', (array)($_POST[$field] ?? [])))))); }

/** Photo de produit : réduite à 600 px pour un affichage rapide sur les tablettes. */
function bars_photo(string $field): ?string
{
    $p = upload_image($field);
    if (!$p || !function_exists('imagecreatefromstring')) return $p;
    $file = ROOT . '/' . $p; $img = @imagecreatefromstring((string)file_get_contents($file));
    if (!$img) return $p;
    $w = imagesx($img); $h = imagesy($img); $max = 600;
    if ($w > $max || $h > $max) {
        $r = min($max / $w, $max / $h); $nw = (int)round($w * $r); $nh = (int)round($h * $r);
        $dst = imagecreatetruecolor($nw, $nh); imagealphablending($dst, false); imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $jpg = preg_replace('~\.\w+$~', '.jpg', $p);
        $bg = imagecreatetruecolor($nw, $nh); imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255)); imagecopy($bg, $dst, 0, 0, 0, 0, $nw, $nh);
        imagejpeg($bg, ROOT . '/' . $jpg, 84);
        if ($jpg !== $p) @unlink($file);
        return $jpg;
    }
    return $p;
}

// ---------- Tableau de bord ----------
function bars_dashboard(array $ev, array $org, string $base): array
{
    if (input('live')) json_out(bars_live($ev));
    return ['live' => bars_live($ev), 'cashlessOn' => (int)$ev['cashless_enabled']];
}

function bars_live(array $ev): array
{
    $day = input('jour') === 'tout' ? null : date('Y-m-d');
    $w = 'event_id = ? AND undone = 0' . ($day ? ' AND created_at >= ?' : ''); $p = $day ? [$ev['id'], $day . ' 00:00:00'] : [$ev['id']];
    $bm = Till::byMethod(all("SELECT method, kind, amount, offered_value, discount, payments_json FROM pos_sales WHERE $w", $p));
    $m = []; foreach ($bm['methods'] as $k => $r) $m[$k] = ['n' => $r['n'], 't' => $r['total'], 'o' => $r['offered']];
    $w .= " AND kind = 'sale'";
    $hours = []; foreach (all("SELECT DATE_FORMAT(created_at, '%Hh') h, SUM(amount) t, COUNT(*) n FROM pos_sales WHERE $w GROUP BY h ORDER BY h", $p) as $r) $hours[$r['h']] = ['t' => (int)$r['t'], 'n' => (int)$r['n']];
    $prod = [];
    foreach (all("SELECT items FROM pos_sales WHERE $w", $p) as $r) foreach (json_decode((string)$r['items'], true) ?: [] as $l) {
        if (($l['kind'] ?? 'product') !== 'product') continue;
        $k = (string)$l['name']; $prod[$k]['q'] = ($prod[$k]['q'] ?? 0) + (int)$l['qty']; $prod[$k]['t'] = ($prod[$k]['t'] ?? 0) + (int)$l['total'];
    }
    uasort($prod, fn($a, $b) => $b['q'] <=> $a['q']);
    $bars = all("SELECT b.id, b.name, COUNT(s.id) n, COALESCE(SUM(s.amount), 0) t, MAX(s.created_at) last FROM bars b LEFT JOIN pos_sales s ON s.bar_id = b.id AND s.undone = 0 AND s.kind = 'sale'" . ($day ? ' AND s.created_at >= ?' : '') . " WHERE b.event_id = ? GROUP BY b.id, b.name ORDER BY b.id", $day ? [$day . ' 00:00:00', $ev['id']] : [$ev['id']]);
    foreach ($bars as &$b) $b['rate'] = (int)val('SELECT COUNT(*) FROM pos_sales WHERE bar_id = ? AND undone = 0 AND created_at >= NOW() - INTERVAL 15 MINUTE', [$b['id']]);
    unset($b);
    $alertCash = (int)Till::settings($ev)['drop_alert'];
    $sessions = [];
    foreach (all('SELECT s.*, b.name bar, (SELECT GROUP_CONCAT(DISTINCT d2.name SEPARATOR \', \') FROM pos_sales x JOIN pos_devices d2 ON d2.id = x.device_id WHERE x.session_id = s.id) device FROM pos_sessions s JOIN bars b ON b.id = s.bar_id WHERE s.event_id = ? AND s.closed_at IS NULL ORDER BY s.id', [$ev['id']]) as $se) {
        $t = Till::sessionTotals($se);
        $sessions[] = ['id' => (int)$se['id'], 'bar' => $se['bar'], 'device' => $se['device'], 'since' => $se['opened_at'], 'cash' => $t['expected_cash'], 'total' => $t['total'], 'full' => $alertCash > 0 && $t['expected_cash'] >= $alertCash];
    }
    $low = all('SELECT p.name, b.name bar, s.qty, p.stock_alert FROM pos_stock s JOIN bar_products p ON p.id = s.product_id JOIN bars b ON b.id = s.bar_id WHERE p.event_id = ? AND p.track_stock = 1 AND p.active = 1 AND s.qty <= COALESCE(p.stock_alert, 10) ORDER BY s.qty LIMIT 12', [$ev['id']]);
    $devices = all('SELECT name, last_seen FROM pos_devices WHERE event_id = ? AND revoked = 0 AND token_hash IS NOT NULL', [$ev['id']]);
    $online = count(array_filter($devices, fn($x) => $x['last_seen'] && strtotime($x['last_seen']) > time() - 300));
    $total = $bm['total'];
    $n = (int)val("SELECT COUNT(*) FROM pos_sales WHERE $w AND method NOT IN ('offered','allowance')", $p);
    return ['day' => $day, 'total' => $total, 'count' => $n, 'basket' => $n ? (int)round($total / $n) : 0, 'methods' => $m, 'hours' => $hours, 'products' => array_slice($prod, 0, 10, true),
        'bars' => $bars, 'topups' => $bm['topups'], 'cashout' => $bm['cashout'], 'sessions' => $sessions, 'low' => $low, 'devices' => count($devices), 'online' => $online, 'cups' => Till::cups((int)$ev['id']), 'at' => date('H:i:s')];
}

// ---------- Catalogue ----------
function bars_catalogue(array $ev, array $org, string $base): array
{
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'category') {
            $name = mb_substr(trim((string)input('name')), 0, 60);
            if ($name === '') back(['error' => 'Donne un nom à la catégorie.']);
            $d = ['name' => $name, 'color' => preg_match('~^#[0-9a-f]{6}$~i', (string)input('color')) ? (string)input('color') : null, 'vat_rate' => input('vat_rate') === '' ? null : (float)str_replace(',', '.', (string)input('vat_rate')), 'prep' => checked('prep')];
            if ((int)input('category_id')) update('pos_categories', $d, 'id = ? AND event_id = ?', [(int)input('category_id'), $ev['id']]);
            else insert('pos_categories', $d + ['event_id' => $ev['id'], 'sort' => (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM pos_categories WHERE event_id = ?', [$ev['id']]), 'active' => 1]);
            back(['success' => 'Catégorie enregistrée.']);
        }
        if ($a === 'category_delete') {
            if (val('SELECT COUNT(*) FROM bar_products WHERE category_id = ? AND event_id = ?', [(int)input('category_id'), $ev['id']])) back(['error' => 'Déplace d\'abord les produits de cette catégorie.']);
            q('DELETE FROM pos_categories WHERE id = ? AND event_id = ?', [(int)input('category_id'), $ev['id']]); back(['success' => 'Catégorie supprimée.']);
        }
        if ($a === 'sort') {
            foreach (array_values(array_filter(array_map('intval', explode(',', (string)input('order'))))) as $i => $pid) q('UPDATE bar_products SET sort = ? WHERE id = ? AND event_id = ?', [$i, $pid, $ev['id']]);
            json_out(['ok' => true]);
        }
        if ($a === 'toggle') { q('UPDATE bar_products SET active = 1 - active WHERE id = ? AND event_id = ?', [(int)input('product_id'), $ev['id']]); back(); }
        if ($a === 'product') {
            $pid = (int)input('product_id');
            $name = mb_substr(trim((string)input('name')), 0, 80);
            if ($name === '') back(['error' => 'Donne un nom au produit.']);
            $variants = [];
            foreach ((array)($_POST['var_name'] ?? []) as $i => $vn) { $vn = trim((string)$vn); if ($vn !== '') $variants[] = ['name' => mb_substr($vn, 0, 40), 'price' => parse_money((string)($_POST['var_price'][$i] ?? '0'))]; }
            $barPrices = [];
            foreach ((array)($_POST['bar_price'] ?? []) as $bid => $v) if (trim((string)$v) !== '') $barPrices[(int)$bid] = parse_money((string)$v);
            $cat = (int)input('category_id') ? one('SELECT * FROM pos_categories WHERE id = ? AND event_id = ?', [(int)input('category_id'), $ev['id']]) : null;
            $d = ['name' => $name, 'description' => mb_substr(trim((string)input('description')), 0, 255) ?: null, 'category_id' => $cat['id'] ?? null, 'category' => $cat['name'] ?? 'Divers',
                'price' => parse_money((string)input('price')), 'cost' => input('cost') === '' ? null : parse_money((string)input('cost')), 'deposit' => 0, 'deposit_type_id' => (int)input('deposit_type_id') ?: null, 'vat_rate' => input('vat_rate') === '' ? null : (float)str_replace(',', '.', (string)input('vat_rate')),
                'variants_json' => $variants ? json_encode($variants, JSON_UNESCAPED_UNICODE) : null, 'bar_prices_json' => $barPrices ? json_encode($barPrices) : null,
                'bar_ids' => bars_ids('bar_ids') ?: null, 'track_stock' => checked('track_stock'), 'stock_alert' => input('stock_alert') === '' ? null : max(0, (int)input('stock_alert')),
                'color' => preg_match('~^#[0-9a-f]{6}$~i', (string)input('color')) && input('use_color') ? (string)input('color') : null];
            // 2.8 : options payantes, formule, préparation
            $opts = [];
            foreach ((array)($_POST['opt_name'] ?? []) as $g => $gn) {
                $gn = trim((string)$gn); if ($gn === '') continue;
                $items = [];
                foreach (preg_split('~\R~', (string)($_POST['opt_items'][$g] ?? '')) as $line) {
                    $parts = preg_split('~\s*[:=]\s*~', trim($line), 2);
                    if (trim($parts[0] ?? '') === '') continue;
                    $items[] = ['name' => mb_substr(trim($parts[0]), 0, 40), 'price' => isset($parts[1]) ? parse_money($parts[1]) : 0];
                }
                if ($items) $opts[] = ['name' => mb_substr($gn, 0, 40), 'multi' => !empty($_POST['opt_multi'][$g]), 'required' => !empty($_POST['opt_required'][$g]), 'items' => $items];
            }
            $d['options_json'] = $opts ? json_encode($opts, JSON_UNESCAPED_UNICODE) : null;
            $d['kind'] = input('kind') === 'menu' ? 'menu' : 'item';
            $steps = [];
            if ($d['kind'] === 'menu') foreach ((array)($_POST['menu_step'] ?? []) as $k => $sn) {
                $sn = trim((string)$sn); $ids = array_values(array_filter(array_map('intval', (array)($_POST['menu_products'][$k] ?? []))));
                if ($sn !== '' && $ids) $steps[] = ['name' => mb_substr($sn, 0, 40), 'products' => $ids];
            }
            if ($d['kind'] === 'menu' && !$steps) back(['error' => 'Une formule a besoin d\'au moins une étape (par exemple « Plat » avec ses choix).']);
            $d['menu_json'] = $steps ? json_encode($steps, JSON_UNESCAPED_UNICODE) : null;
            $d['prep'] = in_array(input('prep'), ['0', '1'], true) ? (int)input('prep') : null;
            if ($d['price'] <= 0 && !$variants) back(['error' => 'Indique un prix (ou des variantes avec leur prix).']);
            if ($variants && $d['price'] <= 0) $d['price'] = $variants[0]['price'];
            try { if ($ph = bars_photo('photo')) $d['photo'] = $ph; } catch (Throwable $e) { back(['error' => $e->getMessage()]); }
            if (checked('remove_photo')) $d['photo'] = null;
            if ($pid) update('bar_products', $d, 'id = ? AND event_id = ?', [$pid, $ev['id']]);
            else $pid = insert('bar_products', $d + ['event_id' => $ev['id'], 'active' => 1, 'sort' => (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM bar_products WHERE event_id = ?', [$ev['id']])]);
            redirect($base . '?p=' . $pid . '#fiche', ['success' => 'Produit enregistré.']);
        }
        if ($a === 'duplicate') {
            $p = one('SELECT * FROM bar_products WHERE id = ? AND event_id = ?', [(int)input('product_id'), $ev['id']]);
            if ($p) { unset($p['id']); $p['name'] = mb_substr($p['name'] . ' (copie)', 0, 80); $nid = insert('bar_products', $p); redirect($base . '?p=' . $nid . '#fiche', ['success' => 'Produit dupliqué.']); }
            back();
        }
        if ($a === 'delete') {
            $pid = (int)input('product_id');
            if (val("SELECT COUNT(*) FROM pos_sales WHERE event_id = ? AND items LIKE ?", [$ev['id'], '%"id":' . $pid . ',%'])) { q('UPDATE bar_products SET active = 0 WHERE id = ? AND event_id = ?', [$pid, $ev['id']]); redirect($base, ['success' => 'Ce produit a déjà été vendu : il est retiré de la carte (conservé dans l\'historique).']); }
            q('DELETE FROM bar_products WHERE id = ? AND event_id = ?', [$pid, $ev['id']]); redirect($base, ['success' => 'Produit supprimé.']);
        }
        if ($a === 'copy_from') {
            $src = one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [(int)input('event_id'), $ev['organizer_id']]);
            if (!$src || (int)$src['id'] === (int)$ev['id']) back(['error' => 'Choisis un autre événement.']);
            $map = []; $dmap = [];
            foreach (all('SELECT * FROM pos_categories WHERE event_id = ?', [$src['id']]) as $c) { $old = $c['id']; unset($c['id']); $c['event_id'] = $ev['id']; $map[$old] = insert('pos_categories', $c); }
            foreach (all('SELECT * FROM pos_deposit_types WHERE event_id = ?', [$src['id']]) as $c) { $old = $c['id']; unset($c['id']); $c['event_id'] = $ev['id']; $dmap[$old] = insert('pos_deposit_types', $c); }
            $n = 0;
            foreach (all('SELECT * FROM bar_products WHERE event_id = ? AND active = 1', [$src['id']]) as $p) { unset($p['id']); $p['event_id'] = $ev['id']; $p['category_id'] = $map[$p['category_id']] ?? null; $p['deposit_type_id'] = $dmap[$p['deposit_type_id']] ?? null; $p['bar_ids'] = null; $p['bar_prices_json'] = null; insert('bar_products', $p); $n++; }
            back(['success' => $n . ' produit(s) importé(s) depuis « ' . $src['name'] . ' ».']);
        }
        back();
    }
    $cats = all('SELECT c.*, (SELECT COUNT(*) FROM bar_products p WHERE p.category_id = c.id) n FROM pos_categories c WHERE c.event_id = ? ORDER BY c.sort, c.id', [$ev['id']]);
    $products = all('SELECT * FROM bar_products WHERE event_id = ? ORDER BY sort, name', [$ev['id']]);
    $bars = all('SELECT * FROM bars WHERE event_id = ? ORDER BY id', [$ev['id']]);
    $deps = all('SELECT * FROM pos_deposit_types WHERE event_id = ? ORDER BY id', [$ev['id']]);
    $sel = input('p') === 'new' ? [] : ((int)input('p') ? one('SELECT * FROM bar_products WHERE id = ? AND event_id = ?', [(int)input('p'), $ev['id']]) : null);
    $others = all('SELECT id, name FROM events WHERE organizer_id = ? AND id <> ? ORDER BY starts_at DESC LIMIT 30', [$ev['organizer_id'], $ev['id']]);
    $sold = [];
    foreach (all('SELECT items FROM pos_sales WHERE event_id = ? AND undone = 0', [$ev['id']]) as $r) foreach (json_decode((string)$r['items'], true) ?: [] as $l) if (($l['kind'] ?? '') === 'product') $sold[(int)$l['id']] = ($sold[(int)$l['id']] ?? 0) + (int)$l['qty'];
    return compact('cats', 'products', 'bars', 'deps', 'sel', 'others', 'sold') + ['settings' => Till::settings($ev)];
}

// ---------- Consignes ----------
function bars_consignes(array $ev, array $org, string $base): array
{
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'save') {
            $name = mb_substr(trim((string)input('name')), 0, 60);
            if ($name === '' || parse_money((string)input('amount')) <= 0) back(['error' => 'Nom et montant obligatoires.']);
            $d = ['name' => $name, 'amount' => parse_money((string)input('amount')), 'refundable' => checked('refundable'), 'stock_initial' => input('stock_initial') === '' ? null : max(0, (int)input('stock_initial'))];
            try { if ($ph = bars_photo('photo')) $d['photo'] = $ph; } catch (Throwable $e) { back(['error' => $e->getMessage()]); }
            if ((int)input('deposit_id')) update('pos_deposit_types', $d, 'id = ? AND event_id = ?', [(int)input('deposit_id'), $ev['id']]);
            else insert('pos_deposit_types', $d + ['event_id' => $ev['id'], 'active' => 1]);
            back(['success' => 'Consigne enregistrée.']);
        }
        if ($a === 'toggle') { q('UPDATE pos_deposit_types SET active = 1 - active WHERE id = ? AND event_id = ?', [(int)input('deposit_id'), $ev['id']]); back(); }
        back();
    }
    $cups = Till::cups((int)$ev['id']);
    $byBar = [];
    foreach (all('SELECT s.bar_id, b.name, s.items FROM pos_sales s JOIN bars b ON b.id = s.bar_id WHERE s.event_id = ? AND s.undone = 0', [$ev['id']]) as $r)
        foreach (json_decode((string)$r['items'], true) ?: [] as $l) if (in_array($l['kind'] ?? '', ['deposit', 'return'], true)) {
            $k = $r['name']; $byBar[$k][$l['kind']] = ($byBar[$k][$l['kind']] ?? 0) + (int)$l['qty'];
        }
    $products = all('SELECT p.name, d.name dep FROM bar_products p JOIN pos_deposit_types d ON d.id = p.deposit_type_id WHERE p.event_id = ? AND p.active = 1 ORDER BY d.id, p.name', [$ev['id']]);
    return compact('cups', 'byBar', 'products');
}

// ---------- Équipe ----------
function bars_equipe(array $ev, array $org, string $base): array
{
    if (is_post()) {
        $a = (string)input('action');
        $sid = (int)input('staff_id');
        if ($a === 'save') {
            $name = mb_substr(trim((string)input('name')), 0, 80);
            $pin = preg_replace('~\D~', '', (string)input('pin'));
            if ($name === '') back(['error' => 'Donne un nom.']);
            if (!$sid && strlen($pin) < 4) back(['error' => 'Choisis un code de 4 à 6 chiffres.']);
            if ($pin !== '' && (strlen($pin) < 4 || strlen($pin) > 6)) back(['error' => 'Le code fait 4 à 6 chiffres.']);
            if ($pin !== '' && Till::pinTaken((int)$org['id'], $pin, $sid)) back(['error' => 'Ce code est déjà utilisé par quelqu\'un de l\'équipe : choisis-en un autre.']);
            $role = array_key_exists((string)input('role'), Till::ROLES) ? (string)input('role') : 'server';
            $perms = array_values(array_intersect((array)($_POST['perms'] ?? []), array_keys(Till::PERMS)));
            $custom = checked('custom');
            $d = ['name' => $name, 'role' => $role, 'perms' => $custom ? implode(',', $perms) : null];
            if ($pin !== '') { $d['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT); $d['pin_hint'] = substr($pin, 0, 2); }
            if ($sid) update('pos_staff', $d, 'id = ? AND organizer_id = ?', [$sid, $org['id']]);
            else insert('pos_staff', $d + ['organizer_id' => $org['id'], 'active' => 1, 'created_at' => now()]);
            back(['success' => 'Équipier enregistré' . ($pin !== '' ? ' : son code est ' . $pin . ' (note-le, il ne sera plus affiché).' : '.')]);
        }
        if ($a === 'toggle') { q('UPDATE pos_staff SET active = 1 - active WHERE id = ? AND organizer_id = ?', [$sid, $org['id']]); back(); }
        if ($a === 'generate') {
            $n = 0;
            foreach (preg_split('~\R~', (string)input('names')) as $nm) {
                $nm = mb_substr(trim($nm), 0, 80); if ($nm === '') continue;
                do { $pin = (string)random_int(1000, 9999); } while (Till::pinTaken((int)$org['id'], $pin));
                insert('pos_staff', ['organizer_id' => $org['id'], 'name' => $nm, 'pin_hash' => password_hash($pin, PASSWORD_DEFAULT), 'pin_hint' => substr($pin, 0, 2), 'role' => 'server', 'active' => 1, 'created_at' => now()]);
                $_SESSION['new_pins'][] = [$nm, $pin]; $n++;
            }
            back(['success' => $n . ' serveur(s) créé(s). Leurs codes sont affichés ci-dessous une seule fois : imprime ou note-les.']);
        }
        back();
    }
    $pins = $_SESSION['new_pins'] ?? []; unset($_SESSION['new_pins']);
    $since = date('Y-m-d 00:00:00', strtotime((string)$ev['starts_at']) - 7 * 86400);
    $staff = all("SELECT st.*, (SELECT COUNT(*) FROM pos_sales s WHERE s.staff_id = st.id AND s.event_id = ? AND s.undone = 0 AND s.kind = 'sale') n,
            (SELECT COALESCE(SUM(amount), 0) FROM pos_sales s WHERE s.staff_id = st.id AND s.event_id = ? AND s.undone = 0 AND s.kind = 'sale') t,
            (SELECT COALESCE(SUM(offered_value), 0) FROM pos_sales s WHERE (s.staff_id = st.id OR s.approved_by = st.id) AND s.event_id = ? AND s.undone = 0 AND s.method <> 'allowance') o
        FROM pos_staff st WHERE st.organizer_id = ? ORDER BY st.active DESC, st.role DESC, st.name", [$ev['id'], $ev['id'], $ev['id'], $org['id']]);
    $sel = input('s') === 'new' ? [] : ((int)input('s') ? one('SELECT * FROM pos_staff WHERE id = ? AND organizer_id = ?', [(int)input('s'), $org['id']]) : null);
    return compact('staff', 'sel', 'pins') + ['settings' => Till::settings($ev)];
}

// ---------- Happy hours & remises ----------
function bars_remises(array $ev, array $org, string $base): array
{
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'save') {
            $name = mb_substr(trim((string)input('name')), 0, 80);
            $kind = array_key_exists((string)input('kind'), Till::PROMO_KINDS) ? (string)input('kind') : 'percent';
            $value = $kind === 'percent' ? max(0, min(100, (int)input('value'))) : ($kind === 'second' ? 0 : parse_money((string)input('value')));
            if ($name === '') back(['error' => 'Donne un nom (par exemple « Happy hour »).']);
            $d = ['name' => $name, 'kind' => $kind, 'value' => $value, 'product_ids' => bars_ids('product_ids') ?: null, 'category_ids' => bars_ids('category_ids') ?: null, 'bar_ids' => bars_ids('bar_ids') ?: null,
                'days' => implode(',', array_intersect((array)($_POST['days'] ?? []), array_keys(Till::DAYS))) ?: null, 'start_time' => input('start_time') ?: null, 'end_time' => input('end_time') ?: null,
                'starts_on' => input('starts_on') ?: null, 'ends_on' => input('ends_on') ?: null, 'active' => 1];
            if ((int)input('promo_id')) update('pos_promos', $d, 'id = ? AND event_id = ?', [(int)input('promo_id'), $ev['id']]);
            else insert('pos_promos', $d + ['event_id' => $ev['id']]);
            redirect($base, ['success' => 'Règle enregistrée : elle s\'applique automatiquement aux caisses.']);
        }
        if ($a === 'toggle') { q('UPDATE pos_promos SET active = 1 - active WHERE id = ? AND event_id = ?', [(int)input('promo_id'), $ev['id']]); back(); }
        if ($a === 'delete') { q('DELETE FROM pos_promos WHERE id = ? AND event_id = ?', [(int)input('promo_id'), $ev['id']]); back(['success' => 'Règle supprimée.']); }
        back();
    }
    $promos = all('SELECT * FROM pos_promos WHERE event_id = ? ORDER BY active DESC, id', [$ev['id']]);
    $now = time();
    foreach ($promos as &$pr) $pr['now'] = (int)$pr['active'] && Till::promoActive(array_merge($pr, ['bar_ids' => null]), 0, $now);
    unset($pr);
    $sel = input('r') === 'new' ? [] : ((int)input('r') ? one('SELECT * FROM pos_promos WHERE id = ? AND event_id = ?', [(int)input('r'), $ev['id']]) : null);
    $products = all('SELECT id, name FROM bar_products WHERE event_id = ? AND active = 1 ORDER BY sort, name', [$ev['id']]);
    $cats = all('SELECT id, name FROM pos_categories WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    $bars = all('SELECT id, name FROM bars WHERE event_id = ? ORDER BY id', [$ev['id']]);
    $discounts = all("SELECT s.created_at, s.ticket_no, s.discount, s.offered_value, s.offer_reason, s.method, b.name bar, st.name staff, ap.name approver FROM pos_sales s JOIN bars b ON b.id = s.bar_id LEFT JOIN pos_staff st ON st.id = s.staff_id LEFT JOIN pos_staff ap ON ap.id = s.approved_by
        WHERE s.event_id = ? AND s.undone = 0 AND (s.discount > 0 OR (s.offered_value > 0 AND s.method <> 'allowance')) ORDER BY s.id DESC LIMIT 50", [$ev['id']]);
    return compact('promos', 'sel', 'products', 'cats', 'bars', 'discounts');
}

// ---------- Crédits offerts ----------
function bars_credits(array $ev, array $org, string $base): array
{
    if (is_post()) {
        $a = (string)input('action');
        $mk = function (string $holder, string $grp, ?int $memberId = null) use ($ev): int {
            do { $code = 'CR' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 7)); } while (val('SELECT id FROM pos_allowances WHERE code = ?', [$code]));
            $mode = input('mode') === 'euros' ? 'euros' : 'items';
            return insert('pos_allowances', ['event_id' => $ev['id'], 'holder' => mb_substr($holder, 0, 120), 'grp' => array_key_exists($grp, Till::GROUPS) ? $grp : 'staff', 'code' => $code, 'mode' => $mode,
                'per_day' => $mode === 'euros' ? parse_money((string)input('per_day')) : max(0, (int)input('per_day')), 'total_limit' => input('total_limit') === '' ? null : ($mode === 'euros' ? parse_money((string)input('total_limit')) : max(0, (int)input('total_limit'))),
                'category_ids' => bars_ids('category_ids') ?: null, 'staff_member_id' => $memberId, 'active' => 1, 'created_at' => now()]);
        };
        if ($a === 'add') {
            $n = 0;
            foreach (preg_split('~\R~', (string)input('holders')) as $h) { $h = trim($h); if ($h !== '') { $mk($h, (string)input('grp')); $n++; } }
            back(['success' => $n . ' crédit(s) créé(s). Imprime les badges et remets-les.']);
        }
        if ($a === 'from_staff') {
            $n = 0;
            foreach (all("SELECT DISTINCT m.id, m.first_name, m.last_name FROM staff_members m JOIN staff_assignments a ON a.member_id = m.id JOIN staff_shifts s ON s.id = a.shift_id
                    WHERE s.event_id = ? AND a.status IN ('confirmed','present','done') AND m.id NOT IN (SELECT COALESCE(staff_member_id, 0) FROM pos_allowances WHERE event_id = ?)", [$ev['id'], $ev['id']]) as $m) { $mk(trim($m['first_name'] . ' ' . $m['last_name']), 'staff', (int)$m['id']); $n++; }
            back(['success' => $n ? $n . ' crédit(s) créé(s) pour les bénévoles confirmés.' : 'Tous les bénévoles confirmés ont déjà leur crédit.']);
        }
        if ($a === 'toggle') { q('UPDATE pos_allowances SET active = 1 - active WHERE id = ? AND event_id = ?', [(int)input('allowance_id'), $ev['id']]); back(); }
        if ($a === 'edit') {
            $al = one('SELECT * FROM pos_allowances WHERE id = ? AND event_id = ?', [(int)input('allowance_id'), $ev['id']]);
            if ($al) update('pos_allowances', ['holder' => mb_substr(trim((string)input('holder')) ?: $al['holder'], 0, 120), 'per_day' => $al['mode'] === 'euros' ? parse_money((string)input('per_day')) : max(0, (int)input('per_day')),
                'nfc_uid' => Pos::nfc((string)input('nfc_uid')) ?: null], 'id = ?', [$al['id']]);
            back(['success' => 'Crédit modifié.']);
        }
        back();
    }
    $list = all('SELECT * FROM pos_allowances WHERE event_id = ? ORDER BY grp, holder', [$ev['id']]);
    foreach ($list as &$al) $al['used'] = Till::allowanceUsed($al);
    unset($al);
    if (input('badges')) bars_badges($ev, $org, $list);
    $cats = all('SELECT id, name FROM pos_categories WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    $volunteers = (int)val("SELECT COUNT(DISTINCT a.member_id) FROM staff_assignments a JOIN staff_shifts s ON s.id = a.shift_id WHERE s.event_id = ? AND a.status IN ('confirmed','present','done')", [$ev['id']]);
    $value = (int)val("SELECT COALESCE(SUM(offered_value), 0) FROM pos_sales WHERE event_id = ? AND method = 'allowance' AND undone = 0", [$ev['id']]);
    return compact('list', 'cats', 'volunteers', 'value');
}

/** Badges de crédit à imprimer (8 par page A4), avec QR code. */
function bars_badges(array $ev, array $org, array $list): never
{
    $t = fn($s) => PdfDoc::t((string)$s);
    $pdf = new PdfDoc('P', 'mm', 'A4'); $pdf->SetAutoPageBreak(false); $pdf->SetMargins(10, 10, 10);
    $list = array_values(array_filter($list, fn($a) => (int)$a['active']));
    foreach ($list as $i => $a) {
        if ($i % 8 === 0) $pdf->AddPage();
        $x = 12 + ($i % 2) * 96; $y = 12 + intdiv($i % 8, 2) * 68;
        $pdf->SetDrawColor(210, 210, 215); $pdf->RoundedRect($x, $y, 90, 62, 4, 'D');
        Qr::drawPdf($pdf, $a['code'], $x + 54, $y + 14, 32);
        $pdf->SetXY($x + 6, $y + 6); $pdf->SetFont('Helvetica', '', 8); $pdf->SetTextColor(110, 110, 115); $pdf->Cell(80, 4, $t(mb_strtoupper($ev['name'])), 0, 2);
        $pdf->SetFont('Helvetica', 'B', 15); $pdf->SetTextColor(29, 29, 31); $pdf->SetXY($x + 6, $y + 14); $pdf->MultiCell(46, 6.5, $t($a['holder']), 0, 'L');
        $pdf->SetFont('Helvetica', '', 9); $pdf->SetXY($x + 6, $y + 34); $pdf->MultiCell(46, 4.5, $t((Till::GROUPS[$a['grp']] ?? '') . "\n" . ($a['mode'] === 'euros' ? money((int)$a['per_day']) . ' offerts par jour' : (int)$a['per_day'] . ' consommation(s) par jour')), 0, 'L');
        $pdf->SetFont('Courier', 'B', 9); $pdf->SetXY($x + 54, $y + 48); $pdf->Cell(32, 4, $a['code'], 0, 0, 'C');
        $pdf->SetFont('Helvetica', '', 7); $pdf->SetTextColor(110, 110, 115); $pdf->SetXY($x + 6, $y + 54); $pdf->Cell(80, 4, $t('Badge personnel, à présenter au bar. ' . $org['name']), 0, 0);
    }
    if (!$list) { $pdf->AddPage(); $pdf->SetFont('Helvetica', '', 12); $pdf->Cell(0, 10, $t('Aucun crédit actif.')); }
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="badges-credits.pdf"');
    echo $pdf->Output('S'); exit;
}

// ---------- Caisses & clôtures ----------
function bars_caisses(array $ev, array $org, string $base): array
{
    if (is_post() && input('action') === 'validate') {
        // 2.10 : l'organisateur contrôle chaque Z (écart justifié) avant la comptabilité
        $se = one('SELECT * FROM pos_sessions WHERE id = ? AND event_id = ? AND closed_at IS NOT NULL', [(int)input('session_id'), $ev['id']]);
        if ($se) update('pos_sessions', $se['validated_at'] ? ['validated_at' => null, 'validated_by' => null] : ['validated_at' => now(), 'validated_by' => user()['id'], 'validation_note' => mb_substr(trim((string)input('note')), 0, 500) ?: null], 'id = ?', [$se['id']]);
        back(['success' => $se && !$se['validated_at'] ? 'Clôture validée.' : 'Validation retirée.']);
    }
    if (is_post() && input('action') === 'force_close') {
        $se = one('SELECT * FROM pos_sessions WHERE id = ? AND event_id = ? AND closed_at IS NULL', [(int)input('session_id'), $ev['id']]);
        if ($se) {
            $tot = Till::sessionTotals($se);
            update('pos_sessions', ['closed_at' => now(), 'expected_cash' => $tot['expected_cash'], 'counted_cash' => input('counted') === '' ? null : parse_money((string)input('counted')), 'z_number' => (int)val('SELECT COALESCE(MAX(z_number), 0) + 1 FROM pos_sessions WHERE event_id = ?', [$ev['id']]),
                'totals_json' => json_encode($tot, JSON_UNESCAPED_UNICODE), 'note' => 'Clôturée depuis l\'espace organisateur par ' . user()['name']], 'id = ?', [$se['id']]);
        }
        back(['success' => 'Caisse clôturée.']);
    }
    if (($zid = (int)input('z'))) bars_z_pdf($ev, $org, $zid);
    $sessions = all('SELECT s.*, b.name bar, (SELECT GROUP_CONCAT(DISTINCT d2.name SEPARATOR \', \') FROM pos_sales x JOIN pos_devices d2 ON d2.id = x.device_id WHERE x.session_id = s.id) device, o.name opener, c.name closer FROM pos_sessions s JOIN bars b ON b.id = s.bar_id LEFT JOIN pos_staff o ON o.id = s.opened_by LEFT JOIN pos_staff c ON c.id = s.closed_by WHERE s.event_id = ? ORDER BY s.closed_at IS NULL DESC, s.id DESC', [$ev['id']]);
    $validators = array_column(all('SELECT DISTINCT u.id, u.name FROM pos_sessions s JOIN users u ON u.id = s.validated_by WHERE s.event_id = ?', [$ev['id']]), 'name', 'id');
    $tabs = []; foreach (all("SELECT bar_id, COUNT(*) n, SUM(total) t FROM pos_tabs WHERE event_id = ? AND status = 'open' GROUP BY bar_id", [$ev['id']]) as $r) $tabs[(int)$r['bar_id']] = $r;
    foreach ($sessions as &$se) { $se['validator'] = $validators[(int)$se['validated_by']] ?? null; $se['tabs'] = !$se['closed_at'] ? ($tabs[(int)$se['bar_id']] ?? null) : null; $se['t'] = $se['closed_at'] && $se['totals_json'] ? json_decode((string)$se['totals_json'], true) : Till::sessionTotals($se); $se['moves'] = all('SELECT m.*, st.name staff FROM pos_cash_moves m LEFT JOIN pos_staff st ON st.id = m.staff_id WHERE m.session_id = ? ORDER BY m.id', [$se['id']]); }
    unset($se);
    if (!empty($GLOBALS['pay_bars'])) $sessions = array_values(array_filter($sessions, fn($s) => in_array((int)$s['bar_id'], $GLOBALS['pay_bars'], true)));
    $toValidate = count(array_filter($sessions, fn($s) => $s['closed_at'] && !$s['validated_at']));
    return compact('sessions', 'toValidate');
}

/** 2.10 : détail d'un comptage billets/pièces. */
function bars_count_txt(?string $json): string
{
    $c = json_decode((string)$json, true); if (!$c) return '';
    return implode(' · ', array_map(fn($v, $q) => $q . ' × ' . ((int)$v >= 100 ? ((int)$v / 100) . ' €' : (int)$v . ' c'), array_keys($c), $c));
}

function bars_z_pdf(array $ev, array $org, int $sid): never
{
    $se = one('SELECT s.*, b.name bar, (SELECT GROUP_CONCAT(DISTINCT d2.name SEPARATOR \', \') FROM pos_sales x JOIN pos_devices d2 ON d2.id = x.device_id WHERE x.session_id = s.id) device, o.name opener, c.name closer FROM pos_sessions s JOIN bars b ON b.id = s.bar_id LEFT JOIN pos_staff o ON o.id = s.opened_by LEFT JOIN pos_staff c ON c.id = s.closed_by WHERE s.id = ? AND s.event_id = ?', [$sid, $ev['id']]);
    if (!$se) abort(404);
    $tt = $se['closed_at'] && $se['totals_json'] ? json_decode((string)$se['totals_json'], true) : Till::sessionTotals($se);
    $t = fn($s) => PdfDoc::t((string)$s);
    $pdf = new PdfDoc('P', 'mm', 'A4'); $pdf->SetMargins(18, 18, 18); $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 20); $pdf->Cell(0, 10, $t($se['z_number'] ? 'Clôture de caisse Z' . $se['z_number'] : 'Caisse en cours (X)'), 0, 1);
    $pdf->SetFont('Helvetica', '', 10); $pdf->SetTextColor(110, 110, 115);
    $pdf->MultiCell(0, 5, $t($org['legal_name'] ?: $org['name']) . "\n" . $t($ev['name'] . ' · ' . $se['bar'] . ($se['device'] ? ' · ' . $se['device'] : '')) . "\n" . $t('Ouverte le ' . date('d/m/Y H:i', strtotime($se['opened_at'])) . ($se['opener'] ? ' par ' . $se['opener'] : '') . ($se['closed_at'] ? ' · clôturée le ' . date('d/m/Y H:i', strtotime($se['closed_at'])) . ($se['closer'] ? ' par ' . $se['closer'] : '') : '')));
    $pdf->Ln(4); $pdf->SetTextColor(29, 29, 31);
    $row = function (string $l, string $v, bool $b = false) use ($pdf, $t) { $pdf->SetFont('Helvetica', $b ? 'B' : '', 11); $pdf->Cell(120, 7.5, $t($l), 'B'); $pdf->Cell(54, 7.5, $t($v), 'B', 1, 'R'); };
    foreach (Till::METHODS as $k => $l) if (isset($tt['methods'][$k])) $row($l . ' (' . $tt['methods'][$k]['n'] . ')', money((int)$tt['methods'][$k]['total']) . ((int)$tt['methods'][$k]['offered'] ? ' · offert ' . money((int)$tt['methods'][$k]['offered']) : ''));
    $row('Total des ventes', money((int)$tt['total']), true);
    if (!empty($tt['topups']['n'])) $row('Recharges cashless (espèces · carte)', money((int)$tt['topups']['cash']) . ' · ' . money((int)$tt['topups']['card']));
    if (!empty($tt['cashout'])) $row('Soldes remboursés en espèces', '- ' . money((int)$tt['cashout']));
    if (!empty($tt['refunds'])) $row('Tickets remboursés', '- ' . money((int)$tt['refunds']));
    if (!empty($tt['tips'])) $row('Pourboires (dont espèces dans le tiroir)', money((int)$tt['tips']));
    $pdf->Ln(4);
    $row('Fond de caisse', money((int)$tt['float']));
    $row('Apports', money((int)$tt['moves']['add']));
    $row('Remises en banque', '- ' . money((int)$tt['moves']['drop']));
    $row('Espèces attendues', money((int)$tt['expected_cash']), true);
    if ($se['counted_cash'] !== null) { $row('Espèces comptées', money((int)$se['counted_cash']), true); $row('Écart', money((int)$se['counted_cash'] - (int)$tt['expected_cash']), true); }
    $pdf->SetFont('Helvetica', '', 9); $pdf->SetTextColor(110, 110, 115);
    if ($se['float_json']) $pdf->MultiCell(0, 5, $t('Fond compté : ' . bars_count_txt($se['float_json'])));
    if ($se['count_json']) $pdf->MultiCell(0, 5, $t('Clôture comptée : ' . bars_count_txt($se['count_json'])));
    if ($se['validated_at']) $pdf->MultiCell(0, 5, $t('Validée le ' . date('d/m/Y H:i', strtotime($se['validated_at'])) . ($se['validation_note'] ? ' : ' . $se['validation_note'] : '')));
    $pdf->SetTextColor(29, 29, 31);
    if (!empty($tt['staff'])) {
        $pdf->Ln(4); $pdf->SetFont('Helvetica', 'B', 12); $pdf->Cell(0, 8, $t('Par serveur'), 0, 1); $pdf->SetFont('Helvetica', '', 10);
        foreach ($tt['staff'] as $name => $x) { $pdf->Cell(80, 6.5, $t($name), 'B'); $pdf->Cell(26, 6.5, $t($x['n'] . ' vente(s)'), 'B', 0, 'R'); $pdf->Cell(34, 6.5, $t('esp. ' . money((int)$x['cash'])), 'B', 0, 'R'); $pdf->Cell(34, 6.5, $t(money((int)$x['total'])), 'B', 1, 'R'); }
    }
    $pdf->Ln(5); $pdf->SetFont('Helvetica', 'B', 12); $pdf->Cell(0, 8, $t('Articles'), 0, 1); $pdf->SetFont('Helvetica', '', 10);
    foreach ($tt['products'] as $name => $p) { $pdf->Cell(120, 6.5, $t($name), 'B'); $pdf->Cell(20, 6.5, (string)$p['qty'], 'B', 0, 'R'); $pdf->Cell(34, 6.5, $t(money((int)$p['total'])), 'B', 1, 'R'); }
    if ($se['note']) { $pdf->Ln(4); $pdf->SetFont('Helvetica', '', 9); $pdf->SetTextColor(110, 110, 115); $pdf->MultiCell(0, 5, $t($se['note'])); }
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="caisse-z' . (int)$se['z_number'] . '.pdf"');
    echo $pdf->Output('S'); exit;
}

// ---------- Stocks ----------
function bars_stocks(array $ev, array $org, string $base): array
{
    $bars = all('SELECT id, name FROM bars WHERE event_id = ? ORDER BY id', [$ev['id']]);
    if (!empty($GLOBALS['pay_bars'])) $bars = array_values(array_filter($bars, fn($b) => in_array((int)$b['id'], $GLOBALS['pay_bars'], true)));
    if (is_post()) {
        $a = (string)input('action');
        $p = one('SELECT * FROM bar_products WHERE id = ? AND event_id = ?', [(int)input('product_id'), $ev['id']]);
        $bar = (int)input('bar_id'); $qty = (int)input('qty');
        $okBar = fn($b) => (bool)array_filter($bars, fn($x) => (int)$x['id'] === $b);
        if (!$p || !$okBar($bar)) back(['error' => 'Choisis un produit et un bar.']);
        if (!(int)$p['track_stock']) update('bar_products', ['track_stock' => 1], 'id = ?', [$p['id']]);
        $uid = (int)user()['id'];
        if ($a === 'in' && $qty) { Till::stockMove((int)$ev['id'], (int)$p['id'], $bar, $qty, 'in', null, $uid, trim((string)input('note')) ?: null); back(['success' => 'Entrée de ' . $qty . ' × ' . $p['name'] . ' enregistrée.']); }
        if ($a === 'transfer' && $qty > 0) {
            $to = (int)input('to_bar_id'); if (!$okBar($to) || $to === $bar) back(['error' => 'Choisis le bar de destination.']);
            Till::stockMove((int)$ev['id'], (int)$p['id'], $bar, -$qty, 'transfer', null, $uid, 'vers ' . $to); Till::stockMove((int)$ev['id'], (int)$p['id'], $to, $qty, 'transfer', null, $uid, 'depuis ' . $bar);
            back(['success' => 'Transfert enregistré.']);
        }
        if ($a === 'count') {
            $cur = (int)val('SELECT qty FROM pos_stock WHERE product_id = ? AND bar_id = ?', [$p['id'], $bar]);
            $real = max(0, (int)input('qty'));
            if ($real !== $cur) Till::stockMove((int)$ev['id'], (int)$p['id'], $bar, $real - $cur, 'inventory', null, $uid, 'Inventaire : ' . $real . ' compté(s) pour ' . $cur . ' attendu(s)');
            back(['success' => 'Inventaire enregistré (' . ($real - $cur >= 0 ? '+' : '') . ($real - $cur) . ').']);
        }
        back();
    }
    $products = all('SELECT * FROM bar_products WHERE event_id = ? AND active = 1 ORDER BY track_stock DESC, sort, name', [$ev['id']]);
    $stock = []; foreach (all('SELECT s.* FROM pos_stock s JOIN bar_products p ON p.id = s.product_id WHERE p.event_id = ?', [$ev['id']]) as $r) $stock[(int)$r['product_id']][(int)$r['bar_id']] = (int)$r['qty'];
    $moves = all('SELECT m.*, p.name product, b.name bar, u.name who FROM pos_stock_moves m JOIN bar_products p ON p.id = m.product_id JOIN bars b ON b.id = m.bar_id LEFT JOIN users u ON u.id = m.user_id WHERE m.event_id = ? AND m.kind <> \'sale\' ORDER BY m.id DESC LIMIT 40', [$ev['id']]);
    $sold = []; foreach (all("SELECT product_id, bar_id, -SUM(delta) q FROM pos_stock_moves WHERE event_id = ? AND kind IN ('sale','undo') GROUP BY product_id, bar_id", [$ev['id']]) as $r) $sold[(int)$r['product_id']][(int)$r['bar_id']] = (int)$r['q'];
    if (input('export') === 'csv') {
        $rows = [array_merge(['Produit'], array_column($bars, 'name'), ['Total'])];
        foreach ($products as $p) if ((int)$p['track_stock']) { $line = [$p['name']]; $sum = 0; foreach ($bars as $b) { $q = $stock[(int)$p['id']][(int)$b['id']] ?? 0; $line[] = $q; $sum += $q; } $line[] = $sum; $rows[] = $line; }
        csv_download('stocks-' . $ev['slug'] . '.csv', $rows);
    }
    return compact('bars', 'products', 'stock', 'moves', 'sold');
}

// ---------- Ventes ----------
function bars_ventes(array $ev, array $org, string $base): array
{
    $w = ['s.event_id = ?']; $p = [$ev['id']];
    $f = ['bar' => (int)input('bar'), 'method' => (string)input('method'), 'staff' => (int)input('staff'), 'day' => (string)input('day'), 'q' => trim((string)input('q')), 'undone' => checked('undone')];
    $f['kind'] = in_array(input('kind'), ['sale', 'topup', 'cashout'], true) ? (string)input('kind') : '';
    if ($f['kind']) { $w[] = 's.kind = ?'; $p[] = $f['kind']; }
    if ($f['bar']) { $w[] = 's.bar_id = ?'; $p[] = $f['bar']; }
    if (isset(Till::METHODS[$f['method']])) { $w[] = "(s.method = ? OR (s.method = 'split' AND s.payments_json LIKE ?))"; $p[] = $f['method']; $p[] = '%"method":"' . $f['method'] . '"%'; }
    if ($f['staff']) { $w[] = 's.staff_id = ?'; $p[] = $f['staff']; }
    if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $f['day'])) { $w[] = 'DATE(s.created_at) = ?'; $p[] = $f['day']; }
    if ($f['q'] !== '') { $w[] = '(s.ticket_no = ? OR s.items LIKE ?)'; $p[] = (int)$f['q']; $p[] = '%' . $f['q'] . '%'; }
    if (!$f['undone']) $w[] = 's.undone = 0';
    if (!empty($GLOBALS['pay_bars'])) $w[] = 's.bar_id IN (' . implode(',', array_map('intval', $GLOBALS['pay_bars'])) . ')';
    $where = implode(' AND ', $w);
    $sales = all("SELECT s.*, b.name bar, st.name staff_name, ap.name approver FROM pos_sales s JOIN bars b ON b.id = s.bar_id LEFT JOIN pos_staff st ON st.id = s.staff_id LEFT JOIN pos_staff ap ON ap.id = s.approved_by WHERE $where ORDER BY s.id DESC LIMIT 300", $p);
    $tot = one("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN s.kind = 'sale' THEN s.amount END), 0) t, COALESCE(SUM(s.offered_value), 0) o, COALESCE(SUM(s.discount), 0) d,
        COALESCE(SUM(CASE WHEN s.kind = 'topup' THEN s.amount END), 0) top, COALESCE(-SUM(CASE WHEN s.kind = 'cashout' THEN s.amount END), 0) cashout FROM pos_sales s WHERE $where", $p);
    if (input('export') === 'csv') {
        $rows = [['Ticket', 'Date', 'Bar', 'Serveur', 'Moyen', 'Montant', 'Offert', 'Remise', 'Motif', 'Validé par', 'Articles', 'Hors ligne', 'Annulée']];
        foreach (all("SELECT s.*, b.name bar, st.name staff_name, ap.name approver FROM pos_sales s JOIN bars b ON b.id = s.bar_id LEFT JOIN pos_staff st ON st.id = s.staff_id LEFT JOIN pos_staff ap ON ap.id = s.approved_by WHERE $where ORDER BY s.id", $p) as $s)
            $rows[] = [$s['ticket_no'], $s['created_at'], $s['bar'], $s['staff_name'] ?: $s['staff'], Till::METHODS[$s['method']] ?? $s['method'], money_input((int)$s['amount']), money_input((int)$s['offered_value']), money_input((int)$s['discount']), (string)$s['offer_reason'], (string)$s['approver'],
                implode(' | ', array_map(fn($l) => $l['qty'] . ' x ' . $l['name'], json_decode((string)$s['items'], true) ?: [])), $s['offline'] ? 'oui' : '', $s['undone'] ? 'oui' : ''];
        csv_download('ventes-bars-' . $ev['slug'] . '.csv', $rows);
    }
    $bars = all('SELECT id, name FROM bars WHERE event_id = ? ORDER BY id', [$ev['id']]);
    $staff = all('SELECT DISTINCT st.id, st.name FROM pos_staff st JOIN pos_sales s ON s.staff_id = st.id WHERE s.event_id = ? ORDER BY st.name', [$ev['id']]);
    $days = array_column(all('SELECT DISTINCT DATE(created_at) d FROM pos_sales WHERE event_id = ? ORDER BY d DESC', [$ev['id']]), 'd');
    return compact('sales', 'tot', 'f', 'bars', 'staff', 'days');
}

// ---------- Appareils ----------
function bars_appareils(array $ev, array $org, string $base): array
{
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'bar' || $a === 'bar_toggle') return bars_reglages($ev, $org, $base);
        if ($a === 'device_add') {
            // 2.9 : l'organisateur appaire seulement ; point de vente et rôle se choisissent sur l'appareil
            $d = Pos::createDevice((int)$ev['id'], null, trim((string)input('name')) ?: 'Appareil ' . ((int)val('SELECT COUNT(*) FROM pos_devices WHERE event_id = ?', [$ev['id']]) + 1), (int)user()['id']);
            redirect($base . '?d=' . $d['id'], ['success' => 'Appareil créé : scanne le QR code avec la tablette ou le téléphone.']);
        }
        $dev = one('SELECT * FROM pos_devices WHERE id = ? AND event_id = ?', [(int)input('device_id'), $ev['id']]);
        if ($dev && $a === 'device_code') { Pos::renewCode($dev); redirect($base . '?d=' . $dev['id'], ['success' => 'Nouveau code d\'appairage (valable 30 minutes).']); }
        if ($dev && $a === 'device_revoke') { update('pos_devices', ['revoked' => 1, 'token_hash' => null, 'pair_code' => null], 'id = ?', [$dev['id']]); back(['success' => 'Appareil déconnecté.']); }
        if ($dev && $a === 'device_tpe') {
            $t = ['mode' => input('mode') === 'nepting' ? 'nepting' : 'manual', 'host' => trim((string)input('host')), 'port' => (int)input('port') ?: 8888, 'cj' => (string)input('cj'), 'ca' => (string)input('ca'), 'cz' => (string)input('cz') ?: '0320', 'crlf' => checked('crlf'), 'timeout' => (int)input('timeout') ?: 120];
            update('pos_devices', ['tpe_json' => json_encode($t), 'name' => mb_substr(trim((string)input('name')) ?: $dev['name'], 0, 80)], 'id = ?', [$dev['id']]);
            back(['success' => 'Réglages de l\'appareil enregistrés.']);
        }
        back();
    }
    $devices = all('SELECT d.*, b.name bar_name, (SELECT COUNT(*) FROM pos_sales s WHERE s.device_id = d.id AND s.undone = 0) sales FROM pos_devices d LEFT JOIN bars b ON b.id = d.bar_id WHERE d.event_id = ? AND d.revoked = 0 ORDER BY d.id', [$ev['id']]);
    $bars = all('SELECT * FROM bars WHERE event_id = ? ORDER BY active DESC, id', [$ev['id']]);
    $tills = [];
    foreach (all('SELECT s.*, o.name opener FROM pos_sessions s LEFT JOIN pos_staff o ON o.id = s.opened_by WHERE s.event_id = ? AND s.closed_at IS NULL', [$ev['id']]) as $se) $tills[(int)$se['bar_id']] = $se + ['tot' => Till::sessionTotals($se)];
    $tabs = []; foreach (all("SELECT bar_id, COUNT(*) n, SUM(total) t FROM pos_tabs WHERE event_id = ? AND status = 'open' GROUP BY bar_id", [$ev['id']]) as $r) $tabs[(int)$r['bar_id']] = $r;
    return ['tabs' => $tabs, 'devices' => $devices, 'bars' => $bars, 'tills' => $tills, 'focus' => (int)input('d'), 'sessionsOn' => (bool)Till::settings($ev)['sessions']];
}

// ---------- Réglages ----------
function bars_reglages(array $ev, array $org, string $base): array
{
    if (is_post()) {
        if (input('action') === 'bar') {
            $name = mb_substr(trim((string)input('name')), 0, 80);
            if ($name === '') back(['error' => 'Donne un nom au point de vente.']);
            if ((int)input('bar_id')) update('bars', ['name' => $name], 'id = ? AND event_id = ?', [(int)input('bar_id'), $ev['id']]);
            else insert('bars', ['event_id' => $ev['id'], 'name' => $name, 'active' => 1]);
            back(['success' => 'Point de vente enregistré.']);
        }
        if (input('action') === 'bar_toggle') {
            if (Till::barSession((int)$ev['id'], (int)input('bar_id'))) back(['error' => 'Clôture d\'abord la caisse de ce point de vente (depuis l\'appli, menu › Caisse).']);
            q('UPDATE bars SET active = 1 - active WHERE id = ? AND event_id = ?', [(int)input('bar_id'), $ev['id']]); back();
        }
        $s = Till::settings($ev);
        $s['methods'] = array_values(array_intersect((array)($_POST['methods'] ?? []), ['cashless', 'card', 'cash', 'allowance']));
        foreach (['require_staff', 'sessions', 'photos', 'big_buttons', 'tabs', 'blind_close', 'free_amount', 'count_denoms', 'tips'] as $k) $s[$k] = checked($k);
        $s['tip_pcts'] = implode(',', array_slice(array_filter(array_map(fn($v) => max(0, min(50, (int)$v)), explode(',', (string)input('tip_pcts')))), 0, 4)) ?: '5,10,15';
        $s['receipt'] = in_array(input('receipt'), ['off', 'qr', 'ask'], true) ? (string)input('receipt') : 'ask';
        $s['receipt_footer'] = mb_substr(trim((string)input('receipt_footer')), 0, 500);
        $s['default_vat'] = max(0, min(30, (float)str_replace(',', '.', (string)input('default_vat'))));
        $s['drop_alert'] = parse_money((string)input('drop_alert'));
        $s['offer_reasons'] = mb_substr(trim((string)input('offer_reasons')), 0, 1000);
        if (!$s['methods']) back(['error' => 'Garde au moins un moyen de paiement.']);
        update('events', ['pos_json' => json_encode($s, JSON_UNESCAPED_UNICODE)], 'id = ?', [$ev['id']]);
        back(['success' => 'Réglages enregistrés : les caisses les reçoivent sous 10 minutes, ou tout de suite avec « Mettre à jour ».']);
    }
    return ['settings' => Till::settings($ev), 'bars' => all('SELECT * FROM bars WHERE event_id = ? ORDER BY id', [$ev['id']])];
}

// ---------- 2.8 : rapport de caisse ----------
/** Agrège les ventes : chiffre d'affaires, TVA par taux, moyens de paiement, offerts, consignes, recharges. */
function bars_report_data(array $ev, ?string $day, ?int $bar): array
{
    $w = 's.event_id = ? AND s.undone = 0'; $p = [$ev['id']];
    if ($day) { $w .= ' AND DATE(s.created_at) = ?'; $p[] = $day; }
    if ($bar) { $w .= ' AND s.bar_id = ?'; $p[] = $bar; }
    $rows = all("SELECT s.*, b.name bar FROM pos_sales s JOIN bars b ON b.id = s.bar_id WHERE $w ORDER BY s.created_at", $p);
    $blank = fn() => ['ttc' => 0, 'ht' => 0, 'tva' => 0, 'n' => 0, 'offered' => 0, 'discount' => 0, 'cashless' => 0, 'card' => 0, 'cash' => 0, 'dep_in' => 0, 'dep_out' => 0, 'topup' => 0, 'cashout' => 0, 'cost' => 0, 'tip' => 0, 'refund' => 0];
    $tot = $blank(); $days = []; $bars = []; $vat = []; $cats = []; $prods = []; $staff = [];
    $catName = array_column(all('SELECT id, name FROM pos_categories WHERE event_id = ?', [$ev['id']]), 'name', 'id');
    $acc = function (array &$t, array $s, array $calc) {
        foreach ($calc as $k => $v) $t[$k] += $v;
    };
    foreach ($rows as $s) {
        $kind = $s['kind'] ?? 'sale';
        $c = $blank();
        if ($kind === 'topup') $c['topup'] = (int)$s['amount'];
        elseif ($kind === 'cashout') $c['cashout'] = -(int)$s['amount'];
        else {
            $c['n'] = $kind === 'refund' ? 0 : 1; $c['offered'] = (int)$s['offered_value']; $c['discount'] = (int)$s['discount']; $c['tip'] = (int)($s['tip'] ?? 0);
            if ($kind === 'refund') $c['refund'] = -(int)$s['amount'];
            $paid = !in_array($s['method'], ['allowance', 'offered'], true);
            $parts = $s['method'] === 'split' ? (json_decode((string)$s['payments_json'], true) ?: []) : [['method' => $s['method'], 'amount' => (int)$s['amount']]];
            foreach ($parts as $pt) if (isset($c[$pt['method']])) $c[$pt['method']] += (int)$pt['amount'];
            foreach (json_decode((string)$s['items'], true) ?: [] as $l) {
                $k = $l['kind'] ?? 'product';
                if ($k === 'deposit') $c['dep_in'] += (int)($l['total'] ?? 0);
                elseif ($k === 'return') $c['dep_out'] += -(int)($l['total'] ?? 0);
                elseif ($k === 'product') {
                    $pn = preg_replace('~^Remboursement ~', '', (string)$l['name']); $sign = $kind === 'refund' ? -1 : 1;
                    $prods[$pn]['q'] = ($prods[$pn]['q'] ?? 0) + $sign * (int)($l['qty'] ?? 0); $prods[$pn]['t'] = ($prods[$pn]['t'] ?? 0) + ($paid ? (int)($l['total'] ?? 0) : 0);
                    $lht = $paid ? (int)round((int)($l['total'] ?? 0) / (1 + (float)($l['vat'] ?? 20) / 100)) : 0;
                    $prods[$pn]['ht'] = ($prods[$pn]['ht'] ?? 0) + $lht;
                    if (isset($l['cost']) && $l['cost'] !== null) { $prods[$pn]['c'] = ($prods[$pn]['c'] ?? 0) + (int)$l['cost']; $prods[$pn]['cht'] = ($prods[$pn]['cht'] ?? 0) + $lht; $c['cost'] += (int)$l['cost']; }
                    $cn = $catName[$l['category_id'] ?? 0] ?? 'Sans catégorie'; $cats[$cn]['q'] = ($cats[$cn]['q'] ?? 0) + (int)($l['qty'] ?? 0); $cats[$cn]['t'] = ($cats[$cn]['t'] ?? 0) + ($paid ? (int)($l['total'] ?? 0) : 0);
                }
            }
            if ($paid) foreach (json_decode((string)$s['vat_json'], true) ?: [] as $rate => $ttc) {
                $ht = (int)round($ttc / (1 + (float)$rate / 100)); $r = rtrim(rtrim((string)$rate, '0'), '.');
                $vat[$r] = $vat[$r] ?? ['ttc' => 0, 'ht' => 0, 'tva' => 0]; $vat[$r]['ttc'] += (int)$ttc; $vat[$r]['ht'] += $ht; $vat[$r]['tva'] += (int)$ttc - $ht;
                $c['ttc'] += (int)$ttc; $c['ht'] += $ht; $c['tva'] += (int)$ttc - $ht;
            }
        }
        $d = substr($s['created_at'], 0, 10);
        $days[$d] = $days[$d] ?? $blank(); $bars[$s['bar']] = $bars[$s['bar']] ?? $blank();
        $acc($days[$d], $s, $c); $acc($bars[$s['bar']], $s, $c); $acc($tot, $s, $c);
        if ($kind === 'sale') { $sn = (string)$s['staff'] ?: 'Sans nom'; $staff[$sn] = $staff[$sn] ?? $blank(); $acc($staff[$sn], $s, $c); }
    }
    ksort($days); ksort($vat, SORT_NUMERIC); arsort($cats); uasort($prods, fn($a, $b) => $b['t'] <=> $a['t']);
    uasort($staff, fn($a, $b) => $b['ttc'] <=> $a['ttc']);
    return compact('tot', 'days', 'bars', 'vat', 'cats', 'prods', 'staff') + ['count' => count($rows)];
}

function bars_rapport(array $ev, array $org, string $base): array
{
    $day = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('jour')) ? (string)input('jour') : null;
    $bar = (int)input('bar') ?: null;
    $r = bars_report_data($ev, $day, $bar);
    $label = ($day ? fr_date($day, false) : 'Tout l\'événement') . ($bar ? ' · ' . val('SELECT name FROM bars WHERE id = ?', [$bar]) : '');
    if (input('export') === 'csv') {
        $m = fn($c) => money_input((int)$c);
        $rows = [['Jour', 'Tickets', 'CA TTC', 'CA HT', 'TVA', 'Cashless', 'Carte', 'Espèces', 'Offert', 'Remises', 'Consignes encaissées', 'Consignes rendues', 'Recharges', 'Soldes remboursés']];
        foreach ($r['days'] as $d => $x) $rows[] = [$d, $x['n'], $m($x['ttc']), $m($x['ht']), $m($x['tva']), $m($x['cashless']), $m($x['card']), $m($x['cash']), $m($x['offered']), $m($x['discount']), $m($x['dep_in']), $m($x['dep_out']), $m($x['topup']), $m($x['cashout'])];
        $rows[] = []; $rows[] = ['Taux de TVA', 'HT', 'TVA', 'TTC'];
        foreach ($r['vat'] as $rate => $v) $rows[] = [$rate . ' %', $m($v['ht']), $m($v['tva']), $m($v['ttc'])];
        $rows[] = []; $rows[] = ['Point de vente', 'Tickets', 'CA TTC', 'Cashless', 'Carte', 'Espèces', 'Offert'];
        foreach ($r['bars'] as $n => $x) $rows[] = [$n, $x['n'], $m($x['ttc']), $m($x['cashless']), $m($x['card']), $m($x['cash']), $m($x['offered'])];
        csv_download('rapport-caisse-' . $ev['slug'] . ($day ? '-' . $day : '') . '.csv', $rows);
    }
    if (input('export') === 'pdf') bars_report_pdf($ev, $org, $r, $label);
    $daysList = array_column(all('SELECT DISTINCT DATE(created_at) d FROM pos_sales WHERE event_id = ? ORDER BY d', [$ev['id']]), 'd');
    $barsList = all('SELECT id, name FROM bars WHERE event_id = ? ORDER BY id', [$ev['id']]);
    return ['r' => $r, 'day' => $day, 'bar' => $bar, 'label' => $label, 'daysList' => $daysList, 'barsList' => $barsList];
}

function bars_report_pdf(array $ev, array $org, array $r, string $label): never
{
    $t = fn($s) => PdfDoc::t((string)$s);
    $pdf = new PdfDoc('P', 'mm', 'A4'); $pdf->SetMargins(16, 16, 16); $pdf->SetAutoPageBreak(true, 16); $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 20); $pdf->Cell(0, 10, $t('Rapport de caisse'), 0, 1);
    $pdf->SetFont('Helvetica', '', 10); $pdf->SetTextColor(110, 110, 115);
    $pdf->MultiCell(0, 5, $t(($org['legal_name'] ?: $org['name']) . ' · ' . $ev['name'] . "\n" . $label . ' · édité le ' . date('d/m/Y H:i')));
    $pdf->Ln(4); $pdf->SetTextColor(29, 29, 31);
    $T = $r['tot'];
    $tiles = [[money($T['ttc']), 'CA TTC'], [money($T['ht']), 'CA HT'], [money($T['tva']), 'TVA collectée'], [(string)$T['n'], 'tickets'], [money($T['offered']), 'offert'], [money($T['topup']), 'recharges cashless']];
    $y0 = $pdf->GetY();
    foreach ($tiles as $i => [$v, $l]) {
        $x = 16 + ($i % 3) * 60; $y = $y0 + intdiv($i, 3) * 24;
        $pdf->SetFillColor(245, 245, 247); $pdf->RoundedRect($x, $y, 56, 20, 3, 'F');
        $pdf->SetXY($x + 4, $y + 3); $pdf->SetFont('Helvetica', 'B', 13); $pdf->Cell(48, 7, $t($v), 0, 2); $pdf->SetFont('Helvetica', '', 8.5); $pdf->SetTextColor(110, 110, 115); $pdf->Cell(48, 5, $t($l)); $pdf->SetTextColor(29, 29, 31);
    }
    $pdf->SetY($y0 + 50);
    $table = function (string $title, array $head, array $rows, array $w) use ($pdf, $t) {
        if ($pdf->GetY() > 245) $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 12); $pdf->Cell(0, 8, $t($title), 0, 1);
        $pdf->SetFont('Helvetica', 'B', 8); $pdf->SetTextColor(110, 110, 115);
        foreach ($head as $i => $h) $pdf->Cell($w[$i], 6, $t($h), 'B', 0, $i ? 'R' : 'L');
        $pdf->Ln(); $pdf->SetFont('Helvetica', '', 9); $pdf->SetTextColor(29, 29, 31); $pdf->SetDrawColor(225, 225, 230);
        foreach ($rows as $row) { if ($pdf->GetY() > 275) $pdf->AddPage(); foreach ($row as $i => $c) $pdf->Cell($w[$i], 6, $t(mb_strimwidth((string)$c, 0, $i ? 18 : 48, '…')), 'B', 0, $i ? 'R' : 'L'); $pdf->Ln(); }
        $pdf->Ln(4);
    };
    $table('TVA par taux', ['Taux', 'HT', 'TVA', 'TTC'], array_map(fn($k, $v) => [$k . ' %', money($v['ht']), money($v['tva']), money($v['ttc'])], array_keys($r['vat']), $r['vat']), [70, 36, 36, 36]);
    $table('Moyens de paiement', ['', 'Montant'], [['Cashless (consommé)', money($T['cashless'])], ['Carte bancaire', money($T['card'])], ['Espèces', money($T['cash'])], ['Offert (dont crédits)', money($T['offered'])], ['Remises accordées', money($T['discount'])]], [142, 36]);
    $table('Consignes et cashless', ['', 'Montant'], [['Consignes encaissées', money($T['dep_in'])], ['Consignes rendues', money($T['dep_out'])], ['Consignes conservées', money($T['dep_in'] - $T['dep_out'])], ['Recharges sur place', money($T['topup'])], ['Soldes remboursés sur place', money($T['cashout'])]], [142, 36]);
    $table('Par jour', ['Jour', 'Tickets', 'CA TTC', 'Cashless', 'Carte', 'Espèces'], array_map(fn($d, $x) => [date('d/m/Y', strtotime($d)), $x['n'], money($x['ttc']), money($x['cashless']), money($x['card']), money($x['cash'])], array_keys($r['days']), $r['days']), [42, 20, 29, 29, 29, 29]);
    $table('Par point de vente', ['Point de vente', 'Tickets', 'CA TTC', 'Cashless', 'Carte', 'Espèces'], array_map(fn($n, $x) => [$n, $x['n'], money($x['ttc']), money($x['cashless']), money($x['card']), money($x['cash'])], array_keys($r['bars']), $r['bars']), [42, 20, 29, 29, 29, 29]);
    $table('Meilleures ventes', ['Produit', 'Quantité', 'TTC'], array_map(fn($n, $x) => [$n, $x['q'], money($x['t'])], array_slice(array_keys($r['prods']), 0, 25), array_slice($r['prods'], 0, 25)), [118, 30, 30]);
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="rapport-caisse.pdf"');
    echo $pdf->Output('S'); exit;
}
