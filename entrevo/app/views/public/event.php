<?php
$anyOnSale = (bool)array_filter($rates, fn($r) => $r['sale']['ok']);
$useDrawer = !$seatmap && $anyOnSale && empty($cart['partner']);
$hasCodes = (bool)val('SELECT COUNT(*) FROM promos WHERE event_id = ? AND active = 1', [$ev['id']]);
$addr = trim($ev['venue'] . ' ' . $ev['address'] . ' ' . $ev['city']);
$base = '/e/' . $ev['slug'];
$jours = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
$moisC = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$mois = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
// relief : compte à rebours, places restantes, prix d'appel pour la barre mobile
$startAt = $session['starts_at'] ?? ($sessions ? $sessions[0]['starts_at'] : $ev['starts_at']);
$startTs = strtotime((string)$startAt);
$showCountdown = $startTs > time() && $startTs - time() < 90 * 86400;
$capTotal = Tickets::capacity($ev, $session ?? null);
$capLeft = $capTotal > 0 ? max(0, $capTotal - Tickets::eventUsed((int)$ev['id'], isset($session) && $session ? (int)$session['id'] : null)) : null;
$scarce = $capLeft !== null && $capLeft > 0 && $capLeft <= max(10, (int)ceil($capTotal * 0.15));
$onSalePrices = array_map(fn($r) => (int)$r['price'], array_filter($rates, fn($r) => $r['sale']['ok']));
$fromPrice = $onSalePrices ? min($onSalePrices) : null;
?>
<?php if ($ev['status'] === 'draft'): ?><div class="flash warning">👀 Aperçu : cet événement est en brouillon. Toi seul le vois.</div><?php endif; ?>
<div class="event-top reveal">
  <div class="event-poster"><?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt="<?= e($ev['name']) ?>"><?php endif; ?></div>
  <div>
    <span class="eyebrow"><?= e($org['name']) ?><?= $ev['category'] ? ' · ' . e($ev['category']) : '' ?></span>
    <h1 class="event-title"><?= e($ev['name']) ?></h1>
    <div class="event-pills">
      <?php if ($showCountdown): ?><span class="pill pill-dark" data-countdown="<?= e(date('c', $startTs)) ?>">⏳ <span data-cd-text><?= e(ago_until($startTs)) ?></span></span><?php endif; ?>
      <?php if ($scarce): ?><span class="pill pill-hot">🔥 Plus que <?= (int)$capLeft ?> place<?= $capLeft > 1 ? 's' : '' ?></span><?php elseif ($capLeft === 0 && $capTotal > 0): ?><span class="pill">😢 Complet</span><?php endif; ?>
      <?php if ($fromPrice !== null): ?><span class="pill pill-soft"><?= $fromPrice === 0 ? '🎁 Gratuit' : '🎟️ Dès ' . e(money($fromPrice)) ?></span><?php endif; ?>
    </div>
    <dl class="event-facts">
      <div class="fact"><dt>Quand</dt><dd>
        <?php if ($sessions): ?><b><?= count($sessions) ?> date<?= count($sessions) > 1 ? 's' : '' ?></b><span class="muted">du <?= e(fr_date($sessions[0]['starts_at'], false)) ?> au <?= e(fr_date(end($sessions)['starts_at'], false)) ?></span>
        <?php else: ?><b><?= e(fr_date($ev['starts_at'])) ?></b><?php if ($ev['ends_at']): ?><span class="muted">jusqu'au <?= e(fr_date($ev['ends_at'])) ?></span><?php endif; ?><?php endif; ?>
      </dd></div>
      <?php if ($addr !== ''): ?>
      <div class="fact"><dt>Où</dt><dd><b><?= e($ev['venue'] ?: $ev['city']) ?></b><span class="muted"><?= e(trim($ev['address'] . ' ' . $ev['city'])) ?></span><br><a href="https://www.google.com/maps/search/?api=1&amp;query=<?= urlencode($addr) ?>" target="_blank" rel="noopener">📍 Y aller</a></dd></div>
      <?php endif; ?>
      <div class="fact"><dt>Par</dt><dd><a href="<?= url('/o/' . $org['slug']) ?>"><?= e($org['name']) ?></a></dd></div>
    </dl>
    <?php if (!empty($ev['volunteer_recruit'])): ?><a class="vol-cta" href="<?= url('/e/' . $ev['slug'] . '/benevoles') ?>">🙋 On cherche des bénévoles : rejoins l'équipe →</a><?php endif; ?>
    <a class="btn btn-lg" href="#billets" <?= $useDrawer ? 'data-dw-open' : '' ?>><?= $sessions && !$session ? 'Choisir ma date 📅' : 'Je réserve 🎟️' ?></a>
    <div class="ev-actions"><?= pub_soon('favoris', '❤️ Favori') ?><?= pub_soon('agenda', '📅 Agenda') ?><?= pub_soon('inviter-amis', '👥 Inviter des amis') ?><?= pub_soon('covoiturage', '🚗 Covoiturage') ?></div>
  </div>
