<?php
declare(strict_types=1);

/*
 * 4.1 : widget de vente complet. Tout l'achat se fait dans le widget, sur le site de l'organisateur (date, tarifs, options,
 * code promo, coordonnées, questions, conditions) sans cookie tiers. Seul le paiement s'ouvre dans une fenêtre sécurisée
 * du prestataire ; le widget suit la commande et affiche la confirmation et les billets.
 */

require_once APP . '/controllers/public.php';

function widget_frame_headers(): void
{
    header_remove('X-Frame-Options');
    header('Content-Security-Policy: frame-ancestors *');
}

/** Réglages du widget de l'événement (couleur, texte du bouton, options affichées). */
function widget_settings(array $ev): array
{
    $j = json_decode((string)($ev['widget_json'] ?? ''), true);
    return (is_array($j) ? $j : []) + ['color' => (string)setting('primary_color', '#0071e3'), 'button' => 'Réserver mes billets', 'radius' => 18, 'theme' => 'light', 'show_image' => 1, 'show_desc' => 0, 'products' => 1, 'promo' => 1];
}

function widget_full(string $slug): string
{
    widget_frame_headers();
    [$ev, $org] = public_event($slug, false);
    $ws = widget_settings($ev);
    $sessions = array_values(array_filter(Tickets::sessions((int)$ev['id']), fn($x) => strtotime((string)($x['ends_at'] ?: $x['starts_at'])) >= time()));
    $session = null;
    if ($sessions) { $sid = (int)input('seance') ?: (count($sessions) === 1 ? (int)$sessions[0]['id'] : 0); foreach ($sessions as $x) if ((int)$x['id'] === $sid) $session = $x; }
    foreach ($sessions as &$x) { $cap = Tickets::capacity($ev, $x); $x['left'] = $cap > 0 ? max(0, $cap - Tickets::eventUsed((int)$ev['id'], (int)$x['id'])) : null; }
    unset($x);
    $rates = ($sessions && !$session) ? [] : visible_rates($ev, ['unlocked' => [], 'waitlist' => 0], $session);
    $products = $ws['products'] ? all('SELECT * FROM products WHERE event_id = ? AND active = 1 ORDER BY sort, id', [$ev['id']]) : [];
    $fields = applicable_fields($ev, array_map(fn($r) => (int)$r['id'], $rates));
    [$fpct, $ffix] = Tickets::feeRates($org);
    $seatmap = (bool)Seating::map($ev);
    return view('widget/full', compact('ev', 'org', 'ws', 'sessions', 'session', 'rates', 'products', 'fields', 'fpct', 'ffix', 'seatmap'));
}

/** Calcul du total (code promo, remises de groupe, frais) sans rien créer. */
function widget_quote(string $slug): never
{
    widget_frame_headers();
    [$ev, $org] = public_event($slug, false);
    $cart = wcart_build($ev, $_POST);
    $sum = cart_summary($ev, $org, $cart);
    $promoErr = null;
    if ($cart['promo'] !== '') { $r = Tickets::findPromo((int)$ev['id'], $cart['promo']); if (!empty($r['error'])) $promoErr = $r['error']; }
    json_out(['subtotal' => $sum['subtotal'], 'discount' => $sum['discount'], 'fees' => $sum['fees'], 'donation' => $sum['donation'], 'total' => $sum['total'], 'places' => $sum['places'],
        'promo' => $sum['promo'] ? ($sum['promo']['label'] ?: $sum['promo']['code']) : null, 'promo_error' => $promoErr,
        'lines' => array_map(fn($l) => ['name' => $l['name'], 'qty' => $l['qty'], 'amount' => $l['qty'] * $l['unit_price']], $sum['lines'])]);
}

