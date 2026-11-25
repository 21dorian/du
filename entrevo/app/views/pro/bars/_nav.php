<?php /** @var string $btab, $bbase */
if (!empty($GLOBALS['pay_ctx'])) return; // 3.0 : dans Entrevo Pay, la navigation est dans la barre latérale
$grp = ''; foreach (BARS_GROUPS as $gk => [$gl, $tabs]) if (in_array($btab, $tabs, true)) $grp = $gk; ?>
<nav class="bars-nav"><?php foreach (BARS_GROUPS as $gk => [$gl, $tabs]): ?><a href="<?= url($bbase . ($gk ? '/' . $gk : '')) ?>" class="<?= $gk === $grp ? 'on' : '' ?>"><?= e($gl) ?></a><?php endforeach; ?>
  <a class="bars-app" href="<?= url('/caisse/') ?>" target="_blank">Ouvrir la caisse ↗</a></nav>
<?php if (count(BARS_GROUPS[$grp][1]) > 1): ?><nav class="bars-sub"><?php foreach (BARS_GROUPS[$grp][1] as $k): ?><a href="<?= url($bbase . ($k ? '/' . $k : '')) ?>" class="<?= $k === $btab ? 'on' : '' ?>"><?= e(BARS_TABS[$k]) ?></a><?php endforeach; ?></nav><?php endif; ?>
