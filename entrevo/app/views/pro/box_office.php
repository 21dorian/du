<form method="post"><?= csrf_field() ?>
<?php if ($errors): ?><div class="flash error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>Billets</h2><a class="btn btn-sm btn-ghost" href="<?= url('/pro/evenements/' . $ev['id'] . '/caisse') ?>">Journal & clôture de caisse</a></div>
    <?php if ($sessions): ?><div class="field"><label>Date</label><select name="session_id" onchange="location.href='?session_id='+this.value"><?php foreach ($sessions as $x): ?><option value="<?= (int)$x['id'] ?>" <?= (int)$sel['id'] === (int)$x['id'] ? 'selected' : '' ?>><?= e(fr_date($x['starts_at'])) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <p class="muted">Vente au guichet (espèces, TPE…) ou invitations gratuites. L'argent du guichet va directement dans ta caisse : aucune commission.</p>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Tarif</th><th class="num">Prix</th><th class="num">Restant</th><th style="width:<?= $seatmap ? 220 : 130 ?>px">Quantité</th></tr></thead>
      <tbody>
      <?php foreach ($rates as $r): ?>
        <tr><td><b><?= e($r['name']) ?></b><?= !$r['active'] ? ' ' . badge('draft') : '' ?></td><td class="num"><?= (int)$r['price'] ? e(money((int)$r['price'])) : 'Gratuit' ?></td>
        <td class="num"><?= $r['remaining'] === PHP_INT_MAX ? '∞' : (int)$r['remaining'] ?></td>
        <td><input type="number" name="qty[<?= (int)$r['id'] ?>]" data-price="<?= (int)$r['price'] ?>" min="0" value="<?= (int)($_POST['qty'][$r['id']] ?? 0) ?>"><?php if ($seatmap): ?><input name="seats[<?= (int)$r['id'] ?>]" placeholder="Places : ORCH-A1, ORCH-A2" style="margin-top:6px;font-size:13px"><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card">
    <h2>Pour qui ?</h2>
    <div class="field"><label>Type</label>
      <label class="check" style="margin-bottom:6px"><input type="radio" name="method" value="guichet" <?= input('method', 'guichet') === 'guichet' ? 'checked' : '' ?>> Vente au guichet</label>
      <label class="check"><input type="radio" name="method" value="invitation" <?= input('method') === 'invitation' ? 'checked' : '' ?>> Invitation (gratuit)</label></div>
    <div class="field" data-show-if="method=guichet"><label>Payé en</label><select name="pay_method"><option value="especes">Espèces</option><option value="cb">Carte (TPE)</option><option value="cheque">Chèque</option><option value="virement">Virement</option><option value="autre">Autre</option></select></div>
    <div class="grid2">
      <div class="field"><label>Prénom</label><input name="first_name" value="<?= e(input('first_name')) ?>"></div>
      <div class="field"><label>Nom *</label><input name="last_name" value="<?= e(input('last_name')) ?>" required></div>
    </div>
    <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= e(input('email')) ?>"><small>Facultatif. Rempli = billets envoyés par e-mail.</small></div>
    <div class="field"><label>Note interne</label><input name="note" value="<?= e(input('note')) ?>" placeholder="Ex. : partenaire, presse, bénévole…"></div>
    <label class="check" style="margin-bottom:8px"><input type="checkbox" name="send" value="1" checked> Envoyer les billets par e-mail</label>
    <label class="check" style="margin-bottom:16px"><input type="checkbox" name="force" value="1"> Dépasser la jauge si besoin (à tes risques)</label>
    <div class="till" data-show-if="method=guichet">
      <div class="till-row"><span>Total à encaisser</span><b data-till-total>0,00 €</b></div>
      <div class="till-row" data-till-cash><span>Reçu en espèces</span><div class="input-suffix" style="max-width:130px"><input inputmode="decimal" data-till-given placeholder="0"><span>€</span></div></div>
      <div class="till-quick" data-till-cash><?php foreach ([5, 10, 20, 50, 100] as $b): ?><button type="button" class="chip" data-till-bill="<?= $b ?>"><?= $b ?> €</button><?php endforeach; ?></div>
      <div class="till-row till-change" data-till-cash><span>À rendre</span><b data-till-change>—</b></div>
    </div>
    <button class="btn btn-block btn-lg">Valider et émettre les billets</button>
<script>
(() => {
  const f = document.querySelector('[data-till-total]')?.closest('form'); if (!f) return;
  const fmt = c => (c / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
  const total = () => [...f.querySelectorAll('[data-price]')].reduce((t, i) => t + (parseInt(i.value) || 0) * parseInt(i.dataset.price), 0);
  const upd = () => {
    const t = total(); f.querySelector('[data-till-total]').textContent = fmt(t);
    const cash = (f.querySelector('[name=pay_method]')?.value || 'especes') === 'especes';
    f.querySelectorAll('[data-till-cash]').forEach(e => e.style.display = cash ? '' : 'none');
    const g = Math.round(parseFloat((f.querySelector('[data-till-given]').value || '').replace(',', '.')) * 100);
    const c = f.querySelector('[data-till-change]');
    c.textContent = isNaN(g) ? '—' : (g < t ? 'manque ' + fmt(t - g) : fmt(g - t)); c.classList.toggle('text-red', !isNaN(g) && g < t);
  };
  f.addEventListener('input', upd); f.addEventListener('change', upd);
  f.querySelectorAll('[data-till-bill]').forEach(b => b.addEventListener('click', () => { const i = f.querySelector('[data-till-given]'); i.value = (parseFloat((i.value || '0').replace(',', '.')) || 0) + parseInt(b.dataset.tillBill); upd(); }));
  upd();
})();
</script>
  </div>
</div>
</form>