function wcart_build(array $ev, array $in): array
{
    $cart = cart_get(0);
    fill_cart_from_input($ev, $cart, $in);
    $cart['promo'] = strtoupper(trim((string)($in['promo'] ?? '')));
    if (!empty($in['tarifs']) && !empty($in['k'])) { $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$in['tarifs']))))); if ($ids && hash_equals(filter_sig((int)$ev['id'], $ids), (string)$in['k'])) $cart['unlocked'] = $ids; } // 4.2 : widget filtré
    if ($cart['promo'] !== '') { $r = Tickets::findPromo((int)$ev['id'], $cart['promo']); if (!empty($r['promo']) && $r['promo']['kind'] === 'unlock') $cart['unlocked'] = $r['promo']['rate_list']; }
    return $cart;
}

/** Création de la commande depuis le widget. */
function widget_order(string $slug): never
{
    widget_frame_headers();
    [$ev, $org] = public_event($slug, false);
    if (!rate_limit('wo:' . client_ip(), 30, 600)) json_out(['errors' => ['Trop de tentatives : patiente quelques minutes.']], 429);
    $cart = wcart_build($ev, $_POST);
    if (!$cart['q']) json_out(['errors' => ['Choisis au moins un billet.']], 422);
    $session = !empty($cart['session']) ? one('SELECT * FROM sessions WHERE id = ? AND event_id = ? AND active = 1', [(int)$cart['session'], $ev['id']]) : null;
    $sum = cart_summary($ev, $org, $cart);
    $fields = applicable_fields($ev, array_keys($cart['q']));
    $_SESSION['utm'] = ['source' => 'widget', 'medium' => mb_substr((string)input('host'), 0, 120) ?: 'site', 'campaign' => null];
    $r = place_order($ev, $org, $cart, $session, $sum, array_values(array_filter($fields, fn($f) => !$f['per_ticket'])), array_values(array_filter($fields, fn($f) => $f['per_ticket'])), null, null);
    if ($r['errors']) json_out(['errors' => $r['errors']], 422);
    $o = $r['order'];
    update('orders', ['channel' => 'widget'], 'id = ?', [$o['id']]);
    json_out(['ref' => $o['ref'], 'token' => $o['token'], 'free' => !empty($r['free']), 'total' => (int)$o['total'], 'pay_url' => abs_url($r['url'])]);
}

