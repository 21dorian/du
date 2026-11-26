<?php /** @var array $rows */ ?>
<div class="card table-card"><div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Type</th><th>Contact</th><th>Message</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr style="<?= $r['status'] === 'done' ? 'opacity:.5' : '' ?>"><td><small><?= e(short_date($r['created_at'])) ?></small></td><td><?= $r['kind'] === 'demo' ? 'Démo' : 'Migration' ?></td>
  <td><b><?= e($r['name']) ?></b><?= $r['org_name'] ? ' · ' . e($r['org_name']) : '' ?><br><small><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a><?= $r['phone'] ? ' · ' . e($r['phone']) : '' ?></small></td>
  <td><small><?= nl2br(e((string)$r['message'])) ?></small></td>
  <td class="right"><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light" name="status" value="<?= $r['status'] === 'done' ? 'new' : 'done' ?>"><?= $r['status'] === 'done' ? 'Rouvrir' : 'Traité' ?></button></form></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty">Aucune demande.</td></tr><?php endif; ?></tbody></table></div></div>
