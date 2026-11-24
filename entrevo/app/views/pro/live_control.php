<div data-autorefresh="20"></div>
<a class="pc-cta" href="<?= url('/pro/evenements/' . $ev['id'] . '/pc') ?>" target="_blank"><span></span><span><b>Ouvrir le PC sécurité</b><small>Écran plein écran : jauges par zone, flux, refus, téléphones, alertes automatiques</small></span><i></i></a>
<?php $rate = $stats['sold'] ? round($stats['checked'] / $stats['sold'] * 100) : 0; ?>
<div class="kpis">
  <div class="kpi"><small class="muted">Entrés</small><b><?= $stats['checked'] ?> <small style="font-size:18px;color:var(--muted)">/ <?= $stats['sold'] ?></small></b><div class="bar green"><i style="width:<?= $rate ?>%"></i></div><div class="sub"><?= $rate ?> % du public est là</div></div>
  <div class="kpi"><small class="muted">Sur place en ce moment</small><b><?= $stats['inside'] ?></b><div class="sub"><?= $ev['allow_reentry'] ? 'sorties déduites' : 'pas de ré-entrée' ?></div></div>
  <div class="kpi"><small class="muted">Encore attendus</small><b><?= $stats['sold'] - $stats['checked'] ?></b></div>
</div>
<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>Derniers passages</h2><small>Mise à jour toute seule toutes les 20 s</small></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Heure</th><th>Résultat</th><th>Participant</th><th>Accès</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($scans as $s): $ok = in_array($s['result'], ['ok', 'exit'], true); ?>
        <tr style="<?= $s['undone'] ? 'opacity:.4;text-decoration:line-through' : '' ?>">
          <td class="nowrap"><?= e(date('H:i:s', strtotime($s['scanned_at']))) ?><?= $s['offline'] ? ' <span class="badge grey" title="Scanné hors ligne puis synchronisé">hors ligne</span>' : '' ?></td>
          <td><span class="badge <?= $ok ? 'green' : (in_array($s['result'], ['already', 'not_inside'], true) ? 'orange' : 'red') ?>"><?= e(Tickets::LABELS[$s['result']] ?? $s['result']) ?></span></td>
          <td><?= $s['first_name'] !== null ? e($s['first_name'] . ' ' . $s['last_name']) . '<br><small>' . e($s['rate_name']) . '</small>' : '<span class="mono" style="font-size:12.5px">' . e($s['code']) . '</span>' ?></td>
          <td><?= e($s['ap_name'] ?: 'Général') ?><?= $s['direction'] === 'out' ? '' : '' ?><br><small class="muted"><?= e($s['user_name'] ?? '') ?></small></td>
          <td class="right"><?php if ($ok && !$s['undone']): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="scan_id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="undo" data-confirm="Annuler ce passage ? (erreur de scan)">Annuler</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$scans): ?><tr><td colspan="5" class="empty">Aucun scan pour l'instant. Les portes sont fermées ?</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <div>
    <div class="card">
      <h2>Message aux contrôleurs</h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="scan_message">
        <textarea name="body" rows="2" maxlength="280" placeholder="Ex. : Porte B fermée, tout le monde par la porte A" required></textarea>
        <div class="row" style="gap:8px"><select name="level" style="width:auto"><option value="info">Info</option><option value="warn">Attention</option><option value="alert">Urgent</option></select><button class="btn btn-sm">Envoyer aux téléphones</button></div>
      </form>
      <?php foreach ($messages as $m): ?><div class="list-item" style="display:block"><small class="muted"><?= e(date('H:i', strtotime($m['created_at']))) ?> · <?= e((string)$m['user_name']) ?></small><br><?= e($m['body']) ?></div><?php endforeach; ?>
    </div>
    <div class="card">
      <h2>Téléphones de contrôle</h2>
      <?php foreach ($devices as $d): $on = strtotime($d['last_seen']) > time() - 90; ?>
        <div class="device"><span class="dev-dot <?= $on ? 'on' : '' ?>" title="<?= $on ? 'Connecté' : 'Vu ' . e(ago($d['last_seen'])) ?>"></span>
          <span class="grow"><b><?= e($d['user_name'] ?: 'Contrôleur') ?></b><small><?= e($d['ap_name'] ?: 'Porte ?') ?> · <?= $d['direction'] === 'out' ? 'sortie' : 'entrée' ?> · <?= (int)$d['scans'] ?> scan(s)<?= $on ? '' : ' · vu ' . e(ago($d['last_seen'])) ?></small></span>
          <?php if ((int)$d['pending']): ?><span class="badge orange" title="Passages scannés hors ligne pas encore envoyés"><?= (int)$d['pending'] ?></span><?php endif; ?>
          <?php if ($d['battery'] !== null): ?><span class="batt <?= (int)$d['battery'] <= 20 ? 'low' : '' ?>" title="Batterie"><?= (int)$d['battery'] ?> %</span><?php endif; ?></div>
      <?php endforeach; ?>
      <?php if (!$devices): ?><p class="muted">Aucun téléphone connecté. Les contrôleurs ouvrent <b><?= e(abs_url('/scan')) ?></b>.</p><?php endif; ?>
    </div>
    <?= soon_card('Bientôt au PC', [
      'badges-live' => ['Badges imprimés à l\'arrivée', 'Pour les salons et congrès'],
      'vestiaire' => ['Vestiaire', 'Tickets numérotés liés au billet'],
      'kiosque' => ['Borne libre-service', 'Achat et retrait sans faire la queue'],
    ]) ?>
    <div class="card">
      <h2>Par point d'accès</h2>
      <?php foreach ($aps as $a): ?><div class="list-item"><b class="grow"><?= e($a['name']) ?></b><span><?= (int)$a['entries'] ?> entrées<?= $a['exits'] ? ' · ' . (int)$a['exits'] . ' sorties' : '' ?><?= $a['refused'] ? ' · <span style="color:var(--red)">' . (int)$a['refused'] . ' refus</span>' : '' ?></span></div><?php endforeach; ?>
    </div>
    <div class="card">
      <h2>Par tarif</h2>
      <?php foreach ($stats['by_rate'] as $r): $p = $r['sold'] ? round($r['checked'] / $r['sold'] * 100) : 0; ?>
        <div style="margin-bottom:12px"><div class="row between"><b><?= e($r['name']) ?></b><span><?= (int)$r['checked'] ?> / <?= (int)$r['sold'] ?></span></div><div class="bar green"><i style="width:<?= $p ?>%"></i></div></div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <h2>Afflux (12 h)</h2>
      <?php if (!$perHour): ?><p class="muted">Rien à afficher.</p><?php else: $mx = max(array_column($perHour, 'n')); foreach ($perHour as $h): ?>
        <div class="row" style="gap:10px;margin-bottom:6px"><span class="mono" style="width:50px"><?= e($h['h']) ?></span><div class="bar grow" style="margin:0"><i style="width:<?= round($h['n'] / $mx * 100) ?>%"></i></div><span style="width:40px" class="right"><?= (int)$h['n'] ?></span></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
