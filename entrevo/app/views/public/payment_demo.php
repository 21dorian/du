<div class="auth-box card center">
  <div class="state-mark wait">🧪</div>
  <h1>Paiement pour de faux</h1>
  <p class="muted">Mode démo : aucun euro ne bouge. Pratique pour tester avant de brancher Stripe, PayPal, SumUp ou Mollie (à couper dans Super-admin › Réglages).</p>
  <p style="font-size:22px;font-weight:800"><?= e(money((int)$o['total'])) ?></p>
  <form method="post" action="<?= url('/paiement/demo/' . $o['ref'], ['t' => $o['token']]) ?>"><?= csrf_field() ?><button class="btn btn-lg btn-block btn-success">Faire comme si j'avais payé ✅</button></form>
  <p class="mt"><a href="<?= url('/paiement/annule/' . $o['ref'], ['t' => $o['token']]) ?>">Non, j'annule</a></p>
</div>
