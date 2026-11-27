<?php /** @var array $ev, $org, $g, $members; int $left; ?array $session */
$link = abs_url('/e/' . $ev['slug'] . '/bande/' . $g['token']); $paid = count(array_filter($members, fn($m) => $m['status'] === 'paid')); $exp = strtotime((string)$g['expires_at']); ?>
<div class="container" style="max-width:720px;padding:28px 16px">
  <a class="muted" href="<?= url('/e/' . $ev['slug']) ?>">← <?= e($ev['name']) ?></a>
  <div class="card mt grp">
    <span class="bl-eyebrow">Payer à plusieurs</span>
    <h1 style="margin:6px 0">La bande de <?= e($g['name']) ?></h1>
    <p class="muted"><?= e($ev['name']) ?> · <?= e(fr_date($session['starts_at'] ?? $ev['starts_at'])) ?><?= $ev['venue'] ? ' · ' . e($ev['venue']) : '' ?></p>
    <div class="grp-seats"><?php for ($i = 0; $i < (int)$g['qty']; $i++): $m = $members[$i] ?? null; ?><span class="<?= $m ? ($m['status'] === 'paid' ? 'paid' : 'pend') : '' ?>"><?= $m ? e(mb_strtoupper(mb_substr((string)$m['first_name'], 0, 1))) : '' ?></span><?php endfor; ?></div>
    <p><b><?= $paid ?> / <?= (int)$g['qty'] ?></b> places payées · <b><?= e($g['rate_name']) ?></b> à <?= e(money((int)$g['price'])) ?> par personne</p>
    <?php if ($left > 0): ?>
      <p class="grp-cd" data-countdown="<?= e(date('c', $exp)) ?>" data-cd-end="Le groupe est terminé : les places non payées sont libérées.">Places bloquées encore <span data-cd-text><?= e(ago_until($exp)) ?></span></p>
      <form method="post"><?= csrf_field() ?><button class="btn btn-lg btn-block">Je prends ma place · <?= e(money((int)$g['price'])) ?></button></form>
      <div class="grp-share"><button type="button" class="btn btn-light" data-copy="<?= e($link) ?>">Copier le lien</button>
        <a class="btn btn-light" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode('On y va ? Prends ta place pour ' . $ev['name'] . ' : ' . $link) ?>">WhatsApp</a>
        <a class="btn btn-light" href="sms:?&body=<?= rawurlencode('Prends ta place pour ' . $ev['name'] . ' : ' . $link) ?>">SMS</a></div>
    <?php elseif ($exp < time()): ?><div class="flash warning">Le délai est passé : les places non payées ont été remises en vente. <a href="<?= url('/e/' . $ev['slug']) ?>">Voir les billets</a></div>
    <?php else: ?><div class="flash success">Tout le monde a sa place ! Rendez-vous le <?= e(fr_date($session['starts_at'] ?? $ev['starts_at'], false)) ?>.</div><?php endif; ?>
    <?php if ($members): ?><h3 class="mt">Déjà dans la bande</h3><?php foreach ($members as $m): ?><div class="list-item"><span class="grow"><?= e(trim($m['first_name'] . ' ' . mb_substr((string)$m['last_name'], 0, 1) . '.')) ?></span><?= $m['status'] === 'paid' ? '<span class="tag green">payé</span>' : '<span class="tag">en cours</span>' ?></div><?php endforeach; ?><?php endif; ?>
    <p class="muted" style="font-size:13px;margin-top:14px">Chacun paie sa place et reçoit son propre billet. Les places non payées avant l'échéance retournent en vente.</p>
  </div>
</div>
