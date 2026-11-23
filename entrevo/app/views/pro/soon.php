<?php /** @var array $f, $others */ $c = Features::CATEGORIES[$f['cat']]; ?>
<div class="soon-hero">
  <div class="soon-copy">
    <span class="eyebrow"><?= $c[0] ?> <?= e($c[1]) ?> · <span class="soon-pill">Bientôt<?= !empty($f['eta']) ? ' · ' . e($f['eta']) : '' ?></span></span>
    <h2><span class="soon-emoji"><?= $f['emoji'] ?></span> <?= e($f['name']) ?></h2>
    <p class="lead"><?= e($f['pitch']) ?></p>
    <?php if (!empty($f['points'])): ?><ul class="soon-points"><?php foreach ($f['points'] as $pt): ?><li><?= e($pt) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" class="soon-vote"><?= csrf_field() ?>
      <?php if ($voted): ?>
        <div class="flash success mb0">🙌 Tu as voté pour ce module. On te prévient dès qu'il sort !</div>
        <button class="link-btn" name="action" value="unvote">Retirer mon vote</button>
      <?php else: ?>
        <div class="field mb0"><label for="cm">Ce que tu en attends <small class="muted">(optionnel, ça nous aide vraiment)</small></label><textarea id="cm" name="comment" rows="2" maxlength="500" placeholder="Ex. : je veux pouvoir envoyer un SMS la veille à tous les participants…"></textarea></div>
        <button class="btn btn-lg" name="action" value="vote">👍 Ça m'intéresse, préviens-moi</button>
      <?php endif; ?>
      <small class="muted"><?= $count ? $count . ' organisateur' . ($count > 1 ? 's ont' : ' a') . ' déjà voté' : 'Sois le premier à voter' ?> · les modules les plus demandés passent en premier.</small>
    </form>
  </div>
  <div class="soon-preview" aria-hidden="true">
    <div class="sk-window">
      <div class="sk-bar"><i></i><i></i><i></i></div>
      <div class="sk-body">
        <div class="sk-row"><span class="sk w40"></span><span class="sk w20 blue"></span></div>
        <div class="sk-kpis"><span class="sk-kpi"><span class="sk w60"></span><span class="sk w40 big"></span></span><span class="sk-kpi"><span class="sk w60"></span><span class="sk w40 big"></span></span><span class="sk-kpi"><span class="sk w60"></span><span class="sk w40 big"></span></span></div>
        <div class="sk-chart"><?php foreach ([40, 62, 48, 75, 58, 88, 70, 95] as $h): ?><i style="height:<?= $h ?>%"></i><?php endforeach; ?></div>
        <div class="sk-row"><span class="sk w60"></span><span class="sk w20"></span></div>
        <div class="sk-row"><span class="sk w50"></span><span class="sk w20"></span></div>
      </div>
      <span class="sk-stamp">En chantier 🚧</span>
    </div>
  </div>
</div>
<?php if ($others): ?>
<h2 class="mod-cat">Dans la même veine</h2>
<div class="mod-grid">
  <?php foreach ($others as $k => $o): ?><a class="mod-card is-soon" href="<?= url('/pro/bientot/' . $k) ?>"><span class="mod-ico"><?= $o['emoji'] ?></span><span class="mod-body"><b><?= e($o['name']) ?></b><span><?= e($o['pitch']) ?></span></span><span class="mod-foot"><span class="soon-pill">Bientôt</span><span class="muted">Voir →</span></span></a><?php endforeach; ?>
</div>
<?php endif; ?>
<p class="center mt"><a href="<?= url('/pro/modules') ?>">← Tous les modules</a></p>
