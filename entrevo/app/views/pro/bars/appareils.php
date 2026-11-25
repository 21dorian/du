<?php /** @var array $devices, $bars, $tills; int $focus; bool $sessionsOn; string $btab, $bbase */ echo view('pro/bars/_nav', compact('btab', 'bbase')); $pos = url('/caisse/');
$byBar = []; foreach ($devices as $d) if ($d['bar_id'] && $d['last_seen'] && strtotime($d['last_seen']) > time() - 900) $byBar[(int)$d['bar_id']][] = $d; ?>
<div class="notice info mb"><b>Comment ça marche.</b> Tu crées les points de vente et tu appaires les appareils. Sur place, chaque serveur tape son code, choisit son point de vente et ce qu'il fait (vendre, recharger, préparer, écran d'appel). <?= $sessionsOn ? 'Un responsable ouvre la caisse une fois par point de vente : tous ses appareils vendent dedans, et la clôture (Z) est unique.' : '' ?></div>
<section class="card">
  <div class="card-head"><div><h2>Points de vente</h2><p class="muted mb0">Bar, stand de restauration, merch, point de recharge…</p></div></div>
  <div class="pv-list">
    <?php foreach ($bars as $b): $t = $tills[(int)$b['id']] ?? null; $on = $byBar[(int)$b['id']] ?? []; ?>
      <div class="pv<?= (int)$b['active'] ? '' : ' off' ?>">
        <div class="pv-head"><b class="grow"><?= e($b['name']) ?></b>
          <?php if (!(int)$b['active']): ?><span class="badge">fermé</span>
          <?php elseif ($sessionsOn): ?><span class="badge <?= $t ? 'badge-green' : '' ?>"><?= $t ? 'Caisse ouverte' : 'Caisse fermée' ?></span><?php endif; ?></div>
        <?php if ($t): ?><p class="muted mb0"><small>Ouverte <?= e(fr_date($t['opened_at'])) ?><?= $t['opener'] ? ' par ' . e($t['opener']) : '' ?> · fond <?= e(money((int)$t['float_amount'])) ?> · <?= (int)$t['tot']['count'] ?> vente(s), <?= e(money((int)$t['tot']['total'])) ?> · espèces attendues <?= e(money((int)$t['tot']['expected_cash'])) ?></small></p><?php endif; ?>
        <p class="muted mb0"><small><?= $on ? 'En ce moment : ' . e(implode(', ', array_map(fn($d) => $d['name'] . ' (' . (Pos::MODE_LABELS[$d['mode']] ?? 'Vente') . ($d['staff_name'] ? ', ' . $d['staff_name'] : '') . ')', array_slice($on, 0, 4))) . (count($on) > 4 ? ' et ' . (count($on) - 4) . ' autre(s)' : '')) : 'Aucun appareil sur ce point de vente.' ?></small></p>
        <details class="pos-set"><summary class="linklike">Renommer, fermer</summary>
          <form method="post" class="row mt"><?= csrf_field() ?><input type="hidden" name="bar_id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="action" value="bar"><input name="name" value="<?= e($b['name']) ?>" class="grow"><button class="btn btn-sm btn-light">Renommer</button><button class="btn btn-sm btn-ghost" name="action" value="bar_toggle"><?= (int)$b['active'] ? 'Fermer le point de vente' : 'Rouvrir' ?></button></form></details>
      </div>
    <?php endforeach; ?>
    <form method="post" class="pv pv-add"><?= csrf_field() ?><input type="hidden" name="action" value="bar"><b>Ajouter un point de vente</b><div class="row"><input name="name" placeholder="Bar principal, food truck, stand merch…" required class="grow"><button class="btn btn-sm">Ajouter</button></div></form>
  </div>
</section>

