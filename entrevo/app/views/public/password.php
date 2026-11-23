<div class="auth-box card center">
  <div class="state-mark info">🔒</div>
  <h1><?= e($ev['name']) ?></h1>
  <p class="muted">Événement privé. Entre le mot de passe donné par l'organisateur.</p>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?><div class="row" style="flex-wrap:nowrap;gap:0"><input type="password" name="password" required autofocus aria-label="Mot de passe"><button class="btn">Entrer</button></div></form>
</div>
