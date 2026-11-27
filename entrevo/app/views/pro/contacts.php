<?php /** @var array $dups, $imported, $sources; int $nImported */ ?>
<div class="split">
  <form method="post" enctype="multipart/form-data" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="import"><h2>Importer des contacts</h2>
    <p class="muted mb0"><small>Ta liste d'anciens participants ou d'abonnés (fichier CSV exporté d'Excel, de Weezevent, de Mailchimp…) ou un copier-coller. Une ligne par personne : on repère l'e-mail et le nom tout seuls. Les doublons et les désinscrits sont ignorés.</small></p>
    <div class="field"><label>Fichier CSV</label><input type="file" name="file" accept=".csv,.txt,text/csv"></div>
    <div class="field"><label>Ou colle ta liste</label><textarea name="paste" rows="5" placeholder="marie@exemple.fr;Marie Dupont&#10;Paul Martin, paul@exemple.fr"></textarea></div>
    <div class="field"><label>Origine (pour t'y retrouver)</label><input name="source" placeholder="Ex. : festival 2025, newsletter"></div>
    <label class="check"><input type="checkbox" name="consent" value="1"> Ces personnes ont accepté de recevoir mes e-mails (RGPD)</label>
    <button class="btn">Importer</button></form>
  <div class="card"><h2>Contacts importés</h2><div class="kpi-mini"><span>Dans les campagnes</span><b><?= $nImported ?></b></div>
    <?php foreach ($sources as $s): ?><div class="list-item"><span class="grow"><?= e($s['source'] ?: '—') ?></span><b><?= (int)$s['n'] ?></b></div><?php endforeach; ?>
    <p class="muted"><small>Ils reçoivent les campagnes envoyées aux « inscrits aux actus » (avec le lien de désinscription habituel) : <a href="<?= url('/pro/campagnes') ?>">campagnes e-mail</a>.</small></p></div>
</div>
<div class="card mt stack"><h2>Doublons à fusionner (<?= count($dups) ?>)</h2>
  <p class="muted mb0"><small>Même personne, plusieurs adresses : choisis celle à garder, les commandes des autres adresses y sont rattachées (historique, fidélité et e-mails réunis).</small></p>
  <?php foreach ($dups as $i => $d): ?><form method="post" class="list-item u-block"><?= csrf_field() ?><input type="hidden" name="action" value="merge">
    <div class="row between"><b><?= e($d['name'] ?: $d['emails'][0]) ?></b><small class="muted"><?= e($d['why']) ?></small></div>
    <div class="dup-row mt"><?php foreach ($d['emails'] as $j => $em): ?><label><input type="radio" name="keep" value="<?= e($em) ?>" <?= $j === 0 ? 'checked' : '' ?>><?= e($em) ?> <small class="muted">· <?= (int)$d['counts'][$j] ?> cmd</small><input type="hidden" name="drop[]" value="<?= e($em) ?>"></label><?php endforeach; ?>
      <button class="btn btn-sm" data-confirm="Rattacher toutes les commandes à l'adresse choisie ?">Fusionner</button></div></form><?php endforeach; ?>
  <?php if (!$dups): ?><p class="empty">Aucun doublon détecté.</p><?php endif; ?>
</div>
<?php if ($imported): ?><div class="card table-card mt"><div class="card-head"><h2>Derniers imports</h2></div><div class="table-wrap"><table class="table table-sm"><tbody>
  <?php foreach ($imported as $c): ?><tr><td><?= e($c['email']) ?></td><td><?= e((string)$c['name']) ?></td><td><small class="muted"><?= e((string)$c['source']) ?></small></td><td><small><?= e(short_date($c['created_at'])) ?></small></td><td class="right"><?php if ($c['source'] !== 'site'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-ghost">Retirer</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div><?php endif; ?>
