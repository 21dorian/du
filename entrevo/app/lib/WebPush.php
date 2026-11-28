<?php
declare(strict_types=1);

/**
 * 4.3 : notifications Web Push sans dépendance (RFC 8030 / 8291 / 8292).
 * Clés VAPID générées une fois et gardées dans les réglages ; charge utile chiffrée en aes128gcm.
 * Fonctionne avec Chrome, Edge, Firefox et Safari (iPhone : appli ajoutée à l'écran d'accueil).
 */
final class WebPush
{
    private const SPKI_P256 = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
    public static function unb64u(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

    /** Paire VAPID [clé privée PEM, clé publique brute 65 octets], créée au premier appel. */
    public static function vapid(): array
    {
        $pem = (string)setting('vapid_private');
        if ($pem === '' || !openssl_pkey_get_private($pem)) {
            $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
            if (!$k) throw new RuntimeException('OpenSSL ne sait pas créer de clé EC sur ce serveur.');
            openssl_pkey_export($k, $pem);
            set_setting('vapid_private', $pem);
        }
        return [$pem, self::rawPublic(openssl_pkey_get_private($pem))];
    }

    public static function publicKey(): string { return self::b64u(self::vapid()[1]); }

    private static function rawPublic($key): string
    {
        $d = openssl_pkey_get_details($key);
        return "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    private static function pemFromRaw(string $raw): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(self::SPKI_P256) . $raw), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function hmac(string $key, string $data): string { return hash_hmac('sha256', $data, $key, true); }

    /** Signature ES256 brute (r||s) à partir d'une signature DER. */
    private static function derToRaw(string $der): string
    {
        $off = 2; if (ord($der[1]) & 0x80) $off += ord($der[1]) & 0x7f;
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$off + 1]); $int = substr($der, $off + 2, $len); $off += 2 + $len;
            $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
        }
        return $out;
    }

    public static function jwt(string $endpoint, string $subject): string
    {
        [$pem] = self::vapid();
        $u = parse_url($endpoint);
        $h = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $p = self::b64u(json_encode(['aud' => $u['scheme'] . '://' . $u['host'], 'exp' => time() + 12 * 3600, 'sub' => $subject]));
        openssl_sign($h . '.' . $p, $sig, openssl_pkey_get_private($pem), OPENSSL_ALGO_SHA256);
        return $h . '.' . $p . '.' . self::b64u(self::derToRaw($sig));
    }

    /** Chiffre la charge utile pour un abonné (aes128gcm). $p256dh et $auth en base64url. */
    public static function encrypt(string $payload, string $p256dh, string $auth, ?string $salt = null, $local = null): string
    {
        $ua = self::unb64u($p256dh); $secret = self::unb64u($auth);
        if (strlen($ua) !== 65 || strlen($secret) < 16) throw new RuntimeException('Abonnement de notification invalide.');
        $local = $local ?: openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $as = self::rawPublic($local);
        $ecdh = openssl_pkey_derive(openssl_pkey_get_public(self::pemFromRaw($ua)), $local, 32);
        if ($ecdh === false) throw new RuntimeException('Échange de clés impossible.');
        $ikm = substr(self::hmac(self::hmac($secret, $ecdh), "WebPush: info\0" . $ua . $as . "\x01"), 0, 32);
        $salt = $salt ?? random_bytes(16);
        $prk = self::hmac($salt, $ikm);
        $cek = substr(self::hmac($prk, "Content-Encoding: aes128gcm\0\x01"), 0, 16);
        $nonce = substr(self::hmac($prk, "Content-Encoding: nonce\0\x01"), 0, 12);
        $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        return $salt . pack('N', 4096) . chr(65) . $as . $ct . $tag;
    }

    /** Envoie une notification. Retourne le code HTTP (201 = reçu ; 404/410 = abonnement expiré). */
    public static function send(array $sub, array $msg): int
    {
        $body = self::encrypt(json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string)$sub['p256dh'], (string)$sub['auth']);
        $mail = (string)(setting('contact_email') ?: setting('mail_from') ?: 'contact@entrevo.local');
        $ch = curl_init((string)$sub['endpoint']);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['TTL: 86400', 'Urgency: normal', 'Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm',
                'Authorization: vapid t=' . self::jwt((string)$sub['endpoint'], 'mailto:' . $mail) . ', k=' . self::publicKey()]]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }
}
