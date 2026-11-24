<?php /** @var array $t, $msgs, $orders, $history, $agents, $orgs; ?array $org, $order */ ?>
<p><?= Support::badge($t['status']) ?> <small class="muted"><?= e($t['ref']) ?> · ouverte le <?= e(fr_date($t['created_at'])) ?><?= $t['first_reply_at'] ? ' · 1re réponse en ' . e(ago_between($t['created_at'], $t['first_reply_at'])) : '' ?></small></p>
<div class="split-3">
  <div>
    <div class="card"><?= view('partials/support_msgs', ['msgs' => $msgs, 'staffView' => true]) ?></div>
    <form method="post" class="card" id="reply"><?= csrf_field() ?>
      <div class="card-head"><h2>Répondre à <?= e((string)($t['name'] ?: $t['email'])) ?></h2>
        <select class="macro-pick" data-target="#reply-body" style="width:auto"><option value="">Réponse enregistrée…</option><?php foreach (Support::macros() as $m): ?><option value="<?= e($m[1]) ?>"><?= e($m[0]) ?></option><?php endforeach; ?></select></div>
      <div class="field"><textarea name="message" id="reply-body" rows="7" placeholder="Ta réponse part par e-mail avec un lien vers la conversation."></textarea></div>
      <div class="row">
        <button class="btn" name="action" value="reply">Envoyer</button>
        <button class="btn btn-light" name="action" value="reply" onclick="this.form.then.value='solved'">Envoyer et résoudre</button>
        <button class="btn btn-ghost" name="action" value="note">Note interne</button>
        <input type="hidden" name="then" value="">
      </div>
    </form>
  </div>
  <div class="stack">
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="meta">
      <h2>Suivi</h2>
      <div class="field"><label>Pris en charge par</label><select name="assigned_to"><option value="">Personne</option><?php foreach ($agents as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$t['assigned_to'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
      <div class="grid2">
        <div class="field"><label>Priorité</label><select name="priority"><?php foreach (Support::PRIORITY as $k => $l): ?><option value="<?= $k ?>" <?= $t['priority'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Thème</label><select name="category"><?php foreach (Support::CATEGORIES as $k => $l): ?><option value="<?= $k ?>" <?= $t['category'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label>Organisateur lié</label><select name="organizer_id"><option value="">Aucun</option><?php foreach ($orgs as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)$t['organizer_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-light btn-block">Enregistrer</button>
      <div class="status-pick mt"><?php foreach (Support::STATUS as $k => [$l]): if ($k === $t['status']) continue; ?><button class="btn btn-sm btn-ghost" form="st-<?= $k ?>"><?= $k === 'solved' ? 'Marquer résolue' : ($k === 'open' ? 'Rouvrir' : 'Mettre en attente') ?></button><?php endforeach; ?></div>
    </form>
    <?php foreach (array_keys(Support::STATUS) as $k): ?><form id="st-<?= $k ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= $k ?>"></form><?php endforeach; ?>
    <div class="card"><h2>Demandeur</h2>
      <p class="mb0"><b><?= e((string)($t['name'] ?: '—')) ?></b><br><small class="muted"><?= e($t['email']) ?></small></p>
      <?php if ($org): ?><p class="mb0 mt"><a href="<?= url('/admin/organisateurs/' . $org['id']) ?>"><?= e($org['name']) ?></a> <small class="muted">organisateur</small></p><?php endif; ?>
      <?php if ($order): ?><p class="mt mb0"><small class="muted">Commande citée</small><br><a class="mono" href="<?= url('/admin/commandes/' . $order['id']) ?>"><?= e($order['ref']) ?></a> · <?= badge($order['status']) ?><br><small class="muted"><?= e((string)$order['ev_name']) ?> · <?= e(money((int)$order['total'])) ?></small></p><?php endif; ?>
    </div>
    <?php if ($orders): ?><div class="card"><h2>Ses commandes</h2><?php foreach ($orders as $o): ?><div class="list-item"><span class="grow"><a class="mono" href="<?= url('/admin/commandes/' . $o['id']) ?>"><?= e($o['ref']) ?></a><br><small class="muted"><?= e((string)$o['ev_name']) ?> · <?= e(short_date($o['created_at'])) ?></small></span><span><?= e(money((int)$o['total'])) ?></span></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($history): ?><div class="card"><h2>Demandes précédentes</h2><?php foreach ($history as $h): ?><div class="list-item"><a class="grow" href="<?= url('/admin/support/' . $h['id']) ?>"><?= e($h['subject']) ?></a><?= Support::badge($h['status']) ?></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
<script>
document.querySelectorAll('.macro-pick').forEach(s => s.addEventListener('change', () => { const t = document.querySelector(s.dataset.target); if (s.value && t) { t.value = (t.value ? t.value + '\n\n' : '') + s.value; t.focus(); } s.value = ''; }));
</script>
