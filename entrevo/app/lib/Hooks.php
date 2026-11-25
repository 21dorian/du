<?php
declare(strict_types=1);

/**
 * Webhooks sortants (2.4) : plusieurs adresses par organisateur, chacune abonnée aux événements de son choix.
 * Les envois passent par une file (webhook_deliveries) : rien ne ralentit le scan ni le paiement.
 * Relances automatiques (1 min, 5 min, 30 min, 2 h, 6 h, 24 h) ; après 20 échecs d'affilée, l'adresse est coupée
 * et l'organisateur prévenu. Chaque envoi est signé : en-tête X-Entrevo-Signature = sha256=HMAC(corps, secret).
 */
final class Hooks
{
    public const EVENTS = [
        'order.paid' => 'Commande payée', 'order.refunded' => 'Commande remboursée', 'order.cancelled' => 'Commande annulée',
        'ticket.checked_in' => 'Entrée scannée', 'event.published' => 'Événement publié', 'wallet.topup' => 'Recharge cashless',
        'quote.requested' => 'Demande de devis groupe', 'review.created' => 'Nouvel avis',
    ];
    private const BACKOFF = [60, 300, 1800, 7200, 21600, 86400];

    /** Met en file l'événement pour toutes les adresses abonnées de l'organisateur. */
    public static function fire(int $orgId, string $event, array $data): void
    {
        try {
            $eps = all("SELECT * FROM webhook_endpoints WHERE organizer_id = ? AND active = 1", [$orgId]);
            if (!$eps) { self::legacy($orgId, $event, $data); return; }
            $body = json_encode(['event' => $event, 'id' => token(12), 'date' => date('c'), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach ($eps as $ep) {
                if (!in_array($event, explode(',', (string)$ep['events']), true)) continue;
                insert('webhook_deliveries', ['endpoint_id' => $ep['id'], 'event' => $event, 'payload' => $body, 'status' => 'pending', 'next_try_at' => now(), 'created_at' => now()]);
            }
        } catch (Throwable $e) { log_line('webhooks', 'File : ' . $e->getMessage()); }
    }

    /** Ancienne adresse unique (Mon organisation › API) : envoyée tout de suite, commandes payées seulement. */
    private static function legacy(int $orgId, string $event, array $data): void
    {
        if ($event !== 'order.paid') return;
        $org = one('SELECT webhook_url, webhook_secret FROM organizers WHERE id = ?', [$orgId]);
        if (!$org || !$org['webhook_url'] || !preg_match('~^https?://~', $org['webhook_url'])) return;
        $body = json_encode(['event' => $event, 'date' => date('c'), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        [$code] = self::post($org['webhook_url'], $body, (string)$org['webhook_secret'], $event);
        log_line('webhooks', "org $orgId $event -> HTTP $code");
    }

    /** @return array{0:int, 1:string} code HTTP, début de la réponse */
    public static function post(string $url, string $body, string $secret, string $event): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: ' . setting('platform_name', 'Entrevo') . '-Webhooks', 'X-Entrevo-Event: ' . $event, 'X-Entrevo-Signature: sha256=' . hash_hmac('sha256', $body, $secret)]]);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [$code, mb_substr($res === false ? $err : (string)$res, 0, 500)];
    }

    /** Tâche de fond : envoie la file. */
    public static function deliver(int $limit = 40): int
    {
        $n = 0;
        foreach (all("SELECT d.*, e.url, e.secret, e.organizer_id, e.fail_streak FROM webhook_deliveries d JOIN webhook_endpoints e ON e.id = d.endpoint_id
            WHERE d.status = 'pending' AND d.next_try_at <= NOW() AND e.active = 1 ORDER BY d.id LIMIT " . (int)$limit) as $d) {
            [$code, $resp] = self::post($d['url'], $d['payload'], $d['secret'], $d['event']);
            $ok = $code >= 200 && $code < 300;
            $att = (int)$d['attempts'] + 1;
            update('webhook_deliveries', ['attempts' => $att, 'http_code' => $code ?: null, 'response' => $resp,
                'status' => $ok ? 'ok' : ($att > count(self::BACKOFF) ? 'failed' : 'pending'), 'delivered_at' => $ok ? now() : null,
                'next_try_at' => $ok ? null : date('Y-m-d H:i:s', time() + (self::BACKOFF[$att - 1] ?? 86400))], 'id = ?', [$d['id']]);
            $streak = $ok ? 0 : (int)$d['fail_streak'] + 1;
            update('webhook_endpoints', ['last_status' => $code, 'last_at' => now(), 'fail_streak' => $streak, 'active' => $streak >= 20 ? 0 : 1], 'id = ?', [$d['endpoint_id']]);
            if ($streak === 20) {
                $org = one('SELECT email, name FROM organizers WHERE id = ?', [$d['organizer_id']]);
                try { if ($org['email']) Mailer::send($org['email'], 'Webhook désactivé', '<p>L\'adresse <b>' . e($d['url']) . '</b> a refusé 20 envois d\'affilée : nous l\'avons mise en pause. Corrige-la puis réactive-la dans Intégrations.</p>'); } catch (Throwable $e) { /* rien */ }
            }
            $n++;
        }
        q("DELETE FROM webhook_deliveries WHERE created_at < NOW() - INTERVAL 30 DAY");
        return $n;
    }

    public static function orderPayload(array $o): array
    {
        $tickets = all("SELECT code, first_name, last_name, rate_name, price, seat, session_id FROM tickets WHERE order_id = ?", [$o['id']]);
        return ['ref' => $o['ref'], 'event_id' => (int)$o['event_id'], 'session_id' => $o['session_id'] ? (int)$o['session_id'] : null, 'status' => $o['status'],
            'email' => $o['email'], 'first_name' => $o['first_name'], 'last_name' => $o['last_name'], 'phone' => $o['phone'],
            'total' => (int)$o['total'], 'refunded' => (int)($o['refunded_amount'] ?? 0), 'currency' => 'EUR', 'method' => $o['method'], 'channel' => $o['channel'], 'paid_at' => $o['paid_at'],
            'answers' => json_decode((string)$o['answers'], true)['order'] ?? [], 'tickets' => $tickets];
    }

    /** Exemple réaliste pour le bouton « Tester ». */
    public static function sample(string $event): array
    {
        return match (true) {
            str_starts_with($event, 'order.') => ['ref' => 'ETEST123', 'event_id' => 1, 'status' => explode('.', $event)[1], 'email' => 'camille@exemple.fr', 'first_name' => 'Camille', 'last_name' => 'Martin', 'total' => 6914, 'currency' => 'EUR', 'tickets' => [['code' => 'ABCDEFGHJK23', 'rate_name' => 'Plein tarif', 'price' => 3200]]],
            $event === 'ticket.checked_in' => ['code' => 'ABCDEFGHJK23', 'event_id' => 1, 'first_name' => 'Camille', 'last_name' => 'Martin', 'rate_name' => 'Plein tarif', 'access_point' => 'Entrée principale', 'scanned_at' => date('c')],
            default => ['test' => true, 'event' => $event],
        };
    }
}
