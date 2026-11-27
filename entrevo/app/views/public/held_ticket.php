<?php $start = $session['starts_at'] ?? $ev['starts_at']; $valid = $t['status'] === 'valid' && $o['status'] === 'paid'; ?>
<div class="state-head">
  <div class="state-mark"><?= ui_icon('gift', 30) ?></div>
  <h1><?= $valid ? 'Ton billet, ' . e($t['first_name']) . ' !' : 'Billet annulé' ?></h1>
  <p class="muted">Offert pour <b><?= e($ev['name']) ?></b><?= $valid ? ' · montre le QR code à l\'entrée' : '' ?></p>
  <?php if ($valid): ?>
  <div class="row no-print" style="justify-content:center">
    <a class="btn" href="<?= url('/billet/' . $token . '/pdf', ['dl' => 1]) ?>">Télécharger (PDF)</a>
    <details class="cal-menu"><summary class="btn btn-ghost">Ajouter à mon agenda</summary><div class="cal-pop"><a href="<?= e(Buyers::googleUrl($ev, $session)) ?>" target="_blank" rel="noopener">Google Agenda</a><a href="<?= e(Buyers::icsUrl($ev, $session)) ?>">Apple / Outlook (.ics)</a></div></details>
  </div>
  <?php endif; ?>
</div>
<div style="max-width:860px;margin:0 auto">
  <div class="tickets-list">
    <div class="ticket <?= $valid ? '' : 'cancelled' ?>">
      <div class="ticket-main">
        <div class="ticket-org"><?= e($org['name']) ?> présente</div>
        <div class="ticket-event"><?= e($ev['name']) ?></div>
        <div class="ticket-grid">
          <div><small>Date</small><b><?= e(date('d.m.Y', strtotime($start))) ?></b></div>
          <div><small>Heure</small><b><?= e(date('H:i', strtotime($start))) ?></b></div>
          <div><small>Tarif</small><b><?= e($t['rate_name']) ?></b></div>
          <div class="u-span2"><small>Lieu</small><b><?= e(trim($ev['venue'] . ($ev['city'] ? ', ' . $ev['city'] : ''), ', ')) ?: '—' ?></b></div>
          <div><small>Origine</small><b>Offert</b></div>
          <div style="grid-column:1/-1"><small>Participant</small><b><?= e(trim($t['first_name'] . ' ' . $t['last_name'])) ?></b></div>
        </div>
        <?php if ($t['seat']): ?><span class="ticket-seat"><?= e(Seating::label($t['seat'], $map)) ?></span><?php endif; ?>
      </div>
      <div class="ticket-stub">
        <span class="ticket-num">Billet</span>
        <?= $valid ? Qr::svg($t['code'], 170) : '<div style="width:170px;height:170px"></div>' ?>
        <span class="ticket-code"><?= e($t['code']) ?></span>
      </div>
    </div>
  </div>
  <?php if ($valid && !$why): ?>
  <details class="card give-box no-print">
    <summary><b>Tu ne peux finalement pas y aller ?</b> <span class="muted">Donne ce billet à quelqu'un d'autre (encore <?= Buyers::MAX_TRANSFERS - (int)$t['transfers'] ?> transfert<?= Buyers::MAX_TRANSFERS - (int)$t['transfers'] > 1 ? 's' : '' ?> possible<?= Buyers::MAX_TRANSFERS - (int)$t['transfers'] > 1 ? 's' : '' ?>).</span></summary>
    <form method="post" action="<?= url('/billet/' . $token . '/donner') ?>" class="give-form"><?= csrf_field() ?>
      <input name="to_first_name" placeholder="Prénom" required aria-label="Prénom"><input name="to_last_name" placeholder="Nom" required aria-label="Nom"><input type="email" name="to_email" placeholder="son@email.fr" required aria-label="E-mail">
      <button class="btn btn-sm" data-confirm="Donner ce billet ? Ton QR code actuel ne fonctionnera plus.">Donner le billet</button>
    </form>
  </details>
  <?php endif; ?>
  <p class="muted center u-fs13">Un QR code = une entrée. Garde ce lien pour toi : il ouvre ton billet.</p>
</div>
