<?php $flt = $flt ?? ['session' => 0, 'type' => '', 'channel' => '', 'answer' => '', 'nfc' => false]; $qs = array_filter(['q' => $q, 'rate' => $rate ?: null, 'st' => $st ?: null, 'session' => $flt['session'] ?: null, 'type' => $flt['type'] ?: null, 'channel' => $flt['channel'] ?: null, 'answer' => $flt['answer'] ?: null, 'nfc' => $flt['nfc'] ? 1 : null]); ?>
<form class="toolbar" method="get">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, e-mail, code billet, commande…" aria-label="Rechercher un participant">
  <select name="rate" aria-label="Tarif"><option value="">Tous les tarifs</option><?php foreach ($rates as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $rate === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select>
  <select name="st" aria-label="Statut">
    <option value="">Billets valides</option><option value="in" <?= $st === 'in' ? 'selected' : '' ?>>Déjà entrés</option><option value="inside" <?= $st === 'inside' ? 'selected' : '' ?>>Sur place maintenant</option>
    <option value="out" <?= $st === 'out' ? 'selected' : '' ?>>Pas encore arrivés</option><option value="cancelled" <?= $st === 'cancelled' ? 'selected' : '' ?>>Annulés</option>
  </select>
  <?php if (!empty($sessions)): ?><select name="session" aria-label="Date"><option value="">Toutes les dates</option><?php foreach ($sessions as $x): ?><option value="<?= (int)$x['id'] ?>" <?= (int)$flt['session'] === (int)$x['id'] ? 'selected' : '' ?>><?= e(short_date($x['starts_at'])) ?></option><?php endforeach; ?></select><?php endif; ?>
  <details class="menu"><summary class="btn btn-sm btn-light">Plus de filtres<?= ($flt['type'] || $flt['channel'] || $flt['answer'] || $flt['nfc']) ? ' ·' : '' ?></summary><div class="menu-list stack" style="min-width:280px;padding:12px">
    <div class="field mb0"><label>Type de billet</label><select name="type"><option value="">Tous</option><?php foreach (['ticket' => 'Billets vendus', 'invitation' => 'Invitations', 'accreditation' => 'Accréditations', 'external' => 'Importés'] as $k => $l): ?><option value="<?= $k ?>" <?= $flt['type'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="field mb0"><label>Canal de vente</label><select name="channel"><option value="">Tous</option><?php foreach (['online' => 'En ligne', 'guichet' => 'Guichet', 'appli' => 'Appli organisateur', 'widget' => 'Widget', 'reservation' => 'Réservation', 'stock' => 'Billets imprimés', 'complement' => 'Complément de commande', 'invitation' => 'Invitation'] as $k => $l): ?><option value="<?= $k ?>" <?= $flt['channel'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="field mb0"><label>Réponse au formulaire contient</label><input name="answer" value="<?= e($flt['answer']) ?>" placeholder="Végétarien, Lyon…"></div>
    <label class="check"><input type="checkbox" name="nfc" value="1" <?= $flt['nfc'] ? 'checked' : '' ?>> <span>Avec bracelet NFC</span></label>
  </div></details>
  <button class="btn btn-sm">Filtrer</button>
  <span class="grow"></span>
  <details class="menu"><summary class="btn btn-sm btn-ghost">Export (CSV)</summary><div class="menu-list" style="min-width:300px;padding:12px">
    <form method="get" class="stack"><?php foreach ($qs as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>"><?php endforeach; ?><input type="hidden" name="csv" value="1">
      <b class="u-fs13">Colonnes</b>
      <div class="grid2"><?php foreach ($allCols ?? [] as $k => $l): ?><label class="check"><input type="checkbox" name="cols[]" value="<?= $k ?>" <?= in_array($k, ['code', 'first', 'last', 'rate', 'status', 'entered', 'ref', 'email'], true) ? 'checked' : '' ?>> <span class="u-fs13"><?= e($l) ?></span></label><?php endforeach; ?></div>
      <?php if ($fields): ?><b class="u-fs13">Questions du formulaire</b><?php foreach ($fields as $f): ?><label class="check"><input type="checkbox" name="fcols[]" value="<?= (int)$f['id'] ?>" checked> <span class="u-fs13"><?= e($f['label']) ?></span></label><?php endforeach; ?><?php endif; ?>
      <button class="btn btn-sm">Télécharger</button>
      <a class="u-fs13" href="<?= url('/pro/evenements/' . $ev['id'] . '/participants', $qs + ['csv' => 1]) ?>">Export complet (toutes les colonnes)</a>
    </form></div></details>
</form>
<div class="card">
  <div class="card-head"><h2><?= count($rows) ?> participant<?= count($rows) > 1 ? 's' : '' ?></h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Participant</th><th>Tarif</th><th>Code</th><th>Commande</th><th>Entrée</th><?php foreach (array_slice($fields, 0, 2) as $f): ?><th><?= e($f['label']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $ta = json_decode((string)$r['answers'], true) ?: []; $oa = (json_decode((string)$r['order_answers'], true) ?: [])['order'] ?? []; ?>
      <tr>
        <td><a href="<?= url('/pro/billets/' . $r['id']) ?>"><b><?= e($r['last_name']) ?></b> <?= e($r['first_name']) ?></a><br><small><?= e($r['email']) ?></small><?= $r['nfc_uid'] ? ' <span class="badge grey">NFC</span>' : '' ?></td>
        <td><?= e($r['rate_name']) ?></td>
        <td class="mono u-fs13"><?= e($r['code']) ?></td>
        <td><a href="<?= url('/pro/commandes/' . $r['order_id']) ?>"><?= e($r['ref']) ?></a><br><small><?= e(method_label($r['method'])) ?></small></td>
        <td><?= $r['status'] !== 'valid' ? badge('cancelled') : ($r['entries'] > 0 ? '<span class="badge green">Entré ' . e(date('H:i', strtotime($r['checked_in_at']))) . '</span>' : '<span class="badge grey">Pas arrivé</span>') ?></td>
        <?php foreach (array_slice($fields, 0, 2) as $f): ?><td><?= e(($f['per_ticket'] ? $ta : $oa)[$f['id']]['value'] ?? '') ?></td><?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty">Personne pour l'instant Partage le lien de ta billetterie !</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
