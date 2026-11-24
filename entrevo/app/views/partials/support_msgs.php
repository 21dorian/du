<?php /** @var array $msgs; bool $staffView */ $staffView ??= false; ?>
<div class="thread">
<?php foreach ($msgs as $m): $mine = $staffView ? $m['author'] === 'staff' : $m['author'] === 'customer'; ?>
  <div class="msg msg-<?= e($m['author']) ?><?= $mine ? ' mine' : '' ?>">
    <div class="msg-meta"><b><?= $m['author'] === 'staff' ? e($staffView ? ($m['user_name'] ?: 'Équipe') : 'Équipe ' . setting('platform_name', 'Entrevo')) : ($m['author'] === 'note' ? 'Note interne' . ($m['user_name'] ? ' · ' . e($m['user_name']) : '') : ($staffView ? 'Demandeur' : 'Toi')) ?></b><small><?= e(fr_date($m['created_at'])) ?></small></div>
    <div class="msg-body"><?= nl2br(e($m['body'])) ?></div>
  </div>
<?php endforeach; ?>
</div>
