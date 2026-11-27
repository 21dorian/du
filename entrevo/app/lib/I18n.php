<?php
declare(strict_types=1);

/**
 * 4.2 : billetterie multilingue (français / anglais).
 * Le parcours d'achat public (page événement, tiroir, widget, commande, paiement, e-mails, billets PDF) bascule en anglais :
 *  - choix explicite (?lang=en, mémorisé) ou navigateur anglophone si l'organisateur a activé l'anglais sur l'événement ;
 *  - les textes de l'interface passent par un dictionnaire (nœuds de texte entiers + règles pour les dates, frais, quantités) ;
 *  - les contenus de l'organisateur (nom, description, tarifs) utilisent ses traductions *_en quand elles existent.
 */
final class I18n
{
    private static string $lang = 'fr';
    private static bool $public = false;

    public static function boot(string $path): void
    {
        foreach (['/pro', '/admin', '/api', '/scan', '/caisse', '/cron', '/borne', '/pc/', '/paiement/webhook'] as $p) if (str_starts_with($path, $p)) return;
        self::$public = true;
        $q = $_GET['lang'] ?? null;
        if ($q === 'en' || $q === 'fr') { $_SESSION['lang'] = $q; @setcookie('lang', $q, ['expires' => time() + 86400 * 365, 'path' => '/', 'samesite' => 'Lax']); }
        $l = $_SESSION['lang'] ?? ($_COOKIE['lang'] ?? null);
        if (!$l) $l = preg_match('~^en\b~i', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) && setting('auto_english', '1') === '1' ? 'en' : 'fr';
        self::$lang = $l === 'en' ? 'en' : 'fr';
    }

    public static function lang(): string { return self::$lang; }
    public static function en(): bool { return self::$lang === 'en'; }
    /** Force la langue (e-mails et billets d'une commande passée en anglais). */
    public static function with(string $lang, callable $fn) { $old = self::$lang; self::$lang = $lang === 'en' ? 'en' : 'fr'; try { return $fn(); } finally { self::$lang = $old; } }

    /** Remplace les champs d'un événement / d'un tarif par leur traduction anglaise. */
    public static function localize(array $row, array $fields = ['name', 'description']): array
    {
        if (!self::en()) return $row;
        foreach ($fields as $f) if (!empty($row[$f . '_en'])) $row[$f] = $row[$f . '_en'];
        return $row;
    }

    public static function t(string $fr): string
    {
        if (!self::en()) return $fr;
        $d = self::dict();
        return $d[$fr] ?? self::rules($fr);
    }

