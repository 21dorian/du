<?php /** @var array $w, $ev, $org, $tx; bool $ended, $canRefund */ ?>
<section class="store-wrap" style="max-width:720px">
  <span class="eyebrow"><?= e($ev['name']) ?> · cashless</span>
  <h1><?= e(money((int)$w['balance'])) ?></h1>
  <p class="lead">Ton solde. Au bar, présente le QR code de ton billet <span class="mono"><?= e($w['code']) ?></span>.</p>
  <?php if (!$ended && (int)$ev['cashless_enabled']): ?>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="topup">
    <h2>Recharger</h2>
    <div class="amount-pick"><?php foreach ([1000, 2000, 3000, 5000] as $a): if ($a < (int)$ev['cashless_min_topup']) continue; ?><label class="seg-r"><input type="radio" name="amount" value="<?= $a ?>" <?= $a === 2000 ? 'checked' : '' ?>><span><?= e(money($a)) ?></span></label><?php endforeach; ?>
      <label class="seg-r"><input type="radio" name="amount" value="libre"><span>Autre</span></label></div>
    <div class="field mt" data-show-if="amount=libre"><div class="input-suffix" style="max-width:160px"><input name="free" inputmode="decimal"><span>€</span></div></div>
    <button class="btn btn-lg mt">Recharger</button>
  </form>
  <?php endif; ?>
  <?php if ($canRefund): ?>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="refund"><h2>Récupérer mon solde</h2><p class="muted">Possible jusqu'au <?= e(date('d/m/Y', strtotime((string)$ev['cashless_refund_until']))) ?>, par virement.</p>
    <div class="field"><label>IBAN</label><input name="iban" required placeholder="FR76 …"></div><button class="btn">Demander le remboursement de <?= e(money((int)$w['balance'])) ?></button></form>
  <?php elseif ($w['status'] === 'refund_req'): ?><div class="card"><p class="mb0">Remboursement demandé : il arrive par virement.</p></div>
  <?php elseif ($w['status'] === 'refunded'): ?><div class="card"><p class="mb0">Solde remboursé le <?= e(date('d/m/Y', strtotime((string)$w['refunded_at']))) ?>.</p></div><?php endif; ?>
  <div class="card"><h2>Historique</h2>
    <?php foreach ($tx as $t): $items = json_decode((string)$t['items'], true) ?: []; ?><div class="list-item"><span class="grow"><b><?= e(Cashless::KINDS[$t['kind']] ?? $t['kind']) ?></b><?= $t['bar'] ? ' · ' . e($t['bar']) : '' ?><?= $t['undone'] ? ' <span class="badge grey">annulé</span>' : '' ?><br><small class="muted"><?= e(date('d/m H:i', strtotime($t['created_at']))) ?><?= $t['kind'] === 'sale' ? ' · ' . e(implode(', ', array_map(fn($l) => ($l['qty'] ?? 1) . ' × ' . ($l['name'] ?? ''), array_filter($items, 'is_array')))) : '' ?></small></span>
      <b class="<?= (int)$t['amount'] < 0 ? '' : 'text-green' ?>"><?= (int)$t['amount'] > 0 ? '+' : '' ?><?= e(money((int)$t['amount'])) ?></b></div><?php endforeach; ?>
    <?php if (!$tx): ?><p class="muted mb0">Aucune opération pour l'instant.</p><?php endif; ?>
  </div>
</section>
