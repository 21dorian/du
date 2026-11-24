<?php /** @var array $org, $docs, $history */ $st = $org['kyc_status'] ?: 'none'; ?>
<div class="kyc-hero <?= e($st) ?>">
  <div><span class="eyebrow">Vérification du compte</span>
    <h2><?= $st === 'verified' ? 'Ton compte est vérifié' : ($st === 'pending' ? 'On vérifie tes documents' : ($st === 'rejected' ? 'Un document est à corriger' : 'Encore quelques papiers et c\'est bon')) ?></h2>
    <p><?= $st === 'verified' ? 'Merci ! Tes reversements partent normalement.' : 'La loi nous oblige à vérifier qui reçoit l\'argent des ventes. Trois documents suffisent, ils restent privés et ne sont vus que par l\'équipe ' . e(setting('platform_name')) . '.' ?>
    <?= Kyc::required() && $st !== 'verified' ? '<b>Les reversements sont en attente tant que ce n\'est pas vérifié.</b>' : '' ?></p></div>
  <?= Kyc::badge($st) ?>
</div>
<div class="kyc-grid">
  <?php foreach (Kyc::KINDS as $k => [$ic, $label, $help]): $d = $docs[$k] ?? null; ?>
    <div class="card kyc-card <?= $d ? e($d['status']) : '' ?>">
      <div class="card-head"><h2><?= $ic ?> <?= e($label) ?></h2><?= $d ? Kyc::badge($d['status'], true) : '<span class="badge grey">À déposer</span>' ?></div>
      <p class="muted" style="font-size:14px"><?= e($help) ?></p>
      <?php if ($d): ?><p class="mb0"><a href="<?= url('/pro/verification/fichier/' . $d['id']) ?>" target="_blank"><?= e($d['original_name'] ?: 'document') ?></a> <small class="muted">· <?= e(human_size((int)$d['size'])) ?> · <?= e(short_date($d['uploaded_at'])) ?></small></p>
        <?php if ($d['status'] === 'rejected' && $d['note']): ?><div class="flash error mb0" style="margin-top:8px"><?= e($d['note']) ?></div><?php endif; ?><?php endif; ?>
      <?php if (!$d || $d['status'] !== 'approved'): ?>
      <form method="post" enctype="multipart/form-data" class="kyc-up"><?= csrf_field() ?><input type="hidden" name="kind" value="<?= e($k) ?>">
        <input type="file" name="file" accept=".pdf,image/*" required aria-label="<?= e($label) ?>"><button class="btn btn-sm"><?= $d ? 'Remplacer' : 'Envoyer' ?></button></form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<p class="muted" style="font-size:13px">PDF, JPG ou PNG, 10 Mo maximum. Les fichiers sont stockés hors du site public et ne sont jamais partagés.</p>
