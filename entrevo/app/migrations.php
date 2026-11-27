<?php
// Évolutions de la base de données : un fichier par version dans app/migrations/ (01.php, 02.php…).
// Pour une évolution, ajoute le fichier suivant ; il est appliqué automatiquement au premier chargement après une mise à jour.
$m = [];
foreach (glob(__DIR__ . '/migrations/[0-9]*.php') ?: [] as $f) $m[(int)basename($f, '.php')] = require $f;
ksort($m);
return $m;
