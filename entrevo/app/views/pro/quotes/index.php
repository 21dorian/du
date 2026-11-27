<?php /** @var array $org, $list, $k, $events; int $won; string $f */ ?>
<p class="muted lead-sm">CE, écoles, associations : ils demandent un devis depuis la page de ton événement (bouton « Venir en groupe »), tu ajustes les quantités et la remise, ils acceptent et paient en ligne ou par virement. Les billets partent tout seuls.</p>
<div class="page-head-row">
  <div class="kpi-mini<?= (int)$k['req'] ? ' kpi-alert' : '' ?>"><span>Demandes à traiter</span><b><?= (int)$k['req'] ?></b></div>
  <div class="kpi-mini"><span>Devis en cours</span><b><?= (int)$k['open'] ?></b></div>
  <div class="kpi-mini"><span>Devis payés</span><b><?= (int)$k['paid'] ?></b><small class="muted"><?= (int)$k['n'] ? round((int)$k['paid'] / (int)$k['n'] * 100) : 0 ?> % de transformation</small></div>
  <div class="kpi-mini"><span>Chiffre d'affaires</span><b><?= e(money($won)) ?></b></div>
</div>
<div class="split-3">
  <div>
    <nav class="pills mb"><a class="<?= $f === '' ? 'on' : '' ?>" href="<?= url('/pro/devis') ?>">Tous</a><?php foreach (Quotes::STATUS as $kk => [$l]): ?><a class="<?= $f === $kk ? 'on' : '' ?>" href="<?= url('/pro/devis', ['f' => $kk]) ?>"><?= e($l) ?></a><?php endforeach; ?></nav>
    <div class="card table-card"><div class="table-wrap"><table class="table">
      <thead><tr><th>Client</th><th>Événement</th><th class="num">Places</th><th class="num">Montant</th><th>Statut</th></tr></thead><tbody>
      <?php foreach ($list as $q): ?><tr data-href="<?= url('/pro/devis/' . $q['id']) ?>"><td><a href="<?= url('/pro/devis/' . $q['id']) ?>"><b><?= e($q['company'] ?: $q['contact_name']) ?></b></a><br><small class="muted"><?= e($q['ref']) ?> · <?= e(short_date($q['created_at'])) ?></small></td>
        <td><?= e($q['ev_name']) ?></td><td class="num"><?= $q['t']['tickets'] ?: '<span class="muted">' . (int)$q['qty_wanted'] . ' demandées</span>' ?></td><td class="num"><?= $q['t']['total'] ? e(money($q['t']['total'])) : '—' ?></td><td><?= Quotes::badge($q['status']) ?></td></tr><?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="5" class="empty">Aucun devis pour l'instant.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  </div>
  <div class="stack">
    <form class="card" method="post"><?= csrf_field() ?>
      <h2>Nouveau devis</h2><p class="muted u-fs13">Pour un client qui t'a contacté directement.</p>
      <div class="field"><label>Événement</label><select name="event_id" required><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>"><?= e($e2['name']) ?> · <?= e(short_date($e2['starts_at'])) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Structure</label><input name="company"></div>
      <div class="field"><label>Contact</label><input name="contact_name"></div>
      <div class="field"><label>E-mail</label><input type="email" name="email" required></div>
      <button class="btn btn-block">Préparer le devis</button></form>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2>Réglages</h2>
      <label class="check mb"><input type="checkbox" name="quotes_enabled" value="1" <?= (int)$org['quotes_enabled'] ? 'checked' : '' ?>> <span>Bouton « Venir en groupe » sur mes événements</span></label>
      <div class="field"><label>À partir de</label><div class="input-suffix"><input type="number" name="quote_min" min="2" value="<?= (int)setting('quote_min_' . $org['id'], '10') ?>"><span>places</span></div></div>
      <button class="btn btn-light btn-block">Enregistrer</button></form>
  </div>
</div>
