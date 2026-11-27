<?php
declare(strict_types=1);

/* 4.0 : côté public — favoris, suivre un organisateur, covoiturage, centre d'aide, ressources, démo et migration, recommandations. */

require_once APP . '/controllers/public.php';

/** Événements à venir proches de celui-ci (même organisateur, même ville ou même catégorie). */
function recommendations(array $ev, int $n = 4): array
{
    return array_map('event_card_data', all("SELECT e.*, o.name org_name FROM events e JOIN organizers o ON o.id = e.organizer_id
        WHERE e.status = 'published' AND e.password IS NULL AND o.status = 'active' AND e.id <> ? AND e.starts_at >= NOW()
        ORDER BY (e.organizer_id = ?) * 3 + (e.city <> '' AND e.city = ?) * 2 + (e.category <> '' AND e.category = ?) DESC, e.starts_at LIMIT $n", [$ev['id'], $ev['organizer_id'], (string)$ev['city'], (string)$ev['category']]));
}

// ---------- Favoris (gardés dans le navigateur, sans compte) ----------
function favorites(): string
{
    $ids = array_slice(array_filter(array_map('intval', explode(',', (string)input('ids')))), 0, 60);
    $events = $ids ? array_map('event_card_data', all("SELECT e.*, o.name org_name FROM events e JOIN organizers o ON o.id = e.organizer_id WHERE e.id IN (" . implode(',', $ids) . ") AND e.status IN ('published','closed') ORDER BY e.starts_at")) : [];
    return pub('favorites', ['events' => $events, 'asked' => (bool)$ids], 'Mes favoris', ['noindex' => true]);
}

// ---------- Suivre un organisateur ----------
function follow_org(string $slug): never
{
    $org = one("SELECT * FROM organizers WHERE slug = ? AND status = 'active'", [$slug]);
    if (!$org) abort(404);
    $email = strtolower(trim((string)input('email')));
    if (!is_email($email)) back(['error' => 'Indique une adresse e-mail valide.']);
    if (!rate_limit('follow:' . client_ip(), 10, 3600)) back(['error' => 'Trop de demandes, réessaie plus tard.']);
    $tok = bin2hex(random_bytes(16));
    q('INSERT INTO org_followers (organizer_id, email, token, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE unsub_at = NULL', [$org['id'], $email, $tok]);
    $tok = (string)val('SELECT token FROM org_followers WHERE organizer_id = ? AND email = ?', [$org['id'], $email]);
    try { Mailer::send($email, 'Tu suis ' . $org['name'], '<p>C\'est noté : tu recevras un e-mail dès que <b>' . e($org['name']) . '</b> met un nouvel événement en vente.</p><p style="color:#86868b"><a href="' . e(abs_url('/suivre/' . $tok)) . '">Ne plus suivre</a></p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
    back(['success' => 'C\'est noté : tu seras prévenu(e) des prochaines dates de ' . $org['name'] . '.']);
}

function unfollow(string $token): string
{
    $f = one('SELECT f.*, o.name, o.slug FROM org_followers f JOIN organizers o ON o.id = f.organizer_id WHERE f.token = ?', [$token]);
    if (!$f) abort(404);
    if (is_post()) { update('org_followers', ['unsub_at' => input('again') ? null : now()], 'id = ?', [$f['id']]); redirect('/suivre/' . $token); }
    $on = $f['unsub_at'] === null;
    return view('layout_public', ['title' => 'Suivre ' . $f['name'], 'noindex' => true, 'content' => '<form method="post" class="card auth-box center stack">' . csrf_field()
        . '<h1>' . e($f['name']) . '</h1><p class="muted">' . ($on ? 'Tu es prévenu(e) des nouveaux événements.' : 'Tu ne suis plus cet organisateur.') . '</p>'
        . ($on ? '<button class="btn">Ne plus suivre</button>' : '<button class="btn" name="again" value="1">Suivre à nouveau</button>') . '<a class="btn btn-ghost" href="' . url('/o/' . $f['slug']) . '">Voir ses événements</a></form>']);
}

// ---------- Covoiturage ----------
function carpool(string $slug): string
{
    [$ev, $org] = public_event($slug, false);
    if (is_post()) {
        $a = (string)input('action');
        if (!rate_limit('carpool:' . client_ip(), 15, 3600)) back(['error' => 'Trop de demandes, réessaie plus tard.']);
        if ($a === 'add') {
            $email = strtolower(trim((string)input('email'))); $name = mb_substr(trim((string)input('name')), 0, 80); $city = mb_substr(trim((string)input('city')), 0, 120);
            if (!is_email($email) || $name === '' || $city === '') back(['error' => 'Prénom, ville de départ et e-mail sont nécessaires.']);
            $tok = bin2hex(random_bytes(16));
            insert('carpools', ['event_id' => $ev['id'], 'kind' => input('kind') === 'need' ? 'need' : 'offer', 'city' => $city, 'seats' => max(1, min(8, (int)input('seats', 1))), 'depart_at' => mb_substr(trim((string)input('depart_at')), 0, 60) ?: null,
                'name' => $name, 'email' => $email, 'note' => mb_substr(trim((string)input('note')), 0, 500) ?: null, 'token' => $tok, 'created_at' => now()]);
            try { Mailer::send($email, 'Ton annonce de covoiturage · ' . $ev['name'], '<p>Ton annonce est en ligne. Les participants intéressés t\'écrivent via le site : tu reçois leurs messages ici, ton adresse n\'est jamais affichée.</p><p><a href="' . e(abs_url('/covoiturage/retirer/' . $tok)) . '">Retirer mon annonce</a> (quand c\'est complet)</p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            back(['success' => 'Annonce publiée. On t\'a envoyé un lien pour la retirer quand c\'est complet.']);
        }
        if ($a === 'contact') {
            $c = one('SELECT * FROM carpools WHERE id = ? AND event_id = ? AND active = 1', [(int)input('id'), $ev['id']]);
            $email = strtolower(trim((string)input('email'))); $msg = trim((string)input('message'));
            if (!$c || !is_email($email) || mb_strlen($msg) < 5) back(['error' => 'Ton e-mail et un petit message sont nécessaires.']);
            try { Mailer::send($c['email'], 'Covoiturage ' . $ev['name'] . ' : un message de ' . mb_substr(trim((string)input('name')), 0, 60), '<p>Au sujet de ton annonce (' . e($c['city']) . ') :</p><blockquote style="border-left:3px solid #d2d2d7;padding-left:12px">' . nl2br(e(mb_substr($msg, 0, 1500))) . '</blockquote><p>Réponds simplement à cet e-mail, ou écris à <a href="mailto:' . e($email) . '">' . e($email) . '</a>.</p>', [], $email); } catch (Throwable $e) { back(['error' => 'Le message n\'est pas parti, réessaie.']); }
            back(['success' => 'Message envoyé à ' . $c['name'] . '. Sa réponse arrivera dans ta boîte mail.']);
        }
        back();
    }
    $list = all('SELECT id, kind, city, seats, depart_at, name, note, created_at FROM carpools WHERE event_id = ? AND active = 1 ORDER BY kind, city', [$ev['id']]);
    return pub('carpool', compact('ev', 'org', 'list'), 'Covoiturage · ' . $ev['name']);
}

function carpool_remove(string $token): string
{
    $c = one('SELECT c.*, e.slug FROM carpools c JOIN events e ON e.id = c.event_id WHERE c.token = ?', [$token]);
    if (!$c) abort(404);
    if (is_post()) { update('carpools', ['active' => 0], 'id = ?', [$c['id']]); redirect('/e/' . $c['slug'] . '/covoiturage', ['success' => 'Annonce retirée.']); }
    return view('layout_public', ['title' => 'Retirer mon annonce', 'noindex' => true, 'content' => '<form method="post" class="card auth-box center stack">' . csrf_field() . '<h1>Retirer mon annonce</h1><p class="muted">' . e($c['city']) . ' · ' . ($c['kind'] === 'offer' ? 'je propose ' . (int)$c['seats'] . ' place(s)' : 'je cherche une place') . '</p>'
        . ($c['active'] ? '<button class="btn">Retirer</button>' : '<p>Déjà retirée.</p>') . '</form>']);
}

// ---------- Centre d'aide et ressources ----------
const HELP_FAQ = [
    'Mes billets' => [
        ['Je n\'ai pas reçu mes billets', 'Vérifie tes courriers indésirables, puis utilise « Retrouver mes billets » avec l\'adresse e-mail de la commande : on te renvoie un lien immédiatement.'],
        ['Je dois imprimer mon billet ?', 'Non : le QR code sur ton téléphone suffit. Pense à monter la luminosité à l\'entrée.'],
        ['Je ne peux plus y aller', 'Depuis ton billet, tu peux le donner à un ami (nouveau nom, nouveau QR code), le revendre sur la bourse officielle si l\'organisateur l\'a activée, ou changer de date sur un événement à plusieurs dates.'],
        ['Le nom sur le billet est faux', 'Depuis l\'e-mail de confirmation, « Gérer ma commande » : tu peux corriger le nom tant que le billet n\'a pas été scanné (si l\'organisateur l\'autorise).'],
    ],
    'Paiement et remboursement' => [
        ['L\'événement est annulé', 'L\'organisateur te rembourse automatiquement sur le moyen de paiement utilisé, en général sous 5 à 10 jours ouvrés. Tu reçois un e-mail.'],
        ['Puis-je être remboursé si je ne viens pas ?', 'Cela dépend des conditions de l\'organisateur, indiquées sur la page de l\'événement. Tu peux faire une demande depuis ta commande ; sinon la revente ou le don à un ami restent possibles.'],
        ['J\'ai été débité deux fois', 'Une pré-autorisation peut apparaître puis disparaître sous quelques jours. Si les deux débits restent, écris-nous avec la référence de commande.'],
        ['Facture pour mon entreprise', 'Sur la page de ta commande, « Facture » : renseigne la raison sociale et l\'adresse, le PDF est généré tout de suite.'],
    ],
    'Sur place' => [
        ['Cashless et bracelet', 'Recharge ton bracelet ou ton QR code de paiement avant d\'arriver depuis le lien reçu, et récupère le solde non dépensé après l\'événement.'],
        ['Accessibilité', 'Les tarifs PMR incluent un billet accompagnateur gratuit quand l\'organisateur le propose. Les infos d\'accès sont dans « Infos pratiques » sur la page de l\'événement.'],
        ['Objet perdu', 'Utilise la page « Objets trouvés » de l\'événement (lien dans l\'e-mail d\'après-événement) ou contacte l\'organisateur.'],
        ['Covoiturage', 'Sur la page de l\'événement, « Covoiturage » : propose ou trouve un trajet. Les échanges passent par e-mail, ton adresse n\'est jamais affichée.'],
    ],
];
const RESOURCES = [
    ['Lancer sa billetterie en 30 minutes', 'Créer l\'événement, choisir ses tarifs, préparer la page et tester un achat.', ['Un tarif « prévente » limité en nombre crée de l\'urgence.', 'Ajoute une image en 1600 × 900 : c\'est elle qui s\'affiche sur les réseaux.', 'Fais un achat test avec un code promo à 100 %.']],
    ['Remplir sa salle', 'Les leviers qui marchent : prix dynamiques, ambassadeurs, relances et campagnes ciblées.', ['Active les paliers de prix dès l\'ouverture.', 'Donne un lien d\'ambassadeur à chaque artiste et partenaire.', 'Relance les « fidèles » et les « dormants » avec une offre ciblée.']],
    ['Réussir le jour J', 'Contrôle d\'accès, bars cashless, sécurité, bénévoles : la check-list.', ['Teste le scan la veille avec deux téléphones.', 'Prévois une file « problèmes » avec la recherche par nom.', 'Fais valider la jauge par zone par le responsable sécurité.']],
    ['Budget et subventions', 'Suivre son budget prévisionnel, sortir un bilan propre pour les financeurs.', ['Saisis les dépenses au fil de l\'eau.', 'Le bilan PDF reprend fréquentation, publics et recettes.', 'Partage les chiffres en lecture seule avec le financeur.']],
    ['Bénévoles et staff', 'Recruter, planifier les créneaux, pointer les arrivées et remercier.', ['Le formulaire de candidature se partage en un lien.', 'Les badges bénévoles se pointent au QR code.', 'Les crédits staff se chargent sur le bracelet cashless.']],
    ['RGPD et e-mails', 'Ce que tu peux envoyer, à qui, et comment rester dans les clous.', ['Seuls les acheteurs qui ont coché « actus » reçoivent les campagnes.', 'Chaque e-mail contient un lien de désinscription en un clic.', 'L\'import de contacts exige leur consentement préalable.']],
];

function help_center(): string
{
    $q = trim((string)input('q'));
    $faq = HELP_FAQ;
    if ($q !== '') foreach ($faq as $k => $items) { $faq[$k] = array_values(array_filter($items, fn($i) => mb_stripos($i[0] . ' ' . $i[1], $q) !== false)); if (!$faq[$k]) unset($faq[$k]); }
    return pub('help_center', compact('faq', 'q'), 'Centre d\'aide');
}

function resources(): string
{
    return pub('resources', ['items' => RESOURCES], 'Guides & ressources');
}

// ---------- Démo et migration (vitrine organisateurs) ----------
function pro_request(string $kind): string
{
    $kind = $kind === 'migration' ? 'migration' : 'demo';
    if (is_post()) {
        if (!rate_limit('preq:' . client_ip(), 5, 3600)) back(['error' => 'Trop de demandes, réessaie plus tard.']);
        if ((string)input('website') !== '') back(['success' => 'Merci !']); // pot de miel
        $email = strtolower(trim((string)input('email'))); $name = mb_substr(trim((string)input('name')), 0, 120);
        if (!is_email($email) || $name === '') back(['error' => 'Ton nom et ton e-mail sont nécessaires.']);
        $msg = trim((string)input('message'));
        if ($kind === 'demo') $msg = 'Créneau souhaité : ' . mb_substr(trim((string)input('slot')), 0, 80) . "\n" . $msg;
        else $msg = 'Billetterie actuelle : ' . mb_substr(trim((string)input('from')), 0, 80) . ' · volume : ' . mb_substr(trim((string)input('volume')), 0, 40) . "\n" . $msg;
        insert('public_requests', ['kind' => $kind, 'name' => $name, 'email' => $email, 'org_name' => mb_substr(trim((string)input('org_name')), 0, 190) ?: null, 'phone' => mb_substr(trim((string)input('phone')), 0, 40) ?: null, 'message' => mb_substr($msg, 0, 4000), 'created_at' => now()]);
        $to = (string)(setting('contact_email') ?: setting('mail_from'));
        if (is_email($to)) { try { Mailer::send($to, ($kind === 'demo' ? 'Demande de démo' : 'Demande de migration') . ' : ' . $name, '<p><b>' . e($name) . '</b> (' . e($email) . ')' . (input('org_name') ? ' · ' . e((string)input('org_name')) : '') . '</p><p>' . nl2br(e($msg)) . '</p><p><a href="' . e(abs_url('/admin/demandes')) . '">Voir les demandes</a></p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); } }
        redirect('/' . $kind, ['success' => $kind === 'demo' ? 'Merci ! On te recontacte sous 48 h pour caler la démo.' : 'Merci ! On revient vers toi sous 48 h avec un plan de migration.']);
    }
    return pub('pro_request', ['kind' => $kind], $kind === 'demo' ? 'Démo en visio' : 'Migrer vers ' . (string)setting('platform_name', 'Entrevo'));
}

function admin_requests(): string
{
    require_login(['superadmin']);
    if (is_post()) { update('public_requests', ['status' => input('status') === 'done' ? 'done' : 'new'], 'id = ?', [(int)input('id')]); back(); }
    $rows = all('SELECT * FROM public_requests ORDER BY status = \'done\', id DESC LIMIT 300');
    return view('layout_back', ['title' => 'Demandes de démo et de migration', 'area' => 'admin', 'active' => 'demandes', 'content' => view('admin/requests', ['rows' => $rows])]);
}

function demo_request(): string { return pro_request('demo'); }
function migration_request(): string { return pro_request('migration'); }

/** 4.1 : « préviens-moi » avant l'ouverture des ventes. */
function sale_alert(string $slug): never
{
    [$ev] = public_event($slug, false);
    if (!rate_limit('alert:' . client_ip(), 10, 3600)) back(['error' => 'Trop de demandes, réessaie plus tard.']);
    if (!RatesPlus::alertSubscribe($ev, strtolower(trim((string)input('email'))))) back(['error' => 'Indique une adresse e-mail valide.']);
    back(['success' => 'C\'est noté ! Tu recevras un e-mail dès l\'ouverture de la billetterie.']);
}

// ---------- 4.1 : payer à plusieurs ----------
function group_create(string $slug): never
{
    [$ev] = public_event($slug);
    if (!rate_limit('grp:' . client_ip(), 8, 3600)) back(['error' => 'Trop de demandes, réessaie plus tard.']);
    $rate = one('SELECT * FROM rates WHERE id = ? AND event_id = ? AND active = 1 AND hidden = 0', [(int)input('rate_id'), $ev['id']]);
    $session = (int)input('session_id') ? one('SELECT * FROM sessions WHERE id = ? AND event_id = ? AND active = 1', [(int)input('session_id'), $ev['id']]) : null;
    if (!$rate) back(['error' => 'Choisis un tarif.']);
    if (Tickets::hasSessions((int)$ev['id']) && !$session) back(['error' => 'Choisis d\'abord une date.']);
    try { $g = Social::create($ev, $session, $rate, (int)input('qty'), (string)input('name'), strtolower(trim((string)input('email')))); }
    catch (RuntimeException $e) { back(['error' => $e->getMessage()]); }
    $link = abs_url('/e/' . $ev['slug'] . '/bande/' . $g['token']);
    try { Mailer::send($g['email'], 'Ta bande pour ' . $ev['name'], '<p>' . (int)$g['qty'] . ' places « ' . e($g['rate_name']) . ' » sont bloquées pour ta bande jusqu\'au <b>' . e(fr_date($g['expires_at'])) . '</b>.</p><p>Envoie ce lien à tes amis : chacun paie sa place (toi aussi !).</p><p><a href="' . e($link) . '">' . e($link) . '</a></p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
    redirect('/e/' . $ev['slug'] . '/bande/' . $g['token'], ['success' => 'Places bloquées ! Partage le lien à ta bande : chacun paie la sienne.']);
}

function group_page(string $slug, string $token): string
{
    [$ev, $org] = public_event($slug, false);
    $g = Social::group($token);
    if (!$g || (int)$g['event_id'] !== (int)$ev['id']) abort(404, 'Ce groupe n\'existe pas.');
    if (is_post()) {
        if (Social::left($g) < 1) back(['error' => 'Plus de place dans ce groupe.']);
        $cart = cart_get((int)$ev['id']);
        $cart['q'] = [(int)$g['rate_id'] => 1]; $cart['session'] = $g['session_id'] ? (int)$g['session_id'] : null; $cart['group'] = $g['token']; $cart['products'] = []; $cart['seats'] = [];
        cart_set((int)$ev['id'], $cart);
        redirect('/e/' . $ev['slug'] . '/infos');
    }
    $members = Social::members((int)$g['id']);
    $left = Social::left($g);
    $session = $g['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$g['session_id']]) : null;
    return pub('group', compact('ev', 'org', 'g', 'members', 'left', 'session'), 'Ta bande · ' . $ev['name'], ['noindex' => true]);
}

// ---------- 4.1 : billets dans le Wallet ----------
function wallet_ticket(string $ref, string $code, string $kind): array
{
    $o = order_by_ref($ref);
    $t = one("SELECT * FROM tickets WHERE order_id = ? AND code = ? AND status = 'valid'", [$o['id'], $code]);
    if (!$t) abort(404);
    return [$o, $t, one('SELECT * FROM events WHERE id = ?', [$o['event_id']]), one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']])];
}

function wallet_apple(string $ref, string $code): never
{
    if (!Wallet::appleOn()) abort(404);
    [$o, $t, $ev, $org] = wallet_ticket($ref, $code, 'apple');
    try { $pk = Wallet::applePass($t, $o, $ev, $org); } catch (Throwable $e) { log_line('erreurs', 'Wallet Apple : ' . $e->getMessage()); abort(500, 'Le billet Wallet n\'a pas pu être créé.'); }
    header('Content-Type: application/vnd.apple.pkpass'); header('Content-Disposition: attachment; filename="billet-' . $t['code'] . '.pkpass"');
    echo $pk; exit;
}

function wallet_google(string $ref, string $code): never
{
    if (!Wallet::googleOn()) abort(404);
    [$o, $t, $ev, $org] = wallet_ticket($ref, $code, 'google');
    try { $u = Wallet::googleUrl($t, $o, $ev, $org); } catch (Throwable $e) { log_line('erreurs', 'Wallet Google : ' . $e->getMessage()); abort(500, 'Le billet Google Wallet n\'a pas pu être créé.'); }
    header('Location: ' . $u, true, 302); exit;
}