/** État de la commande (suivi pendant le paiement dans l'autre fenêtre). */
function widget_status(string $ref): never
{
    widget_frame_headers();
    $o = one('SELECT * FROM orders WHERE ref = ?', [$ref]);
    if (!$o || !hash_equals((string)$o['token'], (string)input('t'))) json_out(['error' => 'Commande introuvable.'], 404);
    if ($o['status'] === 'pending' && $o['provider'] && !in_array($o['provider'], ['demo', 'free'], true) && rate_limit('wsv:' . $o['id'], 1, 8)) {
        try { if (Payments::verify($o)) $o = one('SELECT * FROM orders WHERE id = ?', [$o['id']]); } catch (Throwable $e) { log_line('paiements', 'Widget : ' . $e->getMessage()); }
    }
    $tickets = $o['status'] === 'paid' ? all('SELECT code, first_name, last_name, rate_name, seat FROM tickets WHERE order_id = ? AND status = \'valid\' ORDER BY id', [$o['id']]) : [];
    json_out(['status' => $o['status'], 'email' => $o['email'], 'total' => (int)$o['total'], 'tickets' => $tickets,
        'order_url' => abs_url('/commande/' . $o['ref'], ['t' => $o['token']]), 'pdf_url' => abs_url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token']])]);
}

/** Script à coller sur le site : widget intégré (hauteur automatique) ou bouton qui ouvre la billetterie par-dessus la page. */
function widget_script(): never
{
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    $base = json_encode(abs_url('/widget/achat/'));
    echo <<<JS
(function(){
  var BASE = $base;
  function frame(slug, extra){ var f = document.createElement('iframe'); f.src = BASE + encodeURIComponent(slug) + '?host=' + encodeURIComponent(location.hostname) + (extra || ''); f.title = 'Billetterie';
    f.setAttribute('allow', 'payment'); f.style.cssText = 'width:100%;border:0;display:block;min-height:420px;background:transparent'; return f; }
  window.addEventListener('message', function(e){ if (!e.data || e.data.entrevo !== 'height') return;
    document.querySelectorAll('iframe[data-entrevo]').forEach(function(f){ if (f.contentWindow === e.source) f.style.height = e.data.h + 'px'; }); });
  function overlay(slug){ var o = document.createElement('div'); o.style.cssText = 'position:fixed;inset:0;z-index:2147483000;background:rgba(10,10,15,.55);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:16px';
    var box = document.createElement('div'); box.style.cssText = 'width:100%;max-width:520px;max-height:92vh;overflow:auto;background:#fff;border-radius:22px;box-shadow:0 30px 90px rgba(0,0,0,.35);position:relative';
    var x = document.createElement('button'); x.type = 'button'; x.setAttribute('aria-label', 'Fermer'); x.textContent = '×'; x.style.cssText = 'position:absolute;top:10px;right:12px;z-index:2;border:0;background:#f2f2f5;width:34px;height:34px;border-radius:50%;font-size:22px;cursor:pointer';
    var f = frame(slug, '&mode=popup'); f.setAttribute('data-entrevo', ''); box.appendChild(x); box.appendChild(f); o.appendChild(box); document.body.appendChild(o);
    var close = function(){ o.remove(); document.removeEventListener('keydown', esc); }; var esc = function(e){ if (e.key === 'Escape') close(); };
    x.onclick = close; o.addEventListener('click', function(e){ if (e.target === o) close(); }); document.addEventListener('keydown', esc); }
  function init(){ document.querySelectorAll('[data-entrevo-event]').forEach(function(el){ if (el.dataset.entrevoReady) return; el.dataset.entrevoReady = 1; var slug = el.getAttribute('data-entrevo-event');
      if ((el.getAttribute('data-mode') || 'inline') === 'button') { var b = document.createElement('button'); b.type = 'button'; b.textContent = el.getAttribute('data-label') || 'Réserver mes billets';
        var c = el.getAttribute('data-color') || '#0071e3'; b.style.cssText = 'background:' + c + ';color:#fff;border:0;border-radius:999px;padding:14px 26px;font:600 16px -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;cursor:pointer;box-shadow:0 8px 24px ' + c + '55';
        b.onclick = function(){ overlay(slug); }; el.appendChild(b); }
      else { var f = frame(slug); f.setAttribute('data-entrevo', ''); el.appendChild(f); } }); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
JS;
    exit;
}

/** Page organisateur : réglages, aperçu et code du widget. */
function widget_admin(string $id): string
{
    require_once APP . '/controllers/pro.php';
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $ws = ['color' => preg_match('~^#[0-9a-f]{6}$~i', (string)input('color')) ? (string)input('color') : '#0071e3', 'button' => mb_substr(trim((string)input('button')), 0, 40) ?: 'Réserver mes billets',
            'radius' => max(0, min(28, (int)input('radius'))), 'theme' => input('theme') === 'dark' ? 'dark' : 'light', 'show_image' => checked('show_image'), 'show_desc' => checked('show_desc'), 'products' => checked('products'), 'promo' => checked('promo')];
        update('events', ['widget_json' => json_encode($ws, JSON_UNESCAPED_UNICODE)], 'id = ?', [$ev['id']]);
        redirect('/pro/evenements/' . $ev['id'] . '/widget', ['success' => 'Widget enregistré : les changements s\'appliquent tout de suite sur ton site.']);
    }
    $ws = widget_settings($ev);
    $stats = one("SELECT COUNT(*) n, COALESCE(SUM(total), 0) t FROM orders WHERE event_id = ? AND status = 'paid' AND channel = 'widget'", [$ev['id']]);
    $hosts = all("SELECT utm_medium host, COUNT(*) n, SUM(total) t FROM orders WHERE event_id = ? AND status = 'paid' AND channel = 'widget' GROUP BY utm_medium ORDER BY n DESC LIMIT 8", [$ev['id']]);
    return ev_page($ev, 'widget', 'Widget de vente', 'widget', compact('ws', 'stats', 'hosts'));
}
