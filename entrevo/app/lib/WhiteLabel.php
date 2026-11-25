<?php
declare(strict_types=1);

/**
 * 2.5 : marque blanche (activée par le super-admin, option payante).
 * - Domaine personnalisé : billetterie.monfestival.fr sert la page de l'organisateur (et son mini-site) à la racine.
 * - Sur ce domaine, la marque de la plateforme disparaît : en-tête, pied de page et titre aux couleurs de l'organisateur.
 * - Les e-mails de commande partent au nom de l'organisateur, avec son logo.
 * Le domaine doit pointer vers l'hébergement (CNAME ou enregistrement A) et être ajouté comme alias du site chez l'hébergeur.
 */
final class WhiteLabel
{
    private static array|false|null $cur = null;
    private static ?array $mailOrg = null;

    public static function host(): string
    {
        return strtolower(preg_replace(['~:\d+$~', '~^www\.~'], '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    }

    public static function normalize(string $d): string
    {
        $d = strtolower(trim($d));
        $d = preg_replace(['~^https?://~', '~/.*$~', '~:\d+$~', '~^www\.~'], '', $d);
        return preg_match('~^(?=.{4,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$~', $d) ? $d : '';
    }

    /** Organisateur servi sur le domaine de la requête en cours, s'il y en a un. */
    public static function org(): ?array
    {
        if (self::$cur === null) {
            self::$cur = false;
            $h = self::host();
            if ($h !== '' && PHP_SAPI !== 'cli') {
                try { self::$cur = one("SELECT * FROM organizers WHERE custom_domain = ? AND white_label = 1 AND domain_status = 'active' AND status = 'active'", [$h]) ?: false; }
                catch (Throwable $e) { self::$cur = false; }
            }
        }
        return self::$cur ?: null;
    }

    public static function settings(array $org): array
    {
        $j = json_decode((string)($org['wl_json'] ?? ''), true);
        return (is_array($j) ? $j : []) + ['hide_credit' => 0, 'mail_name' => '', 'mail_brand' => 1];
    }

    /** Hôte de la plateforme (pour les instructions DNS). */
    public static function platformHost(): string
    {
        $b = (string)setting('base_url', '');
        return strtolower((string)(parse_url($b, PHP_URL_HOST) ?: preg_replace('~:\d+$~', '', (string)($_SERVER['HTTP_HOST'] ?? ''))));
    }

    /** Vérifie que le domaine pointe bien vers la plateforme (même adresse IP ou CNAME vers son hôte). */
    public static function check(string $domain): array
    {
        $target = self::platformHost();
        $ips = array_unique(array_filter(array_merge(@gethostbynamel($target) ?: [], [(string)($_SERVER['SERVER_ADDR'] ?? '')])));
        $cname = []; $a = [];
        foreach ((@dns_get_record($domain, DNS_CNAME) ?: []) as $r) $cname[] = rtrim(strtolower((string)$r['target']), '.');
        $a = @gethostbynamel($domain) ?: [];
        $ok = in_array($target, $cname, true) || (bool)array_intersect($a, $ips);
        return ['ok' => $ok, 'cname' => $cname, 'a' => $a, 'target' => $target, 'ips' => array_values($ips),
            'message' => $ok ? 'Le domaine pointe vers la plateforme.' : ($a || $cname ? 'Le domaine pointe ailleurs (' . implode(', ', array_merge($cname, $a)) . ').' : 'Le domaine ne répond pas encore (DNS non configuré ou en cours de propagation).')];
    }

    // ---------- E-mails ----------
    /** Les e-mails envoyés dans $fn partent aux couleurs de l'organisateur s'il est en marque blanche. */
    public static function mailAs(?array $org, callable $fn): mixed
    {
        $prev = self::$mailOrg;
        self::$mailOrg = $org && (int)($org['white_label'] ?? 0) && ($org['domain_status'] ?? '') === 'active' && self::settings($org)['mail_brand'] ? $org : null;
        try { return $fn(); } finally { self::$mailOrg = $prev; }
    }

    public static function mailOrg(): ?array { return self::$mailOrg ?? self::org(); }

    /** URL absolue sur le domaine de l'organisateur. */
    public static function url(array $org, string $path = '/'): string
    {
        return 'https://' . $org['custom_domain'] . base_path_for_domain() . '/' . ltrim($path, '/');
    }
}

/** Chemin de base sur un domaine personnalisé : le domaine pointe en principe directement sur le dossier de l'application. */
function base_path_for_domain(): string { return ''; }
