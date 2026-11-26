<?php /** @var array $events; bool $asked */ ?>
<div class="container" style="max-width:860px;padding:28px 16px">
  <h1>Mes favoris</h1>
  <p class="muted">Gardés sur cet appareil, sans compte. Touche « Favori » sur la page d'un événement pour l'ajouter.</p>
  <div id="fav-list"><?php foreach ($events as $ev): ?><div class="fav-item" data-fav-row="<?= (int)$ev['id'] ?>"><?= view('public/_ev_mini', ['ev' => $ev]) ?><button type="button" class="btn btn-sm btn-ghost" data-fav-del="<?= (int)$ev['id'] ?>">Retirer</button></div><?php endforeach; ?></div>
  <div class="empty" id="fav-empty" <?= $events ? 'hidden' : '' ?>>Aucun favori pour l'instant. <a href="<?= url('/') ?>">Voir les événements</a></div>
</div>
<script>
(function(){var K='entrevo_favs',get=function(){try{return JSON.parse(localStorage.getItem(K)||'[]')}catch(e){return[]}},set=function(a){try{localStorage.setItem(K,JSON.stringify(a))}catch(e){}};
var asked=<?= $asked ? 'true' : 'false' ?>,f=get();if(!asked&&f.length){location.replace(location.pathname+'?ids='+f.join(','));return}
document.querySelectorAll('[data-fav-del]').forEach(function(b){b.onclick=function(){var id=+b.dataset.favDel;set(get().filter(function(x){return x!==id}));var r=document.querySelector('[data-fav-row="'+id+'"]');if(r)r.remove();if(!document.querySelector('[data-fav-row]'))document.getElementById('fav-empty').hidden=false}})})();
</script>
