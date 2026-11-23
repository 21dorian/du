<?php $cap = (int)$ev['capacity']; ?>
<?php if ($ev['status'] === 'draft'): ?><div class="flash info">👀 Brouillon : personne ne le voit encore. Vérifie tes <a href="<?= url('/pro/evenements/' . $ev['id'] . '/tarifs') ?>">tarifs</a>, puis clique sur <b>Publier</b> pour ouvrir les vannes.</div><?php endif; ?>
<div class="kpis">
  <div class="kpi"><small class="muted">🎟️ Billets vendus</small><b><?= $stats['sold'] ?><?= $cap ? '<small style="font-size:16px;color:var(--muted)"> / ' . $cap . '</small>' : '' ?></b><?php if ($cap): ?><div class="bar"><i style="width:<?= min(100, round($stats['sold'] / $cap * 100)) ?>%"></i></div><?php endif; ?></div>
  <div class="kpi"><small class="muted">💶 Recettes</small><b><?= e(money($stats['revenue'])) ?></b><div class="sub"><?= $stats['orders'] ?> commandes</div></div>
  <div class="kpi"><small class="muted">💰 Ta part (en ligne)</small><b><?= e(money($stats['org_amount'])) ?></b><div class="sub">commission : <?= e(money($stats['commission'])) ?></div></div>
  <div class="kpi"><small class="muted">✅ Entrées</small><b><?= $stats['checked'] ?></b><div class="bar green"><i style="width:<?= $stats['sold'] ? round($stats['checked'] / $stats['sold'] * 100) : 0 ?>%"></i></div></div>
</div>
<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>Ventes par tarif</h2><a href="<?= url('/pro/evenements/' . $ev['id'] . '/tarifs') ?>">Gérer les tarifs</a></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Tarif</th><th class="num">Prix</th><th>Vendus</th><th class="num">Recettes</th><th class="num">Entrés</th></tr></thead>
      <tbody>
      <?php foreach ($stats['by_rate'] as $r): ?>
        <tr><td><?= e($r['name']) ?></td><td class="num"><?= (int)$r['price'] ? e(money((int)$r['price'])) : 'Gratuit' ?></td>
        <td style="min-width:140px"><?= (int)$r['sold'] ?><?= $r['quota'] ? ' / ' . (int)$r['quota'] : '' ?><?php if ($r['quota']): ?><div class="bar"><i style="width:<?= min(100, round($r['sold'] / $r['quota'] * 100)) ?>%"></i></div><?php endif; ?></td>
        <td class="num"><?= e(money((int)$r['revenue'])) ?></td><td class="num"><?= (int)$r['checked'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$stats['by_rate']): ?><tr><td colspan="5" class="empty">Aucun tarif. <a href="<?= url('/pro/evenements/' . $ev['id'] . '/tarifs') ?>">Ajouter un tarif</a></td></tr><?php endif; ?>
      </tbody>
    </table></div>
    <h3 class="mt">Ventes des 30 derniers jours</h3>
    <?= view('pro/_chart', ['chart' => $chart]) ?>
  </div>
  <div>
    <div class="card">
      <h2>📣 Fais tourner</h2>
      <p class="muted" style="font-size:14px">Le lien de ta billetterie, à coller partout (Insta, site, mails, frigo…) :</p>
      <input readonly value="<?= e(abs_url('/e/' . $ev['slug'])) ?>" onclick="this.select()">
      <div class="center mt"><?= Qr::svg(abs_url('/e/' . $ev['slug']), 150) ?><br><small>À imprimer sur tes affiches 👆</small></div>
      <?php $w = '<iframe src="' . abs_url('/widget/e/' . $ev['slug']) . '" style="width:100%;max-width:480px;min-height:460px;border:0" loading="lazy" title="Billetterie ' . e($ev['name']) . '"></iframe>'; ?>
      <h3 class="mt">🧩 Widget pour ton site</h3>
      <textarea readonly rows="3" onclick="this.select()" class="mono" style="font-size:12px"><?= e($w) ?></textarea>
      <button type="button" class="btn btn-sm btn-light mt" data-copy="<?= e($w) ?>">Copier le code</button>
    </div>
    <div class="card">
      <div class="card-head"><h2>Dernières ventes</h2></div>
      <?php foreach ($recent as $o): ?>
        <a class="list-item" href="<?= url('/pro/commandes/' . $o['id']) ?>" style="color:inherit;text-decoration:none"><span class="grow"><b style="font-size:14px"><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b><br><small><?= e(short_date($o['created_at'])) ?> · <?= e(method_label($o['method'])) ?></small></span><span class="right"><?= e(money((int)$o['total'])) ?><br><?= $o['status'] !== 'paid' ? badge($o['status']) : '' ?></span></a>
      <?php endforeach; ?>
      <?php if (!$recent): ?><p class="muted">Pas encore de vente. Patience, et partage ce lien !</p><?php endif; ?>
    </div>
  </div>
</div>
