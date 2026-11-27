<?php /** @var string $tab; array $pages; ?array $edit */ ?>
<nav class="tabs"><a class="<?= $tab === 'pages' ? 'on' : '' ?>" href="<?= url('/admin/contenus') ?>">Pages</a><a class="<?= $tab === 'emails' ? 'on' : '' ?>" href="<?= url('/admin/contenus', ['tab' => 'emails']) ?>">Habillage des e-mails</a></nav>
<?php if ($tab === 'pages'): ?>
<p class="muted lead-sm">FAQ, à propos, comment ça marche, conditions d'utilisation… Des pages simples, en ligne à l'adresse /page/…, avec un lien en pied de page si tu veux. Les CGV et mentions légales se règlent dans Réglages › Textes légaux.</p>
<div class="split-3">
  <div>
    <?php if ($edit !== null): $p = $edit ?: ['id' => 0, 'title' => '', 'slug' => '', 'body' => '', 'in_footer' => 1, 'published' => 1, 'sort' => 0]; ?>
    <form class="card" method="post" id="editeur"><?= csrf_field() ?><input type="hidden" name="action" value="page"><input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
      <div class="grid2"><div class="field"><label>Titre</label><input name="title" required value="<?= e($p['title']) ?>" placeholder="Questions fréquentes"></div>
        <div class="field"><label>Adresse</label><div class="input-prefix"><span>/page/</span><input name="slug" value="<?= e($p['slug']) ?>" placeholder="faq"></div></div></div>
      <div class="field"><label>Contenu</label><textarea name="body" rows="18" class="mono u-fs14" data-preview-src><?= e((string)$p['body']) ?></textarea>
        <small>## Titre · ### Sous-titre · - liste · **gras** · *italique* · [texte du lien](https://…)</small></div>
      <div class="row" style="gap:22px"><label class="check"><input type="checkbox" name="published" value="1" <?= $p['published'] ? 'checked' : '' ?>> <span>En ligne</span></label>
        <label class="check"><input type="checkbox" name="in_footer" value="1" <?= $p['in_footer'] ? 'checked' : '' ?>> <span>Lien en pied de page</span></label>
        <label class="inline-field">Ordre <input type="number" name="sort" value="<?= (int)$p['sort'] ?>" class="u-w80"></label></div>
      <div class="form-actions mt"><button class="btn">Enregistrer</button><?php if ($p['id']): ?><a class="btn btn-ghost" href="<?= url('/page/' . $p['slug']) ?>" target="_blank">Voir</a><button class="btn btn-ghost u-red" name="action" value="delete" formnovalidate data-confirm="Supprimer cette page ?">Supprimer</button><?php endif; ?></div>
    </form>
    <?php endif; ?>
  </div>
  <div class="card"><div class="card-head"><h2>Pages</h2><a class="btn btn-sm" href="<?= url('/admin/contenus', ['edit' => 'new']) ?>#editeur">+ Page</a></div>
    <?php foreach ($pages as $p): ?><div class="list-item"><a class="grow" href="<?= url('/admin/contenus', ['edit' => $p['id']]) ?>"><b><?= e($p['title']) ?></b><br><small class="muted">/page/<?= e($p['slug']) ?></small></a><?= $p['published'] ? ($p['in_footer'] ? '<span class="badge green">Pied de page</span>' : '<span class="badge grey">En ligne</span>') : '<span class="badge grey">Brouillon</span>' ?></div><?php endforeach; ?>
    <?php if (!$pages): ?><p class="muted mb0">Aucune page. Idées : « Questions fréquentes », « Qui sommes-nous », « Organisateurs : comment ça marche ».</p><?php endif; ?>
  </div>
</div>
<?php else: ?>
<p class="muted lead-sm">L'apparence de tous les e-mails envoyés par la plateforme (billets, rappels, relances, support…). Le logo vient de Réglages › Apparence.</p>
<div class="split">
  <form class="card" method="post" id="mailform"><?= csrf_field() ?><input type="hidden" name="action" value="email">
    <div class="field"><label>En-tête</label><div class="seg seg-full"><?php foreach (['light' => 'Clair', 'dark' => 'Noir', 'color' => 'Couleur'] as $k => $l): ?><label class="seg-r"><input type="radio" name="email_header" value="<?= $k ?>" <?= setting('email_header', 'light') === $k ? 'checked' : '' ?>><span><?= $l ?></span></label><?php endforeach; ?></div></div>
    <div class="field"><label>Couleur des boutons (et de l'en-tête « Couleur »)</label><input type="color" name="email_accent" value="<?= e((string)(setting('email_accent') ?: '#1d1d1f')) ?>"></div>
    <div class="field"><label>Signature</label><textarea name="email_signature" rows="2" placeholder="À très vite,&#10;L'équipe <?= e((string)setting('platform_name')) ?>"><?= e((string)setting('email_signature')) ?></textarea></div>
    <div class="field"><label>Pied de page</label><textarea name="email_footer" rows="2" placeholder="Raison sociale, adresse, SIRET…"><?= e((string)setting('email_footer')) ?></textarea></div>
    <div class="row"><input type="email" name="test_to" placeholder="M'envoyer un aperçu à…" style="max-width:260px"><button class="btn">Enregistrer</button></div>
  </form>
  <div class="card"><div class="card-head"><h2>Aperçu</h2><small class="muted">se met à jour en direct</small></div><iframe class="mail-preview" id="mailprev" src="<?= url('/admin/contenus/apercu-email') ?>" title="Aperçu de l'e-mail"></iframe></div>
</div>
<script>
(() => { const f = document.getElementById('mailform'), fr = document.getElementById('mailprev'), base = fr.src; let t;
  f.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { const d = new FormData(f), q = new URLSearchParams(); ['email_header', 'email_accent', 'email_signature', 'email_footer'].forEach(k => q.set(k, d.get(k) || '')); fr.src = base + '?' + q; }, 300); }); })();
</script>
<?php endif; ?>
