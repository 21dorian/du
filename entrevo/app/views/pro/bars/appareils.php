<?php /** @var array $devices, $bars; int $focus; string $btab, $bbase */ echo view('pro/bars/_nav', compact('btab', 'bbase')); $pos = url('/caisse/'); ?>
<section class="card pos-card">
  <div class="card-head"><div><h2>Appareils</h2><p class="muted mb0">Une application dédiée pour les bars : cashless (QR du billet ou bracelet NFC), carte bancaire (terminal autonome ou Nepting) et espèces, même sans réseau pour la carte et les espèces.</p></div>
    <a class="btn btn-sm btn-light" href="<?= $pos ?>" target="_blank">Ouvrir l'application</a></div>
  <div class="pos-devs">
    <?php foreach ($devices as $d): $t = Pos::tpe($d); $pairing = $d['pair_code'] && strtotime((string)$d['pair_expires']) > time(); $online = $d['last_seen'] && strtotime($d['last_seen']) > time() - 300; ?>
      <div class="pos-dev<?= $focus === (int)$d['id'] ? ' on' : '' ?>">
        <div class="pos-dev-head"><span class="dot <?= $pairing ? 'wait' : ($online ? 'ok' : '') ?>"></span><div class="grow"><b><?= e($d['name']) ?></b><small class="muted"><?= $d['bar_name'] ? e($d['bar_name']) : 'Bar choisi sur l\'appareil' ?> · <?= $t['mode'] === 'nepting' ? 'Nepting ' . e($t['host']) : 'TPE autonome' ?></small></div>
          <small class="muted"><?= $pairing ? 'à appairer' : ($d['last_seen'] ? 'vue ' . e(ago($d['last_seen'])) : 'jamais connectée') ?> · <?= (int)$d['sales'] ?> vente(s)</small></div>
        <?php if ($pairing): ?><div class="pos-pair"><div class="pos-qr"><?= Qr::svg(Pos::pairUrl($d), 168) ?></div><div><small class="muted">Code d'appairage</small><div class="pos-code"><?= e(chunk_split($d['pair_code'], 3, ' ')) ?></div><small class="muted">Scanne le QR avec l'appareil du bar (ou ouvre <span class="mono"><?= e(abs_url('/caisse/')) ?></span> et saisis le code). Valable jusqu'à <?= e(date('H:i', strtotime($d['pair_expires']))) ?>.</small></div></div><?php endif; ?>
        <details class="pos-set"><summary class="linklike">Réglages, terminal de paiement</summary>
          <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="action" value="device_tpe"><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
            <div class="grid2"><div class="field"><label>Nom</label><input name="name" value="<?= e($d['name']) ?>"></div>
              <div class="field"><label>Bar</label><select name="bar_id"><option value="">Choisi sur l'appareil</option><?php foreach ($bars as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)$d['bar_id'] === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div></div>
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
    <form method="post" class="pos-dev pos-add"><?= csrf_field() ?><input type="hidden" name="action" value="device_add"><b>Ajouter un appareil</b><small class="muted">Tablette ou téléphone d'un bar. Un QR code d'appairage s'affiche ensuite.</small>
      <input name="name" placeholder="Caisse bar principal"><select name="bar_id"><option value="">Bar choisi sur l'appareil</option><?php foreach ($bars as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select><button class="btn btn-sm">Créer</button></form>
  </div>
</section>

<div class="card mt"><h2>Installer l'application</h2>
  <div class="split"><div><b>Web app (tablette ou téléphone)</b><p class="muted">Ouvre <span class="mono"><?= e(abs_url('/caisse/')) ?></span> dans le navigateur de l'appareil, puis « Ajouter à l'écran d'accueil » : elle s'ouvre en plein écran et fonctionne sans réseau pour la carte et les espèces.</p></div>
  <div><b>Application Android</b><p class="muted">Pour les bracelets NFC et le terminal Nepting. Installe le fichier « Entrevo Caisse » fourni par ta plateforme, puis scanne le QR code d'appairage de l'appareil.</p></div></div></div>
