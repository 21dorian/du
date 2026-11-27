<div class="store-head">
  <span class="eyebrow"><?= e($org['name']) ?></span>
  <h1>Offrez une soirée</h1>
  <p class="lead">Une carte cadeau valable sur tous les événements de <?= e($org['name']) ?>, pendant <?= (int)$org['gift_months'] ?> mois. Envoyée par e-mail, prête à imprimer.</p>
</div>
<?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="store-grid"><?= csrf_field() ?>
  <div class="stack">
    <div class="card">
      <h2>Montant</h2>
      <div class="amount-pick">
        <?php foreach ($amounts as $i => $a): ?><label><input type="radio" name="amount" value="<?= $a ?>" <?= $i === 1 || (count($amounts) === 1) ? 'checked' : '' ?>><span><?= e(money($a)) ?></span></label><?php endforeach; ?>
        <label><input type="radio" name="amount" value="libre"><span>Autre</span></label>
      </div>
      <div class="field mt" data-show-if="amount=libre"><label>Montant libre</label><div class="input-suffix u-mw200"><input name="free_amount" inputmode="decimal" placeholder="75"><span>€</span></div></div>
    </div>
    <div class="card">
      <h2>Pour qui ?</h2>
      <div class="seg seg-full"><label class="seg-r"><input type="radio" name="for" value="other" checked><span>Je l'offre</span></label><label class="seg-r"><input type="radio" name="for" value="me"><span>C'est pour moi</span></label></div>
      <div data-show-if="for=other" class="mt">
        <div class="grid2">
          <div class="field"><label>Prénom de la personne</label><input name="recipient_name" maxlength="190"></div>
          <div class="field"><label>Son e-mail</label><input type="email" name="recipient_email"></div>
        </div>
        <div class="field"><label>Un petit mot <small class="muted">(facultatif)</small></label><textarea name="message" rows="3" maxlength="500" placeholder="Joyeux anniversaire ! On y va ensemble ?"></textarea></div>
      </div>
    </div>
    <div class="card"><h2>Toi</h2><?= view('partials/buyer_fields') ?></div>
  </div>
  <aside class="gift-preview">
    <div class="giftcard"><small>Carte cadeau</small><b data-gift-amount><?= e(money($amounts[1] ?? $amounts[0])) ?></b><span><?= e($org['name']) ?></span><code>KDO-····-····</code></div>
    <button class="btn btn-lg btn-block">Continuer vers le paiement</button>
    <p class="muted small-note">Valable <?= (int)$org['gift_months'] ?> mois sur tous les événements, utilisable en plusieurs fois. <a href="<?= url('/o/' . $org['slug'] . '/cartes-cadeaux/solde') ?>">Connaître le solde d'une carte</a></p>
  </aside>
</form>
<script>
(function () { var out = document.querySelector('[data-gift-amount]'), fmt = function (c) { return (c / 100).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) + ' €'; };
  function upd() { var r = document.querySelector('input[name=amount]:checked'); if (!r) return; if (r.value === 'libre') { var v = parseFloat((document.querySelector('[name=free_amount]').value || '0').replace(',', '.')) || 0; out.textContent = fmt(Math.round(v * 100)); } else out.textContent = fmt(+r.value); }
  document.addEventListener('input', upd); document.addEventListener('change', upd); })();
</script>
