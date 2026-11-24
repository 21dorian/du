<?php
declare(strict_types=1);

/* 2.1 : espace organisateur des cartes cadeaux, abonnements & pass, boutique. */

require_once APP . '/controllers/pro.php';

function store_orders(int $orgId, string $kind, int $limit = 200): array
{
    return all("SELECT o.*, (SELECT GROUP_CONCAT(CONCAT(i.qty, ' × ', i.name) SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) detail
        FROM orders o WHERE o.organizer_id = ? AND o.event_id = 0 AND o.status IN ('paid','refunded') AND EXISTS (SELECT 1 FROM order_items i WHERE i.order_id = o.id AND i.kind = ?)
        ORDER BY o.id DESC LIMIT $limit", [$orgId, $kind]);
}

// ---------- Cartes cadeaux ----------
function pro_gift_cards(): string
{
    $org = require_org();
    $oid = (int)$org['id'];
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'settings') {
            $vals = array_filter(array_map(fn($v) => parse_money(trim($v)), explode(',', (string)input('gift_amounts'))), fn($v) => $v >= 100);
            $vals = array_slice(array_values(array_unique($vals)), 0, 6); sort($vals);
            $amounts = implode(', ', array_map(fn($v) => rtrim(rtrim(money_input($v), '0'), ','), $vals));
            update('organizers', ['gift_enabled' => checked('gift_enabled'), 'gift_amounts' => $amounts ?: null, 'gift_months' => max(1, min(36, (int)input('gift_months', 12)))], 'id = ?', [$oid]);
            back(['success' => 'Réglages enregistrés.']);
        }
        if ($a === 'create') {
            $amount = parse_money(input('amount'));
            if ($amount < 100) back(['error' => 'Indique un montant.']);
            $email = strtolower(trim((string)input('recipient_email')));
            $card = Store::newGiftCard(one('SELECT * FROM organizers WHERE id = ?', [$oid]), $amount, ['recipient_name' => mb_substr(trim((string)input('recipient_name')), 0, 190) ?: null,
                'recipient_email' => is_email($email) ? $email : null, 'message' => mb_substr(trim((string)input('message')), 0, 500) ?: null]);
            Audit::log('gift.created', 'Carte offerte ' . $card['code'] . ' (' . money($amount) . ')', null, null, $oid);
            if (is_email($email) && input('send')) {
                try { Mailer::send($email, 'Une carte cadeau ' . $org['name'] . ' pour toi', '<p>Bonjour' . ($card['recipient_name'] ? ' ' . e($card['recipient_name']) : '') . ',</p><p><b>' . e($org['name']) . '</b> t\'offre une carte cadeau de <b>' . e(money($amount)) . '</b>.</p>'
                    . ($card['message'] ? '<p>« ' . nl2br(e($card['message'])) . ' »</p>' : '') . '<p style="font-size:20px;font-family:monospace"><b>' . e($card['code']) . '</b></p><p>À saisir au moment de réserver sur <a href="' . e(abs_url('/o/' . $org['slug'])) . '">' . e(abs_url('/o/' . $org['slug'])) . '</a>.</p>',
                    [['data' => Store::giftPdf($card, $org), 'name' => 'carte-cadeau-' . $card['code'] . '.pdf']], $org['email'] ?? null); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            }
            back(['success' => 'Carte ' . $card['code'] . ' créée.']);
        }
        if ($a === 'cancel') { q("UPDATE gift_cards SET status = 'cancelled' WHERE id = ? AND organizer_id = ?", [(int)input('card_id'), $oid]); back(['success' => 'Carte annulée.']); }
    }
    $q = strtoupper(trim((string)input('q')));
    $cards = all('SELECT c.*, (SELECT COUNT(*) FROM gift_card_uses u WHERE u.gift_card_id = c.id) uses FROM gift_cards c WHERE c.organizer_id = ?' . ($q !== '' ? ' AND (c.code LIKE ? OR c.recipient_email LIKE ? OR c.buyer_email LIKE ?)' : '') . ' ORDER BY c.id DESC LIMIT 300',
        $q !== '' ? [$oid, "%$q%", '%' . strtolower($q) . '%', '%' . strtolower($q) . '%'] : [$oid]);
    $kpi = one("SELECT COUNT(*) n, COALESCE(SUM(initial), 0) sold, COALESCE(SUM(CASE WHEN status = 'active' AND (expires_at IS NULL OR expires_at > NOW()) THEN balance END), 0) open,
        COALESCE(SUM(initial - balance), 0) used FROM gift_cards WHERE organizer_id = ? AND status <> 'cancelled'", [$oid]);
    return pro_page('Cartes cadeaux', 'store/gifts', compact('org', 'cards', 'kpi', 'q'), 'cartes-cadeaux');
}

// ---------- Abonnements & pass ----------
function pro_passes(): string
{
    $org = require_org();
    $oid = (int)$org['id'];
    if (is_post()) {
        $a = (string)input('action');
        $tid = (int)input('type_id');
        if ($a === 'toggle') { q('UPDATE pass_types SET active = 1 - active WHERE id = ? AND organizer_id = ?', [$tid, $oid]); back(); }
        if ($a === 'cancel_pass') { q("UPDATE passes SET status = 'cancelled' WHERE id = ? AND organizer_id = ?", [(int)input('pass_id'), $oid]); back(['success' => 'Abonnement annulé (le remboursement se fait depuis la commande).']); }
        $evIds = array_map('intval', (array)($_POST['event_ids'] ?? []));
        $valid = array_map('intval', array_column(all('SELECT id FROM events WHERE organizer_id = ?', [$oid]), 'id'));
        $evIds = array_values(array_intersect($evIds, $valid));
        $kind = input('kind') === 'carnet' ? 'carnet' : 'season';
        $data = ['name' => mb_substr(trim((string)input('name')), 0, 190), 'description' => mb_substr(trim((string)input('description')), 0, 500) ?: null, 'kind' => $kind,
            'price' => parse_money(input('price')), 'entries' => $kind === 'carnet' ? max(2, min(100, (int)input('entries', 10))) : null, 'event_ids' => $evIds ? implode(',', $evIds) : null,
            'valid_until' => dt_from_input(input('valid_until')), 'quota' => max(0, (int)input('quota')), 'active' => checked('active'), 'sort' => (int)input('sort')];
        if ($data['name'] === '') back(['error' => 'Donne un nom à cet abonnement.']);
        if ($tid) update('pass_types', $data, 'id = ? AND organizer_id = ?', [$tid, $oid]);
        else insert('pass_types', $data + ['organizer_id' => $oid, 'created_at' => now()]);
        back(['success' => 'Abonnement enregistré.']);
    }
    $types = all("SELECT t.*, (SELECT COUNT(*) FROM passes p WHERE p.pass_type_id = t.id AND p.status <> 'cancelled') sold,
        (SELECT COALESCE(SUM(o.total), 0) FROM passes p JOIN orders o ON o.id = p.order_id WHERE p.pass_type_id = t.id AND o.status = 'paid') revenue FROM pass_types t WHERE organizer_id = ? ORDER BY sort, id", [$oid]);
    $passes = all("SELECT p.*, t.name type_name, (SELECT COUNT(*) FROM orders o WHERE o.pass_id = p.id AND o.status = 'paid') booked FROM passes p JOIN pass_types t ON t.id = p.pass_type_id WHERE p.organizer_id = ? ORDER BY p.id DESC LIMIT 300", [$oid]);
    $events = all('SELECT id, name, starts_at FROM events WHERE organizer_id = ? AND COALESCE(ends_at, starts_at) >= NOW() ORDER BY starts_at', [$oid]);
    return pro_page('Abonnements & pass', 'store/passes', compact('org', 'types', 'passes', 'events'), 'abonnements');
}

// ---------- Boutique ----------
function pro_shop(): string
{
    $org = require_org();
    $oid = (int)$org['id'];
    if (is_post()) {
        $a = (string)input('action');
        $pid = (int)input('product_id');
        if ($a === 'settings') { update('organizers', ['shop_enabled' => checked('shop_enabled'), 'pickup_info' => mb_substr(trim((string)input('pickup_info')), 0, 500) ?: null], 'id = ?', [$oid]); back(['success' => 'Réglages enregistrés.']); }
        if ($a === 'fulfil') { q('UPDATE orders SET fulfilled_at = IF(fulfilled_at IS NULL, NOW(), NULL) WHERE id = ? AND organizer_id = ? AND event_id = 0', [(int)input('order_id'), $oid]); back(); }
        if ($a === 'delete') {
            if (val("SELECT i.id FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.kind = 'shop' AND i.item_ref = ? AND o.status = 'paid' LIMIT 1", [$pid])) back(['error' => 'Ce produit a déjà été vendu : désactive-le plutôt.']);
            q('DELETE FROM shop_products WHERE id = ? AND organizer_id = ?', [$pid, $oid]);
            back(['success' => 'Produit supprimé.']);
        }
        $data = ['name' => mb_substr(trim((string)input('name')), 0, 190), 'description' => mb_substr(trim((string)input('description')), 0, 1000) ?: null, 'price' => parse_money(input('price')),
            'stock' => max(0, (int)input('stock')), 'delivery' => in_array(input('delivery'), ['pickup', 'ship', 'both'], true) ? (string)input('delivery') : 'pickup', 'ship_price' => parse_money(input('ship_price')),
            'variants' => implode(', ', array_slice(array_values(array_unique(array_filter(array_map(fn($v) => mb_substr(trim($v), 0, 30), explode(',', (string)input('variants'))), fn($v) => $v !== ''))), 0, 20)) ?: null,
            'active' => checked('active'), 'sort' => (int)input('sort')];
        if ($data['name'] === '') back(['error' => 'Donne un nom au produit.']);
        try { $img = upload_image('image'); if ($img) $data['image'] = $img; } catch (RuntimeException $e) { back(['error' => $e->getMessage()]); }
        if ($pid) update('shop_products', $data, 'id = ? AND organizer_id = ?', [$pid, $oid]);
        else insert('shop_products', $data + ['organizer_id' => $oid, 'created_at' => now()]);
        back(['success' => 'Produit enregistré.']);
    }
    $products = all("SELECT p.*, (SELECT COALESCE(SUM(i.qty), 0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.kind = 'shop' AND i.item_ref = p.id AND o.status = 'paid') sold FROM shop_products p WHERE organizer_id = ? ORDER BY sort, id", [$oid]);
    $orders = store_orders($oid, 'shop');
    return pro_page('Boutique', 'store/shop', compact('org', 'products', 'orders'), 'boutique');
}
