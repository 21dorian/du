<?php /** @var array $ev, $bars, $products, $k, $sold, $hours, $refunds, $devices, $pstats; int $focus */ $pos = url('/caisse/'); ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Rechargé</span><b><?= e(money((int)$k['loaded'])) ?></b><small class="muted"><?= e(money((int)$k['online'])) ?> en ligne · <?= e(money((int)$k['onsite'])) ?> sur place</small></div>
  <div class="kpi-mini"><span>Dépensé aux bars</span><b><?= e(money((int)$k['spent'])) ?></b><small class="muted"><?= (int)$k['sales'] ?> ventes · panier <?= e(money((int)$k['basket'])) ?></small></div>
  <div class="kpi-mini"><span>Soldes restants</span><b><?= e(money((int)$k['left'])) ?></b><small class="muted"><?= (int)$k['wallets'] ?> porte-monnaie · <?= e(money((int)$k['refunded'])) ?> remboursés</small></div>
  <div class="kpi-mini"><span>Ventes aux bars</span><b><?= e(money((int)$pstats['total'])) ?></b><small class="muted"><?php foreach (Pos::METHODS as $m => $l): ?><?= e($l) ?> <?= e(money((int)($pstats['methods'][$m]['total'] ?? 0))) ?> · <?php endforeach; ?></small></div>
