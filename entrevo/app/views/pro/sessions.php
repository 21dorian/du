<?php $jours = [1 => 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']; ?>
<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>📅 <?= count($sessions) ?> date<?= count($sessions) > 1 ? 's' : '' ?></h2></div>
    <?php if (!$sessions): ?><p class="muted">Pas de date pour l'instant : l'événement a lieu une seule fois, à la date de ses infos. Ajoute des dates pour un spectacle joué plusieurs soirs, des visites, des créneaux… Chaque date a sa propre jauge.</p><?php endif; ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Date</th><th>Libellé</th><th>Remplissage</th><th class="num">Entrés</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($sessions as $x): $cap = (int)$x['capacity'] ?: (int)$ev['capacity']; ?>
        <tr style="<?= $x['active'] ? '' : 'opacity:.5' ?>">
          <td class="nowrap"><b><?= e(fr_date($x['starts_at'])) ?></b><?= $x['ends_at'] ? '<br><small>→ ' . e(date('H:i', strtotime($x['ends_at']))) . '</small>' : '' ?></td>
          <td><?= e($x['label']) ?></td>
          <td style="min-width:150px"><?= (int)$x['sold'] ?><?= $cap ? ' / ' . $cap : '' ?><?php if ($cap): ?><div class="bar"><i style="width:<?= min(100, round($x['sold'] / $cap * 100)) ?>%"></i></div><?php endif; ?></td>
          <td class="num"><?= (int)$x['checked'] ?></td>
          <td class="right nowrap"><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="session_id" value="<?= (int)$x['id'] ?>">
            <button class="btn btn-sm btn-light" name="action" value="toggle"><?= $x['active'] ? 'Désactiver' : 'Activer' ?></button>
            <button class="btn btn-sm btn-light" name="action" value="delete" data-confirm="Supprimer cette date ?">✕</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div>
    <form class="card" method="post"><?= csrf_field() ?>
      <h2>+ Une date</h2>
      <div class="field"><label>Début *</label><input type="datetime-local" name="starts_at" required></div>
      <div class="field"><label>Fin</label><input type="datetime-local" name="ends_at"></div>
      <div class="grid2"><div class="field"><label>Jauge</label><input type="number" name="capacity" min="0" value="<?= (int)$ev['capacity'] ?>"><small>0 = celle de l'événement</small></div>
      <div class="field"><label>Libellé</label><input name="label" placeholder="Matinée, VO…"></div></div>
      <button class="btn btn-block">Ajouter</button>
    </form>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="generate">
      <h2>🔁 Dates récurrentes</h2>
      <div class="grid2"><div class="field"><label>Du</label><input type="date" name="from" required></div><div class="field"><label>Au</label><input type="date" name="to" required></div></div>
      <div class="field"><label>Jours</label><div class="row" style="gap:6px 14px"><?php foreach ($jours as $k => $j): ?><label class="check" style="font-weight:400"><input type="checkbox" name="days[]" value="<?= $k ?>"> <?= $j ?></label><?php endforeach; ?></div></div>
      <div class="field"><label>Heures</label><input name="times" placeholder="14:00, 16:30, 20:30" required></div>
      <div class="grid2"><div class="field"><label>Durée (min)</label><input type="number" name="duration" min="0" value="90"></div><div class="field"><label>Jauge par date</label><input type="number" name="capacity" min="0" value="<?= (int)$ev['capacity'] ?>"></div></div>
      <div class="field"><label>Libellé</label><input name="label"></div>
      <button class="btn btn-block">Générer les dates</button>
    </form>
  </div>
</div>