</div>

<div class="event-layout">
  <div>
    <?php if ($sessions): ?>
      <h2 id="dates">📅 Choisis ta date</h2>
      <div class="sessions">
        <?php $month = null; foreach ($sessions as $x): $t = strtotime($x['starts_at']); $m = date('Y-m', $t); ?>
          <?php if ($m !== $month): $month = $m; ?><div class="sessions-month"><?= $mois[(int)date('n', $t)] ?> <?= date('Y', $t) ?></div><?php endif; ?>
          <a class="session <?= $session && (int)$session['id'] === (int)$x['id'] ? 'on' : '' ?> <?= $x['left'] === 0 ? 'full' : '' ?>" href="<?= url($base, ['seance' => $x['id']]) ?>#billets">
            <span class="session-day"><b><?= date('j', $t) ?></b><?= $moisC[(int)date('n', $t)] ?></span>
            <span class="session-info"><b><?= $jours[(int)date('w', $t)] ?> <?= date('H:i', $t) ?></b><?= $x['label'] ? '<small>' . e($x['label']) . '</small>' : '' ?></span>
            <span class="session-left"><?= $x['left'] === null ? '' : ($x['left'] === 0 ? 'Complet 🫠' : ($x['left'] <= 20 ? '😬 ' . $x['left'] . ' places' : 'Dispo')) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($ev['description']): ?><h2 class="mt">Le programme</h2><div class="prose"><?= e($ev['description']) ?></div><?php endif; ?>
    <?php if ($resales): ?>
      <h2 class="mt">🔁 Bourse aux billets officielle</h2>
      <p class="muted">Des billets revendus par d'autres spectateurs, au prix d'origine (jamais plus cher). Un nouveau QR code à ton nom est émis.</p>
      <div class="resale-list">
        <?php foreach ($resales as $x): ?>
          <div class="resale-item"><span><b><?= e($x['rate_name']) ?></b><?= $x['seat'] ? '<small>' . e(Seating::label($x['seat'], Seating::map($ev))) . '</small>' : '' ?></span><span class="rate-price"><?= e(money((int)$x['price'])) ?></span><a class="btn btn-sm" href="<?= url('/revente/' . $x['id']) ?>">Racheter</a></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <aside class="buy-box" id="billets">
    <?php if ($useDrawer): $onSale = array_values(array_filter($rates, fn($r) => $r['sale']['ok'])); ?>
      <div class="buy-launch" hidden>
        <div class="bl-top"><span class="bl-eyebrow">🎟️ Billetterie ouverte</span><b><?= $fromPrice === 0 ? 'Gratuit' : 'Dès ' . e(money((int)$fromPrice)) ?></b><?php if ($session): ?><small><?= e(fr_date($session['starts_at'])) ?></small><?php endif; ?></div>
        <ul class="bl-rates"><?php foreach (array_slice($onSale, 0, 4) as $r): ?><li><span><?= e($r['name']) ?></span><b><?= $r['price_mode'] === 'free' ? 'Prix libre' : ((int)$r['price'] ? e(money((int)$r['price'])) : 'Gratuit') ?></b></li><?php endforeach; ?>
          <?php if (count($onSale) > 4): ?><li class="muted">+ <?= count($onSale) - 4 ?> autre(s) tarif(s)</li><?php endif; ?></ul>
        <button type="button" class="btn btn-lg btn-block" data-dw-open>Réserver mes billets →</button>
        <p class="secure-note">🔒 Paiement sécurisé · billets par e-mail · <?= $org['fee_mode'] === 'buyer' ? 'frais affichés avant de payer' : 'prix tout compris' ?></p>
      </div>
    <?php endif; ?>
    <?php if ($sessions && !$session): ?>
      <div class="tickets-box"><div class="tickets-box-head"><h2>🎟️ Billets</h2></div><div class="rate"><span class="muted">👈 Choisis d'abord une date dans la liste.</span></div></div>
    <?php else: ?>
    <?php [$fpct, $ffix] = Tickets::feeRates($org); ?>
    <form class="tickets-box" method="post" id="buy" action="<?= url($base) ?>" data-seatmap="<?= $seatmap ? url($base . '/plan') : '' ?>" data-fee-pct="<?= $org['fee_mode'] === 'buyer' ? e((string)$fpct) : '0' ?>" data-fee-fixed="<?= $org['fee_mode'] === 'buyer' ? (int)$ffix : 0 ?>"><?= csrf_field() ?>
      <div class="tickets-box-head"><h2>🎟️ Billets</h2><small><?= $session ? e(fr_date($session['starts_at'])) : ($org['fee_mode'] === 'buyer' ? 'Frais inclus dans le total' : 'Prix tout compris') ?></small></div>
      <?php if ($cart['waitlist']): ?><div class="applied-code"><span>⏳ Place réservée pour toi (liste d'attente)</span></div><?php endif; ?>
      <?php if (!$rates): ?><div class="rate"><span class="muted">Pas encore de billets. Patience… 🍿</span></div><?php endif; ?>
      <?php foreach ($rates as $r):
          $fee = Tickets::unitFee($org, (int)$r['price']);
          $max = $r['sale']['ok'] ? min(50, (int)$r['sale']['remaining'], (int)$r['max_per_order'] ?: 50, (int)$ev['max_per_order'] ?: 50) : 0;
          $pack = max(1, (int)$r['pack_size']); $free = $r['price_mode'] === 'free'; ?>
        <div class="rate <?= $r['sale']['ok'] ? '' : 'off' ?>">
          <div class="rate-info">
            <b><?= e($r['name']) ?></b>
            <?php if ($r['description']): ?><small><?= e($r['description']) ?></small><?php endif; ?>
            <?php if ($pack > 1): ?><small>👥 Pack de <?= $pack ?> billets</small><?php endif; ?>
            <?php if ((int)$r['max_entries'] > 1 || $r['per_day']): ?><small>🔁 <?= $r['per_day'] ? '1 entrée par jour' : (int)$r['max_entries'] . ' entrées' ?></small><?php endif; ?>
            <?php if (!$r['sale']['ok']): ?><span class="rate-tag" style="color:var(--muted)"><?= e($r['sale']['why']) ?></span>
            <?php elseif ($r['sale']['remaining'] <= 10): ?><span class="rate-tag">😬 Plus que <?= (int)$r['sale']['remaining'] ?></span>
            <?php elseif ($r['hidden']): ?><span class="rate-tag" style="color:var(--primary)">🤫 Tarif secret débloqué</span><?php endif; ?>
            <?php if ($free && $max > 0): ?><label class="free-price">Ton prix (min. <?= e(money((int)$r['price'])) ?>) <span class="input-suffix"><input name="price[<?= (int)$r['id'] ?>]" value="<?= e(money_input(max((int)$r['price'], (int)($cart['prices'][$r['id']] ?? $r['price'])))) ?>" inputmode="decimal" data-free="<?= (int)$r['id'] ?>"><span>€</span></span></label><?php endif; ?>
          </div>
          <div class="rate-price"><?= $free ? 'Prix libre' : ((int)$r['price'] === 0 ? 'Gratuit' : e(money((int)$r['price']))) ?><?php if ($fee && !$free): ?><small>+ <?= e(money($fee)) ?> de frais</small><?php endif; ?></div>
          <?php if ($max > 0): ?>
          <div class="qty" data-base="<?= (int)$r['price'] ?>" data-unit="1" data-price="<?= (int)$r['price'] + $fee ?>" data-rate="<?= (int)$r['id'] ?>" data-pack="<?= $pack ?>" data-cat="<?= e((string)$r['seat_category']) ?>" data-name="<?= e($r['name']) ?>" <?= $free ? 'data-freeprice="1"' : '' ?>>
            <button type="button" data-d="-1" aria-label="Moins">−</button>
            <input type="number" name="qty[<?= (int)$r['id'] ?>]" value="<?= (int)($cart['q'][$r['id']] ?? 0) ?>" min="0" max="<?= $max ?>" inputmode="numeric" aria-label="Quantité <?= e($r['name']) ?>">
            <button type="button" data-d="1" aria-label="Plus">+</button>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if ($seatmap && $anyOnSale): ?>
        <div class="seat-zone">
          <div class="row between"><b>💺 Tes places</b><small id="seat-count"></small></div>
          <div id="seat-picked" class="seat-picked muted">Choisis tes billets, puis clique sur les places du plan.</div>
          <button type="button" class="btn btn-ghost btn-block btn-sm" id="seat-open">Ouvrir le plan de salle</button>
          <div id="seat-inputs"></div>
        </div>
      <?php endif; ?>

      <?php if ($products && $anyOnSale): ?>
        <div class="extras-head">✨ Options</div>
        <?php foreach ($products as $p): $pmax = min(20, $p['left'] === PHP_INT_MAX ? 20 : (int)$p['left'], (int)$p['max_per_order'] ?: 20); ?>
          <div class="rate">
            <div class="rate-info"><b><?= e($p['name']) ?></b><?php if ($p['description']): ?><small><?= e($p['description']) ?></small><?php endif; ?><?php if ($pmax <= 0): ?><span class="rate-tag" style="color:var(--muted)">Épuisé</span><?php endif; ?></div>
            <div class="rate-price"><?= e(money((int)$p['price'])) ?></div>
            <?php if ($pmax > 0): ?><div class="qty" data-base="<?= (int)$p['price'] ?>" data-price="<?= (int)$p['price'] ?>"><button type="button" data-d="-1">−</button><input type="number" name="product[<?= (int)$p['id'] ?>]" value="<?= (int)($cart['products'][$p['id']] ?? 0) ?>" min="0" max="<?= $pmax ?>"><button type="button" data-d="1">+</button></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($ev['donation_enabled'] && $anyOnSale): ?>
        <div class="rate"><div class="rate-info"><b>💛 <?= e($ev['donation_label'] ?: 'Un petit don pour soutenir ?') ?></b><small>Facultatif, 100 % pour l'organisateur.</small></div>
          <span class="input-suffix" style="width:110px"><input name="donation" value="<?= $cart['donation'] ? e(money_input((int)$cart['donation'])) : '' ?>" placeholder="0,00" inputmode="decimal" id="donation"><span>€</span></span></div>
      <?php endif; ?>

      <?php if ($promo): ?>
        <div class="applied-code"><span>🎉 Code <?= e($promo['code']) ?> : <?= $promo['kind'] === 'percent' ? '−' . (int)$promo['value'] . ' %' : '−' . e(money((int)$promo['value'])) . ' par billet' ?></span>
        <button class="linklike" name="action" value="remove_code" formnovalidate>retirer</button></div>
      <?php endif; ?>
      <?php if ($anyOnSale): ?>
        <div class="buy-foot">
          <div class="buy-total"><span>Total</span><span id="buy-total">0,00 €</span></div>
          <button class="btn btn-block btn-lg" name="action" value="buy">C'est parti →</button>
          <p class="secure-note">🔒 Paiement sécurisé. Tes billets arrivent direct par e-mail.</p>
          <div class="soon-row center-row"><?= pub_soon('payer-a-plusieurs', '🤝 Payer à plusieurs') ?><?= pub_soon('paiement-mobile', '📱 Apple Pay / Google Pay') ?></div>
        </div>
      <?php endif; ?>
    </form>
    <?php if ($hasCodes && $anyOnSale): ?>
    <form method="post" action="<?= url($base) ?>"><?= csrf_field() ?>
      <details class="code-box"><summary>T'as un code ? 🤫</summary>
        <div class="row" style="margin-top:12px;flex-wrap:nowrap;gap:0"><input type="text" name="code" placeholder="CODE" style="text-transform:uppercase" required aria-label="Code"><button class="btn" name="action" value="code">OK</button></div>
      </details>
    </form>
    <?php endif; ?>
    <?php if ($soldOut && $ev['waitlist_enabled']): ?>
      <form class="card waitlist-box" method="post" action="<?= url($base . '/attente') ?>"><?= csrf_field() ?>
        <h3>⏳ Complet… mais pas fini !</h3>
        <p class="muted" style="font-size:14.5px">Inscris-toi sur la liste d'attente : si une place se libère, la première personne inscrite la reçoit par e-mail et on la lui garde 24 h.</p>
        <div class="grid2"><input name="first_name" placeholder="Prénom"><input name="last_name" placeholder="Nom"></div>
        <div class="row mt" style="flex-wrap:nowrap;gap:0"><input type="email" name="email" placeholder="ton@email.fr" required><select name="qty" style="width:90px"><?php for ($i = 1; $i <= 6; $i++): ?><option value="<?= $i ?>"><?= $i ?> pl.</option><?php endfor; ?></select></div>
        <button class="btn btn-block mt">Je m'inscris</button>
      </form>
    <?php endif; ?>
    <?php endif; ?>
  </aside>
</div>
<?php if ($seatmap): ?>
<div class="seat-modal" id="seat-modal" hidden>
  <div class="seat-modal-in">
    <div class="row between seat-modal-head"><h2 class="mb0">💺 Plan de salle</h2><button type="button" class="btn btn-sm" id="seat-close">Valider mes places</button></div>
    <div class="seat-legend" id="seat-legend"></div>
    <div class="seat-canvas" id="seat-canvas"><p class="muted">Chargement du plan…</p></div>
    <p class="muted" style="font-size:13.5px;margin:8px 0 0" id="seat-hint">Clique sur une place libre pour la prendre, reclique pour la libérer.</p>
  </div>
</div>
<script src="<?= asset('js/seats.js') ?>"></script>
<?php endif; ?>

<?php if (!empty($useDrawer)): $buyerPre = $_SESSION['buyer'] ?? null; ?>
<div class="dw-backdrop" data-dw-close hidden></div>
<aside class="drawer" id="drawer" hidden aria-label="Réserver mes billets" role="dialog" aria-modal="true"
  data-cart="<?= url($base . '/tiroir/panier') ?>" data-order="<?= url($base . '/tiroir/commande') ?>" data-link="<?= url('/acheteur/lien') ?>" data-back="<?= e($base . ($session ? '?seance=' . (int)$session['id'] : '')) ?>"
  data-csrf="<?= e(csrf_token()) ?>" data-buyer="<?= e(json_encode($buyerPre ? array_intersect_key($buyerPre, array_flip(['first_name', 'last_name', 'email', 'phone'])) : null, JSON_UNESCAPED_UNICODE)) ?>">
  <header class="dw-head">
    <div class="dw-ev"><?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt=""><?php endif; ?><div><b><?= e($ev['name']) ?></b><small><?= e(fr_date($session['starts_at'] ?? $ev['starts_at'])) ?></small></div></div>
    <button type="button" class="dw-x" data-dw-close aria-label="Fermer">✕</button>
    <ol class="dw-steps"><li data-step="1">Billets</li><li data-step="2">Toi</li><li data-step="3">Récap</li><li data-step="4">Paiement</li></ol>
  </header>
  <div class="dw-body">
    <section class="dw-panel" data-panel="1"><h3 class="dw-h">Choisis tes billets</h3><div data-slot="rates"></div></section>
    <section class="dw-panel" data-panel="2" hidden>
      <h3 class="dw-h">C'est pour qui ?</h3>
      <form id="dw-buyer" novalidate>
        <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
        <div class="dw-quick" data-quick><span>Déjà venu ? </span><button type="button" class="link-btn" data-quick-open>Préremplis tes infos avec un lien par e-mail</button>
          <div class="dw-quick-form" hidden><input type="email" placeholder="ton@email.fr" data-quick-mail aria-label="E-mail"><button type="button" class="btn btn-sm" data-quick-send>Envoyer le lien</button></div></div>
        <div class="grid2"><div class="field mb0"><label for="dw-fn">Prénom</label><input id="dw-fn" name="first_name" required autocomplete="given-name"></div>
          <div class="field mb0"><label for="dw-ln">Nom</label><input id="dw-ln" name="last_name" required autocomplete="family-name"></div></div>
        <div class="field mb0"><label for="dw-em">E-mail</label><input id="dw-em" type="email" name="email" required autocomplete="email" inputmode="email"><small data-email-hint>Tes billets arriveront ici.</small></div>
        <div class="field mb0"><label for="dw-ph">Téléphone <small class="muted">(facultatif)</small></label><input id="dw-ph" type="tel" name="phone" autocomplete="tel"></div>
        <div data-slot="fields"></div>
        <label class="check dw-remember"><input type="checkbox" data-remember checked> <span>Me reconnaître sur cet appareil la prochaine fois</span></label>
      </form>
    </section>
    <section class="dw-panel" data-panel="3" hidden>
      <div data-slot="options"></div>
      <div class="dw-recap" data-slot="recap"></div>
      <?php if ($hasCodes): ?><div class="dw-code"><input data-code placeholder="Code promo" aria-label="Code promo" autocapitalize="characters"><button type="button" class="btn btn-sm btn-light" data-code-apply>Appliquer</button></div><?php endif; ?>
      <label class="check"><input type="checkbox" name="optin" value="1" form="dw-buyer"> <span>📬 Recevoir les prochains événements de <b><?= e($org['name']) ?></b> (désinscription en un clic)</span></label>
      <?php if (setting('cgv')): ?><label class="check"><input type="checkbox" name="cgv" value="1" form="dw-buyer" data-cgv> <span>J'accepte les <a href="<?= url('/cgv') ?>" target="_blank">conditions générales de vente</a></span></label><?php endif; ?>
    </section>
    <section class="dw-panel" data-panel="4" hidden><h3 class="dw-h">Dernière étape 💳</h3><div data-slot="pay"></div></section>
    <div class="dw-errors" data-errors hidden></div>
  </div>
  <footer class="dw-foot">
    <button type="button" class="dw-back" data-dw-back hidden aria-label="Étape précédente">←</button>
    <div class="dw-total"><small data-count>0 billet</small><b data-total>0,00 €</b></div>
    <button type="button" class="btn btn-lg dw-next" data-dw-next>Continuer →</button>
  </footer>
</aside>
<script src="<?= asset('js/drawer.js') ?>"></script>
<?php endif; ?>

<?php if ($anyOnSale || ($sessions && !$session)): ?>
<div class="buy-sticky" data-buy-sticky hidden>
  <div><b><?= e($ev['name']) ?></b><span><?= $fromPrice === null ? 'Choisis ta date' : ($fromPrice === 0 ? 'Gratuit' : 'Dès ' . e(money($fromPrice))) ?></span></div>
  <a class="btn" href="#billets" <?= !empty($useDrawer) ? 'data-dw-open' : '' ?>><?= $sessions && !$session ? 'Choisir 📅' : 'Je réserve 🎟️' ?></a>
</div>
<?php endif; ?>
