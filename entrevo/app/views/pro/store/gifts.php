<?php $pub = url('/o/' . $org['slug'] . '/cartes-cadeaux'); ?>
<p class="muted lead-sm">Tes clients offrent des soirées : une carte d'un montant au choix, valable sur tous tes événements. Pas de commission à la vente de la carte : elle est prélevée normalement quand la carte sert à acheter des billets.</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>Cartes émises</span><b><?= (int)$kpi['n'] ?></b></div>
  <div class="kpi-mini"><span>Montant vendu</span><b><?= e(money((int)$kpi['sold'])) ?></b></div>
  <div class="kpi-mini"><span>Déjà utilisé</span><b><?= e(money((int)$kpi['used'])) ?></b></div>
  <div class="kpi-mini"><span>Encore à utiliser</span><b><?= e(money((int)$kpi['open'])) ?></b></div>
</div>
<div class="split-3">
  <div class="card table-card">
    <div class="card-head"><h2>Cartes</h2><form class="inline-form" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Code ou e-mail" style="width:200px"></form></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Code</th><th>Pour</th><th class="num">Montant</th><th class="num">Solde</th><th>Validité</th><th></th></tr></thead><tbody>
    <?php foreach ($cards as $c): $exp = $c['expires_at'] && strtotime($c['expires_at']) < time(); ?>
      <tr style="<?= $c['status'] === 'active' && !$exp ? '' : 'opacity:.55' ?>"><td><b class="mono"><?= e($c['code']) ?></b><br><small class="muted"><?= $c['order_id'] ? 'Vendue' : 'Offerte par toi' ?> · <?= e(short_date($c['created_at'])) ?></small></td>
        <td><?= e((string)($c['recipient_name'] ?: '—')) ?><br><small class="muted"><?= e((string)($c['recipient_email'] ?: $c['buyer_email'])) ?></small></td>
        <td class="num"><?= e(money((int)$c['initial'])) ?></td><td class="num"><b><?= e(money((int)$c['balance'])) ?></b><?= (int)$c['uses'] ? '<br><small class="muted">' . (int)$c['uses'] . ' utilisation(s)</small>' : '' ?></td>
        <td><?= $c['status'] === 'cancelled' ? '<span class="badge red">Annulée</span>' : ($exp ? '<span class="badge grey">Expirée</span>' : ($c['status'] === 'used' ? '<span class="badge grey">Épuisée</span>' : '<small>jusqu\'au ' . e(date('d/m/Y', strtotime((string)$c['expires_at']))) . '</small>')) ?></td>
        <td class="right"><?php if ($c['status'] === 'active'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="card_id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="cancel" data-confirm="Annuler cette carte ? Elle ne pourra plus servir.">Annuler</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$cards): ?><tr><td colspan="6" class="empty">Aucune carte pour l'instant.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <div class="stack">
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2>Vente en ligne</h2>
      <label class="check mb"><input type="checkbox" name="gift_enabled" value="1" <?= $org['gift_enabled'] ? 'checked' : '' ?>> <span>Proposer des cartes cadeaux sur ma page</span></label>
      <div class="field"><label>Montants proposés</label><input name="gift_amounts" value="<?= e((string)($org['gift_amounts'] ?: '20, 50, 100')) ?>"><small>Séparés par des virgules. Un montant libre est toujours possible.</small></div>
      <div class="field"><label>Validité</label><div class="input-suffix u-mw160"><input type="number" name="gift_months" min="1" max="36" value="<?= (int)$org['gift_months'] ?>"><span>mois</span></div></div>
      <button class="btn btn-block">Enregistrer</button>
      <?php if ($org['gift_enabled']): ?><p class="muted mt mb0">Page de vente : <a href="<?= $pub ?>" target="_blank"><?= e(preg_replace('~^https?://~', '', abs_url('/o/' . $org['slug'] . '/cartes-cadeaux'))) ?></a></p><?php endif; ?>
    </form>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <h2>Offrir une carte</h2>
      <p class="muted">Un geste commercial, un lot de concours, un partenaire…</p>
      <div class="field"><label>Montant</label><div class="input-suffix"><input name="amount" inputmode="decimal" placeholder="30"><span>€</span></div></div>
      <div class="field"><label>Pour</label><input name="recipient_name" placeholder="Prénom Nom"></div>
      <div class="field"><label>E-mail <small class="muted">(facultatif)</small></label><input type="email" name="recipient_email"></div>
      <div class="field"><label>Message</label><input name="message" maxlength="500"></div>
      <label class="check mb"><input type="checkbox" name="send" value="1" checked> <span>Lui envoyer par e-mail</span></label>
      <button class="btn btn-block">Créer la carte</button>
    </form>
  </div>
</div>
