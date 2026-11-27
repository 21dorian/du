<?php /** @var array $list, $k, $counts, $ev; string $q, $filter, $base */ ?>
<div class="pk">
  <div><span>Porte-monnaie</span><b><?= (int)$counts['n'] ?></b><small><?= (int)$counts['nfc'] ?> bracelets · <?= (int)$counts['virt'] ?> cartes virtuelles</small></div>
  <div><span>Soldes en circulation</span><b><?= e(money((int)$k['left'])) ?></b><small><?= (int)$counts['positive'] ?> porte-monnaie avec un solde</small></div>
  <div><span>Rechargé</span><b><?= e(money((int)$k['loaded'])) ?></b><small>en ligne <?= e(money((int)$k['online'])) ?></small></div>
  <div><span>Dépensé aux bars</span><b><?= e(money((int)$k['spent'])) ?></b><small>panier moyen <?= e(money((int)$k['basket'])) ?></small></div>
  <div class="<?= (int)$counts['blocked'] ? 'warn' : '' ?>"><span>Bloqués</span><b><?= (int)$counts['blocked'] ?></b><small><?= (int)$counts['refund'] ?> remboursement(s) demandé(s)</small></div>
</div>
<div class="card">
  <form class="psearch"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Code billet ou carte, n° ou UID de bracelet, nom, e-mail…" autofocus><button class="btn">Rechercher</button>
    <a class="btn btn-light" href="<?= url($base . '/porte-monnaie', ['export' => 'csv']) ?>">Export CSV</a></form>
  <div class="pchips mt"><?php foreach (['' => 'Récents', 'positive' => 'Avec un solde', 'blocked' => 'Bloqués', 'refund' => 'Remboursement demandé'] as $fk => $fl): ?><a class="<?= $filter === $fk ? 'on' : '' ?>" href="<?= url($base . '/porte-monnaie', array_filter(['f' => $fk])) ?>"><?= $fl ?></a><?php endforeach; ?></div>
</div>
<div class="pgrid mt">
  <div class="card table-card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Porte-monnaie</th><th>Titulaire</th><th>Support</th><th>Profil</th><th class="num">Solde</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $w): ?><tr>
      <td><a href="<?= url($base . '/porte-monnaie/' . $w['id']) ?>"><b class="mono"><?= e($w['code']) ?></b></a><?= $w['status'] === 'blocked' ? ' <span class="ptag bad">bloqué</span>' : ($w['status'] === 'refund_req' ? ' <span class="ptag warn">remb. demandé</span>' : ($w['status'] === 'refunded' ? ' <span class="ptag">remboursé</span>' : '')) ?></td>
      <td><?= e(Pay::holder($w) ?: '—') ?><br><small class="muted"><?= e((string)$w['email']) ?></small></td>
      <td><?= $w['nfc_uid'] ? '<span class="ptag vio">bracelet</span>' : '' ?> <?= (int)$w['virtual'] ? '<span class="ptag">carte virtuelle</span>' : ($w['ticket_id'] ? '<span class="ptag">billet</span>' : '') ?></td>
      <td><?= e(Pay::PROFILES[$w['profile']] ?? $w['profile']) ?></td>
      <td class="num"><b><?= e(money((int)$w['balance'])) ?></b></td>
      <td class="num"><a class="btn btn-sm btn-light" href="<?= url($base . '/porte-monnaie/' . $w['id']) ?>">Ouvrir</a></td></tr><?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="6" class="pempty">Aucun porte-monnaie trouvé.</td></tr><?php endif; ?>
  </tbody></table></div></div>
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="virtual">
    <h2>Nouvelle carte cashless</h2><p class="pnote u-m0">Carte virtuelle (QR code sur le téléphone) pour un invité, un artiste, un partenaire. On peut ensuite lui associer un bracelet.</p>
    <input name="name" placeholder="Nom du titulaire"><input name="email" type="email" placeholder="E-mail (facultatif)">
    <select name="profile"><?php foreach (Pay::PROFILES as $pk => $pl): ?><option value="<?= $pk ?>"><?= e($pl) ?></option><?php endforeach; ?></select>
    <div class="input-suffix"><input name="amount" inputmode="decimal" placeholder="Crédit offert (facultatif)"><span>€</span></div>
    <label class="check"><input type="checkbox" name="send" value="1" checked> <span>Envoyer la carte par e-mail</span></label>
    <button class="btn">Créer la carte</button></form>
</div>
