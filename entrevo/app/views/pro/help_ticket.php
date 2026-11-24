<?php /** @var array $t, $msgs */ ?>
<p><?= Support::badge($t['status']) ?> <small class="muted"><?= e($t['ref']) ?> · <?= e(Support::CATEGORIES[$t['category']] ?? '') ?> · ouverte le <?= e(fr_date($t['created_at'])) ?></small></p>
<div class="card"><?= view('partials/support_msgs', ['msgs' => $msgs]) ?></div>
<form method="post" class="card"><?= csrf_field() ?>
  <div class="field"><label>Répondre</label><textarea name="message" rows="5"></textarea></div>
  <div class="row"><button class="btn">Envoyer</button><?php if ($t['status'] !== 'solved'): ?><button class="btn btn-ghost" name="action" value="solved" formnovalidate>Marquer comme résolue</button><?php endif; ?></div>
</form>
