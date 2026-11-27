<?php /** @var array $s, $ev; string $html */ ?>
<section class="store-wrap" style="max-width:520px">
  <span class="eyebrow">Ticket de caisse</span>
  <h1><?= e(money((int)$s['amount'])) ?></h1>
  <div class="card receipt-card"><?= $html ?><?= $s['undone'] ? '<p class="notice warn mt mb0">Cette vente a été annulée.</p>' : '' ?></div>
  <form method="post" class="card"><?= csrf_field() ?>
    <h2>Recevoir par e-mail</h2>
    <div class="row"><input type="email" name="email" required placeholder="ton@email.fr" value="<?= e((string)$s['receipt_email']) ?>" class="grow"><button class="btn">Envoyer</button></div>
  </form>
  <button class="btn btn-light no-print" data-print>Imprimer</button>
</section>
