<div class="order-top"><div><div class="order-ref"><?= Kyc::badge($org['kyc_status']) ?></div>
  <div class="muted"><?= e($org['legal_name'] ?: $org['name']) ?> · SIRET <?= e($org['siret'] ?: '—') ?> · IBAN <?= e($org['iban'] ? substr($org['iban'], 0, 4) . '…' . substr($org['iban'], -4) : '—') ?></div></div>
  <a class="btn btn-sm btn-light" href="<?= url('/admin/organisateurs/' . $org['id']) ?>">Fiche organisateur</a></div>
<div class="kyc-grid">
<?php foreach (Kyc::KINDS as $k => [$ic, $label]): $d = $docs[$k] ?? null; ?>
  <div class="card kyc-card <?= $d ? e($d['status']) : '' ?>">
    <div class="card-head"><h2><?= $ic ?> <?= e($label) ?></h2><?= $d ? Kyc::badge($d['status'], true) : '<span class="badge grey">Absent</span>' ?></div>
    <?php if ($d): ?>
      <?php if (str_starts_with((string)$d['mime'], 'image/')): ?><a href="<?= url('/pro/verification/fichier/' . $d['id']) ?>" target="_blank"><img class="kyc-thumb" src="<?= url('/pro/verification/fichier/' . $d['id']) ?>" alt=""></a>
      <?php else: ?><a class="btn btn-sm btn-light" href="<?= url('/pro/verification/fichier/' . $d['id']) ?>" target="_blank">Ouvrir <?= e($d['original_name']) ?></a><?php endif; ?>
      <p class="muted" style="font-size:13px">Déposé <?= e(ago($d['uploaded_at'])) ?> · <?= e(human_size((int)$d['size'])) ?></p>
      <?php if ($d['status'] === 'pending'): ?><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
        <input name="note" placeholder="Motif si refus (ex. : document illisible, expiré…)" maxlength="500">
        <div class="row" style="gap:6px"><button class="btn btn-sm btn-success" name="decision" value="approve">Valider</button><button class="btn btn-sm btn-danger" name="decision" value="reject">Refuser</button></div></form>
      <?php elseif ($d['note']): ?><p class="muted" style="font-size:13px">Motif : <?= e($d['note']) ?></p><?php endif; ?>
    <?php else: ?><p class="muted">Pas encore déposé.</p><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<div class="card"><h2>Historique</h2>
<?php foreach ($history as $h): ?><div class="list-item"><span class="grow"><b><?= e(Kyc::KINDS[$h['kind']][1] ?? $h['kind']) ?></b> · <a href="<?= url('/pro/verification/fichier/' . $h['id']) ?>" target="_blank"><?= e($h['original_name']) ?></a><br><small class="muted"><?= e(short_date($h['uploaded_at'])) ?><?= $h['reviewed_at'] ? ' · revu par ' . e((string)$h['reviewer']) . ' le ' . e(short_date($h['reviewed_at'])) : '' ?><?= $h['note'] ? ' · « ' . e($h['note']) . ' »' : '' ?></small></span><?= Kyc::badge($h['status'], true) ?></div><?php endforeach; ?>
</div>
