<?php /** @var array $org, $settings, $items, $messages, $contacts, $k; ?array $edit; string $tab, $kind */
$base = '/o/' . $org['slug']; $on = (bool)$settings['enabled']; ?>
<div class="site-head card">
  <div><span class="badge <?= $on ? 'green' : 'grey' ?>"><?= $on ? 'En ligne' : 'Hors ligne' ?></span>
    <p class="mb0 mt"><?= $on ? 'Ton mini-site enrichit ta page publique : un menu avec tes pages, tes actualités, un formulaire de contact et l\'inscription à ta lettre d\'information.' : 'Active-le dans Réglages : ta page publique gagnera un menu, tes pages, tes actualités, un formulaire de contact et une inscription à ta lettre d\'information.' ?></p></div>
  <a class="btn btn-light" target="_blank" href="<?= url($base) ?>">Voir ma page</a>
</div>
<nav class="tabs mb">
  <?php foreach (SITE_TABS as $t => $l): ?><a class="<?= $t === $tab ? 'on' : '' ?>" href="<?= url('/pro/mini-site', ['tab' => $t]) ?>"><?= $l ?><?= $t === 'messages' && $k['unread'] ? ' <span class="badge red">' . $k['unread'] . '</span>' : '' ?><?= $t === 'abonnes' ? ' <span class="muted">' . $k['subs'] . '</span>' : '' ?></a><?php endforeach; ?>
</nav>

<?php if (in_array($tab, ['pages', 'actualites'], true)): $news = $kind === 'news'; ?>
<div class="split">
  <div class="card table-card">
    <div class="card-head"><h2><?= $news ? 'Actualités' : 'Pages' ?></h2><a class="btn btn-sm" href="<?= url('/pro/mini-site', ['tab' => $tab, 'edit' => 'new']) ?>">+ <?= $news ? 'Actualité' : 'Page' ?></a></div>
    <?php foreach ($items as $i => $it): ?>
      <div class="list-item site-li<?= $edit && (int)($edit['id'] ?? 0) === (int)$it['id'] ? ' on' : '' ?>">
        <a class="grow" href="<?= url('/pro/mini-site', ['tab' => $tab, 'edit' => $it['id']]) ?>"><b><?= e($it['title']) ?></b><br><small class="muted"><?= $news ? e(fr_date((string)($it['published_at'] ?: $it['created_at']), false)) : e($base . '/p/' . $it['slug']) ?><?= !$news && !$it['in_menu'] ? ' · hors menu' : '' ?></small></a>
        <?= (int)$it['published'] ? ($news && $it['published_at'] > now() ? '<span class="badge orange">programmée</span>' : '') : '<span class="badge grey">brouillon</span>' ?>
        <?php if (!$news): ?><form method="post" class="row" style="gap:2px"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="page_id" value="<?= (int)$it['id'] ?>">
          <button class="icon-btn" name="dir" value="up" <?= $i ? '' : 'disabled' ?> aria-label="Monter">↑</button><button class="icon-btn" name="dir" value="down" <?= $i < count($items) - 1 ? '' : 'disabled' ?> aria-label="Descendre">↓</button></form><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$items): ?><p class="muted" style="padding:0 24px 20px"><?= $news ? 'Annonce une programmation, raconte une édition, présente un artiste : tes actualités apparaissent sur ta page.' : 'Ajoute « Qui sommes-nous », « Infos pratiques », « Accessibilité »… Elles apparaissent dans le menu de ta page.' ?></p><?php endif; ?>
  </div>
  <?php if ($edit !== null): $p = $edit ?: ['id' => 0, 'title' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'image' => null, 'in_menu' => 1, 'published' => 1, 'published_at' => null]; ?>
  <form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="kind" value="<?= $kind ?>">
    <div class="card-head"><h2><?= $p['id'] ? 'Modifier' : ($news ? 'Nouvelle actualité' : 'Nouvelle page') ?></h2><?php if ($p['id'] && $p['published']): ?><a class="btn btn-sm btn-light" target="_blank" href="<?= url($base . ($news ? '/actualites/' : '/p/') . $p['slug']) ?>">Voir</a><?php endif; ?></div>
    <div class="field"><label>Titre</label><input name="title" required value="<?= e($p['title']) ?>"></div>
    <div class="field"><label>Adresse</label><div class="input-prefix"><span><?= e($base . ($news ? '/actualites/' : '/p/')) ?></span><input name="slug" value="<?= e($p['slug']) ?>" placeholder="créée depuis le titre"></div></div>
    <div class="field"><label><?= $news ? 'Chapeau' : 'Résumé' ?> <small class="muted">facultatif</small></label><input name="excerpt" maxlength="500" value="<?= e((string)$p['excerpt']) ?>"></div>
    <div class="field"><label>Texte</label><textarea name="body" rows="12"><?= e((string)$p['body']) ?></textarea><small class="muted">Mise en forme : **gras**, *italique*, [texte du lien](https://…), une ligne qui commence par « - » pour une liste.</small></div>
    <div class="field"><label>Image</label><?php if ($p['image']): ?><img class="site-thumb" src="<?= e(url($p['image'])) ?>" alt=""><label class="check"><input type="checkbox" name="remove_image" value="1"> <span>Retirer l'image</span></label><?php endif; ?><input type="file" name="image" accept="image/*"></div>
    <?php if ($news): ?><div class="field"><label>Date de publication</label><input type="datetime-local" name="published_at" value="<?= e($p['published_at'] ? date('Y-m-d\TH:i', strtotime($p['published_at'])) : date('Y-m-d\TH:i')) ?>"><small class="muted">Une date future programme la publication.</small></div>
    <?php else: ?><label class="check"><input type="checkbox" name="in_menu" value="1" <?= $p['in_menu'] ? 'checked' : '' ?>> <span>Afficher dans le menu</span></label><?php endif; ?>
    <label class="check"><input type="checkbox" name="published" value="1" <?= $p['published'] ? 'checked' : '' ?>> <span>Publié</span></label>
    <div class="row mt"><button class="btn">Enregistrer</button><?php if ($p['id']): ?><button class="btn btn-light danger" name="action" value="delete" formnovalidate data-confirm="Supprimer ?">Supprimer</button><?php endif; ?></div>
  </form>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'messages'): ?>
