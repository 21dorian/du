<?php
declare(strict_types=1);

/**
 * Petits éléments de visualisation rendus côté serveur (sans JS) : mini-courbe, anneau de remplissage, variation.
 * Couleurs : série = bleu #0071e3 (contraste ≥ 3:1 sur fond clair), atténué = gris neutre.
 */
final class Viz
{
    public const SERIES = '#0071e3';
    public const MUTED = '#7d8699';

    /** Mini-courbe (12+ points) : la courbe en gris, le dernier point en accent. */
    public static function sparkline(array $values, int $w = 120, int $h = 32): string
    {
        $values = array_values(array_map('floatval', $values));
        $n = count($values);
        if ($n < 2) return '';
        $max = max($values) ?: 1;
        $pts = [];
        foreach ($values as $i => $v) $pts[] = round($i / ($n - 1) * ($w - 6) + 3, 1) . ',' . round($h - 4 - ($v / $max) * ($h - 8), 1);
        $last = explode(',', end($pts));
        return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" aria-hidden="true">'
            . '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . self::MUTED . '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>'
            . '<circle cx="' . $last[0] . '" cy="' . $last[1] . '" r="4" fill="' . self::SERIES . '" stroke="#fff" stroke-width="2"/></svg>';
    }

    /** Anneau de remplissage (jauge). La piste est un ton plus clair de la même teinte. */
    public static function ring(?float $pct, string $center = '', int $size = 84): string
    {
        $r = ($size - 12) / 2; $c = 2 * M_PI * $r; $p = $pct === null ? 0 : max(0, min(100, $pct));
        $color = $p >= 95 ? '#d92d3a' : self::SERIES;
        return '<svg class="ring" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '" role="img" aria-label="' . e($center ?: round($p) . ' %') . '">'
            . '<circle cx="' . $size / 2 . '" cy="' . $size / 2 . '" r="' . $r . '" fill="none" stroke="#e1e8ff" stroke-width="8"/>'
            . '<circle cx="' . $size / 2 . '" cy="' . $size / 2 . '" r="' . $r . '" fill="none" stroke="' . $color . '" stroke-width="8" stroke-linecap="round" stroke-dasharray="' . round($c * $p / 100, 2) . ' ' . round($c, 2) . '" transform="rotate(-90 ' . $size / 2 . ' ' . $size / 2 . ')"/>'
            . '<text x="50%" y="50%" text-anchor="middle" dominant-baseline="central" font-family="Barlow Condensed,sans-serif" font-weight="800" font-size="' . round($size / 4.2) . '" fill="#0f0f14">' . e($center ?: ($pct === null ? '∞' : round($p) . '%')) . '</text></svg>';
    }

    /** Variation signée vs la période précédente (vert si c'est mieux, rouge si c'est moins bien), avec flèche (pas la couleur seule). */
    public static function delta(float $cur, float $prev, string $period = 'vs période préc.', bool $upIsGood = true): string
    {
        if ($prev == 0.0) return $cur > 0 ? '<span class="delta up">▲ nouveau</span> <small>' . e($period) . '</small>' : '<small class="muted">— ' . e($period) . '</small>';
        $d = ($cur - $prev) / abs($prev) * 100;
        $good = $upIsGood ? $d >= 0 : $d <= 0;
        return '<span class="delta ' . ($good ? 'up' : 'down') . '">' . ($d >= 0 ? '▲ +' : '▼ ') . e(number_format($d, abs($d) < 10 ? 1 : 0, ',', ' ')) . ' %</span> <small>' . e($period) . '</small>';
    }

    /** Données d'un graphique pour charts.js (+ tableau accessible). */
    public static function chart(string $type, array $data, string $title, string $format = 'money', int $height = 240): string
    {
        $json = e(json_encode(['type' => $type, 'format' => $format] + $data, JSON_UNESCAPED_UNICODE));
        $rows = '';
        foreach ($data['labels'] ?? [] as $i => $l) {
            $rows .= '<tr><td>' . e($l) . '</td>';
            foreach ($data['series'] as $s) $rows .= '<td class="num">' . e($format === 'money' ? money((int)($s['values'][$i] ?? 0)) : (string)($s['values'][$i] ?? 0)) . '</td>';
            $rows .= '</tr>';
        }
        $head = '<th>' . e($data['xlabel'] ?? '') . '</th>' . implode('', array_map(fn($s) => '<th class="num">' . e($s['name']) . '</th>', $data['series']));
        return '<div class="viz" data-chart="' . $json . '" style="height:' . $height . 'px" role="img" aria-label="' . e($title) . '"></div>'
            . '<details class="viz-table"><summary>Voir les données</summary><div class="table-wrap"><table class="table"><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table></div></details>';
    }
}
