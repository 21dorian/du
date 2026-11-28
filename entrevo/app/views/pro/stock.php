<?php /** @var array $ev, $grid, $holds, $rates, $sessions */ ?>
<div class="page-head"><div><p class="muted u-mt0">Ce qui est vendu, en cours d'achat, bloqué et encore disponible, tarif par tarif<?= $sessions ? ' et date par date' : '' ?>. Les places bloquées ne sont plus en vente.</p></div></div>
<?php foreach ($grid as $g): $s = $g['session']; ?>
<div class="card table-card"><div class="card-head"><h2><?= $s ? e(fr_date($s['starts_at'])) : 'Stock' ?></h2><span class="grow"></span>
  <small class="muted"><?= $g['cap'] ? 'Jauge ' . (int)$g['used'] . ' / ' . (int)$g['cap'] . ' · reste ' . max(0, (int)$g['cap'] - (int)$g['used']) : 'Pas de jauge globale' ?><?= $g['blocked_all'] ? ' · ' . (int)$g['blocked_all'] . ' bloquée(s)' : '' ?></small></div>
  <div class="table-wrap"><table class="table"><thead><tr><th>Tarif</th><th class="num">Quota</th><th class="num">Vendus</th><th class="num">Paniers en cours</th><th class="num">Bloqués</th><th class="num">Disponibles</th><th>Remplissage</th></tr></thead><tbody>
  <?php foreach ($g['rows'] as $r): $q = (int)$r['rate']['quota']; $p = $q ? min(100, round(($r['sold'] + $r['cart'] + $r['blocked']) / $q * 100)) : null; ?>
    <tr class="<?= $r['rate']['active'] ? '' : 'muted' ?>"><td><b><?= e($r['rate']['name']) ?></b><?= $r['rate']['active'] ? '' : ' <small>(inactif)</small>' ?></td><td class="num"><?= $q ?: '—' ?></td><td class="num"><?= (int)$r['sold'] ?></td><td class="num"><?= (int)$r['cart'] ?: '—' ?></td><td class="num"><?= (int)$r['blocked'] ?: '—' ?></td>
      <td class="num"><b class="<?= $r['left'] === 0 ? 'u-red' : '' ?>"><?= $r['left'] === null ? 'illimité' : (int)$r['left'] ?></b></td>
      <td style="min-width:140px"><?php if ($p !== null): ?><div class="bar-track"><i style="width:<?= $p ?>%"></i></div><small class="muted"><?= $p ?> %</small><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php endforeach; ?>

<div class="grid2 mt">
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="hold">
    <h2>Bloquer des places</h2>
    <p class="muted u-m0">Pour les artistes, partenaires, places techniques ou une option en cours : retirées de la vente jusqu'à ce que tu les libères ou les transformes en invitations.</p>
    <div class="grid2"><div class="field mb0"><label>Pour</label><input name="label" required maxlength="120" placeholder="Artistes, mairie, option groupe…"></div><div class="field mb0"><label>Nombre de places</label><input type="number" name="qty" min="1" max="10000" value="10" required></div></div>
    <div class="grid2"><div class="field mb0"><label>Tarif</label><select name="rate_id"><option value="">Jauge générale (tous tarifs)</option><?php foreach ($rates as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
      <?php if ($sessions): ?><div class="field mb0"><label>Date</label><select name="session_id"><option value="">Toutes les dates</option><?php foreach ($sessions as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e(fr_date($s['starts_at'])) ?></option><?php endforeach; ?></select></div><?php endif; ?></div>
    <div class="field mb0"><label>Note <small class="muted">(facultatif)</small></label><input name="note" maxlength="255"></div>
    <div><button class="btn">Bloquer</button></div>
  </form>
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="limit">
    <h2>Limite par acheteur</h2>
    <p class="muted u-m0">Nombre maximum de billets par adresse e-mail sur tout l'événement, toutes commandes confondues (contre la revente sauvage). Le guichet et les invitations ne sont pas limités.</p>
    <div class="field mb0"><label>Billets maximum par personne</label><input type="number" name="max_per_buyer" min="0" max="1000" value="<?= (int)($ev['max_per_buyer'] ?? 0) ?>"><small>0 = pas de limite</small></div>
    <div><button class="btn">Enregistrer</button></div>
  </form>
</div>

<div class="card table-card mt"><div class="card-head"><h2>Places bloquées</h2></div>
<?php if (!$holds): ?><p class="muted" style="padding:0 20px 20px">Aucune place bloquée.</p><?php else: ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Pour</th><th>Tarif</th><th class="num">Places</th><th>Par</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($holds as $h): ?><tr><td><b><?= e($h['label']) ?></b><?= $h['note'] ? '<br><small class="muted">' . e($h['note']) . '</small>' : '' ?></td><td><?= e($h['rate_name'] ?: 'Jauge générale') ?><?= $h['starts_at'] ? '<br><small class="muted">' . e(fr_date($h['starts_at'])) . '</small>' : '' ?></td><td class="num"><?= (int)$h['qty'] ?></td><td><small><?= e((string)$h['who']) ?><br><?= e(short_date($h['created_at'])) ?></small></td>
  <td><details class="menu"><summary class="btn btn-sm btn-light">Gérer</summary><div class="menu-list" style="min-width:300px;padding:12px">
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="hold_id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="action" value="release">
      <div class="row u-gap8"><input type="number" name="qty" min="1" max="<?= (int)$h['qty'] ?>" value="<?= (int)$h['qty'] ?>" class="u-w80" aria-label="Places à libérer"><button class="btn btn-sm btn-light">Remettre en vente</button></div></form>
    <?php if ($h['rate_id']): ?><form method="post" class="stack u-mt12"><?= csrf_field() ?><input type="hidden" name="hold_id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="action" value="invite">
      <b class="u-fs13">Transformer en invitations</b>
      <input name="name" placeholder="Nom de l'invité" required aria-label="Nom de l'invité"><input type="email" name="email" placeholder="E-mail (pour envoyer les billets)" aria-label="E-mail de l'invité">
      <div class="row u-gap8"><input type="number" name="qty" min="1" max="<?= (int)$h['qty'] ?>" value="1" class="u-w80" aria-label="Nombre d'invitations"><button class="btn btn-sm">Créer</button></div></form><?php endif; ?>
  </div></details></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></div>
