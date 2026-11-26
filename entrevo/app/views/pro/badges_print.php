<?php /** @var array $ev, $people; string $format */ $label = $format === 'label'; ?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Badges · <?= e($ev['name']) ?></title>
<style>
@page{size:<?= $label ? '90mm 60mm' : 'A4' ?>;margin:<?= $label ? '0' : '10mm' ?>}
*{box-sizing:border-box}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","Helvetica Neue",Arial,sans-serif;color:#1d1d1f;background:#f5f5f7}
.sheet{display:grid;grid-template-columns:<?= $label ? '90mm' : 'repeat(2, 90mm)' ?>;gap:<?= $label ? '0' : '6mm' ?>;justify-content:center;padding:<?= $label ? '0' : '10mm 0' ?>}
.b{width:90mm;height:<?= $label ? '60mm' : '62mm' ?>;background:#fff;border:<?= $label ? '0' : '0.3mm dashed #c7c7cc' ?>;border-radius:<?= $label ? '0' : '3mm' ?>;padding:5mm 6mm;display:grid;grid-template-columns:1fr 24mm;grid-template-rows:auto 1fr auto;gap:1mm 4mm;break-inside:avoid;page-break-inside:avoid<?= $label ? ';page-break-after:always' : '' ?>}
.ev{grid-column:1/3;font-size:8pt;text-transform:uppercase;letter-spacing:.08em;color:#6e6e73;border-bottom:.3mm solid #e5e5ea;padding-bottom:1.5mm}
.n{font-size:18pt;font-weight:800;line-height:1.05;align-self:center}.c{font-size:10pt;color:#3a3a3c;margin-top:1.5mm;font-weight:500}
.q{align-self:center}.q svg{width:24mm;height:24mm;display:block}
.r{grid-column:1/3;background:#1d1d1f;color:#fff;border-radius:1.5mm;padding:1.6mm 3mm;font-size:9pt;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:flex;justify-content:space-between}
.r span{font-weight:400;opacity:.7;font-family:ui-monospace,Menlo,monospace}
.bar{position:sticky;top:0;background:#fff;padding:10px 16px;display:flex;gap:10px;align-items:center;border-bottom:1px solid #e5e5ea;font-size:14px}
.bar button{background:#0071e3;color:#fff;border:0;border-radius:980px;padding:8px 16px;font-size:14px;cursor:pointer}
@media print{.bar{display:none}body{background:#fff}}
</style></head><body>
<div class="bar"><b><?= count($people) ?> badge(s)</b><span style="color:#6e6e73">· <?= e($ev['name']) ?></span><span style="flex:1"></span><button onclick="window.print()">Imprimer</button></div>
<div class="sheet">
<?php foreach ($people as $p): ?><div class="b"><div class="ev"><?= e($ev['name']) ?> · <?= e(fr_date($ev['starts_at'], false)) ?></div>
  <div><div class="n"><?= e($p['name'] ?: 'Invité') ?></div><?php if ($p['company']): ?><div class="c"><?= e($p['company']) ?></div><?php endif; ?></div>
  <div class="q"><?= $p['code'] ? Qr::svg($p['code'], 120) : '' ?></div>
  <div class="r"><?= e(mb_strimwidth((string)$p['role'], 0, 34, '…')) ?><span><?= e($p['code']) ?></span></div></div><?php endforeach; ?>
</div>
<?php if (count($people) === 1): ?><script>setTimeout(function(){window.print()},300)</script><?php endif; ?>
</body></html>
