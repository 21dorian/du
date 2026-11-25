<?php /** @var array $r, $ev, $org, $rates, $sessions, $errors; ?array $sel; string $token; float $pct */ ?>
<section class="store-wrap rs-wrap">
  <a class="back-link" href="<?= url('/revendeur/' . $token) ?>">‹ <?= e($r['name']) ?></a>
  <span class="eyebrow"><?= e($org['name']) ?></span>
  <h1><?= e($ev['name']) ?></h1>
  <p class="lead"><?= e(fr_date($sel['starts_at'] ?? $ev['starts_at'])) ?><?= $ev['venue'] ? ' · ' . e($ev['venue']) : '' ?></p>
  <?php if ($errors): ?><div class="notice error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
  <?php if ($sessions): ?><form class="row mb"><label class="muted">Date</label><select name="session_id" onchange="this.form.submit()"><?php foreach ($sessions as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === (int)$sel['id'] ? 'selected' : '' ?>><?= e(fr_date($s['starts_at'])) ?><?= $s['full'] ? ' · complet' : '' ?></option><?php endforeach; ?></select></form><?php endif; ?>
  <form method="post" class="rs-sell" data-rs><?= csrf_field() ?><?php if ($sel): ?><input type="hidden" name="session_id" value="<?= (int)$sel['id'] ?>"><?php endif; ?>
    <div class="card">
      <h2>Billets</h2>
      <?php foreach ($rates as $x): $left = (int)$x['remaining']; ?>
        <div class="rs-rate<?= $left <= 0 ? ' off' : '' ?>"><span class="grow"><b><?= e($x['name']) ?></b><br><small class="muted"><?= e(money((int)$x['price'])) ?><?= $left <= 0 ? ' · complet' : ($left < 20 ? ' · plus que ' . $left : '') ?></small></span>
          <span class="rs-step"><button type="button" data-step="-1" aria-label="Moins">−</button><input type="number" name="qty[<?= (int)$x['id'] ?>]" data-price="<?= (int)$x['price'] ?>" min="0" max="<?= max(0, min(50, $left)) ?>" value="<?= (int)($_POST['qty'][$x['id']] ?? 0) ?>" <?= $left <= 0 ? 'disabled' : '' ?>><button type="button" data-step="1" aria-label="Plus">+</button></span></div>
      <?php endforeach; ?>
      <?php if (!$rates): ?><p class="muted mb0">Aucun tarif en vente pour le moment.</p><?php endif; ?>
      <?php if (Seating::map($ev)): ?><p class="muted mb0 mt"><small>Placement numéroté : les meilleures places libres (côte à côte si possible) sont attribuées automatiquement et indiquées sur le reçu.</small></p><?php endif; ?>
    </div>
    <div class="card"><h2>Acheteur</h2>
      <div class="grid2">
        <div class="field"><label>Prénom</label><input name="first_name" value="<?= e((string)input('first_name')) ?>"></div>
        <div class="field"><label>Nom</label><input name="last_name" required value="<?= e((string)input('last_name')) ?>"></div>
        <div class="field"><label>E-mail</label><input type="email" name="email" required value="<?= e((string)input('email')) ?>"><small class="muted">Les billets y sont envoyés tout de suite.</small></div>
        <div class="field"><label>Téléphone</label><input name="phone" value="<?= e((string)input('phone')) ?>"></div>
      </div>
    </div>
    <div class="rs-bar"><div><small>À encaisser</small><b data-total>0,00 €</b><small class="muted" data-comm></small></div><button class="btn btn-lg">Valider la vente</button></div>
  </form>
</section>
<script>
(() => {
  const f = document.querySelector('[data-rs]'); if (!f) return;
  const fmt = c => (c / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }), pct = <?= json_encode($pct) ?>;
  const upd = () => { const t = [...f.querySelectorAll('[data-price]')].reduce((s, i) => s + (parseInt(i.value) || 0) * parseInt(i.dataset.price), 0);
    f.querySelector('[data-total]').textContent = fmt(t); f.querySelector('[data-comm]').textContent = t ? 'dont ta commission ' + fmt(Math.round(t * pct / 100)) : ''; };
  f.querySelectorAll('[data-step]').forEach(b => b.addEventListener('click', () => { const i = b.parentNode.querySelector('input'); if (i.disabled) return;
    i.value = Math.max(0, Math.min(parseInt(i.max) || 50, (parseInt(i.value) || 0) + parseInt(b.dataset.step))); upd(); }));
  f.addEventListener('input', upd); upd();
})();
</script>
