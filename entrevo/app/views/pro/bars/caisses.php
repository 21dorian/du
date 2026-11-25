<?php /** @var array $sessions; string $btab, $bbase */ echo view('pro/bars/_nav', compact('btab', 'bbase')); ?>
<p class="muted lead-sm">Chaque caisse s'ouvre avec un fond de caisse et se clôture en comptant les espèces : l'écart avec le montant attendu est calculé automatiquement. Les remises en banque (espèces retirées en cours de soirée) et les apports sont saisis sur la caisse.</p>
<?php foreach ($sessions as $s): $t = $s['t']; $open = !$s['closed_at']; $gap = $s['counted_cash'] !== null ? (int)$s['counted_cash'] - (int)$s['expected_cash'] : null; ?>
  <div class="card z-card<?= $open ? ' open' : '' ?>">
    <div class="z-head"><div class="grow"><b><?= $open ? 'En cours' : 'Z' . (int)$s['z_number'] ?> · <?= e($s['bar']) ?></b> <?= $open ? '<span class="badge green">ouverte</span>' : '' ?><br>
      <small class="muted"><?= e((string)$s['device']) ?> · <?= e(date('d/m H:i', strtotime($s['opened_at']))) ?><?= $s['opener'] ? ' (' . e($s['opener']) . ')' : '' ?><?= $s['closed_at'] ? ' → ' . e(date('d/m H:i', strtotime($s['closed_at']))) . ($s['closer'] ? ' (' . e($s['closer']) . ')' : '') : '' ?></small></div>
      <a class="btn btn-sm btn-light" target="_blank" href="<?= url($bbase . '/caisses', ['z' => $s['id']]) ?>"><?= $open ? 'Ticket X (PDF)' : 'Ticket Z (PDF)' ?></a></div>
    <div class="z-grid">
      <div><span>Cashless</span><b><?= e(money((int)($t['methods']['cashless']['total'] ?? 0))) ?></b></div>
      <div><span>Carte</span><b><?= e(money((int)($t['methods']['card']['total'] ?? 0))) ?></b></div>
      <div><span>Espèces</span><b><?= e(money((int)($t['methods']['cash']['total'] ?? 0))) ?></b></div>
      <div><span>Offert</span><b><?= e(money((int)$t['offered'])) ?></b></div>
      <div><span>Espèces attendues</span><b><?= e(money((int)$t['expected_cash'])) ?></b><small class="muted">fond <?= e(money((int)$t['float'])) ?> · banque −<?= e(money((int)$t['moves']['drop'])) ?></small></div>
      <div class="<?= $gap === null ? '' : ($gap === 0 ? 'ok' : 'ko') ?>"><span>Compté</span><b><?= $s['counted_cash'] !== null ? e(money((int)$s['counted_cash'])) : '—' ?></b><?= $gap !== null ? '<small>écart ' . ($gap > 0 ? '+' : '') . e(money($gap)) . '</small>' : '' ?></div>
    </div>
    <?php if ($s['moves']): ?><details><summary class="linklike">Mouvements d'espèces (<?= count($s['moves']) ?>)</summary><?php foreach ($s['moves'] as $m): ?><div class="list-item"><span class="grow"><?= $m['kind'] === 'drop' ? 'Remise en banque' : 'Apport' ?><?= $m['note'] ? ' · ' . e($m['note']) : '' ?><br><small class="muted"><?= e(date('H:i', strtotime($m['created_at']))) ?> <?= e((string)$m['staff']) ?></small></span><b><?= $m['kind'] === 'drop' ? '−' : '+' ?><?= e(money((int)$m['amount'])) ?></b></div><?php endforeach; ?></details><?php endif; ?>
    <?php if ($open): ?><details class="mt"><summary class="linklike">Clôturer à distance</summary><form method="post" class="row mt"><?= csrf_field() ?><input type="hidden" name="action" value="force_close"><input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>"><div class="input-suffix" style="max-width:200px"><input name="counted" inputmode="decimal" placeholder="Espèces comptées"><span>€</span></div><button class="btn btn-sm" onclick="return confirm('Clôturer cette caisse ?')">Clôturer</button></form></details><?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if (!$sessions): ?><div class="card empty">Aucune caisse ouverte pour l'instant. Sur l'application, « Ouvrir la caisse » demande le fond de caisse.</div><?php endif; ?>
