<?php /** @var array $d, $org, $tickets, $other; ?array $o, $ev */ ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Statut</span><b><?= Disputes::badge($d['status']) ?></b></div>
  <div class="kpi-mini"><span>Montant contesté</span><b><?= e(money((int)$d['amount'])) ?></b><?= $o ? '<small class="muted">sur ' . e(money((int)$o['total'])) . '</small>' : '' ?></div>
  <div class="kpi-mini"><span>Retenue si perdu</span><b><?= e(money((int)$d['amount'] + (int)$d['bank_fee'])) ?></b><small class="muted">dont <?= e(money((int)$d['bank_fee'])) ?> de frais</small></div>
  <div class="kpi-mini"><span>Échéance</span><b><?= $d['due_on'] ? e(date('d/m/Y', strtotime($d['due_on']))) : '—' ?></b><?php if ($d['due_on'] && $d['status'] === 'open'): $days = (int)floor((strtotime($d['due_on']) - strtotime('today')) / 86400); ?><small class="<?= $days < 7 ? 'text-red' : 'muted' ?>"><?= $days >= 0 ? 'dans ' . $days . ' jour' . ($days > 1 ? 's' : '') : 'dépassée' ?></small><?php endif; ?></div>
</div>
<div class="split-3">
  <form class="card" method="post"><?= csrf_field() ?>
    <div class="card-head"><h2>Dossier de preuves</h2><button class="btn btn-sm btn-ghost" name="action" value="regen" formnovalidate data-confirm="Remplacer le texte par une version régénérée ?">Régénérer</button></div>
    <p class="muted u-fs13">Pré-rempli avec ce que la plateforme sait de la commande. Copie-le dans l'espace litiges de ton prestataire de paiement, avec les éventuels éléments de l'organisateur.</p>
    <div class="field"><textarea name="evidence" rows="14" class="mono u-fs13"><?= e((string)$d['evidence']) ?></textarea></div>
    <button type="button" class="btn btn-sm btn-light" data-copy-from="[name=evidence]">Copier le dossier</button>
    <div class="grid3 mt">
      <div class="field"><label>Motif</label><select name="reason"><?php foreach (Disputes::REASONS as $k => $l): ?><option value="<?= $k ?>" <?= $d['reason'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Répondre avant le</label><input type="date" name="due_on" value="<?= e((string)$d['due_on']) ?>"></div>
      <div class="field"><label>Frais bancaires</label><div class="input-suffix"><input name="bank_fee" inputmode="decimal" value="<?= e(money_input((int)$d['bank_fee'])) ?>"><span>€</span></div></div>
    </div>
    <div class="field"><label>Notes internes</label><textarea name="notes" rows="3"><?= e((string)$d['notes']) ?></textarea></div>
    <button class="btn">Enregistrer</button>
  </form>
  <div class="stack">
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status">
      <h2>Issue</h2>
      <div class="status-pick"><?php foreach (Disputes::STATUS as $k => [$l]): ?><button class="btn btn-sm <?= $d['status'] === $k ? '' : 'btn-light' ?>" name="status" value="<?= $k ?>" <?= in_array($k, ['lost', 'accepted'], true) && $d['status'] !== $k ? 'data-confirm="Retenir ' . e(money((int)$d['amount'] + (int)$d['bank_fee'])) . ' sur le reversement de ' . e($org['name']) . ' ?"' : '' ?>><?= e($l) ?></button><?php endforeach; ?></div>
      <p class="muted mt mb0 u-fs13"><?= $d['charge_id'] ? 'Retenue appliquée sur le reversement.' : 'Rien n\'est retenu tant que le litige n\'est pas perdu ou accepté.' ?></p>
    </form>
    <?php if ($o): ?><div class="card"><h2>Commande <span class="mono"><?= e($o['ref']) ?></span></h2>
      <p class="mb0"><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?><br><small class="muted"><?= e($o['email']) ?><?= $o['phone'] ? ' · ' . e($o['phone']) : '' ?></small></p>
      <p><small class="muted">Payée le <?= e(fr_date((string)($o['paid_at'] ?: $o['created_at']))) ?> · <?= e((string)$o['provider']) ?> · <?= e(money((int)$o['total'])) ?></small></p>
      <?php foreach ($tickets as $t): ?><div class="list-item"><span class="grow"><b class="mono"><?= e($t['code']) ?></b> <small class="muted"><?= e($t['rate_name']) ?></small></span><?= $t['checked_in_at'] ? '<span class="badge green">Entré</span>' : '<span class="badge grey">Pas scanné</span>' ?></div><?php endforeach; ?>
      <p class="mt mb0"><a href="<?= url('/admin/commandes/' . $o['id']) ?>">Voir la commande →</a></p></div><?php endif; ?>
    <div class="card"><h2>Organisateur</h2><p class="mb0"><a href="<?= url('/admin/organisateurs/' . $org['id']) ?>"><b><?= e($org['name']) ?></b></a><br><small class="muted"><?= e((string)$org['email']) ?></small><?= $ev ? '<br><small class="muted">' . e($ev['name']) . ' · ' . e(short_date($ev['starts_at'])) . '</small>' : '' ?></p></div>
    <?php if ($other): ?><div class="card"><h2>Autres litiges de cet acheteur</h2><?php foreach ($other as $x): ?><div class="list-item"><a class="grow mono" href="<?= url('/admin/litiges/' . $x['id']) ?>"><?= e($x['ref']) ?></a><?= Disputes::badge($x['status']) ?></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
