<?php /** @var array $list, $orgs; ?array $sel; string $host, $ip */
$st = ['active' => ['green', 'Actif'], 'pending' => ['orange', 'DNS à vérifier'], 'off' => ['grey', 'Coupé']]; ?>
<p class="muted lead-sm">La marque blanche sert la billetterie d'un organisateur sur son propre domaine (par exemple <span class="mono">billetterie.monfestival.fr</span>) : sa page et son mini-site à la racine, ses couleurs et son logo, sans la marque de la plateforme. Les e-mails de commande partent à son nom.</p>
<div class="card table-card">
  <div class="card-head"><h2>Domaines</h2><a class="btn btn-sm" href="<?= url('/admin/marque-blanche', ['o' => 'new']) ?>#fiche">+ Domaine</a></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Organisateur</th><th>Domaine</th><th>État</th><th class="num">Ventes (30 j)</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $o): [$c, $l] = (int)$o['white_label'] ? ($st[$o['domain_status']] ?? ['grey', '—']) : $st['off']; ?>
      <tr><td><a href="<?= url('/admin/organisateurs/' . $o['id']) ?>"><b><?= e($o['name']) ?></b></a></td>
        <td><?php if ($o['custom_domain']): ?><a class="mono" target="_blank" rel="noopener" href="https://<?= e($o['custom_domain']) ?>"><?= e($o['custom_domain']) ?></a><?php endif; ?><?= $o['domain_checked_at'] ? '<br><small class="muted">vérifié ' . e(ago($o['domain_checked_at'])) . '</small>' : '' ?></td>
        <td><span class="badge <?= $c ?>"><?= $l ?></span></td><td class="num"><?= e(money((int)$o['sales30'])) ?></td>
        <td class="num"><a class="btn btn-sm btn-light" href="<?= url('/admin/marque-blanche', ['o' => $o['id']]) ?>#fiche">Gérer</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="5" class="empty">Aucun organisateur en marque blanche.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php if ($sel !== null): $s = $sel ? WhiteLabel::settings($sel) : WhiteLabel::settings([]); ?>
<div class="split mt" id="fiche">
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="save">
    <h2><?= $sel ? e($sel['name']) : 'Nouveau domaine' ?></h2>
    <?php if ($sel): ?><input type="hidden" name="organizer_id" value="<?= (int)$sel['id'] ?>">
    <?php else: ?><div class="field"><label>Organisateur</label><select name="organizer_id" required><?php foreach ($orgs as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="field"><label>Domaine</label><input name="domain" required placeholder="billetterie.monfestival.fr" value="<?= e((string)($sel['custom_domain'] ?? '')) ?>"><small class="muted">Un sous-domaine dédié est le plus simple. Le « www. » est ignoré.</small></div>
    <label class="check"><input type="checkbox" name="mail_brand" value="1" <?= $s['mail_brand'] ? 'checked' : '' ?>> <span>E-mails de commande au nom et aux couleurs de l'organisateur</span></label>
    <div class="field mt"><label>Nom d'expéditeur <small class="muted">facultatif</small></label><input name="mail_name" value="<?= e((string)$s['mail_name']) ?>" placeholder="<?= e((string)($sel['name'] ?? '')) ?>"></div>
    <label class="check"><input type="checkbox" name="hide_credit" value="1" <?= $s['hide_credit'] ? 'checked' : '' ?>> <span>Masquer la mention « billetterie <?= e((string)setting('platform_name')) ?> » en pied de page</span></label>
    <div class="row mt"><button class="btn">Enregistrer et vérifier</button></div>
    <?php if ($sel && $sel['custom_domain']): ?>
    <div class="row mt wl-actions">
      <button class="btn btn-sm btn-light" form="wl-check">Vérifier le DNS</button>
      <?php if ($sel['domain_status'] !== 'active'): ?><button class="btn btn-sm btn-light" form="wl-force" onclick="return confirm('Activer sans vérification ? À réserver aux domaines derrière un CDN ou un proxy.')">Activer sans vérifier</button><?php endif; ?>
      <?php if ((int)$sel['white_label']): ?><button class="btn btn-sm btn-light danger" form="wl-off" onclick="return confirm('Couper la marque blanche ?')">Couper</button><?php endif; ?>
    </div>
    <?php endif; ?>
  </form>
  <?php if ($sel): foreach (['check' => 'wl-check', 'force' => 'wl-force', 'disable' => 'wl-off'] as $act => $fid): ?><form id="<?= $fid ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="organizer_id" value="<?= (int)$sel['id'] ?>"><input type="hidden" name="action" value="<?= $act ?>"></form><?php endforeach; endif; ?>
  <div class="card"><h2>Mise en place</h2>
    <ol class="wl-steps">
      <li><b>Chez le registraire du domaine de l'organisateur</b>, créer un enregistrement DNS pour <span class="mono"><?= e((string)($sel['custom_domain'] ?? 'billetterie.exemple.fr')) ?></span> :
        <?php if ($host && !filter_var($host, FILTER_VALIDATE_IP)): ?><div class="wl-dns"><span>CNAME</span><span class="mono"><?= e($host) ?></span></div>
        <small class="muted">ou, pour un domaine sans sous-domaine, un enregistrement A vers <span class="mono"><?= e($ip ?: 'l\'adresse IP de l\'hébergement') ?></span>.</small>
        <?php else: ?><div class="wl-dns"><span>A</span><span class="mono"><?= e($ip ?: $host) ?></span></div><small class="muted">Renseigne l'adresse du site dans Réglages pour obtenir aussi la cible CNAME.</small><?php endif; ?></li>
      <li><b>Dans le panneau de l'hébergeur</b> (cPanel chez Dyjix : « Domaines »), ajouter ce domaine en le faisant pointer vers <b>le même dossier</b> que la billetterie.</li>
      <li><b>Activer le HTTPS</b> pour ce domaine (AutoSSL / Let's Encrypt dans cPanel). Comptez quelques minutes.</li>
      <li>Revenir ici et cliquer sur <b>Vérifier le DNS</b>. Une fois actif, le domaine sert la page de l'organisateur et son mini-site.</li>
    </ol>
    <p class="muted mb0"><small>Les commandes, le contrôle d'accès et l'espace organisateur restent sur la plateforme ; seul le parcours de l'acheteur change d'adresse.</small></p>
  </div>
</div>
<?php endif; ?>