<div class="card">
  <?php foreach ($messages as $m): ?>
    <details class="site-msg<?= $m['read_at'] ? '' : ' unread' ?>"><summary><span class="grow"><b><?= e($m['name']) ?></b> · <?= e($m['subject'] ?: mb_strimwidth($m['body'], 0, 60, '…')) ?></span><small class="muted"><?= e(ago($m['created_at'])) ?></small></summary>
      <div class="site-msg-body"><p><?= nl2br(e($m['body'])) ?></p>
        <div class="row"><a class="btn btn-sm" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . ($m['subject'] ?: 'ton message')) ?>">Répondre à <?= e($m['email']) ?></a>
          <?php if (!$m['read_at']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="read">Marquer comme lu</button></form><?php endif; ?>
          <form method="post" onsubmit="return confirm('Supprimer ce message ?')"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-light danger" name="action" value="delete_message">Supprimer</button></form></div></div></details>
  <?php endforeach; ?>
  <?php if (!$messages): ?><p class="muted mb0">Les messages envoyés depuis ton formulaire de contact arrivent ici, et par e-mail.</p><?php endif; ?>
</div>

<?php elseif ($tab === 'abonnes'): ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Abonnés</span><b><?= (int)$k['subs'] ?></b></div>
  <div class="kpi-mini"><span>Nouveaux (30 j)</span><b><?= (int)$k['subs30'] ?></b></div>
</div>
<div class="split">
  <div class="card table-card"><div class="card-head"><h2>Lettre d'information</h2><a class="btn btn-sm btn-light" href="<?= url('/pro/mini-site', ['tab' => 'abonnes', 'export' => 'csv']) ?>">CSV</a></div>
    <?php foreach ($contacts as $c): ?><div class="list-item"><span class="grow"><?= e($c['email']) ?><?= $c['name'] ? ' · ' . e($c['name']) : '' ?><br><small class="muted"><?= e(['site' => 'Inscrit sur ta page', 'contact' => 'Via le formulaire de contact', 'ajout' => 'Ajouté par toi'][$c['source']] ?? (string)$c['source']) ?> · <?= e(ago($c['created_at'])) ?></small></span>
      <?php if ($c['unsub_at']): ?><span class="badge grey">désinscrit</span><?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="contact_id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="unsub">Retirer</button></form><?php endif; ?></div><?php endforeach; ?>
    <?php if (!$contacts): ?><p class="muted" style="padding:0 24px 20px">Personne pour l'instant. Le formulaire d'inscription est en bas de chaque page de ton mini-site.</p><?php endif; ?>
  </div>
  <div>
    <div class="card"><h2>Écrire à tes abonnés</h2><p class="muted">Tes abonnés font partie de l'audience « contacts qui acceptent tes e-mails » de tes campagnes. Le lien de désinscription de chaque e-mail fonctionne pour eux aussi.</p><a class="btn" href="<?= url('/pro/campagnes') ?>">Créer une campagne</a></div>
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="add_contacts"><h2>Ajouter des abonnés</h2><textarea name="emails" rows="4" placeholder="une adresse par ligne"></textarea><small class="muted">Uniquement des personnes qui ont accepté de recevoir tes annonces.</small><button class="btn btn-sm mt">Ajouter</button></form>
  </div>
</div>

<?php else: ?>
<?php $wlOn = (int)($org['white_label'] ?? 0) && ($org['domain_status'] ?? '') === 'active' && $org['custom_domain']; ?>
<div class="card wl-info"><b>Ton propre nom de domaine</b>
  <?php if ($wlOn): ?><p class="mb0">Ta billetterie est servie sur <a class="mono" target="_blank" rel="noopener" href="https://<?= e($org['custom_domain']) ?>">https://<?= e($org['custom_domain']) ?></a>, à tes couleurs, et tes e-mails de commande partent à ton nom.</p>
  <?php elseif ($org['custom_domain'] ?? null): ?><p class="mb0">Le domaine <span class="mono"><?= e($org['custom_domain']) ?></span> est en cours de mise en place par notre équipe.</p>
  <?php else: ?><p class="mb0">Sers ta billetterie et ton mini-site sur ton domaine (billetterie.tonfestival.fr), sans notre marque : c'est l'option marque blanche. <a href="<?= url('/pro/aide') ?>">Demande-la au support</a>.</p><?php endif; ?>
</div>
<form method="post" class="split"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
  <div class="card"><h2>Mini-site</h2>
    <label class="check"><input type="checkbox" name="enabled" value="1" <?= $on ? 'checked' : '' ?>> <span><b>Mettre le mini-site en ligne</b></span></label>
    <div class="field mt"><label>Texte d'accueil</label><textarea name="intro" rows="6" placeholder="Présente ton projet en quelques lignes : il s'affiche en haut de ta page, avant tes événements."><?= e((string)$settings['intro']) ?></textarea><small class="muted">**gras**, *italique*, [lien](https://…), listes avec « - ».</small></div>
    <label class="check"><input type="checkbox" name="news_home" value="1" <?= $settings['news_home'] ? 'checked' : '' ?>> <span>Montrer les 3 dernières actualités sur l'accueil</span></label>
    <div class="field mt"><label>Pied de page</label><textarea name="footer" rows="3" placeholder="Adresse, licence d'entrepreneur de spectacles, partenaires…"><?= e((string)$settings['footer']) ?></textarea></div>
    <p class="muted mb0"><small>Couleur, logo, image de couverture et réseaux sociaux se règlent dans <a href="<?= url('/pro/organisation') ?>">Organisation</a>.</small></p>
  </div>
  <div class="card"><h2>Contact & lettre d'information</h2>
    <label class="check"><input type="checkbox" name="contact" value="1" <?= $settings['contact'] ? 'checked' : '' ?>> <span>Formulaire de contact</span></label>
    <div class="field mt"><label>Recevoir les messages sur</label><input type="email" name="contact_email" value="<?= e((string)$settings['contact_email']) ?>" placeholder="<?= e((string)($org['email'] ?? '')) ?>"></div>
    <label class="check"><input type="checkbox" name="newsletter" value="1" <?= $settings['newsletter'] ? 'checked' : '' ?>> <span>Inscription à la lettre d'information</span></label>
    <div class="field mt"><label>Phrase d'invitation</label><input name="newsletter_text" maxlength="300" value="<?= e((string)$settings['newsletter_text']) ?>"></div>
    <button class="btn mt">Enregistrer</button>
  </div>
</form>
<?php endif; ?>
