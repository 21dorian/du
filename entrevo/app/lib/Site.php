<?php
declare(strict_types=1);

/**
 * 2.5 : mini-site de l'organisateur, greffé sur sa page publique (/o/{slug}) :
 * pages libres dans un menu, actualités, formulaire de contact, inscription à la lettre d'information.
 * Avec la marque blanche (Super-admin), il peut être servi sur le domaine de l'organisateur.
 */
final class Site
{
    public const DEFAULTS = ['enabled' => 0, 'intro' => '', 'contact' => 1, 'newsletter' => 1, 'contact_email' => '', 'footer' => '', 'news_home' => 1, 'newsletter_text' => 'Reçois les annonces de nos prochains événements en avant-première.'];

    public static function settings(array $org): array
    {
        $j = json_decode((string)($org['site_json'] ?? ''), true);
        return (is_array($j) ? $j : []) + self::DEFAULTS;
    }

    public static function on(array $org): bool
    {
        return (bool)self::settings($org)['enabled'] && (!class_exists('Billing') || Billing::allows($org, 'minisite'));
    }

    public static function slugify(string $s): string
    {
        $s = strtolower(trim((string)iconv('UTF-8', 'ASCII//TRANSLIT', $s)));
        return trim(preg_replace('~[^a-z0-9]+~', '-', $s), '-') ?: 'page';
    }

    public static function uniqueSlug(int $orgId, string $kind, string $slug, int $except = 0): string
    {
        $base = mb_substr(self::slugify($slug), 0, 100); $s = $base; $i = 2;
        while (val('SELECT id FROM site_pages WHERE organizer_id = ? AND kind = ? AND slug = ? AND id <> ?', [$orgId, $kind, $s, $except])) $s = $base . '-' . $i++;
        return $s;
    }

    /** Liens du menu public. */
    public static function menu(array $org): array
    {
        $base = '/o/' . $org['slug'];
        $m = [['Billetterie', $base]];
        foreach (all("SELECT slug, title FROM site_pages WHERE organizer_id = ? AND kind = 'page' AND published = 1 AND in_menu = 1 ORDER BY sort, id", [$org['id']]) as $p) $m[] = [$p['title'], $base . '/p/' . $p['slug']];
        if (val("SELECT 1 FROM site_pages WHERE organizer_id = ? AND kind = 'news' AND published = 1 AND (published_at IS NULL OR published_at <= NOW()) LIMIT 1", [$org['id']])) $m[] = ['Actualités', $base . '/actualites'];
        if (self::settings($org)['contact']) $m[] = ['Contact', $base . '/contact'];
        return $m;
    }

    public static function news(int $orgId, int $limit = 20): array
    {
        return all("SELECT * FROM site_pages WHERE organizer_id = ? AND kind = 'news' AND published = 1 AND (published_at IS NULL OR published_at <= NOW()) ORDER BY COALESCE(published_at, created_at) DESC LIMIT " . max(1, $limit), [$orgId]);
    }

    public static function subscribe(int $orgId, string $email, ?string $name, string $source): bool
    {
        $email = strtolower(trim($email));
        if (!is_email($email)) return false;
        q('INSERT INTO site_contacts (organizer_id, email, name, source, created_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE unsub_at = NULL, name = COALESCE(VALUES(name), name)',
            [$orgId, $email, $name ? mb_substr($name, 0, 190) : null, mb_substr($source, 0, 30)]);
        q('DELETE FROM email_unsubscribes WHERE organizer_id = ? AND email = ?', [$orgId, $email]);
        return true;
    }
}
