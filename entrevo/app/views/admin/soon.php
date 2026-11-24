<div class="soon-hero">
  <div class="soon-copy">
    <span class="eyebrow">Chantier de la plateforme · <span class="soon-pill">En développement</span></span>
    <h2><span class="soon-emoji"><?= feature_icon($f) ?></span> <?= e($f['name']) ?></h2>
    <p class="lead"><?= e($f['pitch']) ?></p>
    <?php if (!empty($f['points'])): ?><ul class="soon-points"><?php foreach ($f['points'] as $pt): ?><li><?= e($pt) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" class="soon-vote"><?= csrf_field() ?>
      <?php if ($prio): ?><div class="flash success mb0">Dans tes priorités : il apparaît en tête de ta feuille de route.</div><button class="link-btn">Retirer des priorités</button>
      <?php else: ?><button class="btn btn-lg">Marquer comme prioritaire</button><?php endif; ?>
      <small class="muted">Cette rubrique est visible dans ton menu pour te rappeler ce qui arrive. Rien n'est encore actif.</small>
    </form>
  </div>
  <div class="soon-preview" aria-hidden="true"><div class="sk-window"><div class="sk-bar"><i></i><i></i><i></i></div><div class="sk-body">
    <div class="sk-row"><span class="sk w40"></span><span class="sk w20 blue"></span></div>
    <div class="sk-kpis"><span class="sk-kpi"><span class="sk w60"></span><span class="sk w40 big"></span></span><span class="sk-kpi"><span class="sk w60"></span><span class="sk w40 big"></span></span><span class="sk-kpi"><span class="sk w60"></span><span class="sk w40 big"></span></span></div>
    <div class="sk-chart"><?php foreach ([55, 40, 70, 62, 80, 66, 92, 75] as $h): ?><i style="height:<?= $h ?>%"></i><?php endforeach; ?></div>
    <div class="sk-row"><span class="sk w60"></span><span class="sk w20"></span></div></div><span class="sk-stamp">En chantier</span></div></div>
</div>
<p class="center"><a href="<?= url('/admin/modules') ?>">← Feuille de route complète</a></p>
