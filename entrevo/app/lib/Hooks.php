<?php
declare(strict_types=1);

/** Webhooks sortants : l'organisateur reçoit les nouvelles commandes sur son URL (Zapier, Make, son propre logiciel…). */
final class Hooks
{
    public static function fire(int $orgId, string $event, array $data): void
    {
        $org = one('SELECT webhook_url, webhook_secret FROM organizers WHERE id = ?', [$orgId]);
        if (!$org || !$org['webhook_url'] || !preg_match('~^https?://~', $org['webhook_url'])) return;
        $body = json_encode(['event' => $event, 'date' => date('c'), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ch = curl_init($org['webhook_url']);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Entrevo-Event: ' . $event, 'X-Entrevo-Signature: sha256=' . hash_hmac('sha256', $body, (string)$org['webhook_secret'])]]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        log_line('webhooks', "org $orgId $event -> HTTP $code");
    }

    public static function orderPayload(array $o): array
    {
        $tickets = all("SELECT code, first_name, last_name, rate_name, price, seat, session_id FROM tickets WHERE order_id = ?", [$o['id']]);
        return ['ref' => $o['ref'], 'event_id' => (int)$o['event_id'], 'session_id' => $o['session_id'] ? (int)$o['session_id'] : null, 'status' => $o['status'],
            'email' => $o['email'], 'first_name' => $o['first_name'], 'last_name' => $o['last_name'], 'phone' => $o['phone'],
            'total' => (int)$o['total'], 'currency' => 'EUR', 'method' => $o['method'], 'channel' => $o['channel'], 'paid_at' => $o['paid_at'],
            'answers' => json_decode((string)$o['answers'], true)['order'] ?? [], 'tickets' => $tickets];
    }
}
