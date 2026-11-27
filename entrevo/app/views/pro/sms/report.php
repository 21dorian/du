<?php /** @var array $c, $msgs, $sales; ?array $ev; string $preview */ $err = Sms::lastError($c); $labels = ['queued' => ['En attente', 'grey'], 'sent' => ['Envoyé', 'green'], 'test' => ['Test (journal)', 'grey'], 'failed' => ['Échec', 'red'], 'skipped' => ['Non envoyé', 'grey']]; ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Statut</span><b><?= Sms::badge($c['status']) ?></b></div>
  <div class="kpi-mini"><span>Envoyés</span><b><?= (int)$c['sent'] ?> / <?= (int)$c['total'] ?></b></div>
  <div class="kpi-mini"><span>Coût</span><b><?= e(money((int)$c['cost'])) ?></b></div>
  <div class="kpi-mini"><span>Ventes générées</span><b><?= e(money((int)$sales['revenue'])) ?></b><?php if ((int)$sales['n']): ?><small class="muted"><?= (int)$sales['n'] ?> commande<?= (int)$sales['n'] > 1 ? 's' : '' ?></small><?php endif; ?></div>
</div>
<?php if ($err): ?><div class="notice warn"><span>Envoi en pause : <?= e($err) ?>. Il reprendra tout seul une fois le problème réglé.</span></div><?php endif; ?>
<?php if ($c['status'] === 'scheduled'): ?><div class="notice info"><span>Départ prévu <?= $c['scheduled_at'] ? 'le ' . e(fr_date($c['scheduled_at'])) : 'au prochain créneau autorisé' ?>.</span></div><?php elseif ($c['status'] === 'sending' && !Sms::windowOpen()): ?><div class="notice info"><span>En attente du créneau autorisé : reprise le <?= e(fr_date(date('Y-m-d H:i:s', Sms::nextWindow()))) ?>.</span></div><?php endif; ?>
<div class="split-3">
  <div class="card table-card">
    <div class="card-head"><h2>Destinataires</h2><small class="muted"><?= count($msgs) ?> numéro<?= count($msgs) > 1 ? 's' : '' ?></small></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Numéro</th><th>Prénom</th><th>État</th><th>Heure</th></tr></thead><tbody>
    <?php foreach ($msgs as $m): [$l, $col] = $labels[$m['status']] ?? [$m['status'], 'grey']; ?>
      <tr><td class="mono"><?= e(Sms::pretty($m['phone'])) ?></td><td><?= e((string)$m['first_name']) ?></td><td><span class="badge <?= $col ?>"><?= e($l) ?></span><?= $m['error'] ? '<br><small class="muted">' . e($m['error']) . '</small>' : '' ?></td><td><small><?= $m['sent_at'] ? e(date('d/m H:i', strtotime($m['sent_at']))) : '—' ?></small></td></tr>
    <?php endforeach; ?>
    <?php if (!$msgs): ?><tr><td colspan="4" class="empty">La liste sera figée au moment du départ.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <div class="stack">
    <div class="card">
      <h2>Message</h2>
      <div class="phone-mock"><div class="phone-top"><b><?= e(Sms::sender($org)) ?></b><small>SMS</small></div><div class="bubble"><?= nl2br(e($preview)) ?></div></div>
      <p class="muted mt mb0 u-fs13"><?= (int)$c['segments'] ?> SMS par personne<?= $ev ? ' · lien vers <a href="' . url('/pro/evenements/' . $ev['id']) . '">' . e($ev['name']) . '</a>' : '' ?></p>
    </div>
    <?php if (in_array($c['status'], ['scheduled', 'sending'], true)): ?>
    <form class="card" method="post" action="<?= url('/pro/sms') ?>"><?= csrf_field() ?><input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>">
      <h2>Arrêter</h2><p class="muted u-fs13">Les SMS pas encore partis ne seront pas envoyés.</p>
      <button class="btn btn-light btn-block" name="action" value="cancel" data-confirm="Arrêter cette campagne ?">Arrêter la campagne</button>
    </form>
    <?php endif; ?>
  </div>
</div>
