<?php /** @var array $list, $events */ ?>
<p class="muted lead-sm">Un bilan d'activité prêt à joindre à un dossier de subvention, à présenter en assemblée générale ou à envoyer à tes partenaires : les chiffres viennent tout seuls de ta billetterie (fréquentation, publics, tarifs réduits, recettes, budget, bénévolat), tu n'as plus qu'à écrire les textes.</p>
<div class="split">
  <div class="card table-card">
    <div class="card-head"><h2>Mes bilans</h2></div>
    <?php foreach ($list as $r): ?>
      <a class="list-item rp-item" href="<?= url('/pro/bilans/' . $r['id']) ?>"><span class="grow"><b><?= e($r['title']) ?></b><br><small class="muted"><?= (int)$r['n'] ?> événement(s) · modifié <?= e(ago($r['updated_at'])) ?></small></span><span class="btn btn-sm btn-light">Ouvrir</span></a>
    <?php endforeach; ?>
    <?php if (!$list): ?><p class="muted" style="padding:0 24px 20px">Aucun bilan pour l'instant. Crée le premier à droite.</p><?php endif; ?>
  </div>
  <form method="post" class="card"><?= csrf_field() ?>
    <h2>Nouveau bilan</h2>
    <div class="field"><label>Titre</label><input name="title" placeholder="Bilan de la saison 2026"></div>
    <div class="seg seg-full mb" id="rp-mode"><label class="seg-r"><input type="radio" name="mode" value="events" checked><span>Choisir les événements</span></label><label class="seg-r"><input type="radio" name="mode" value="period"><span>Toute une période</span></label></div>
    <div data-show-if="mode=events" class="rp-events">
      <?php foreach ($events as $e): ?><label class="check"><input type="checkbox" name="events[]" value="<?= (int)$e['id'] ?>"> <span><?= e($e['name']) ?> <small class="muted">· <?= e(date('d/m/Y', strtotime($e['starts_at']))) ?></small></span></label><?php endforeach; ?>
      <?php if (!$events): ?><p class="muted">Publie un événement pour pouvoir en faire le bilan.</p><?php endif; ?>
    </div>
    <div data-show-if="mode=period" class="grid2"><div class="field"><label>Du</label><input type="date" name="from" value="<?= date('Y') ?>-01-01"></div><div class="field"><label>Au</label><input type="date" name="to" value="<?= date('Y') ?>-12-31"></div></div>
    <button class="btn mt">Créer le bilan</button>
  </form>
</div>
