<?php /** @var array $q, $org, $ev, $t; bool $expired; ?array $order */ ?>
<section class="store-wrap" style="max-width:820px">
  <span class="eyebrow"><?= e($org['name']) ?> · devis <?= e($q['ref']) ?></span>
  <h1><?= e($ev['name']) ?></h1>
  <p class="lead"><?= e(fr_date($ev['starts_at'])) ?><?= $ev['venue'] ? ' · ' . e($ev['venue']) : '' ?></p>
  <div class="card">
    <div class="table-wrap"><table class="table"><thead><tr><th>Places</th><th class="num">Qté</th><th class="num">Prix</th><th class="num">Total</th></tr></thead><tbody>
      <?php foreach ($t['lines'] as $l): ?><tr><td><?= e($l['label']) ?></td><td class="num"><?= $l['qty'] ?></td><td class="num"><?= e(money($l['unit'])) ?></td><td class="num"><?= e(money($l['total'])) ?></td></tr><?php endforeach; ?>
      <?php if ($t['discount']): ?><tr><td colspan="3">Remise de groupe (<?= e(rtrim(rtrim(number_format((float)$q['discount_pct'], 2, ',', ''), '0'), ',')) ?> %)</td><td class="num">−<?= e(money($t['discount'])) ?></td></tr><?php endif; ?>
      <?php if ($t['fees']): ?><tr><td colspan="3">Frais de service</td><td class="num"><?= e(money($t['fees'])) ?></td></tr><?php endif; ?>
      <tr><td colspan="3"><b>Total</b></td><td class="num"><b><?= e(money($t['total'])) ?></b></td></tr>
    </tbody></table></div>
    <p class="muted mb0" style="font-size:13px">Valable jusqu'au <?= e(date('d/m/Y', strtotime((string)$q['valid_until']))) ?> · <a href="<?= url('/devis/' . $q['token'], ['pdf' => 1]) ?>" target="_blank">Télécharger le PDF</a><?= $q['notes'] ? '<br>' . nl2br(e($q['notes'])) : '' ?></p>
  </div>
  <?php if ($q['status'] === 'paid' && $order): ?>
    <div class="card"><h2>C'est réglé, merci !</h2><p>Les <?= $t['tickets'] ?> billets ont été envoyés à <?= e($q['email']) ?>.</p><a class="btn" href="<?= url('/commande/' . $order['ref'], ['t' => $order['token']]) ?>">Voir la commande et les billets</a></div>
  <?php elseif ($expired): ?>
    <div class="card"><h2>Ce devis a expiré</h2><p>Contacte <?= e($org['name']) ?> pour en recevoir un nouveau.</p></div>
  <?php elseif ($q['status'] === 'declined'): ?>
    <div class="card"><p class="mb0">Tu as indiqué ne pas donner suite à ce devis.</p></div>
  <?php else: ?>
    <form method="post" class="card quote-accept"><?= csrf_field() ?>
      <h2><?= $q['status'] === 'accepted' ? 'Devis accepté' : 'Accepter le devis' ?></h2>
      <?php if ($q['status'] === 'accepted'): ?><p>Virement attendu : <b><?= e(money($t['total'] - $t['fees'])) ?></b> avec la référence <b class="mono"><?= e($q['ref']) ?></b><?= $org['iban'] ? ' sur le compte <span class="mono">' . e(trim(chunk_split((string)$org['iban'], 4, ' '))) . '</span> (' . e($org['legal_name'] ?: $org['name']) . ')' : '' ?>. Les billets partent dès sa réception. Tu peux aussi payer par carte tout de suite.</p><?php endif; ?>
      <div class="row">
        <?php if ($q['pay_mode'] !== 'transfer'): ?><button class="btn btn-lg" name="action" value="card">Payer par carte · <?= e(money($t['total'])) ?></button><?php endif; ?>
        <?php if ($q['pay_mode'] !== 'card' && $q['status'] !== 'accepted'): ?><button class="btn btn-lg btn-light" name="action" value="transfer">Payer par virement<?= $t['fees'] ? ' · ' . e(money($t['total'] - $t['fees'])) : '' ?></button><?php endif; ?>
      </div>
      <?php if ($q['status'] === 'sent'): ?><button class="linklike mt" name="action" value="decline" data-confirm="Indiquer que tu ne donnes pas suite ?">Je ne donne pas suite</button><?php endif; ?>
    </form>
  <?php endif; ?>
</section>
