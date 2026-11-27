<?php
declare(strict_types=1);

/** 4.1 : page d'événement composée par blocs (éditeur visuel). */
final class PageBlocks
{
    public const TYPES = [
        'text' => 'Texte', 'image' => 'Image', 'gallery' => 'Galerie', 'video' => 'Vidéo', 'lineup' => 'Line-up', 'highlights' => 'Points forts',
        'quote' => 'Citation', 'button' => 'Bouton', 'map' => 'Carte', 'partners' => 'Partenaires', 'countdown' => 'Compte à rebours', 'divider' => 'Séparateur',
    ];

    public static function get(array $ev): array
    {
        $j = json_decode((string)($ev['page_blocks'] ?? ''), true);
        return is_array($j) ? $j : [];
    }

    private static function str($v, int $n): string { return mb_substr(trim((string)$v), 0, $n); }
    private static function img($v): string { $v = (string)$v; return preg_match('~^uploads/[0-9-]+/[A-Za-z0-9_-]+\.(jpe?g|png|webp|gif)$~', $v) ? $v : ''; }
    private static function link($v): string { $v = trim((string)$v); return preg_match('~^https?://~i', $v) || str_starts_with($v, '/') ? mb_substr($v, 0, 500) : ''; }

    /** Nettoie les blocs envoyés par l'éditeur. */
    public static function clean(array $in): array
    {
        $out = [];
        foreach (array_slice($in, 0, 40) as $b) {
            $t = (string)($b['type'] ?? '');
            if (!isset(self::TYPES[$t])) continue;
            $o = ['type' => $t, 'title' => self::str($b['title'] ?? '', 140)];
            switch ($t) {
                case 'text': $o['body'] = self::str($b['body'] ?? '', 6000); $o['align'] = ($b['align'] ?? '') === 'center' ? 'center' : 'left'; break;
                case 'image': $o['src'] = self::img($b['src'] ?? ''); $o['caption'] = self::str($b['caption'] ?? '', 200); $o['wide'] = !empty($b['wide']) ? 1 : 0; break;
                case 'gallery': $o['images'] = array_values(array_filter(array_map([self::class, 'img'], array_slice((array)($b['images'] ?? []), 0, 24)))); break;
                case 'video': $o['url'] = self::link($b['url'] ?? ''); break;
                case 'lineup': $o['auto'] = !empty($b['auto']) ? 1 : 0; $o['items'] = array_values(array_filter(array_map(fn($x) => ['name' => self::str($x['name'] ?? '', 80), 'when' => self::str($x['when'] ?? '', 40), 'text' => self::str($x['text'] ?? '', 200), 'photo' => self::img($x['photo'] ?? '')], array_slice((array)($b['items'] ?? []), 0, 40)), fn($x) => $x['name'] !== '')); break;
                case 'highlights': $o['items'] = array_values(array_filter(array_map(fn($x) => ['title' => self::str($x['title'] ?? '', 80), 'text' => self::str($x['text'] ?? '', 300)], array_slice((array)($b['items'] ?? []), 0, 6)), fn($x) => $x['title'] !== '')); break;
                case 'quote': $o['text'] = self::str($b['text'] ?? '', 400); $o['author'] = self::str($b['author'] ?? '', 80); break;
                case 'button': $o['label'] = self::str($b['label'] ?? '', 60) ?: 'Réserver'; $o['url'] = self::link($b['url'] ?? '') ?: '#billets'; break;
                case 'map': $o['address'] = self::str($b['address'] ?? '', 200); break;
                case 'partners': $o['logos'] = array_values(array_filter(array_map(fn($x) => ['src' => self::img($x['src'] ?? ''), 'url' => self::link($x['url'] ?? '')], array_slice((array)($b['logos'] ?? []), 0, 30)), fn($x) => $x['src'] !== '')); break;
            }
            $out[] = $o;
        }
        return $out;
    }

    /** Adresse d'intégration d'une vidéo YouTube ou Vimeo (sinon null). */
    public static function embed(string $url): ?string
    {
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) return 'https://player.vimeo.com/video/' . $m[1];
        return null;
    }

    /** Line-up automatique : artistes et horaires du module Artistes & planning. */
    public static function autoLineup(int $eventId): array
    {
        return array_map(fn($r) => ['name' => $r['name'], 'when' => $r['starts_at'] ? date('d/m H\hi', strtotime($r['starts_at'])) . ($r['stage'] ? ' · ' . $r['stage'] : '') : '', 'text' => '', 'photo' => ''],
            all("SELECT a.name, p.starts_at, p.stage FROM artists a LEFT JOIN performances p ON p.artist_id = a.id WHERE a.event_id = ? AND a.status <> 'cancelled' ORDER BY p.starts_at IS NULL, p.starts_at, a.name LIMIT 40", [$eventId]));
    }
}
