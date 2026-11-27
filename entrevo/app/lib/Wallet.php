<?php
declare(strict_types=1);

/**
 * 4.1 : billets dans le téléphone. Apple Wallet : pass .pkpass signé avec le certificat du Pass Type ID.
 * Google Wallet : lien « Enregistrer » (JWT signé par le compte de service, classe et billet inclus dans le lien).
 */
final class Wallet
{
    public static function appleOn(): bool
    {
        return setting('wallet_apple') === '1' && setting('apple_pass_type') && setting('apple_team_id') && is_file((string)setting('apple_cert_file')) && is_file((string)setting('apple_wwdr_file')) && function_exists('openssl_pkcs7_sign') && class_exists('ZipArchive');
    }

    public static function googleOn(): bool
    {
        return setting('wallet_google') === '1' && setting('google_issuer_id') && setting('google_sa_email') && str_contains((string)setting('google_sa_key'), 'PRIVATE KEY');
    }

    private static function start(array $t, array $ev): string
    {
        $s = !empty($t['session_id']) ? val('SELECT starts_at FROM sessions WHERE id = ?', [$t['session_id']]) : null;
        return (string)($s ?: $ev['starts_at']);
    }

    private static function rgb(string $hex): string
    {
        $hex = preg_match('~^#[0-9a-f]{6}$~i', $hex) ? $hex : '#1d1d1f';
        return 'rgb(' . hexdec(substr($hex, 1, 2)) . ', ' . hexdec(substr($hex, 3, 2)) . ', ' . hexdec(substr($hex, 5, 2)) . ')';
    }

    /** Contenu du pass Apple (pass.json). */
    public static function passJson(array $t, array $o, array $ev, array $org): array
    {
        $start = self::start($t, $ev);
        $place = trim(implode(', ', array_filter([(string)$ev['venue'], (string)$ev['city']])));
        $name = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')) ?: trim($o['first_name'] . ' ' . $o['last_name']);
        return [
            'formatVersion' => 1, 'passTypeIdentifier' => (string)setting('apple_pass_type'), 'teamIdentifier' => (string)setting('apple_team_id'),
            'serialNumber' => (string)$t['code'], 'organizationName' => (string)$org['name'], 'description' => 'Billet · ' . $ev['name'],
            'logoText' => mb_substr((string)$ev['name'], 0, 30), 'backgroundColor' => self::rgb((string)($ev['ticket_color'] ?: '#1d1d1f')), 'foregroundColor' => 'rgb(255, 255, 255)', 'labelColor' => 'rgb(230, 230, 235)',
            'relevantDate' => date('c', strtotime($start)), 'expirationDate' => date('c', strtotime((string)($ev['ends_at'] ?: $start)) + 12 * 3600),
            'barcodes' => [['format' => 'PKBarcodeFormatQR', 'message' => (string)$t['code'], 'messageEncoding' => 'iso-8859-1', 'altText' => (string)$t['code']]],
            'eventTicket' => [
                'primaryFields' => [['key' => 'event', 'label' => 'ÉVÉNEMENT', 'value' => (string)$ev['name']]],
                'secondaryFields' => [['key' => 'date', 'label' => 'DATE', 'value' => date('c', strtotime($start)), 'dateStyle' => 'PKDateStyleMedium', 'timeStyle' => 'PKDateStyleShort'], ['key' => 'name', 'label' => 'NOM', 'value' => $name]],
                'auxiliaryFields' => array_values(array_filter([['key' => 'rate', 'label' => 'TARIF', 'value' => (string)$t['rate_name']], !empty($t['seat']) ? ['key' => 'seat', 'label' => 'PLACE', 'value' => (string)$t['seat']] : null])),
                'backFields' => [['key' => 'place', 'label' => 'Lieu', 'value' => $place], ['key' => 'ref', 'label' => 'Commande', 'value' => (string)$o['ref']], ['key' => 'info', 'label' => 'Infos', 'value' => (string)($ev['ticket_info'] ?: 'Présente ce billet à l\'entrée.')]],
            ],
        ];
    }

