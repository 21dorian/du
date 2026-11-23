<?php
declare(strict_types=1);

/**
 * Prestataires de paiement (API HTTP, sans bibliothèque externe).
 * Chaque prestataire : start() renvoie l'URL de paiement, verify() renvoie true si la commande est payée.
 * L'argent est encaissé sur les comptes de la plateforme.
 */
final class Payments
{
    public static function available(): array
    {
        $list = [];
        if (setting('stripe_enabled') === '1' && setting('stripe_secret')) $list['stripe'] = 'Carte bancaire';
        if (setting('paypal_enabled') === '1' && setting('paypal_client_id') && setting('paypal_secret')) $list['paypal'] = 'PayPal';
        if (setting('mollie_enabled') === '1' && setting('mollie_api_key')) $list['mollie'] = 'Mollie (CB, virement…)';
        if (setting('sumup_enabled') === '1' && setting('sumup_api_key') && setting('sumup_merchant_code')) $list['sumup'] = 'Carte bancaire (SumUp)';
        if (setting('demo_enabled') === '1') $list['demo'] = 'Paiement de démonstration';
        return $list;
    }

    private static function http(string $method, string $url, array $headers, $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) throw new RuntimeException('Connexion au prestataire impossible : ' . $err);
        $json = json_decode((string)$raw, true);
        if ($code >= 400) {
            $msg = $json['error']['message'] ?? $json['message'] ?? $json['detail'] ?? $json['error_description'] ?? ('HTTP ' . $code);
            if (is_array($msg)) $msg = json_encode($msg);
            log_line('paiements', "$method $url -> $code $raw");
            throw new RuntimeException('Erreur du prestataire de paiement : ' . $msg);
        }
        return is_array($json) ? $json : [];
    }

    private static function returnUrl(array $o, string $provider): string
    {
        return abs_url('/paiement/retour/' . $o['ref'], ['t' => $o['token'], 'p' => $provider]);
    }

    private static function label(array $o, array $ev): string
    {
        return mb_substr(setting('platform_name') . ' — ' . $ev['name'] . ' (' . $o['ref'] . ')', 0, 200);
    }

    /** Démarre le paiement et renvoie l'URL vers laquelle rediriger l'acheteur. */
    public static function start(string $provider, array $o, array $ev): string
    {
        $amount = (int)$o['total'];
        switch ($provider) {
            case 'stripe':
                $body = http_build_query([
                    'mode' => 'payment',
                    'customer_email' => $o['email'],
                    'client_reference_id' => $o['ref'],
                    'metadata' => ['order_ref' => $o['ref']],
                    'line_items' => [[
                        'quantity' => 1,
                        'price_data' => ['currency' => 'eur', 'unit_amount' => $amount, 'product_data' => ['name' => self::label($o, $ev)]],
                    ]],
                    'expires_at' => time() + 31 * 60,
                    'success_url' => self::returnUrl($o, 'stripe') . '&sid={CHECKOUT_SESSION_ID}',
                    'cancel_url' => abs_url('/paiement/annule/' . $o['ref'], ['t' => $o['token']]),
                ]);
                $body = str_replace('%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $body);
                $r = self::http('POST', 'https://api.stripe.com/v1/checkout/sessions',
                    ['Authorization: Bearer ' . setting('stripe_secret'), 'Content-Type: application/x-www-form-urlencoded'], $body);
                update('orders', ['provider' => 'stripe', 'provider_ref' => $r['id']], 'id = ?', [$o['id']]);
                return $r['url'];

            case 'paypal':
                $r = self::http('POST', self::paypalBase() . '/v2/checkout/orders', self::paypalHeaders(), json_encode([
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => $o['ref'],
                        'custom_id' => $o['ref'],
                        'description' => self::label($o, $ev),
                        'amount' => ['currency_code' => 'EUR', 'value' => number_format($amount / 100, 2, '.', '')],
                    ]],
                    'application_context' => [
                        'brand_name' => mb_substr((string)setting('platform_name'), 0, 120),
                        'user_action' => 'PAY_NOW',
                        'shipping_preference' => 'NO_SHIPPING',
                        'return_url' => self::returnUrl($o, 'paypal'),
                        'cancel_url' => abs_url('/paiement/annule/' . $o['ref'], ['t' => $o['token']]),
                    ],
                ]));
                update('orders', ['provider' => 'paypal', 'provider_ref' => $r['id']], 'id = ?', [$o['id']]);
                foreach ($r['links'] ?? [] as $l) if (in_array($l['rel'], ['approve', 'payer-action'], true)) return $l['href'];
                throw new RuntimeException('PayPal n\'a pas renvoyé de lien de paiement.');

            case 'mollie':
                $r = self::http('POST', 'https://api.mollie.com/v2/payments',
                    ['Authorization: Bearer ' . setting('mollie_api_key'), 'Content-Type: application/json'], json_encode(array_filter([
                        'amount' => ['currency' => 'EUR', 'value' => number_format($amount / 100, 2, '.', '')],
                        'description' => self::label($o, $ev),
                        'redirectUrl' => self::returnUrl($o, 'mollie'),
                        'webhookUrl' => self::webhookAllowed() ? abs_url('/paiement/webhook/mollie') : null,
                        'metadata' => ['order_ref' => $o['ref']],
                    ], fn($v) => $v !== null)));
                update('orders', ['provider' => 'mollie', 'provider_ref' => $r['id']], 'id = ?', [$o['id']]);
                return $r['_links']['checkout']['href'];

            case 'sumup':
                $r = self::http('POST', 'https://api.sumup.com/v0.1/checkouts',
                    ['Authorization: Bearer ' . setting('sumup_api_key'), 'Content-Type: application/json'], json_encode(array_filter([
                        'checkout_reference' => $o['ref'] . '-' . substr(token(6), 0, 6),
                        'amount' => round($amount / 100, 2),
                        'currency' => 'EUR',
                        'merchant_code' => setting('sumup_merchant_code'),
                        'description' => self::label($o, $ev),
                        'redirect_url' => self::returnUrl($o, 'sumup'),
                        'return_url' => self::webhookAllowed() ? abs_url('/paiement/webhook/sumup') : null,
                        'hosted_checkout' => ['enabled' => true],
                    ], fn($v) => $v !== null)));
                update('orders', ['provider' => 'sumup', 'provider_ref' => $r['id']], 'id = ?', [$o['id']]);
                if (empty($r['hosted_checkout_url'])) throw new RuntimeException('SumUp n\'a pas renvoyé de page de paiement (vérifiez que le paiement hébergé est activé sur votre compte).');
                return $r['hosted_checkout_url'];

            case 'demo':
                update('orders', ['provider' => 'demo', 'provider_ref' => 'demo-' . $o['ref']], 'id = ?', [$o['id']]);
                return url('/paiement/demo/' . $o['ref'], ['t' => $o['token']]);
        }
        throw new RuntimeException('Moyen de paiement indisponible.');
    }

    /** Interroge le prestataire : la commande est-elle payée ? */
    public static function verify(array $o): bool
    {
        if (!$o['provider_ref']) return false;
        switch ($o['provider']) {
            case 'stripe':
                $r = self::http('GET', 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($o['provider_ref']), ['Authorization: Bearer ' . setting('stripe_secret')]);
                if (($r['payment_status'] ?? '') === 'paid' && (int)$r['amount_total'] === (int)$o['total']) {
                    update('orders', ['provider_payment' => (string)($r['payment_intent'] ?? '')], 'id = ?', [$o['id']]);
                    return true;
                }
                return false;

            case 'paypal':
                $r = self::http('GET', self::paypalBase() . '/v2/checkout/orders/' . rawurlencode($o['provider_ref']), self::paypalHeaders());
                if (($r['status'] ?? '') === 'APPROVED') {
                    $r = self::http('POST', self::paypalBase() . '/v2/checkout/orders/' . rawurlencode($o['provider_ref']) . '/capture', self::paypalHeaders(), '{}');
                }
                if (($r['status'] ?? '') === 'COMPLETED') {
                    $cap = $r['purchase_units'][0]['payments']['captures'][0] ?? null;
                    if ($cap && (int)round((float)$cap['amount']['value'] * 100) === (int)$o['total']) {
                        update('orders', ['provider_payment' => (string)$cap['id']], 'id = ?', [$o['id']]);
                        return true;
                    }
                }
                return false;

            case 'mollie':
                $r = self::http('GET', 'https://api.mollie.com/v2/payments/' . rawurlencode($o['provider_ref']), ['Authorization: Bearer ' . setting('mollie_api_key')]);
                return ($r['status'] ?? '') === 'paid' && (int)round((float)$r['amount']['value'] * 100) === (int)$o['total'];

            case 'sumup':
                $r = self::http('GET', 'https://api.sumup.com/v0.1/checkouts/' . rawurlencode($o['provider_ref']), ['Authorization: Bearer ' . setting('sumup_api_key')]);
                if (($r['status'] ?? '') === 'PAID' && (int)round((float)$r['amount'] * 100) === (int)$o['total']) {
                    $tx = $r['transactions'][0]['id'] ?? ($r['transaction_id'] ?? '');
                    update('orders', ['provider_payment' => (string)$tx], 'id = ?', [$o['id']]);
                    return true;
                }
                return false;
        }
        return false;
    }

    /** Remboursement automatique quand le prestataire le permet. Renvoie un message. */
    public static function refund(array $o, ?int $amount = null): string
    {
        $amount = $amount ?? (int)$o['total'];
        if ($amount <= 0) return 'Aucun montant à rembourser.';
        switch ($o['provider']) {
            case 'stripe':
                if (!$o['provider_payment']) self::verify($o);
                $pi = (string)val('SELECT provider_payment FROM orders WHERE id = ?', [$o['id']]);
                if (!$pi) throw new RuntimeException('Paiement Stripe introuvable.');
                self::http('POST', 'https://api.stripe.com/v1/refunds', ['Authorization: Bearer ' . setting('stripe_secret'), 'Content-Type: application/x-www-form-urlencoded'],
                    http_build_query(['payment_intent' => $pi, 'amount' => $amount]));
                return 'Remboursement Stripe effectué.';
            case 'paypal':
                if (!$o['provider_payment']) throw new RuntimeException('Capture PayPal introuvable.');
                self::http('POST', self::paypalBase() . '/v2/payments/captures/' . rawurlencode($o['provider_payment']) . '/refund', self::paypalHeaders(),
                    json_encode(['amount' => ['currency_code' => 'EUR', 'value' => number_format($amount / 100, 2, '.', '')]]));
                return 'Remboursement PayPal effectué.';
            case 'mollie':
                self::http('POST', 'https://api.mollie.com/v2/payments/' . rawurlencode($o['provider_ref']) . '/refunds',
                    ['Authorization: Bearer ' . setting('mollie_api_key'), 'Content-Type: application/json'],
                    json_encode(['amount' => ['currency' => 'EUR', 'value' => number_format($amount / 100, 2, '.', '')]]));
                return 'Remboursement Mollie effectué.';
            case 'sumup':
                if (!$o['provider_payment']) throw new RuntimeException('Transaction SumUp introuvable : remboursez depuis votre tableau de bord SumUp.');
                self::http('POST', 'https://api.sumup.com/v0.1/me/refund/' . rawurlencode($o['provider_payment']),
                    ['Authorization: Bearer ' . setting('sumup_api_key'), 'Content-Type: application/json'], json_encode(['amount' => round($amount / 100, 2)]));
                return 'Remboursement SumUp effectué.';
            case 'demo':
                return 'Commande de démonstration : aucun remboursement réel.';
        }
        return 'Paiement hors ligne : remboursez l\'acheteur directement.';
    }

    /**
     * Notification envoyée par un prestataire. On n'en croit jamais le contenu :
     * on retrouve la commande puis on interroge le prestataire (verify) avant de valider.
     * @return string résumé pour le journal
     */
    public static function webhook(string $provider): string
    {
        $raw = (string)file_get_contents('php://input');
        $json = json_decode($raw, true) ?: [];
        $ref = null;
        switch ($provider) {
            case 'stripe':
                if (($json['type'] ?? '') && str_starts_with((string)$json['type'], 'checkout.session.')) $ref = $json['data']['object']['id'] ?? null;
                break;
            case 'paypal':
                $res = $json['resource'] ?? [];
                $ref = ($json['event_type'] ?? '') === 'PAYMENT.CAPTURE.COMPLETED' ? ($res['supplementary_data']['related_ids']['order_id'] ?? null) : ($res['id'] ?? null);
                break;
            case 'sumup':
                $ref = $json['id'] ?? ($json['payload']['checkout_id'] ?? null);
                break;
            case 'mollie':
                $ref = $_POST['id'] ?? null;
                break;
            default:
                return 'prestataire inconnu';
        }
        if (!$ref) return 'notification ignorée';
        $o = one('SELECT * FROM orders WHERE provider = ? AND provider_ref = ?', [$provider, (string)$ref]);
        if (!$o) return 'commande introuvable (' . $ref . ')';
        if ($o['status'] === 'paid') return 'déjà payée ' . $o['ref'];
        if (self::verify($o)) { Tickets::confirm((int)$o['id']); return 'payée ' . $o['ref']; }
        return 'pas encore payée ' . $o['ref'];
    }

    /** Revérifie les paiements restés « en attente » (acheteur parti avant le retour sur le site). */
    public static function recheckPending(int $limit = 15): int
    {
        $n = 0;
        $rows = all("SELECT * FROM orders WHERE status IN ('pending','expired') AND provider IN ('stripe','paypal','sumup','mollie') AND provider_ref IS NOT NULL
                     AND created_at > NOW() - INTERVAL 6 HOUR AND created_at < NOW() - INTERVAL 2 MINUTE ORDER BY id DESC LIMIT " . (int)$limit);
        foreach ($rows as $o) {
            try { if (self::verify($o)) { Tickets::confirm((int)$o['id']); $n++; } }
            catch (Throwable $e) { log_line('paiements', 'Relance ' . $o['ref'] . ' : ' . $e->getMessage()); }
        }
        return $n;
    }

    private static function paypalBase(): string
    {
        return setting('paypal_sandbox') === '1' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    private static function paypalHeaders(): array
    {
        static $tok = null;
        if ($tok === null) {
            $r = self::http('POST', self::paypalBase() . '/v1/oauth2/token', [
                'Authorization: Basic ' . base64_encode(setting('paypal_client_id') . ':' . setting('paypal_secret')),
                'Content-Type: application/x-www-form-urlencoded',
            ], 'grant_type=client_credentials');
            $tok = $r['access_token'] ?? '';
        }
        return ['Authorization: Bearer ' . $tok, 'Content-Type: application/json', 'Prefer: return=representation'];
    }

    private static function webhookAllowed(): bool
    {
        $host = parse_url(abs_url('/'), PHP_URL_HOST);
        return $host && !in_array($host, ['localhost', '127.0.0.1'], true);
    }
}
