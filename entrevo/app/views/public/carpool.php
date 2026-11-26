<?php /** @var array $ev, $org, $list */ $offers = array_filter($list, fn($c) => $c['kind'] === 'offer'); $needs = array_filter($list, fn($c) => $c['kind'] === 'need'); ?>
<div class="container" style="max-width:920px;padding:28px 16px">
  <a class="muted" href="<?= url('/e/' . $ev['slug']) ?>">← <?= e($ev['name']) ?></a>
  <h1 style="margin-top:6px">Covoiturage</h1>
  <p class="muted"><?= e(fr_date($ev['starts_at'])) ?><?= $ev['venue'] ? ' · ' . e($ev['venue']) : '' ?><?= $ev['city'] ? ', ' . e($ev['city']) : '' ?>. Moins de voitures, plus de place sur le parking. Les messages passent par e-mail : les adresses ne sont jamais affichées.</p>
  <div class="split mt">
    <?php foreach (['offer' => ['Trajets proposés', $offers], 'need' => ['Cherchent une place', $needs]] as $k => [$title, $rows]): ?>
    <div class="card"><h2><?= $title ?> <small class="muted">(<?= count($rows) ?>)</small></h2>
      <?php foreach ($rows as $c): ?><details class="cp-item"><summary><span><b><?= e($c['city']) ?></b> <small class="muted">· <?= e($c['name']) ?><?= $c['depart_at'] ? ' · ' . e($c['depart_at']) : '' ?></small></span><span class="tag"><?= $k === 'offer' ? (int)$c['seats'] . ' place(s)' : (int)$c['seats'] . ' pers.' ?></span></summary>
        <?php if ($c['note']): ?><p class="muted"><small><?= nl2br(e($c['note'])) ?></small></p><?php endif; ?>
        <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="contact"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <div class="grid2"><input name="name" placeholder="Ton prénom" required><input type="email" name="email" placeholder="Ton e-mail" required></div>
          <textarea name="message" rows="2" placeholder="Bonjour, je pars aussi de… " required></textarea><button class="btn btn-sm">Écrire à <?= e($c['name']) ?></button></form></details><?php endforeach; ?>
      <?php if (!$rows): ?><p class="empty">Rien pour l'instant.</p><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
  <form method="post" class="card stack mt" style="max-width:560px"><?= csrf_field() ?><input type="hidden" name="action" value="add"><h2>Publier une annonce</h2>
    <nav class="pill-tabs"><label><input type="radio" name="kind" value="offer" checked> Je propose des places</label><label><input type="radio" name="kind" value="need"> Je cherche une place</label></nav>
    <div class="grid2"><div class="field"><label>Prénom</label><input name="name" required></div><div class="field"><label>Ville de départ</label><input name="city" required></div></div>
    <div class="grid2"><div class="field"><label>Places / personnes</label><input name="seats" inputmode="numeric" value="1"></div><div class="field"><label>Départ</label><input name="depart_at" placeholder="vers 18 h, retour après le concert"></div></div>
    <div class="field"><label>E-mail (jamais affiché)</label><input type="email" name="email" required></div>
    <div class="field"><label>Précisions</label><textarea name="note" rows="2" placeholder="Participation aux frais, bagages…"></textarea></div>
    <button class="btn">Publier</button></form>
</div>
