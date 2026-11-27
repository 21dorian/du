<?php /** @var array $gifts, $profiles, $allow, $ev; int $giftTotal; string $base */ ?>
<div class="pk">
  <?php foreach (Pay::PROFILES as $pk => $pl): $p = $profiles[$pk] ?? null; ?><div><span><?= e($pl) ?></span><b><?= (int)($p['n'] ?? 0) ?></b><small>soldes <?= e(money((int)($p['b'] ?? 0))) ?></small></div><?php endforeach; ?>
  <div><span>Crédits offerts</span><b><?= e(money($giftTotal)) ?></b></div>
</div>
<div class="pgrid-2">
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="bulk">
    <h2>Créditer en masse</h2><p class="pnote u-m0">Repas du staff, consommations des artistes, dédommagement… Crédite tous les porte-monnaie d'un profil, et/ou une liste de codes, numéros de bracelet ou UID.</p>
    <select name="profile"><option value="">Aucun profil (liste seulement)</option><?php foreach (Pay::PROFILES as $pk => $pl): ?><option value="<?= $pk ?>"><?= e($pl) ?> (<?= (int)($profiles[$pk]['n'] ?? 0) ?>)</option><?php endforeach; ?></select>
    <textarea name="refs" rows="4" placeholder="Codes, numéros de bracelet ou UID (un par ligne, ou séparés par des virgules)"></textarea>
    <div class="input-suffix"><input name="amount" inputmode="decimal" placeholder="Montant par porte-monnaie" required><span>€</span></div>
    <input name="note" placeholder="Motif (ex. Repas staff samedi)"><button class="btn">Créditer</button></form>
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="cards">
    <h2>Cartes préchargées en série</h2><p class="pnote u-m0">Invitations, partenaires, jeux concours : génère des cartes virtuelles déjà créditées. Tu reçois un fichier avec les codes et les liens à distribuer (QR à imprimer ou à envoyer).</p>
    <div class="grid2"><input name="count" inputmode="numeric" value="10" placeholder="Nombre"><div class="input-suffix"><input name="amount" inputmode="decimal" placeholder="Crédit"><span>€</span></div></div>
    <select name="profile"><?php foreach (Pay::PROFILES as $pk => $pl): ?><option value="<?= $pk ?>" <?= $pk === 'vip' ? 'selected' : '' ?>><?= e($pl) ?></option><?php endforeach; ?></select>
    <input name="label" placeholder="Libellé (ex. Partenaire Brasserie)"><button class="btn">Générer les cartes</button></form>
</div>
<div class="card mt"><h2>Crédits de consommations (badges staff et artistes)</h2>
  <p class="pnote">Les badges à quotas (« 2 boissons par jour », « 15 € de repas ») se gèrent dans <a href="<?= url($base . '/credits') ?>">Crédits staff & artistes</a> : <?= count($allow) ?> badge(s) actif(s). Ils se scannent à la caisse avec « Autre › Crédit staff ou artiste ».</p></div>
<div class="card table-card mt"><div class="card-head"><h2>Derniers crédits offerts</h2></div><div class="table-wrap"><table class="table table-sm"><tbody>
  <?php foreach ($gifts as $g): $n = json_decode((string)$g['items'], true)['note'] ?? ''; ?><tr><td><?= e(date('d/m H:i', strtotime($g['created_at']))) ?></td><td class="mono"><?= e($g['code']) ?></td><td><?= e(Pay::PROFILES[$g['profile']] ?? '') ?></td><td><?= e($n) ?></td><td><?= e((string)$g['who']) ?></td><td class="num"><b>+<?= e(money((int)$g['amount'])) ?></b></td></tr><?php endforeach; ?>
  <?php if (!$gifts): ?><tr><td class="pempty">Aucun crédit offert.</td></tr><?php endif; ?></tbody></table></div></div>
