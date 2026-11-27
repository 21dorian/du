<?php $totClicks = array_sum(array_map(fn($l) => (int)$l['clicks'], $links)); $totSales = array_sum(array_map(fn($s) => (int)$s['n'], $stats)); $totRev = array_sum(array_map(fn($s) => (int)$s['revenue'], $stats));
$amb = array_values(array_filter($links, fn($l) => $l['kind'] === 'ambassadeur'));
usort($amb, fn($a, $b) => (int)($stats[$b['id']]['tickets'] ?? 0) <=> (int)($stats[$a['id']]['tickets'] ?? 0)); ?>
<p class="muted lead-sm">Un lien par endroit où tu communiques (story, affiche, newsletter, partenaire) ou par personne qui fait la promo. Tu sais enfin ce qui fait vendre.</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>Clics sur tes liens</span><b><?= number_format($totClicks, 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>Ventes via un lien</span><b><?= $totSales ?></b></div>
  <div class="kpi-mini"><span>Chiffre d'affaires</span><b><?= e(money($totRev)) ?></b></div>
  <div class="kpi-mini"><span>Ventes sans lien</span><b><?= (int)$direct['n'] ?></b></div>
</div>
<div class="split-3">
  <div class="stack">
    <div class="card table-card">
      <div class="card-head"><h2>Tes liens</h2></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Nom</th><th class="num">Clics</th><th class="num">Ventes</th><th class="num">Conversion</th><th class="num">CA</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($links as $l): $s = $stats[$l['id']] ?? ['n' => 0, 'revenue' => 0, 'tickets' => 0]; $url = abs_url('/e/' . $ev['slug'], ['l' => $l['code']]); ?>
          <tr style="<?= $l['active'] ? '' : 'opacity:.5' ?>">
            <td><b><?= e($l['name']) ?></b> <span class="badge <?= $l['kind'] === 'ambassadeur' ? 'violet' : 'grey' ?>"><?= $l['kind'] === 'ambassadeur' ? 'Ambassadeur' : 'Canal' ?></span>
              <?= $l['promo_code'] ? '<span class="badge">Code ' . e($l['promo_code']) . '</span>' : '' ?>
              <div class="link-url"><code><?= e(preg_replace('~^https?://~', '', $url)) ?></code><button type="button" class="btn btn-sm btn-light" data-copy="<?= e($url) ?>">Copier</button>
                <details class="link-qr"><summary class="btn btn-sm btn-light">QR code</summary><div class="link-qr-pop"><?= Qr::svg($url, 180) ?><small>Pour une affiche ou un flyer</small></div></details></div>
              <?= $l['reward'] ? '<small class="muted">Récompense : ' . e($l['reward']) . '</small>' : '' ?></td>
            <td class="num"><?= (int)$l['clicks'] ?></td>
            <td class="num"><?= (int)$s['n'] ?><br><small class="muted"><?= (int)$s['tickets'] ?> billets</small></td>
            <td class="num"><?= $l['clicks'] ? e(number_format($s['n'] / $l['clicks'] * 100, 1, ',', ' ')) . ' %' : '—' ?></td>
            <td class="num"><b><?= e(money((int)$s['revenue'])) ?></b></td>
            <td class="right nowrap"><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="link_id" value="<?= (int)$l['id'] ?>">
              <?php if ($l['kind'] === 'ambassadeur'): ?><a class="btn btn-sm btn-light" href="<?= url('/ambassadeur/' . $l['token']) ?>" target="_blank">Sa page</a><?php endif; ?>
              <button class="btn btn-sm btn-light" name="action" value="toggle"><?= $l['active'] ? 'Désactiver' : 'Activer' ?></button>
              <?php if (!$s['n']): ?><button class="btn btn-sm btn-light" name="action" value="delete" data-confirm="Supprimer ce lien ?">Supprimer</button><?php endif; ?></form></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$links): ?><tr><td colspan="6" class="empty">Aucun lien pour l'instant. Crée le premier à droite.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
    <?php if ($amb): ?>
    <div class="card">
      <div class="card-head"><h2>Classement des ambassadeurs</h2></div>
      <ol class="leaderboard"><?php foreach ($amb as $i => $a): $s = $stats[$a['id']] ?? ['tickets' => 0, 'revenue' => 0]; ?>
        <li><span class="lb-rank"><?= $i + 1 ?></span><span class="lb-name"><?= e($a['name']) ?></span><span class="lb-val"><?= (int)$s['tickets'] ?> billets · <?= e(money((int)$s['revenue'])) ?></span></li>
      <?php endforeach; ?></ol>
    </div>
    <?php endif; ?>
  </div>
  <form class="card" method="post"><?= csrf_field() ?>
    <h2>Nouveau lien</h2>
    <div class="field"><label>Type</label><select name="kind"><option value="canal">Canal (story, affiche, newsletter…)</option><option value="ambassadeur">Ambassadeur (une personne)</option></select></div>
    <div class="field"><label>Nom</label><input name="name" required maxlength="120" placeholder="Ex. : Story Instagram, Affiche Bar le Rex, Lucie"></div>
    <div class="field"><label>Code du lien <small class="muted">(facultatif)</small></label><input name="code" maxlength="16" placeholder="Ex. : INSTA" class="u-upper"><small>Il apparaît dans l'adresse : …?l=INSTA</small></div>
    <div class="field"><label>Code promo appliqué automatiquement</label><select name="promo_code"><option value="">Aucun</option><?php foreach ($promos as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
    <div data-show-if="kind=ambassadeur">
      <div class="field"><label>E-mail de l'ambassadeur</label><input type="email" name="email" placeholder="lucie@exemple.fr"></div>
      <div class="field"><label>Récompense promise</label><input name="reward" maxlength="190" placeholder="Ex. : 1 place offerte tous les 5 billets vendus"></div>
      <label class="check mb"><input type="checkbox" name="notify" value="1" checked> <span>Lui envoyer son lien et sa page de résultats</span></label>
    </div>
    <button class="btn btn-block">Créer le lien</button>
  </form>
</div>
