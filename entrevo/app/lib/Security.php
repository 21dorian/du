<?php
declare(strict_types=1);

/**
 * 2.2 : sécurité des comptes.
 * - Double authentification TOTP (RFC 6238 : Google Authenticator, 1Password, Authy…), sans dépendance.
 * - Journal des connexions (réussies, échouées, verrouillées) et verrouillage après N échecs.
 * - Sessions actives listées et révocables (un appareil perdu se déconnecte à distance).
 * - Droits restreints pour les membres de l'équipe plateforme (zones du super-admin).
 */
final class Security
{
    public const AREAS = ['organisateurs' => 'Organisateurs & KYC', 'argent' => 'Argent (commandes, reversements, litiges, factures, contrats, formules)',
        'support' => 'Support & données personnelles', 'plateforme' => 'Plateforme (réglages, utilisateurs, mises à jour, santé)', 'stats' => 'Statistiques'];
    /** Préfixe d'URL du super-admin => zone. */
    private const AREA_PATHS = ['/admin/organisateurs' => 'organisateurs', '/admin/kyc' => 'organisateurs', '/admin/commandes' => 'argent', '/admin/reversements' => 'argent',
        '/admin/litiges' => 'argent', '/admin/factures' => 'argent', '/admin/contrats' => 'argent', '/admin/formules' => 'argent', '/admin/support' => 'support', '/admin/rgpd' => 'support',
        '/admin/reglages' => 'plateforme', '/admin/utilisateurs' => 'plateforme', '/admin/mises-a-jour' => 'plateforme', '/admin/sante' => 'plateforme', '/admin/sauvegarde' => 'plateforme',
        '/admin/modules' => 'plateforme', '/admin/sauvegardes' => 'plateforme', '/admin/alertes' => 'plateforme', '/admin/fraude' => 'argent', '/admin/contenus' => 'plateforme', '/admin/prospection' => 'organisateurs', '/admin/revendeurs' => 'argent', '/admin/statistiques' => 'stats'];

    // ---------- TOTP ----------
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string { $s = ''; for ($i = 0; $i < 32; $i++) $s .= self::B32[random_int(0, 31)]; return $s; }

    private static function b32decode(string $s): string
    {
        $bits = ''; $out = '';
        foreach (str_split(strtoupper(rtrim($s, '='))) as $c) { $v = strpos(self::B32, $c); if ($v === false) continue; $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT); }
        foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $out .= chr(bindec($b));
        return $out;
    }

    public static function code(string $secret, ?int $t = null): string
    {
        $c = intdiv($t ?? time(), 30);
        $h = hash_hmac('sha1', pack('N*', 0, $c), self::b32decode($secret), true);
        $o = ord($h[19]) & 0xf;
        $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Code valide (tolérance ± 30 s pour les horloges décalées). */
    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('~\D~', '', $code);
        if (strlen($code) !== 6) return false;
        foreach ([-1, 0, 1] as $w) if (hash_equals(self::code($secret, time() + $w * 30), $code)) return true;
        return false;
    }

    public static function otpauth(array $u, string $secret): string
    {
        $issuer = (string)setting('platform_name', 'Entrevo');
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $u['email']) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
    }

    // ---------- Journal & verrouillage ----------
    public static function log(?array $u, string $email, string $result): void
    {
        try { insert('login_log', ['user_id' => $u['id'] ?? null, 'email' => mb_substr(strtolower($email), 0, 190), 'ip' => self::ip(), 'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), 'result' => $result, 'created_at' => now()]); }
        catch (Throwable $e) { /* la connexion ne doit jamais échouer à cause du journal */ }
    }

    public static function ip(): string { return mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }

    public static function lockedUntil(array $u): ?string { return $u['locked_until'] && strtotime($u['locked_until']) > time() ? $u['locked_until'] : null; }

    public static function failed(array $u): void
    {
        $n = (int)$u['failed_logins'] + 1;
        $max = max(3, (int)setting('lock_after', '8'));
        if ($n >= $max) { update('users', ['failed_logins' => 0, 'locked_until' => date('Y-m-d H:i:s', time() + 60 * max(5, (int)setting('lock_minutes', '15')))], 'id = ?', [$u['id']]); self::log($u, $u['email'], 'locked'); }
        else update('users', ['failed_logins' => $n], 'id = ?', [$u['id']]);
    }

    // ---------- Sessions ----------
    public static function sid(): string { return hash('sha256', session_id() . '|' . config('secret', 'x')); }

    public static function openSession(array $u): void
    {
        try {
            q('INSERT INTO user_sessions (user_id, sid, ip, ua, created_at, last_seen) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), revoked = 0, last_seen = NOW()',
                [$u['id'], self::sid(), self::ip(), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
            update('users', ['failed_logins' => 0, 'locked_until' => null, 'last_ip' => self::ip()], 'id = ?', [$u['id']]);
        } catch (Throwable $e) { /* table absente avant migration */ }
    }

    /** Appelé à chaque requête authentifiée : false si la session a été révoquée. */
    public static function touch(array $u): bool
    {
        try {
            $s = one('SELECT id, revoked, last_seen FROM user_sessions WHERE sid = ?', [self::sid()]);
            if (!$s) { self::openSession($u); return true; }
            if ((int)$s['revoked']) return false;
            if (strtotime($s['last_seen']) < time() - 300) q('UPDATE user_sessions SET last_seen = NOW(), ip = ? WHERE id = ?', [self::ip(), $s['id']]);
        } catch (Throwable $e) { /* sans gravité */ }
        return true;
    }

    public static function closeSession(): void { try { q('UPDATE user_sessions SET revoked = 1 WHERE sid = ?', [self::sid()]); } catch (Throwable $e) { /* rien */ } }

    public static function sessions(int $uid): array
    {
        return all('SELECT * FROM user_sessions WHERE user_id = ? AND revoked = 0 AND last_seen > NOW() - INTERVAL 14 DAY ORDER BY last_seen DESC', [$uid]);
    }

    public static function device(string $ua): string
    {
        $os = match (true) { str_contains($ua, 'iPhone') => 'iPhone', str_contains($ua, 'iPad') => 'iPad', str_contains($ua, 'Android') => 'Android', str_contains($ua, 'Mac OS') => 'Mac', str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Linux') => 'Linux', default => 'Appareil' };
        $br = match (true) { str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'Firefox') => 'Firefox', str_contains($ua, 'Chrome') => 'Chrome', str_contains($ua, 'Safari') => 'Safari', default => 'navigateur' };
        return $os . ' · ' . $br;
    }

    // ---------- Droits du super-admin ----------
    public static function areaFor(string $path): ?string
    {
        foreach (self::AREA_PATHS as $p => $a) if ($path === $p || str_starts_with($path, $p . '/')) return $a;
        return null;
    }

    public static function allowed(array $u, ?string $area): bool
    {
        if ($area === null || $u['admin_perms'] === null || $u['admin_perms'] === '') return true;
        return in_array($area, explode(',', (string)$u['admin_perms']), true);
    }
}
