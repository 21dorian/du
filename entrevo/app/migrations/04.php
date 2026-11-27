<?php
// ---------- 1.2.0 : nouvelle charte (bleu électrique) si l'ancienne couleur par défaut était gardée ----------
return [
        "UPDATE settings SET v = '#0032fa' WHERE k = 'primary_color' AND LOWER(v) IN ('#ff5a4e', '#5b3df5')",
];