    /** Fichier .pkpass (zip signé). */
    public static function applePass(array $t, array $o, array $ev, array $org): string
    {
        $tmp = sys_get_temp_dir() . '/pk_' . bin2hex(random_bytes(6)); mkdir($tmp, 0700);
        try {
            $files = ['pass.json' => json_encode(self::passJson($t, $o, $ev, $org), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
            $icon = ROOT . '/caisse/icon-192.png';
            $logo = !empty($org['logo']) && is_file(ROOT . '/' . $org['logo']) && str_ends_with(strtolower((string)$org['logo']), '.png') ? ROOT . '/' . $org['logo'] : $icon;
            foreach (['icon.png' => $icon, 'icon@2x.png' => $icon, 'logo.png' => $logo, 'logo@2x.png' => $logo] as $n => $f) if (is_file($f)) $files[$n] = (string)file_get_contents($f);
            $manifest = []; foreach ($files as $n => $c) $manifest[$n] = sha1($c);
            $files['manifest.json'] = json_encode($manifest, JSON_UNESCAPED_SLASHES);
            file_put_contents($tmp . '/manifest.json', $files['manifest.json']);
            $p12 = []; if (!openssl_pkcs12_read((string)file_get_contents((string)setting('apple_cert_file')), $p12, (string)setting('apple_pass_password'))) throw new RuntimeException('Certificat Apple illisible (mot de passe ?).');
            $ok = openssl_pkcs7_sign($tmp . '/manifest.json', $tmp . '/sig.smime', $p12['cert'], [$p12['pkey'], ''], [], PKCS7_BINARY | PKCS7_DETACHED, (string)setting('apple_wwdr_file'));
            if (!$ok) throw new RuntimeException('Signature du pass impossible.');
            $smime = (string)file_get_contents($tmp . '/sig.smime');
            if (!preg_match('~filename="?smime\.p7s"?\s*\R\R(.+?)\R\R?------~s', $smime, $m)) throw new RuntimeException('Signature du pass illisible.');
            $files['signature'] = base64_decode(preg_replace('~\s+~', '', $m[1]));
            $zip = new ZipArchive(); $zf = $tmp . '/pass.pkpass'; $zip->open($zf, ZipArchive::CREATE);
            foreach ($files as $n => $c) $zip->addFromString($n, $c);
            $zip->close();
            return (string)file_get_contents($zf);
        } finally { foreach (glob($tmp . '/*') ?: [] as $f) @unlink($f); @rmdir($tmp); }
    }

    private static function b64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

    /** Lien « Enregistrer dans Google Wallet ». */
    public static function googleUrl(array $t, array $o, array $ev, array $org): string
    {
        $iss = (string)setting('google_issuer_id');
        $class = $iss . '.entrevo_ev' . (int)$ev['id'];
        $start = self::start($t, $ev);
        $fr = fn(string $v) => ['defaultValue' => ['language' => 'fr', 'value' => $v]];
        $payload = [
            'eventTicketClasses' => [['id' => $class, 'issuerName' => mb_substr((string)$org['name'], 0, 60), 'eventName' => $fr((string)$ev['name']), 'reviewStatus' => 'UNDER_REVIEW',
                'venue' => ['name' => $fr((string)($ev['venue'] ?: $ev['city'] ?: '—')), 'address' => $fr(trim(implode(', ', array_filter([(string)($ev['address'] ?? ''), (string)$ev['city']]))) ?: '—')],
                'dateTime' => ['start' => date('c', strtotime($start))], 'hexBackgroundColor' => (string)($ev['ticket_color'] ?: '#1d1d1f')]],
            'eventTicketObjects' => [array_filter(['id' => $iss . '.t' . preg_replace('~[^A-Za-z0-9]~', '', (string)$t['code']), 'classId' => $class, 'state' => 'ACTIVE',
                'barcode' => ['type' => 'QR_CODE', 'value' => (string)$t['code'], 'alternateText' => (string)$t['code']],
                'ticketHolderName' => trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')) ?: null, 'ticketNumber' => (string)$o['ref'],
                'ticketType' => $fr((string)$t['rate_name']), 'seatInfo' => !empty($t['seat']) ? ['seat' => $fr((string)$t['seat'])] : null])],
        ];
        $claims = ['iss' => (string)setting('google_sa_email'), 'aud' => 'google', 'typ' => 'savetowallet', 'iat' => time(), 'origins' => [rtrim(abs_url('/'), '/')], 'payload' => $payload];
        $head = self::b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . self::b64(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $key = openssl_pkey_get_private(str_replace('\n', "\n", (string)setting('google_sa_key')));
        if (!$key || !openssl_sign($head, $sig, $key, OPENSSL_ALGO_SHA256)) throw new RuntimeException('Clé Google Wallet invalide.');
        return 'https://pay.google.com/gp/v/save/' . $head . '.' . self::b64($sig);
    }
}
