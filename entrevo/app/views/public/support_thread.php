<?php /** @var array $t, $msgs */ ?>
<section class="store-wrap u-mw760">
  <span class="eyebrow">Demande <?= e($t['ref']) ?></span>
  <h1><?= e($t['subject']) ?></h1>
  <p><?= Support::badge($t['status']) ?> <small class="muted">ouverte le <?= e(fr_date($t['created_at'])) ?></small></p>
  <div class="card"><?= view('partials/support_msgs', ['msgs' => $msgs]) ?></div>
  <form method="post" class="card"><?= csrf_field() ?>
    <div class="field"><label>Répondre</label><textarea name="message" rows="5"></textarea></div>
    <div class="row"><button class="btn">Envoyer</button><?php if ($t['status'] !== 'solved'): ?><button class="btn btn-ghost" name="action" value="solved" formnovalidate>C'est résolu, merci</button><?php endif; ?></div>
  </form>
</section>