</div>
<?php if (!(int)$ev['cashless_enabled']): ?><div class="notice info mb">Le cashless est désactivé : active-le ci-dessous, ajoute tes bars et ta carte, puis ouvre la caisse sur les tablettes des bars.</div><?php endif; ?>
<section class="card pos-card" id="caisse">
  <div class="card-head"><div><h2>Application Entrevo Caisse</h2><p class="muted mb0">Une application dédiée pour les bars : cashless (QR du billet ou bracelet NFC), carte bancaire (terminal autonome ou Nepting) et espèces, même sans réseau pour la carte et les espèces.</p></div>
    <a class="btn btn-sm btn-light" href="<?= $pos ?>" target="_blank">Ouvrir l'application</a></div>
  <div class="pos-devs">
    <?php foreach ($devices as $d): $t = Pos::tpe($d); $pairing = $d['pair_code'] && strtotime((string)$d['pair_expires']) > time(); $online = $d['last_seen'] && strtotime($d['last_seen']) > time() - 300; ?>
      <div class="pos-dev<?= $focus === (int)$d['id'] ? ' on' : '' ?>">
        <div class="pos-dev-head"><span class="dot <?= $pairing ? 'wait' : ($online ? 'ok' : '') ?>"></span><div class="grow"><b><?= e($d['name']) ?></b><small class="muted"><?= $d['bar_name'] ? e($d['bar_name']) : 'Bar choisi sur l\'appareil' ?> · <?= $t['mode'] === 'nepting' ? 'Nepting ' . e($t['host']) : 'TPE autonome' ?></small></div>
          <small class="muted"><?= $pairing ? 'à appairer' : ($d['last_seen'] ? 'vue ' . e(ago($d['last_seen'])) : 'jamais connectée') ?> · <?= (int)$d['sales'] ?> vente(s)</small></div>
        <?php if ($pairing): ?><div class="pos-pair"><div class="pos-qr"><?= Qr::svg(Pos::pairUrl($d), 168) ?></div><div><small class="muted">Code d'appairage</small><div class="pos-code"><?= e(chunk_split($d['pair_code'], 3, ' ')) ?></div><small class="muted">Scanne le QR avec l'appareil du bar (ou ouvre <span class="mono"><?= e(abs_url('/caisse/')) ?></span> et saisis le code). Valable jusqu'à <?= e(date('H:i', strtotime($d['pair_expires']))) ?>.</small></div></div><?php endif; ?>
        <details class="pos-set"><summary class="linklike">Réglages, terminal de paiement</summary>
          <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="action" value="device_tpe"><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
            <div class="grid2"><div class="field"><label>Nom</label><input name="name" value="<?= e($d['name']) ?>"></div>
              <div class="field"><label>Bar</label><select name="bar_id"><option value="">Choisi sur l'appareil</option><?php foreach ($bars as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)$d['bar_id'] === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div></div>
            <div class="field"><label>Paiement par carte</label><select name="mode"><option value="manual">Terminal autonome (le serveur confirme sur l'appli)</option><option value="nepting" <?= $t['mode'] === 'nepting' ? 'selected' : '' ?>>Terminal Nepting piloté par l'appli (Android)</option></select></div>
            <div class="grid2" data-show-if="mode=nepting"><div class="field"><label>Adresse IP du terminal</label><input name="host" value="<?= e($t['host']) ?>" placeholder="192.168.1.50"></div><div class="field"><label>Port</label><input name="port" inputmode="numeric" value="<?= (int)$t['port'] ?>"></div>
              <div class="field"><label>Identifiant caisse (12 chiffres)</label><input name="cj" inputmode="numeric" maxlength="12" value="<?= e($t['cj']) ?>"></div><div class="field"><label>Numéro de caisse</label><input name="ca" inputmode="numeric" maxlength="2" value="<?= e($t['ca']) ?>"></div>
              <div class="field"><label>Version du protocole Caisse-AP</label><input name="cz" inputmode="numeric" maxlength="4" value="<?= e($t['cz']) ?>"><small class="muted">0320 pour la version 3.2 ; à confirmer avec ton prestataire.</small></div><div class="field"><label>Délai maximal (secondes)</label><input name="timeout" inputmode="numeric" value="<?= (int)$t['timeout'] ?>"></div>
              <label class="check"><input type="checkbox" name="crlf" value="1" <?= $t['crlf'] ? 'checked' : '' ?>> <span>Terminer la trame par un retour à la ligne</span></label></div>
            <div class="row mt"><button class="btn btn-sm">Enregistrer</button></div>
          </form>
          <div class="row mt"><form method="post"><?= csrf_field() ?><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="device_code">Nouveau code d'appairage</button></form>
            <form method="post" onsubmit="return confirm('Déconnecter cet appareil ?')"><?= csrf_field() ?><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-light danger" name="action" value="device_revoke">Déconnecter</button></form></div>
        </details>
      </div>
    <?php endforeach; ?>
    <form method="post" class="pos-dev pos-add"><?= csrf_field() ?><input type="hidden" name="action" value="device_add"><b>Ajouter un appareil</b><small class="muted">Tablette ou téléphone d'un bar. Un QR code d'appairage s'affiche ensuite.</small>
      <input name="name" placeholder="Caisse bar principal"><select name="bar_id"><option value="">Bar choisi sur l'appareil</option><?php foreach ($bars as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select><button class="btn btn-sm">Créer</button></form>
  </div>
  <?php if ($pstats['bars'] && $pstats['total']): ?><div class="table-wrap mt"><table class="table table-sm"><thead><tr><th>Bar</th><th class="num">Cashless</th><th class="num">Carte</th><th class="num">Espèces</th><th class="num">Ventes</th></tr></thead><tbody>
    <?php foreach ($pstats['bars'] as $b): ?><tr><td><?= e($b['name']) ?></td><td class="num"><?= e(money((int)$b['cashless'])) ?></td><td class="num"><?= e(money((int)$b['card'])) ?></td><td class="num"><?= e(money((int)$b['cash'])) ?></td><td class="num"><?= (int)$b['n'] ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<div class="split-3">
  <div>
    <div class="card table-card"><div class="card-head"><h2>Carte des bars</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Produit</th><th class="num">Prix</th><th class="num">Vendus</th><th></th></tr></thead><tbody>
      <?php foreach ($products as $p): ?><tr style="<?= $p['active'] ? '' : 'opacity:.5' ?>"><td><b><?= e($p['name']) ?></b><br><small class="muted"><?= e($p['category']) ?><?= (int)$p['deposit'] ? ' · consigne ' . e(money((int)$p['deposit'])) : '' ?></small></td><td class="num"><?= e(money((int)$p['price'])) ?></td><td class="num"><?= (int)($sold[$p['name']]['qty'] ?? 0) ?></td>
        <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="product_toggle"><?= $p['active'] ? 'Retirer' : 'Remettre' ?></button></form></td></tr><?php endforeach; ?>
      <?php if (!$products): ?><tr><td colspan="4" class="empty">Ajoute ta carte : bière, soft, sandwich…</td></tr><?php endif; ?>
    </tbody></table></div>
      <form method="post" class="charge-form prod-form"><?= csrf_field() ?><input type="hidden" name="action" value="product">
        <input name="name" placeholder="Produit" required><input name="category" placeholder="Catégorie" value="boisson"><div class="input-suffix"><input name="price" inputmode="decimal" placeholder="Prix" required><span>€</span></div><div class="input-suffix"><input name="deposit" inputmode="decimal" placeholder="Consigne"><span>€</span></div><button class="btn btn-sm">Ajouter</button></form></div>
    <?php if ($hours): ?><div class="card"><h2>Ventes par heure</h2><?= Viz::chart('columns', ['labels' => array_column($hours, 'h'), 'xlabel' => 'Heure', 'series' => [['name' => 'Ventes', 'values' => array_map('intval', array_column($hours, 's'))]]], 'Ventes par heure', 'money', 200) ?></div><?php endif; ?>
    <?php if ($refunds): ?><div class="card table-card"><div class="card-head"><h2>Remboursements demandés</h2><small class="muted">à faire par virement</small></div><div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($refunds as $w): ?><tr><td class="mono"><?= e($w['code']) ?><br><small class="muted"><?= e((string)$w['email']) ?></small></td><td class="mono"><?= e(trim(chunk_split((string)$w['refund_iban'], 4, ' '))) ?></td><td class="num"><b><?= e(money((int)$w['balance'])) ?></b></td>
        <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="wallet_id" value="<?= (int)$w['id'] ?>"><button class="btn btn-sm" name="action" value="refunded" data-confirm="Virement fait ?">Virement fait</button></form></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
  </div>
  <div class="stack">
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2>Réglages</h2>
      <label class="check mb"><input type="checkbox" name="cashless_enabled" value="1" <?= (int)$ev['cashless_enabled'] ? 'checked' : '' ?>> <span>Cashless actif : le QR du billet sert à payer aux bars</span></label>
      <div class="grid2"><div class="field"><label>Recharge minimum</label><div class="input-suffix"><input name="cashless_min_topup" inputmode="decimal" value="<?= e(money_input((int)$ev['cashless_min_topup'])) ?>"><span>€</span></div></div>
        <div class="field"><label>Remboursement du solde jusqu'au</label><input type="date" name="cashless_refund_until" value="<?= e((string)$ev['cashless_refund_until']) ?>"><small>Vide = pas de remboursement.</small></div></div>
      <button class="btn btn-block">Enregistrer</button></form>
    <div class="card"><h2>Bars</h2>
      <?php foreach ($bars as $b): ?><div class="list-item"><span class="grow"><b><?= e($b['name']) ?></b><?= $b['pin'] ? ' <small class="muted">· code</small>' : '' ?><br><small class="muted"><?= e(money((int)$b['sales'])) ?> de ventes</small></span>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="bar_id" value="<?= (int)$b['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="bar_toggle"><?= $b['active'] ? 'Fermer' : 'Ouvrir' ?></button></form></div><?php endforeach; ?>
      <form method="post" class="row mt"><?= csrf_field() ?><input type="hidden" name="action" value="bar"><input name="name" placeholder="Bar principal" required><input name="pin" placeholder="Code (facultatif)" inputmode="numeric" style="max-width:130px"><button class="btn btn-sm">Ajouter</button></form>
      <p class="muted mt mb0" style="font-size:13px">Chaque bar encaisse avec l'application Entrevo Caisse (appareils ci-dessus).</p></div>
    <?php if ($sold): ?><div class="card"><h2>Meilleures ventes</h2><?php foreach (array_slice($sold, 0, 8, true) as $n => $s): ?><div class="list-item"><span class="grow"><?= e($n) ?></span><small class="muted"><?= (int)$s['qty'] ?> · <?= e(money((int)$s['sum'])) ?></small></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