    /** Traduit une page HTML rendue (nœuds de texte et attributs visibles). */
    public static function html(string $html): string
    {
        if (!self::en() || !self::$public) return $html;
        $html = preg_replace('~<html lang="fr"~', '<html lang="en"', $html, 1);
        $tr = function (string $s): string {
            $t = trim($s);
            if ($t === '' || !preg_match('~\p{L}{2}~u', $t)) return $s;
            $dec = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
            $d = self::dict();
            $out = $d[$dec] ?? self::rules($dec);
            if ($out === $dec) return $s;
            return str_replace($t, htmlspecialchars($out, ENT_NOQUOTES, 'UTF-8'), $s);
        };
        // on ne touche pas aux scripts ni aux styles
        $parts = preg_split('~(<script\b.*?</script>|<style\b.*?</style>|<textarea\b.*?</textarea>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $p) {
            if ($i % 2) continue;
            $p = preg_replace_callback('~>([^<>]+)<~u', fn($m) => '>' . $tr($m[1]) . '<', $p);
            $p = preg_replace_callback('~\b(placeholder|aria-label|title|data-confirm|alt)="([^"]*)"~u', fn($m) => $m[1] . '="' . htmlspecialchars(self::t(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8') . '"', $p);
            $p = preg_replace_callback('~(<input[^>]+type="(?:submit|button)"[^>]*value=")([^"]*)"~u', fn($m) => $m[1] . htmlspecialchars(self::t(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8') . '"', $p);
            $parts[$i] = $p;
        }
        return implode('', $parts);
    }

    /** Traduit récursivement les chaînes d'une réponse JSON (tiroir, widget). */
    public static function data($v)
    {
        if (!self::en() || !self::$public) return $v;
        if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = self::data($x); return $v; }
        if (!is_string($v) || $v === '') return $v;
        return str_contains($v, '<') ? self::html($v) : self::t($v);
    }

    /** Dictionnaire pour les scripts (tiroir d'achat). */
    public static function js(): string
    {
        if (!self::en()) return '';
        $keys = ['Envie d’un petit plus ?', 'Valider ma réservation →', 'Continuer →', 'Ton récap', 'Réduction', 'Frais de service', 'Total', 'Choisis au moins un billet', 'Oups, réessaie.',
            'Connexion perdue : vérifie ton réseau et réessaie.', 'Temps écoulé : tes places sont reparties. Recharge la page pour retenter ta chance.', 'Payer', 'billet', 'billets', 'Tarif groupe', 'Carte cadeau', 'Tes billets arriveront à '];
        $d = self::dict(); $o = [];
        foreach ($keys as $k) $o[$k] = $d[$k] ?? $k;
        return '<script>window.ENTREVO_I18N=' . json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';</script>';
    }

    private const DAYS = ['dim.' => 'Sun', 'lun.' => 'Mon', 'mar.' => 'Tue', 'mer.' => 'Wed', 'jeu.' => 'Thu', 'ven.' => 'Fri', 'sam.' => 'Sat',
        'Dimanche' => 'Sunday', 'Lundi' => 'Monday', 'Mardi' => 'Tuesday', 'Mercredi' => 'Wednesday', 'Jeudi' => 'Thursday', 'Vendredi' => 'Friday', 'Samedi' => 'Saturday'];
    private const MONTHS = ['janvier' => 'January', 'février' => 'February', 'mars' => 'March', 'avril' => 'April', 'mai' => 'May', 'juin' => 'June', 'juillet' => 'July', 'août' => 'August',
        'septembre' => 'September', 'octobre' => 'October', 'novembre' => 'November', 'décembre' => 'December'];
    private const SHORT_MONTHS = ['janv.' => 'Jan', 'févr.' => 'Feb', 'mars' => 'Mar', 'avr.' => 'Apr', 'mai' => 'May', 'juin' => 'Jun', 'juil.' => 'Jul', 'août' => 'Aug', 'sept.' => 'Sep', 'oct.' => 'Oct', 'nov.' => 'Nov', 'déc.' => 'Dec'];

    /** Dates et petites phrases construites (montants, quantités, comptes à rebours). */
    private static function rules(string $s): string
    {
        $o = $s;
        $dayRe = implode('|', array_map(fn($k) => preg_quote($k, '~'), array_keys(self::DAYS)));
        $monRe = implode('|', array_keys(self::MONTHS));
        // « jeu. 17 décembre 2026 à 16h00 » → « Thu 17 December 2026 at 16:00 »
        $o = preg_replace_callback('~\b(' . $dayRe . ')\s(\d{1,2})\s(' . $monRe . ')(\s\d{4})?~iu', fn($m) => self::DAYS[$m[1]] . ' ' . $m[2] . ' ' . self::MONTHS[mb_strtolower($m[3])] . ($m[4] ?? ''), $o);
        $o = preg_replace_callback('~\b(\d{1,2})\s(' . $monRe . ')(\s\d{4})?~iu', fn($m) => $m[1] . ' ' . self::MONTHS[mb_strtolower($m[2])] . ($m[3] ?? ''), $o);
        $o = preg_replace_callback('~^(' . $monRe . ')(\s\d{4})?$~iu', fn($m) => self::MONTHS[mb_strtolower($m[1])] . ($m[2] ?? ''), $o);
        if (isset(self::SHORT_MONTHS[$o])) return self::SHORT_MONTHS[$o];
        $o = preg_replace('~ à (\d{2})h(\d{2})~u', ' at $1:$2', $o);
        $o = preg_replace('~^(\d{2})h(\d{2})$~u', '$1:$2', $o);
        $o = preg_replace('~^du (.+) au (.+)$~u', 'from $1 to $2', $o);
        $o = preg_replace('~^jusqu\'au (.+)$~u', 'until $1', $o);
        $map = [
            '~^\+ (.+) de frais$~u' => '+ $1 fees', '~^Quantité (.+)$~u' => 'Quantity $1', '~^Dans (\d+) j (\d+) h (\d+) min$~u' => 'In $1 d $2 h $3 min', '~^Dans (\d+) h (\d+) min$~u' => 'In $1 h $2 min',
            '~^C\'est dans la poche, (.+) !$~u' => 'You\'re all set, $1!', '~^Places réservées, (.+) !$~u' => 'Seats reserved, $1!', '~^· places gardées (\d+) jour\(s\)$~u' => '· seats held for $1 day(s)',
            '~^\+ (\d+) autre\(s\) tarif\(s\)$~u' => '+ $1 more ticket type(s)', '~^Billet (\d+)/(\d+)$~u' => 'Ticket $1/$2', '~^(\d+) billets?$~u' => '$1 ticket(s)', '~^(\d+) places?$~u' => '$1 seat(s)',
            '~^(\d+) dates$~u' => '$1 dates', '~^Je prends ma place — dès (.+)$~u' => 'Get my ticket — from $1', '~^Dès (.+)$~u' => 'From $1', '~^Ton prix \(min\. (.+)\)$~u' => 'Your price (min. $1)',
            '~^Pack de (\d+) billets$~u' => 'Pack of $1 tickets', '~^Commande (\S+)$~u' => 'Order $1', '~^Payer (.+) →$~u' => 'Pay $1 →', '~^Plus que (\d+) places?$~u' => 'Only $1 left', '~^(.+) présente$~u' => '$1 presents',
            '~^Recevoir les prochains événements de$~u' => 'Get upcoming events from', '~^· (.+) à régler avant le$~u' => '· $1 to pay before', '~^(\d+) événements$~u' => '$1 events', '~^(\d+) billets émis$~u' => '$1 tickets issued',
            '~^(.+) · (\d+) avis sur l\'organisateur$~u' => '$1 · $2 reviews of the organiser', '~^· (\d+) avis$~u' => '· $1 reviews', '~^Quantité$~u' => 'Quantity', '~^(\d) sur 5$~u' => '$1 out of 5',
            '~^Je veux recevoir les actus de (.+)$~u' => 'I want news from $1', '~^au nom de (.+)\.$~u' => 'in the name of $1.', '~^Acheteur : (.+)$~u' => 'Buyer: $1', '~^Organisateur : (.+)$~u' => 'Organiser: $1',
            '~^(INVITATION|BILLET|ACCRÉDITATION)  (\d+) / (\d+)$~u' => 'TICKET  $2 / $3', '~^Billets (.+)$~u' => 'Tickets $1', '~^(\d{2}/\d{2}/\d{4}) à (\d{2})h(\d{2})$~u' => '$1 at $2:$3', '~^Billet (\d+) sur (\d+)$~u' => 'Ticket $1 of $2',
        ];
        // dates en capitales des billets : « JEU. 17 DÉC. 2026 »
        if (preg_match('~^(DIM|LUN|MAR|MER|JEU|VEN|SAM)\. \d{1,2} \S+ \d{4}(\s+·\s+\d{2}h\d{2})?$~u', $o)) {
            $o = preg_replace('~(\d{2})h(\d{2})$~', '$1:$2', $o);
            $o = strtr($o, ['DIM.' => 'SUN', 'LUN.' => 'MON', 'MAR.' => 'TUE', 'MER.' => 'WED', 'JEU.' => 'THU', 'VEN.' => 'FRI', 'SAM.' => 'SAT', 'JANV.' => 'JAN', 'FÉVR.' => 'FEB', 'MARS' => 'MAR', 'AVR.' => 'APR', 'MAI' => 'MAY', 'JUIN' => 'JUN', 'JUIL.' => 'JUL', 'AOÛT' => 'AUG', 'SEPT.' => 'SEP', 'OCT.' => 'OCT', 'NOV.' => 'NOV', 'DÉC.' => 'DEC']);
            return $o;
        }
        $map['~^(.+) PRÉSENTE$~u'] = '$1 PRESENTS';
        foreach ($map as $re => $rep) { $n = preg_replace($re, $rep, $o); if ($n !== $o) { $o = $n; break; } }
        return $o;
    }

    private static ?array $d = null;
    private static function dict(): array
    {
        if (self::$d !== null) return self::$d;
        return self::$d = require APP . '/lang/en.php';
    }
}
