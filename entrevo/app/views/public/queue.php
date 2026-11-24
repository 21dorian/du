<div class="queue-box card">
  <span class="eyebrow">File d'attente virtuelle</span>
  <h1><?= e($ev['name']) ?></h1>
  <p class="muted">Beaucoup de monde en même temps ! Pour que tout le monde achète sans bug, on fait entrer les gens par petits groupes. Ne ferme pas cette page, elle avance toute seule.</p>
  <div class="queue-pos" id="qpos"><?= (int)($st['position'] ?? 0) ?></div>
  <p>personne(s) devant toi · environ <b id="qmin"><?= (int)($st['minutes'] ?? 1) ?></b> min</p>
  <div class="bar"><i id="qbar" style="width:5%"></i></div>
</div>
<script>
(function(){var start=<?= (int)($st['position'] ?? 1) ?>||1;function tick(){fetch(location.pathname+'?json=1',{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.json()}).then(function(j){
if(j.admitted){location.href=j.url;return}document.getElementById('qpos').textContent=j.position;document.getElementById('qmin').textContent=j.minutes;
document.getElementById('qbar').style.width=Math.max(5,Math.min(100,100*(1-(j.position-1)/start)))+'%';setTimeout(tick,8000)}).catch(function(){setTimeout(tick,10000)})}setTimeout(tick,4000)})();
</script>