<section class="card pos-card mt">
  <div class="card-head"><div><h2>Appareils</h2><p class="muted mb0">Tablettes, téléphones et écrans appairés. Tu n'as rien d'autre à régler : le poste se choisit sur l'appareil.</p></div>
    <a class="btn btn-sm btn-light" href="<?= $pos ?>" target="_blank">Ouvrir l'application</a></div>
  <div class="pos-devs">
    <?php foreach ($devices as $d): $t = Pos::tpe($d); $pairing = $d['pair_code'] && strtotime((string)$d['pair_expires']) > time(); $online = $d['last_seen'] && strtotime($d['last_seen']) > time() - 300; ?>
      <div class="pos-dev<?= $focus === (int)$d['id'] ? ' on' : '' ?>">
        <div class="pos-dev-head"><span class="dot <?= $pairing ? 'wait' : ($online ? 'ok' : '') ?>"></span><div class="grow"><b><?= e($d['name']) ?></b><small class="muted"><?= $pairing ? 'En attente d\'appairage' : ($d['station_at'] ? e(($d['bar_name'] ?: 'Tous les points de vente') . ' · ' . (Pos::MODE_LABELS[$d['mode']] ?? 'Vente') . ($d['staff_name'] ? ' · ' . $d['staff_name'] : '')) : 'Poste pas encore choisi') ?><?= $t['mode'] === 'nepting' ? ' · Nepting ' . e($t['host']) : '' ?></small></div>
          <small class="muted"><?= $pairing ? 'à appairer' : ($d['last_seen'] ? 'vu ' . e(ago($d['last_seen'])) : 'jamais connecté') ?> · <?= (int)$d['sales'] ?> vente(s)</small></div>
        <?php if ($pairing): ?><div class="pos-pair"><div class="pos-qr"><?= Qr::svg(Pos::pairUrl($d), 168) ?></div><div><small class="muted">Code d'appairage</small><div class="pos-code"><?= e(chunk_split($d['pair_code'], 3, ' ')) ?></div><small class="muted">Scanne le QR avec l'appareil (ou ouvre <span class="mono"><?= e(abs_url('/caisse/')) ?></span> et saisis le code). Valable jusqu'à <?= e(date('H:i', strtotime($d['pair_expires']))) ?>.</small></div></div><?php endif; ?>
        <details class="pos-set"><summary class="linklike">Nom, terminal de paiement</summary>
          <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="action" value="device_tpe"><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
            <div class="field"><label>Nom</label><input name="name" value="<?= e($d['name']) ?>"></div>
            <div class="field"><label>Paiement par carte</label><select name="mode"><option value="manual">Terminal autonome (le serveur confirme sur l'appli)</option><option value="nepting" <?= $t['mode'] === 'nepting' ? 'selected' : '' ?>>Terminal Nepting piloté par l'appli (Android)</option></select></div>
            <div class="grid2" data-show-if="mode=nepting"><div class="field"><label>Adresse IP du terminal</label><input name="host" value="<?= e($t['host']) ?>" placeholder="192.168.1.50"></div><div class="field"><label>Port</label><input name="port" inputmode="numeric" value="<?= (int)$t['port'] ?>"></div>
              <div class="field"><label>Identifiant caisse (12 chiffres)</label><input name="cj" inputmode="numeric" maxlength="12" value="<?= e($t['cj']) ?>"></div><div class="field"><label>Numéro de caisse</label><input name="ca" inputmode="numeric" maxlength="2" value="<?= e($t['ca']) ?>"></div>
              <div class="field"><label>Version du protocole Caisse-AP</label><input name="cz" inputmode="numeric" maxlength="4" value="<?= e($t['cz']) ?>"><small class="muted">0320 pour la version 3.2 ; à confirmer avec ton prestataire.</small></div><div class="field"><label>Délai maximal (secondes)</label><input name="timeout" inputmode="numeric" value="<?= (int)$t['timeout'] ?>"></div>
              <label class="check"><input type="checkbox" name="crlf" value="1" <?= $t['crlf'] ? 'checked' : '' ?>> <span>Terminer la trame par un retour à la ligne</span></label></div>
            <div class="row mt"><button class="btn btn-sm">Enregistrer</button></div>
          </form>
          <div class="row mt"><form method="post"><?= csrf_field() ?><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="device_code">Nouveau code d'appairage</button></form>
            <form method="post" onsubmit="return confirm('Déconnecter cet appareil ?')"><?= csrf_field() ?><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-light danger" name="action" value="device_revoke">Déconnecter</button></form></div>
        </details>
      </div>
    <?php endforeach; ?>
    <form method="post" class="pos-dev pos-add"><?= csrf_field() ?><input type="hidden" name="action" value="device_add"><b>Appairer un appareil</b><small class="muted">Tablette, téléphone ou écran. Un QR code d'appairage s'affiche ensuite.</small>
      <input name="name" placeholder="Tablette 1"><button class="btn btn-sm">Créer le code</button></form>
  </div>
</section>

<div class="card mt"><h2>Installer l'application</h2>
  <div class="split"><div><b>Web app (tablette ou téléphone)</b><p class="muted">Ouvre <span class="mono"><?= e(abs_url('/caisse/')) ?></span> dans le navigateur de l'appareil, puis « Ajouter à l'écran d'accueil » : elle s'ouvre en plein écran et fonctionne sans réseau pour la carte et les espèces.</p></div>
  <div><b>Application Android</b><p class="muted">Pour les bracelets NFC et le terminal Nepting. Installe le fichier « Entrevo Caisse » fourni par ta plateforme, puis scanne le QR code d'appairage de l'appareil.</p></div></div></div>
